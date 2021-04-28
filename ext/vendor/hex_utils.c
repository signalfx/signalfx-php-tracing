#include "hex_utils.h"

#include <php.h>
#include <stdio.h>
#include <stdlib.h>

#include "configuration.h"

/**
 * Converts the input string to an unsigned long long using base and returns string formatted by format string
 * Will return "0" for all input with invalid characters for given base to match dechex() functionality.
 */
static char *to_ull_str(char *input, int base, int rep_size, char *format) {
    char *endptr;
    unsigned long long to_ull = strtoull(input, &endptr, base);

    char *rep = (char *)emalloc((rep_size + 1) * sizeof(char));
    snprintf(rep, rep_size, format, to_ull);
    if (endptr && ((const char *)endptr)[0] != '\0') {
        // Unacceptable input has been provided if endptr not null or empty string
        strcpy(rep, "0");
    }
    return rep;
}

/**
 * Converts the string representation of a 64bit unsigned int in decimal format to hexadecimal
 * This is necessary since dechex() only supports signed ints
 */
static char *dec_hex(char *dec) { return (char *)to_ull_str(dec, 10, 17, "%llx"); }

/**
 * Converts the hexadecimal representation of a 64bit int into unsigned decimal format.
 * This is necessary since hexdec() only supports signed ints
 */
static char *hex_dec(char *hex) { return (char *)to_ull_str(hex, 16, 21, "%llu"); }

#if PHP_VERSION_ID >= 70200
// zend_strpprintf() wasn't exposed until PHP 7.2
zend_string *dd_trace_dec_hex(char *dec) {
    char *hex = dec_hex(dec);
    zend_string *hex_rep = zend_strpprintf(0, "%s", hex);
    efree(hex);
    return hex_rep;
}
zend_string *dd_trace_hex_dec(char *hex) {
    char *dec = hex_dec(hex);
    zend_string *dec_rep = zend_strpprintf(0, "%s", dec);
    efree(dec);
    return dec_rep;
}
#else
void dd_trace_dec_hex(char *dec, char *buf) {
    char *hex = dec_hex(dec);
    php_sprintf(buf, "%s", hex);
    efree(hex);
}
void dd_trace_hex_dec(char *hex, char *buf) {
    char *dec = hex_dec(hex);
    php_sprintf(buf, "%s", dec);
    efree(dec);
}
#endif
