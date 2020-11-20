#ifdef HAVE_CONFIG_H
#include "config.h"
#endif
#include <SAPI.h>
#include <Zend/zend.h>
#include <Zend/zend_exceptions.h>
#include <php.h>
#include <php_ini.h>
#include <php_main.h>

#include <ext/spl/spl_exceptions.h>
#include <ext/standard/info.h>

#include "backtrace.h"
#include "circuit_breaker.h"
#include "compat_zend_string.h"
#include "compatibility.h"
#include "coms.h"
#include "coms_curl.h"
#include "coms_debug.h"
#include "configuration.h"
#include "configuration_php_iface.h"
#include "ddtrace.h"
#include "debug.h"
#include "dispatch.h"
#include "dispatch_compat.h"
#include "hex_utils.h"
#include "memory_limit.h"
#include "random.h"
#include "request_hooks.h"
#include "serializer.h"

ZEND_DECLARE_MODULE_GLOBALS(signalfx_tracing)

PHP_INI_BEGIN()
STD_PHP_INI_BOOLEAN("ddtrace.disable", "0", PHP_INI_SYSTEM, OnUpdateBool, disable, zend_signalfx_tracing_globals,
                    signalfx_tracing_globals)
STD_PHP_INI_ENTRY("ddtrace.internal_denylisted_modules_list", "ionCube Loader,", PHP_INI_SYSTEM, OnUpdateString,
                  internal_denylisted_modules_list, zend_signalfx_tracing_globals, signalfx_tracing_globals)
STD_PHP_INI_ENTRY("ddtrace.request_init_hook", "", PHP_INI_SYSTEM, OnUpdateString, request_init_hook,
                  zend_signalfx_tracing_globals, signalfx_tracing_globals)
STD_PHP_INI_BOOLEAN("ddtrace.strict_mode", "0", PHP_INI_SYSTEM, OnUpdateBool, strict_mode,
                    zend_signalfx_tracing_globals, signalfx_tracing_globals)
PHP_INI_END()

ZEND_BEGIN_ARG_INFO_EX(arginfo_dd_trace_serialize_msgpack, 0, 0, 1)
ZEND_ARG_INFO(0, trace_array)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_dd_trace_buffer_span, 0, 0, 1)
ZEND_ARG_INFO(0, trace_array)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_dd_trace_env_config, 0, 0, 1)
ZEND_ARG_INFO(0, env_name)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_dd_trace_dec_hex, 0, 0, 1)
ZEND_ARG_INFO(0, dec_string)
ZEND_END_ARG_INFO()

ZEND_BEGIN_ARG_INFO_EX(arginfo_dd_trace_hex_dec, 0, 0, 1)
ZEND_ARG_INFO(0, hex_string)
ZEND_END_ARG_INFO()

static void php_ddtrace_init_globals(zend_signalfx_tracing_globals *ng) {
    memset(ng, 0, sizeof(zend_signalfx_tracing_globals));
}

static PHP_MINIT_FUNCTION(signalfx_tracing) {
    UNUSED(type);
    REGISTER_STRING_CONSTANT("DD_TRACE_VERSION", PHP_DDTRACE_VERSION, CONST_CS | CONST_PERSISTENT);
    ZEND_INIT_MODULE_GLOBALS(signalfx_tracing, php_ddtrace_init_globals, NULL);
    REGISTER_INI_ENTRIES();

    if (SIGNALFX_TRACING_G(disable)) {
        return SUCCESS;
    }
    // config initialization needs to be at the top
    ddtrace_initialize_config(TSRMLS_C);

    ddtrace_install_backtrace_handler();
    ddtrace_dispatch_inject(TSRMLS_C);

    ddtrace_coms_initialize();
    ddtrace_coms_setup_atexit_hook();
    ddtrace_coms_init_and_start_writer();

    return SUCCESS;
}

static PHP_MSHUTDOWN_FUNCTION(signalfx_tracing) {
    UNUSED(module_number, type);

    UNREGISTER_INI_ENTRIES();

    if (SIGNALFX_TRACING_G(disable)) {
        return SUCCESS;
    }

    // when extension is properly unloaded disable the at_exit hook
    ddtrace_coms_disable_atexit_hook();
    if (ddtrace_coms_flush_shutdown_writer_synchronous()) {
        // if writer is ensured to be shutdown we can free up config resources safely
        ddtrace_config_shutdown();
    }

    return SUCCESS;
}

