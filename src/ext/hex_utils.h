#ifndef DD_HEX_UTILS_H
#define DD_HEX_UTILS_H
#include <Zend/zend_types.h>
#include <php.h>

#if PHP_VERSION_ID >= 70200
zend_string* dd_trace_dec_hex(char* dec);
zend_string* dd_trace_hex_dec(char* hex);
#else
void dd_trace_dec_hex(char* dec, char* buf);
void dd_trace_hex_dec(char* hex, char* buf);
#endif
#endif  // DD_HEX_UTILS_H
