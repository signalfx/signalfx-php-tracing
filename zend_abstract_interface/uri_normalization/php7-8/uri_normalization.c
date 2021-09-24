#include "../uri_normalization.h"

#include <Zend/zend_smart_str.h>
#include <stdbool.h>

#include <ext/pcre/php_pcre.h>
#include <ext/standard/php_string.h>

#if PHP_VERSION_ID < 70200
#define zend_strpprintf strpprintf
#define ZSTR_CHAR(chr) (CG(one_char_string)[chr] ? CG(one_char_string)[chr] : zend_string_init((char[]){chr, 0}, 1, 0))
static inline zend_string *zai_php_pcre_replace(zend_string *regex, zend_string *subject_str, char *subject,
                                                int subject_len, zend_string *replace_str, int limit,
                                                size_t *replace_count) {
    zval replacementzv;
    ZVAL_STR(&replacementzv, replace_str);
    return php_pcre_replace(regex, subject_str, subject, subject_len, &replacementzv, 0, limit, (int *)replace_count);
}
#define php_pcre_replace zai_php_pcre_replace
#elif PHP_VERSION_ID < 70300
#define php_pcre_replace(regex, subj, subjstr, subjlen, replace, limit, replacements) \
    php_pcre_replace(regex, subj, subjstr, subjlen, replace, limit, (int *)replacements)
#endif

static zend_bool zai_starts_with_protocol(zend_string *str) {
    // See: https://tools.ietf.org/html/rfc3986#page-17
    if (ZSTR_VAL(str)[0] < 'a' || ZSTR_VAL(str)[0] > 'z') {
        return false;
    }
    for (char *ptr = ZSTR_VAL(str) + 1, *end = ZSTR_VAL(str) + ZSTR_LEN(str) - 2; ptr < end; ++ptr) {
        if (ptr[0] == ':' && ptr[1] == '/' && ptr[2] == '/') {
            return true;
        }
        if ((*ptr < 'a' || *ptr > 'z') && (*ptr < 'A' || *ptr > 'Z') && (*ptr < '0' || *ptr > '9') && *ptr != '+' &&
            *ptr != '-' && *ptr != '.') {
            return false;
        }
    }
    return false;
}

static void zai_apply_fragment_regex(zend_string **path, char *fragment_regex, int fragment_len) {
    // limit regex to only apply between two slashes (or slash and end)
    bool start_anchor = fragment_regex[0] == '^', end_anchor = fragment_regex[fragment_len - 1] == '$';
    zend_string *regex = zend_strpprintf(0, "((?<=/)(?=[^/]++(.*$))%s%.*s%s(?=\\1))", start_anchor ? "" : "[^/]*",
                                         fragment_len - start_anchor - end_anchor, fragment_regex + start_anchor,
                                         end_anchor ? "(?=/|$)" : "[^/]*");
    size_t replacements;
    zend_string *question_mark = ZSTR_CHAR('?');
    zend_string *substituted_path =
        php_pcre_replace(regex, *path, ZSTR_VAL(*path), ZSTR_LEN(*path), question_mark, -1, &replacements);
    if (substituted_path) {  // NULL on invalid regex
        zend_string_release(*path);
        *path = substituted_path;
    }
    zend_string_release(question_mark);
    zend_string_release(regex);
}

zend_string *zai_uri_normalize_path(zend_string *path, zend_array *fragmentRegex, zend_array *mapping) {
    if (path == NULL || ZSTR_LEN(path) == 0 || (ZSTR_LEN(path) == 1 && ZSTR_VAL(path)[0] == '/') ||
        ZSTR_VAL(path)[0] == '?') {
        return ZSTR_CHAR('/');
    }

    path = zend_string_copy(path);

    // Removing query string
    char *query_str = strchr(ZSTR_VAL(path), '?');
    if (query_str) {
        size_t new_len = query_str - ZSTR_VAL(path);
        path = zend_string_truncate(path, new_len, 0);
        ZSTR_VAL(path)[new_len] = 0;
    }

    // We always expect leading slash if it is a pure path, while urls with RFC3986 complaint schemes are preserved.
    if (ZSTR_VAL(path)[0] != '/' && !zai_starts_with_protocol(path)) {
        path = zend_string_realloc(path, ZSTR_LEN(path) + 1, 0);
        memmove(ZSTR_VAL(path) + 1, ZSTR_VAL(path), ZSTR_LEN(path));  // incl. trailing 0 byte
        ZSTR_VAL(path)[0] = '/';
    }

    zend_string *pattern;
    ZEND_HASH_FOREACH_STR_KEY(mapping, pattern) {
        pattern = php_trim(pattern, NULL, 0, 3);
        if (ZSTR_LEN(pattern)) {
            // build a regex starting with a /, properly escaped and * replaced by [^/]+
            smart_str regex = {0}, replacement = {0};
            smart_str_alloc(&regex, ZSTR_LEN(pattern) * 4 + 10, 0);
            smart_str_alloc(&replacement, ZSTR_LEN(pattern), 0);
            smart_str_appends(&regex, "((?<=/)");
            for (char *ptr = ZSTR_VAL(pattern), *end = ZSTR_VAL(pattern) + ZSTR_LEN(pattern); ptr < end; ++ptr) {
                if (*ptr == '*') {
                    smart_str_appends(&regex, "[^/]+");
                    smart_str_appendc(&replacement, '?');
                } else {
                    if (strchr(".\\+?[^]$(){}=!><|:-#", *ptr)) {
                        smart_str_appendc(&regex, '\\');
                    }
                    smart_str_appendc(&regex, *ptr);
                    smart_str_appendc(&replacement, *ptr);
                }
            }
            smart_str_appendc(&regex, ')');
            smart_str_0(&regex);
            smart_str_0(&replacement);

            size_t replacements;
            zend_string *substituted_path =
                php_pcre_replace(regex.s, path, ZSTR_VAL(path), ZSTR_LEN(path), replacement.s, -1, &replacements);
            zend_string_release(path);
            path = substituted_path;

            smart_str_free(&regex);
            smart_str_free(&replacement);
        }
        zend_string_release(pattern);
    }
    ZEND_HASH_FOREACH_END();

    zai_apply_fragment_regex(&path, ZEND_STRL("^\\d+$"));
    zai_apply_fragment_regex(
        &path,
        ZEND_STRL("^[0-9a-fA-F]{8}-?[0-9a-fA-F]{4}-?[1-5][0-9a-fA-F]{3}-?[89abAB][0-9a-fA-F]{3}-?[0-9a-fA-F]{12}$"));
    zai_apply_fragment_regex(&path, ZEND_STRL("^[0-9a-fA-F]{8,128}$"));

    zend_string *fragment_regex;
    ZEND_HASH_FOREACH_STR_KEY(fragmentRegex, fragment_regex) {
        zend_string *trimmed_regex = php_trim(fragment_regex, ZEND_STRL(" \t\n\r\v\0/"), 3);
        if (ZSTR_LEN(trimmed_regex)) {
            zai_apply_fragment_regex(&path, ZSTR_VAL(trimmed_regex), ZSTR_LEN(trimmed_regex));
        }
        zend_string_release(trimmed_regex);
    }
    ZEND_HASH_FOREACH_END();

    return path;
}