static PHP_RINIT_FUNCTION(signalfx_tracing) {
    UNUSED(module_number, type);

#if defined(ZTS) && PHP_VERSION_ID >= 70000
    ZEND_TSRMLS_CACHE_UPDATE();
#endif

    if (SIGNALFX_TRACING_G(disable)) {
        return SUCCESS;
    }

    ddtrace_dispatch_init(TSRMLS_C);
    SIGNALFX_TRACING_G(disable_in_current_request) = 0;

    if (SIGNALFX_TRACING_G(internal_denylisted_modules_list) && !dd_no_denylisted_modules(TSRMLS_C)) {
        return SUCCESS;
    }

    dd_trace_seed_prng(TSRMLS_C);
    ddtrace_coms_on_pid_change();

    if (SIGNALFX_TRACING_G(request_init_hook)) {
        DD_PRINTF("%s", SIGNALFX_TRACING_G(request_init_hook));
        dd_execute_php_file(SIGNALFX_TRACING_G(request_init_hook) TSRMLS_CC);
    }

    SIGNALFX_TRACING_G(traces_group_id) = ddtrace_coms_next_group_id();

    return SUCCESS;
}

static PHP_RSHUTDOWN_FUNCTION(signalfx_tracing) {
    UNUSED(module_number, type);

    if (SIGNALFX_TRACING_G(disable)) {
        return SUCCESS;
    }

    ddtrace_dispatch_destroy(TSRMLS_C);
    ddtrace_coms_on_request_finished();

    return SUCCESS;
}

static int datadog_info_print(const char *str TSRMLS_DC) { return php_output_write(str, strlen(str) TSRMLS_CC); }

static PHP_MINFO_FUNCTION(signalfx_tracing) {
    UNUSED(zend_module);

    php_info_print_box_start(0);
    datadog_info_print("SignalFx Tracing for PHP extension" TSRMLS_CC);
    if (!sapi_module.phpinfo_as_text) {
        datadog_info_print("<br><strong>For help, check out " TSRMLS_CC);
        datadog_info_print(
            "<a href=\"https://docs.signalfx.com/en/latest/apm/apm-instrument/apm-php.html\" "
            "style=\"background:transparent;\">the documentation</a>.</strong>" TSRMLS_CC);
    } else {
        datadog_info_print(
            "\nFor help, check out the documentation at "
            "https://docs.signalfx.com/en/latest/apm/apm-instrument/apm-php.html" TSRMLS_CC);
    }
    datadog_info_print(!sapi_module.phpinfo_as_text ? "<br><br>" : "\n" TSRMLS_CC);
    datadog_info_print("(c) SignalFx 2019\n" TSRMLS_CC);
    datadog_info_print("(c) Datadog 2019\n" TSRMLS_CC);
    php_info_print_box_end();

    php_info_print_table_start();
    php_info_print_table_row(2, "SignalFx Tracing for PHP", SIGNALFX_TRACING_G(disable) ? "disabled" : "enabled");
    php_info_print_table_row(2, "Version", PHP_DDTRACE_VERSION);
    php_info_print_table_end();

    DISPLAY_INI_ENTRIES();
}

