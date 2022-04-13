#if PHP_VERSION_ID < 70000
#include "ext/php5/ddtrace.h"
#elif PHP_VERSION_ID < 80000
#include "ext/php7/ddtrace.h"
#else
#include "ext/php8/ddtrace.h"
#endif

#define phpext_ddtrace_ptr &signalfx_tracing_module_entry
