#include "./config.h"

#include <assert.h>
#include <json/json.h>
#include <main/php.h>
#include <stdbool.h>
#include <stdlib.h>
#include <string.h>

HashTable zai_config_name_map = {0};

_Static_assert(ZAI_CONFIG_ENTRIES_COUNT_MAX < 256, "zai config entry count is overflowing uint8_t");
uint8_t zai_config_memoized_entries_count = 0;
zai_config_memoized_entry zai_config_memoized_entries[ZAI_CONFIG_ENTRIES_COUNT_MAX];

static bool zai_config_get_env_value(zai_string_view name, zai_env_buffer buf) {
    // TODO Handle other return codes
    // We want to explicitly allow pre-RINIT access to env vars here. So that callers can have an early view at config.
    // But in general allmost all configurations shall only be accessed after first RINIT. (the trivial getter will
    return zai_getenv_ex(name, buf, true) == ZAI_ENV_SUCCESS;
}

static void zai_config_find_and_set_value(zai_config_memoized_entry *memoized, zai_config_id id) {
    // TODO Use less buffer space
    // TODO Make a more generic zai_string_buffer
    ZAI_ENV_BUFFER_INIT(buf, ZAI_ENV_MAX_BUFSIZ);

    zval tmp;
    ZVAL_UNDEF(&tmp);

    zai_string_view value = {0};

    int16_t name_index = 0;
    for (; name_index < memoized->names_count; name_index++) {
        zai_string_view name = {.len = memoized->names[name_index].len, .ptr = memoized->names[name_index].ptr};
        if (zai_config_get_env_value(name, buf)) {
            zai_string_view env_value = {.len = strlen(buf.ptr), .ptr = buf.ptr};
            if (!zai_config_decode_value(env_value, memoized->type, &tmp, /* persistent */ true)) {
                // TODO Log decoding error
            } else {
                zai_config_dtor_pzval(&tmp);
                value = env_value;
            }
            break;
        }
    }

    int16_t ini_name_index = zai_config_initialize_ini_value(memoized->ini_entries, memoized->names_count, &value,
                                                             memoized->default_encoded_value, id);
    if (value.ptr != buf.ptr && ini_name_index >= 0) {
        name_index = ini_name_index;
    }

    if (value.ptr) {
        // TODO If name_index > 0, log deprecation notice
        zai_config_decode_value(value, memoized->type, &tmp, /* persistent */ true);
        assert(Z_TYPE(tmp) > IS_NULL);
        zai_config_dtor_pzval(&memoized->decoded_value);
        ZVAL_COPY_VALUE(&memoized->decoded_value, &tmp);
        memoized->name_index = name_index;
    }

    // Nothing to do; default value was already decoded at MINIT
}

static void zai_config_copy_name(zai_config_name *dest, zai_string_view src) {
    assert((src.len < ZAI_CONFIG_NAME_BUFSIZ) && "Name length greater than the buffer size");
    strncpy(dest->ptr, src.ptr, src.len);
    dest->len = src.len;
}

// SIGNALFX: functions for adding SIGNALFX_ prefixed aliases and custom aliases for an upstream DD_
// configuration option.
static void signalfx_memoize_alternate_name(zai_config_memoized_entry *memoized, zai_string_view *name,
                                            zai_string_view dd_name, zai_string_view sfx_name) {
    if (memoized->names_count >= ZAI_CONFIG_NAMES_COUNT_SIGNALFX_MAX) {
        return;
    } else if (name->len != dd_name.len || strncmp(name->ptr, dd_name.ptr, name->len) != 0) {
        return;
    }

    zai_config_name *dest = &memoized->names[memoized->names_count++];
    zai_config_copy_name(dest, sfx_name);
}

static void signalfx_memoize_alternate_prefix(zai_config_memoized_entry *memoized,
                                              zai_string_view *name, zai_string_view dd_prefix,
                                              zai_string_view sfx_prefix) {
    if (memoized->names_count >= ZAI_CONFIG_NAMES_COUNT_SIGNALFX_MAX) {
        return;
    } else if (name->len <= dd_prefix.len || strncmp(name->ptr, dd_prefix.ptr, dd_prefix.len) != 0) {
        return;
    }

    size_t new_length = name->len - dd_prefix.len + sfx_prefix.len;
    assert((new_length  < ZAI_CONFIG_NAME_BUFSIZ) && "SignalFX name length greater than the buffer size");

    zai_config_name *dest = &memoized->names[memoized->names_count++];
    strncpy(dest->ptr, sfx_prefix.ptr, sfx_prefix.len);
    strncpy(&dest->ptr[sfx_prefix.len], &name->ptr[dd_prefix.len], name->len - dd_prefix.len);
    dest->len = new_length;
}

static void signalfx_memoize_alternates_names(zai_config_memoized_entry *memoized, zai_string_view *name, bool is_main) {
    if (is_main) {
        signalfx_memoize_alternate_name(memoized, name, ZAI_STRL_VIEW("DD_TRACE_ENABLED"),
                                        ZAI_STRL_VIEW("SIGNALFX_TRACING_ENABLED"));
    }

    signalfx_memoize_alternate_prefix(memoized, name, ZAI_STRL_VIEW("DD_"), ZAI_STRL_VIEW("SIGNALFX_"));
}