static PHP_FUNCTION(dd_trace) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr);
    zval *function = NULL;
    zval *class_name = NULL;
    zval *callable = NULL;

    if (SIGNALFX_TRACING_G(disable) || SIGNALFX_TRACING_G(disable_in_current_request)) {
        RETURN_BOOL(0);
    }

    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "zzz", &class_name, &function,
                                 &callable) != SUCCESS &&
        zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "zz", &function, &callable) !=
            SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(
                spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                "unexpected parameter combination, expected (class, function, closure) or (function, closure)");
        }

        RETURN_BOOL(0);
    }
    if (class_name) {
        DD_PRINTF("Class name: %s", Z_STRVAL_P(class_name));
    }
    DD_PRINTF("Function name: %s", Z_STRVAL_P(function));

    if (!function || Z_TYPE_P(function) != IS_STRING) {
        if (class_name) {
            ddtrace_zval_ptr_dtor(class_name);
        }
        ddtrace_zval_ptr_dtor(function);

        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "function/method name parameter must be a string");
        }

        RETURN_BOOL(0);
    }

    if (class_name && SIGNALFX_TRACING_G(strict_mode) && Z_TYPE_P(class_name) == IS_STRING) {
        zend_class_entry *class = ddtrace_target_class_entry(class_name, function TSRMLS_CC);

        if (!class) {
            ddtrace_zval_ptr_dtor(class_name);
            ddtrace_zval_ptr_dtor(function);

            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "class not found");

            RETURN_BOOL(0);
        }
    }

    zend_bool rv = ddtrace_trace(class_name, function, callable TSRMLS_CC);
    RETURN_BOOL(rv);
}

// Invoke the function/method from the original context
static PHP_FUNCTION(dd_trace_forward_call) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);

    if (SIGNALFX_TRACING_G(disable)) {
        RETURN_BOOL(0);
    }

#if PHP_VERSION_ID >= 70000
    ddtrace_forward_call(execute_data, return_value TSRMLS_CC);
#else
    ddtrace_forward_call(EG(current_execute_data), return_value TSRMLS_CC);
#endif
}

static PHP_FUNCTION(dd_trace_env_config) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);
    zval *env_name = NULL;

    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z", &env_name) != SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter. the environment variable name must be provided");
        }
        RETURN_FALSE;
    }
    if (env_name) {
        ddtrace_php_get_configuration(return_value, env_name);
        return;
    } else {
        RETURN_NULL();
    }
}

// This function allows untracing a function.
static PHP_FUNCTION(dd_untrace) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    if (SIGNALFX_TRACING_G(disable) && SIGNALFX_TRACING_G(disable_in_current_request)) {
        RETURN_BOOL(0);
    }

    zval *function = NULL;

    // Remove the traced function from the global lookup
    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "z", &function) != SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter. the function name must be provided");
        }
        RETURN_BOOL(0);
    }

    // Remove the traced function from the global lookup
    if (!function || Z_TYPE_P(function) != IS_STRING) {
        RETURN_BOOL(0);
    }

    DD_PRINTF("Untracing function: %s", Z_STRVAL_P(function));
    if (SIGNALFX_TRACING_G(function_lookup)) {
#if PHP_VERSION_ID < 70000
        zend_hash_del(SIGNALFX_TRACING_G(function_lookup), Z_STRVAL_P(function), Z_STRLEN_P(function));
#else
        zend_hash_del(SIGNALFX_TRACING_G(function_lookup), Z_STR_P(function));
#endif
    }

    RETURN_BOOL(1);
}

static PHP_FUNCTION(dd_trace_disable_in_request) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    SIGNALFX_TRACING_G(disable_in_current_request) = 1;

    RETURN_BOOL(1);
}

static PHP_FUNCTION(dd_trace_reset) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    if (SIGNALFX_TRACING_G(disable)) {
        RETURN_BOOL(0);
    }

    ddtrace_dispatch_reset(TSRMLS_C);
    RETURN_BOOL(1);
}

/* {{{ proto string dd_trace_serialize_msgpack(array trace_array) */
static PHP_FUNCTION(dd_trace_serialize_msgpack) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    if (SIGNALFX_TRACING_G(disable)) {
        RETURN_BOOL(0);
    }

    zval *trace_array;

    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "a", &trace_array) == FAILURE) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "Expected an array");
        }
        RETURN_BOOL(0);
    }

    if (ddtrace_serialize_simple_array(trace_array, return_value TSRMLS_CC) != 1) {
        RETURN_BOOL(0);
    }
} /* }}} */

// method used to be able to easily breakpoint the execution at specific PHP line in GDB
static PHP_FUNCTION(dd_trace_noop) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    if (SIGNALFX_TRACING_G(disable)) {
        RETURN_BOOL(0);
    }

    RETURN_BOOL(1);
}

