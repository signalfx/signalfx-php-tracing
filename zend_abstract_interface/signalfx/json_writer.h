#ifndef ZAI_SIGNALFX_JSON_WRITER_H
#define ZAI_SIGNALFX_JSON_WRITER_H

#include <stdint.h>
#include <stddef.h>
#include <stdbool.h>

// SIGNALFX: JSON writer for SignalFX zipkin sender, placed in ZAI avoid copying between PHP7 and PHP8
typedef enum json_writer_error_e {
    json_writer_error_ok = 0,
    json_writer_error_oom = 1,
    json_writer_error_utf8 = 2,
    json_writer_error_printf = 3
} json_writer_error_e;

typedef struct json_writer_s {
    char* buffer;
    size_t position;
    size_t size;
    json_writer_error_e error;
} json_writer_s;

void json_writer_initialize(json_writer_s *writer);
void json_writer_destroy(json_writer_s *writer);
bool json_writer_complete(json_writer_s *writer, char **buffer, size_t *size);

const char* json_writer_error_as_string(json_writer_s *writer);

void json_writer_array_begin(json_writer_s *writer);
void json_writer_array_end(json_writer_s *writer);

void json_writer_object_begin(json_writer_s *writer);
void json_writer_object_end(json_writer_s *writer);

void json_writer_element_separator(json_writer_s *writer);
void json_writer_key_value_separator(json_writer_s *writer);

void json_writer_double(json_writer_s *writer, double value);
void json_writer_i64(json_writer_s *writer, int64_t value);
void json_writer_null(json_writer_s *writer);
void json_writer_bool(json_writer_s *writer, bool value);
void json_writer_utf8(json_writer_s *writer, const char *utf8_string);

#endif