static void signalfx_memoize_entry_alternate_names(zai_config_memoized_entry *memoized, zai_config_entry *entry) {
    memoized->names_count = 0;

    signalfx_memoize_alternates_names(memoized, &entry->name, true);

    for (uint8_t i = 0; i < entry->aliases_count; i++) {
        signalfx_memoize_alternates_names(memoized, &entry->aliases[i], false);
    }
}

static zai_config_memoized_entry *zai_config_memoize_entry(zai_config_entry *entry) {
    assert((entry->id < ZAI_CONFIG_ENTRIES_COUNT_MAX) && "Out of bounds config entry ID");
    assert((entry->aliases_count < ZAI_CONFIG_NAMES_COUNT_MAX) &&
           "Number of aliases + name are greater than ZAI_CONFIG_NAMES_COUNT_MAX");

    zai_config_memoized_entry *memoized = &zai_config_memoized_entries[entry->id];
    // SIGNALFX: add SIGNALFX_ prefixed aliases for a configuration option, which have a higher priority
    // than the DD_ aliases
    signalfx_memoize_entry_alternate_names(memoized, entry);

    uint8_t names_start_index = memoized->names_count;

    zai_config_copy_name(&memoized->names[names_start_index], entry->name);
    for (uint8_t i = 0; i < entry->aliases_count; i++) {
        zai_config_copy_name(&memoized->names[names_start_index + i + 1], entry->aliases[i]);
    }
    memoized->names_count += entry->aliases_count + 1;

    memoized->type = entry->type;
    memoized->default_encoded_value = entry->default_encoded_value;

    ZVAL_UNDEF(&memoized->decoded_value);
    if (!zai_config_decode_value(entry->default_encoded_value, memoized->type, &memoized->decoded_value,
                                 /* persistent */ true)) {
        assert(0 && "Error decoding default value");
    }
    memoized->name_index = -1;
    memoized->ini_change = entry->ini_change;

    return memoized;
}

static void zai_config_entries_init(zai_config_entry entries[], zai_config_id entries_count) {
    assert((entries_count <= ZAI_CONFIG_ENTRIES_COUNT_MAX) &&
           "Number of config entries are greater than ZAI_CONFIG_ENTRIES_COUNT_MAX");

    zai_config_memoized_entries_count = entries_count;

    zend_hash_init(&zai_config_name_map, entries_count * 2, NULL, NULL, /* persistent */ 1);

    for (zai_config_id i = 0; i < entries_count; i++) {
        zai_config_memoized_entry *memoized = zai_config_memoize_entry(&entries[i]);
        for (uint8_t n = 0; n < memoized->names_count; n++) {
            zai_config_register_config_id(&memoized->names[n], i);
        }
    }
}

bool zai_config_minit(zai_config_entry entries[], size_t entries_count, zai_config_env_to_ini_name env_to_ini,
                      int module_number) {
    if (!entries || !entries_count) return false;
    if (!zai_json_setup_bindings()) return false;
    zai_config_entries_init(entries, entries_count);
    zai_config_ini_minit(env_to_ini, module_number);
    return true;
}

static void zai_config_dtor_memoized_zvals(void) {
    for (uint8_t i = 0; i < zai_config_memoized_entries_count; i++) {
        zai_config_dtor_pzval(&zai_config_memoized_entries[i].decoded_value);
    }
}

void zai_config_mshutdown(void) {
    zai_config_dtor_memoized_zvals();
    if (zai_config_name_map.nTableSize) {
        zend_hash_destroy(&zai_config_name_map);
    }
    zai_config_ini_mshutdown();
}

void zai_config_runtime_config_ctor(void);
void zai_config_runtime_config_dtor(void);

void zai_config_first_time_rinit(void) {
    for (uint8_t i = 0; i < zai_config_memoized_entries_count; i++) {
        zai_config_memoized_entry *memoized = &zai_config_memoized_entries[i];
        zai_config_find_and_set_value(memoized, i);
    }
}

void zai_config_rinit(void) {
    zai_config_runtime_config_ctor();
    zai_config_ini_rinit();
}

void zai_config_rshutdown(void) { zai_config_runtime_config_dtor(); }

// SIGNALFX: Replace DD default value with SFX default value. This is done here rather than where
// the configuration options are defined, as the defaults should only be set if SIGNALFX_MODE is
// enabled, which can only be determined at runtime.
void zai_config_use_signalfx_default(zai_config_id id, zai_string_view default_value) {
    zai_config_memoized_entry *memoized = &zai_config_memoized_entries[id];

    memoized->default_encoded_value = default_value;

    // this has been manually set already, different default has no effect
    if (memoized->name_index != -1) {
        return;
    }

    ZVAL_UNDEF(&memoized->decoded_value);
    if (!zai_config_decode_value(default_value, memoized->type, &memoized->decoded_value, true)) {
        assert(0 && "Error decoding signalfx default value");
    }

    zai_config_replace_runtime_config(id, &memoized->decoded_value);
}

bool zai_config_system_ini_change(zval *old_value, zval *new_value) {
    (void)old_value;
    (void)new_value;
    return false;
}