/* {{{ proto int dd_trace_dd_get_memory_limit() */
static PHP_FUNCTION(dd_trace_dd_get_memory_limit) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    RETURN_LONG(ddtrace_get_memory_limit(TSRMLS_C));
}

/* {{{ proto bool dd_trace_check_memory_under_limit() */
static PHP_FUNCTION(dd_trace_check_memory_under_limit) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);

    static int64_t limit = -1;
    static zend_bool fetched_limit = 0;
    if (!fetched_limit) {  // cache get_memory_limit() result to make this function blazing fast
        fetched_limit = 1;
        limit = ddtrace_get_memory_limit(TSRMLS_C);
    }

    if (limit > 0) {
        RETURN_BOOL((zend_ulong)limit > zend_memory_usage(0 TSRMLS_CC));
    } else {
        RETURN_BOOL(1);
    }
}

static PHP_FUNCTION(dd_tracer_circuit_breaker_register_error) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

    dd_tracer_circuit_breaker_register_error();

    RETURN_BOOL(1);
}

static PHP_FUNCTION(dd_tracer_circuit_breaker_register_success) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

    dd_tracer_circuit_breaker_register_success();

    RETURN_BOOL(1);
}

static PHP_FUNCTION(dd_tracer_circuit_breaker_can_try) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

    RETURN_BOOL(dd_tracer_circuit_breaker_can_try());
}

static PHP_FUNCTION(dd_tracer_circuit_breaker_info) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

    array_init_size(return_value, 5);

    add_assoc_bool(return_value, "closed", dd_tracer_circuit_breaker_is_closed());
    add_assoc_long(return_value, "total_failures", dd_tracer_circuit_breaker_total_failures());
    add_assoc_long(return_value, "consecutive_failures", dd_tracer_circuit_breaker_consecutive_failures());
    add_assoc_long(return_value, "opened_timestamp", dd_tracer_circuit_breaker_opened_timestamp());
    add_assoc_long(return_value, "last_failure_timestamp", dd_tracer_circuit_breaker_last_failure_timestamp());
    return;
}

static PHP_FUNCTION(dd_trace_buffer_span) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);

    if (SIGNALFX_TRACING_G(disable)) {
        RETURN_BOOL(0);
    }
    zval *trace_array = NULL;

    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "a", &trace_array) == FAILURE) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "Expected group id and an array");
        }
        RETURN_BOOL(0);
    }

    char *data;
    size_t size;
    if (ddtrace_serialize_simple_array_into_c_string(trace_array, &data, &size TSRMLS_CC)) {
        RETVAL_BOOL(ddtrace_coms_buffer_data(SIGNALFX_TRACING_G(traces_group_id), data, size));

        free(data);
        return;
    } else {
        RETURN_FALSE;
    }
}

static PHP_FUNCTION(dd_trace_coms_trigger_writer_flush) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

    RETURN_LONG(ddtrace_coms_trigger_writer_flush());
}

#define FUNCTION_NAME_MATCHES(function) ((sizeof(function) - 1) == fn_len && strncmp(fn, function, fn_len) == 0)

static PHP_FUNCTION(dd_trace_internal_fn) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht);
    PHP7_UNUSED(execute_data);
    zval ***params = NULL;
    uint32_t params_count = 0;

    zval *function_val = NULL;
    if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "z*", &function_val, &params, &params_count) != SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter. the function name must be provided");
        }
        RETURN_BOOL(0);
    }

    if (!function_val || Z_TYPE_P(function_val) != IS_STRING) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter. the function name must be provided");
        }
        RETURN_BOOL(0);
    }
    char *fn = Z_STRVAL_P(function_val);
    size_t fn_len = Z_STRLEN_P(function_val);
    if (fn_len == 0 && fn) {
        fn_len = strlen(fn);
    }

    RETVAL_FALSE;
    if (fn && fn_len > 0) {
        if (FUNCTION_NAME_MATCHES("ddtrace_reload_config")) {
            ddtrace_reload_config(COMPAT_CTX_C);
            RETVAL_TRUE;
        } else if (FUNCTION_NAME_MATCHES("init_and_start_writer")) {
            RETVAL_BOOL(ddtrace_coms_init_and_start_writer());
        } else if (FUNCTION_NAME_MATCHES("ddtrace_coms_next_group_id")) {
            RETVAL_LONG(ddtrace_coms_next_group_id());
        } else if (params_count == 2 && FUNCTION_NAME_MATCHES("ddtrace_coms_buffer_span")) {
            zval *group_id = ZVAL_VARARG_PARAM(params, 0);
            zval *trace_array = ZVAL_VARARG_PARAM(params, 1);
            char *data = NULL;
            size_t size = 0;
            if (ddtrace_serialize_simple_array_into_c_string(trace_array, &data, &size TSRMLS_CC)) {
                RETVAL_BOOL(ddtrace_coms_buffer_data(Z_LVAL_P(group_id), data, size));
                free(data);
            } else {
                RETVAL_FALSE;
            }
        } else if (params_count == 2 && FUNCTION_NAME_MATCHES("ddtrace_coms_buffer_data")) {
            zval *group_id = ZVAL_VARARG_PARAM(params, 0);
            zval *data = ZVAL_VARARG_PARAM(params, 1);
            RETVAL_BOOL(ddtrace_coms_buffer_data(Z_LVAL_P(group_id), Z_STRVAL_P(data), Z_STRLEN_P(data)));
        } else if (FUNCTION_NAME_MATCHES("shutdown_writer")) {
            RETVAL_BOOL(ddtrace_coms_flush_shutdown_writer_synchronous());
        } else if (params_count == 1 && FUNCTION_NAME_MATCHES("set_writer_send_on_flush")) {
            RETVAL_BOOL(ddtrace_coms_set_writer_send_on_flush(IS_TRUE_P(ZVAL_VARARG_PARAM(params, 0))));
        } else if (FUNCTION_NAME_MATCHES("test_consumer")) {
            ddtrace_coms_test_consumer();
            RETVAL_TRUE;
        } else if (FUNCTION_NAME_MATCHES("test_writers")) {
            ddtrace_coms_test_writers();
            RETVAL_TRUE;
        } else if (FUNCTION_NAME_MATCHES("test_msgpack_consumer")) {
            ddtrace_coms_test_msgpack_consumer();
            RETVAL_TRUE;
        } else if (FUNCTION_NAME_MATCHES("synchronous_flush")) {
            uint32_t timeout = 100;
            if (params_count == 1) {
                timeout = Z_LVAL_P(ZVAL_VARARG_PARAM(params, 0));
            }
            ddtrace_coms_synchronous_flush(timeout);
            RETVAL_TRUE;
        }
    }
#if PHP_VERSION_ID < 70000
    if (params_count > 0) {
        efree(params);
    }
#endif
}

/* {{{ proto string dd_trace_generate_id() */
static PHP_FUNCTION(dd_trace_generate_id) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);

#if PHP_VERSION_ID >= 70200
    RETURN_STR(dd_trace_generate_id());
#else
    char buf[20];
    dd_trace_generate_id(buf);
#if PHP_VERSION_ID >= 70000
    RETURN_STRING(buf);
#else
    RETURN_STRING(buf, 1);
#endif
#endif
}

/* {{{ proto string dd_trace_dec_hex(dec_string) */
static PHP_FUNCTION(dd_trace_dec_hex) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);
    zval *dec = NULL;

    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "z", &dec) != SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter combination, expected (dec).");
        }
#if PHP_VERSION_ID >= 70000
        RETURN_STRING("0");
#else
        RETURN_STRING("0", 1);
#endif
    }
    if (!dec || Z_TYPE_P(dec) != IS_STRING) {
        ddtrace_zval_ptr_dtor(dec);
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "dec parameter must be a string");
        }
#if PHP_VERSION_ID >= 70000
        RETURN_STRING("0");
#else
        RETURN_STRING("0", 1);
#endif
    }
    char *dec_arg = Z_STRVAL_P(dec);
    DD_PRINTF("Dec: %s", dec_arg);

#if PHP_VERSION_ID >= 70200
    RETURN_STR(dd_trace_dec_hex(dec_arg));
#else
    char buf[18];
    dd_trace_dec_hex(dec_arg, buf);
#if PHP_VERSION_ID >= 70000
    RETURN_STRING(buf);
#else
    RETURN_STRING(buf, 1);
#endif
#endif
}

/* {{{ proto string dd_trace_hex_dec(hex_string) */
static PHP_FUNCTION(dd_trace_hex_dec) {
    PHP5_UNUSED(return_value_used, this_ptr, return_value_ptr, ht TSRMLS_CC);
    PHP7_UNUSED(execute_data);
    zval *hex = NULL;

    if (zend_parse_parameters_ex(ZEND_PARSE_PARAMS_QUIET, ZEND_NUM_ARGS() TSRMLS_CC, "z", &hex) != SUCCESS) {
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC,
                                    "unexpected parameter combination, expected (hex).");
        }
#if PHP_VERSION_ID >= 70000
        RETURN_STRING("0");
#else
        RETURN_STRING("0", 1);
#endif
    }

    if (!hex || Z_TYPE_P(hex) != IS_STRING) {
        ddtrace_zval_ptr_dtor(hex);
        if (SIGNALFX_TRACING_G(strict_mode)) {
            zend_throw_exception_ex(spl_ce_InvalidArgumentException, 0 TSRMLS_CC, "hex parameter must be a string");
        }
#if PHP_VERSION_ID >= 70000
        RETURN_STRING("0");
#else
        RETURN_STRING("0", 1);
#endif
    }
    char *hex_arg = Z_STRVAL_P(hex);

#if PHP_VERSION_ID >= 70200
    RETURN_STR(dd_trace_hex_dec(hex_arg));
#else
    char buf[23];
    dd_trace_hex_dec(hex_arg, buf);
#if PHP_VERSION_ID >= 70000
    RETURN_STRING(buf);
#else
    RETURN_STRING(buf, 1);
#endif
#endif
}

static const zend_function_entry signalfx_tracing_functions[] = {
    PHP_FE(dd_trace, NULL) PHP_FE(dd_trace_forward_call, NULL) PHP_FE(dd_trace_reset, NULL) PHP_FE(dd_trace_noop, NULL)
        PHP_FE(dd_untrace, NULL) PHP_FE(dd_trace_disable_in_request, NULL) PHP_FE(dd_trace_dd_get_memory_limit, NULL)
            PHP_FE(dd_trace_check_memory_under_limit, NULL) PHP_FE(
                dd_tracer_circuit_breaker_register_error, NULL) PHP_FE(dd_tracer_circuit_breaker_register_success, NULL)
                PHP_FE(dd_tracer_circuit_breaker_can_try, NULL) PHP_FE(dd_tracer_circuit_breaker_info, NULL) PHP_FE(
                    dd_trace_env_config, arginfo_dd_trace_env_config) PHP_FE(dd_trace_coms_trigger_writer_flush, NULL)
                    PHP_FE(dd_trace_buffer_span, arginfo_dd_trace_buffer_span) PHP_FE(dd_trace_internal_fn, NULL)
                        PHP_FE(dd_trace_serialize_msgpack, arginfo_dd_trace_serialize_msgpack)
                            PHP_FE(dd_trace_generate_id, NULL) PHP_FE(dd_trace_dec_hex, arginfo_dd_trace_dec_hex)
                                PHP_FE(dd_trace_hex_dec, arginfo_dd_trace_hex_dec) ZEND_FE_END};

zend_module_entry signalfx_tracing_module_entry = {STANDARD_MODULE_HEADER,
                                                   PHP_DDTRACE_EXTNAME,
                                                   signalfx_tracing_functions,
                                                   PHP_MINIT(signalfx_tracing),
                                                   PHP_MSHUTDOWN(signalfx_tracing),
                                                   PHP_RINIT(signalfx_tracing),
                                                   PHP_RSHUTDOWN(signalfx_tracing),
                                                   PHP_MINFO(signalfx_tracing),
                                                   PHP_DDTRACE_VERSION,
                                                   STANDARD_MODULE_PROPERTIES};

#ifdef COMPILE_DL_SIGNALFX_TRACING
ZEND_GET_MODULE(signalfx_tracing)
#if defined(ZTS) && PHP_VERSION_ID >= 70000
ZEND_TSRMLS_CACHE_DEFINE();
#endif
#endif
