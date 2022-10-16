#include "json_writer.h"

#include <stdio.h>
#include <stdlib.h>
#include <inttypes.h>
#include <string.h>

// SIGNALFX: JSON writer for SignalFX zipkin sender, placed in ZAI avoid copying between PHP7 and PHP8
#define MAX_UINT64_STRING_LENGTH 20
#define INITIAL_ALLOC_SIZE 128
#define MINIMUM_RESERVE_ON_ALLOC 32

static bool json_writer_guarantee_remaining(json_writer_s *writer, size_t required_remaining) {
    size_t required_size = writer->position + required_remaining + 1;

    if (writer->size >= required_size) {
        return true;
    }

    size_t doubled_size = writer->size > 0 ? writer->size * 2 : INITIAL_ALLOC_SIZE;
    size_t minimum_size = required_size + MINIMUM_RESERVE_ON_ALLOC;

    size_t new_size = minimum_size > doubled_size ? minimum_size : doubled_size;

    char *new_buffer = malloc(new_size);

    if (new_buffer == NULL) {
        writer->error = json_writer_error_oom;
        return false;
    }

    memcpy(new_buffer, writer->buffer, writer->position);
    free(writer->buffer);

    writer->buffer = new_buffer;
    writer->size = new_size;
    return true;
}

static bool json_writer_has_error(json_writer_s *writer) {
    return writer->error != json_writer_error_ok;
}

void json_writer_initialize(json_writer_s *writer) {
    writer->buffer = NULL;
    writer->error = json_writer_error_ok;
    writer->position = 0;
    writer->size = 0;
}

void json_writer_destroy(json_writer_s *writer) {
    if (writer->buffer != NULL) {
        free(writer->buffer);
    }
}

bool json_writer_complete(json_writer_s *writer, char **buffer, size_t *size) {
    if (json_writer_has_error(writer)) {
        return false;
    } else if (!json_writer_guarantee_remaining(writer, 0)) {
        return false;
    }

    writer->buffer[writer->position] = '\0';

    *buffer = writer->buffer;
    *size = writer->position;

    writer->buffer = NULL;
    writer->size = 0;
    writer->position = 0;

    return true;
}

const char *json_writer_error_as_string(json_writer_s *writer) {
    switch (writer->error) {
    case json_writer_error_oom:
        return "error_oom";
    case json_writer_error_utf8:
        return "error_utf8";
    case json_writer_error_printf:
        return "error_printf";
    default:
        return "ok";
    }
}

static void json_writer_append_raw_char(json_writer_s *writer, char character) {
    if (!json_writer_guarantee_remaining(writer, 1)) {
        return;
    }

    writer->buffer[writer->position++] = character;
}

static void json_writer_append_raw_string(json_writer_s *writer, const char *string, size_t length) {
    if (!json_writer_guarantee_remaining(writer, length)) {
        return;
    }

    memcpy(&writer->buffer[writer->position], string, length);
    writer->position += length;
}

void json_writer_array_begin(json_writer_s *writer) {
    json_writer_append_raw_char(writer, '[');
}

void json_writer_array_end(json_writer_s *writer) {
    json_writer_append_raw_char(writer, ']');
}

void json_writer_object_begin(json_writer_s *writer) {
    json_writer_append_raw_char(writer, '{');
}

void json_writer_object_end(json_writer_s *writer) {
    json_writer_append_raw_char(writer, '}');
}

void json_writer_element_separator(json_writer_s *writer) {
    json_writer_append_raw_char(writer, ',');
}

void json_writer_key_value_separator(json_writer_s *writer) {
    json_writer_append_raw_char(writer, ':');
}

static bool json_writer_check_printf_result(json_writer_s *writer, int print_result) {
    if (print_result < 0) {
        if (writer->error == json_writer_error_ok) {
            writer->error = json_writer_error_printf;
        }
        return false;
    }
    return true;
}

void json_writer_double(json_writer_s *writer, double value) {
    int print_result = snprintf(NULL, 0, "%f", value);
    if (!json_writer_check_printf_result(writer, print_result)) {
        return;
    }

    size_t length = (size_t) print_result;

    if (!json_writer_guarantee_remaining(writer, length)) {
        return;
    }

    snprintf(&writer->buffer[writer->position], writer->size - writer->position + 1, "%f", value);
    writer->position += length;
}

void json_writer_i64(json_writer_s *writer, int64_t value) {
    if (!json_writer_guarantee_remaining(writer, MAX_UINT64_STRING_LENGTH)) {
        return;
    }

    int print_result = snprintf(&writer->buffer[writer->position], writer->size - writer->position + 1, "%" PRId64, value);
    if (!json_writer_check_printf_result(writer, print_result)) {
        return;
    }

    writer->position += (size_t) print_result;
}

void json_writer_null(json_writer_s *writer) {
    if (json_writer_has_error(writer)) {
        return;
    }

    json_writer_append_raw_string(writer, "null", 4);
}

void json_writer_bool(json_writer_s *writer, bool value) {
    if (json_writer_has_error(writer)) {
        return;
    } else if (value) {
        json_writer_append_raw_string(writer, "true", 4);
    } else {
        json_writer_append_raw_string(writer, "false", 5);
    }
}

typedef enum utf8_advance_result_e {
    utf8_advance_null = 0,
    utf8_advance_escape_control = 1,
    utf8_advance_escape_special = 2,
    utf8_advance_error = 3
} utf8_advance_result_e;

static bool utf8_is_continuation_byte(uint8_t byte) {
    return (byte & 0xC0) == 0x80;
}

static uint32_t utf8_point(uint8_t byte, int mask, uint32_t shift) {
    return (uint32_t) (byte & (uint8_t) mask) << shift;
}

static utf8_advance_result_e utf8_advance(const uint8_t *buffer, size_t *chunk_length) {
    const uint8_t *cursor = buffer;

    while (true) {
        uint8_t byte0 = cursor[0];

        if (byte0 < 0x80) {
            if (byte0 < 0x20) {
                *chunk_length = cursor - buffer;
                if (byte0 == '\0') {
                    return utf8_advance_null;
                } else {
                    return utf8_advance_escape_control;
                }
            } else if (byte0 == '\"' || byte0 == '\\') {
                *chunk_length = cursor - buffer;
                return utf8_advance_escape_special;
            }

            cursor += 1;
        } else if ((byte0 & 0xE0) == 0xC0) {
            uint8_t byte1 = cursor[1];
            if (!utf8_is_continuation_byte(byte1)) {
                return utf8_advance_error;
            }

            uint32_t codepoint = utf8_point(byte0, ~0xE0, 6) | utf8_point(byte1, ~0xC0, 0);

            if (codepoint < 0x80) {
                return utf8_advance_error;
            }

            cursor += 2;
        } else if ((byte0 & 0xF0) == 0xE0) {
            uint8_t byte1 = cursor[1];
            if (!utf8_is_continuation_byte(byte1)) {
                return utf8_advance_error;
            }
            uint8_t byte2 = cursor[2];
            if (!utf8_is_continuation_byte(byte2)) {
                return utf8_advance_error;
            }

            uint32_t codepoint = utf8_point(byte0, ~0xF0, 12) |
                utf8_point(byte1, ~0xC0, 6) | utf8_point(byte2, ~0xC0, 0);

            if (codepoint < 0x80) {
                return utf8_advance_error;
            } else if (codepoint >= 0xD800 && codepoint <= 0xDFFF) {
                return utf8_advance_error;
            }

            cursor += 3;
        } else if ((byte0 & 0xF8) == 0xF0) {
            uint8_t byte1 = cursor[1];
            if (!utf8_is_continuation_byte(byte1)) {
                return utf8_advance_error;
            }
            uint8_t byte2 = cursor[2];
            if (!utf8_is_continuation_byte(byte2)) {
                return utf8_advance_error;
            }
            uint8_t byte3 = cursor[3];
            if (!utf8_is_continuation_byte(byte3)) {
                return utf8_advance_error;
            }

            uint32_t codepoint = utf8_point(byte0, ~0xF8, 18) | utf8_point(byte1, ~0xC0, 12) |
                utf8_point(byte2, ~0xC0, 6) | utf8_point(byte3, ~0xC0, 0);

            if (codepoint < 0x10000 || codepoint >= 0x10FFFF) {
                return utf8_advance_error;
            }

            cursor += 4;
        } else {
            return utf8_advance_error;
        }
    }
}

static char hex_digit(uint8_t number) {
    if (number < 10) {
        return '0' + number;
    } else {
        return 'A' + number - 10;
    }
}

void json_writer_utf8(json_writer_s *writer, const char *utf8_string) {
    json_writer_append_raw_char(writer, '\"');

    size_t position = 0;

    while (true) {
        size_t chunk_length;
        const char *chunk = &utf8_string[position];

        utf8_advance_result_e result = utf8_advance((const uint8_t *) chunk, &chunk_length);

        if (result == utf8_advance_error) {
            return;
        }

        if (chunk_length > 0) {
            json_writer_append_raw_string(writer, chunk, chunk_length);

            if (json_writer_has_error(writer)) {
                return;
            }
        }

        if (result == utf8_advance_null) {
            break;
        } else if (result == utf8_advance_escape_control) {
            if (!json_writer_guarantee_remaining(writer, 6)) {
                return;
            }

            char control_character = chunk[chunk_length];
            writer->buffer[writer->position++] = '\\';
            writer->buffer[writer->position++] = 'u';
            writer->buffer[writer->position++] = '0';
            writer->buffer[writer->position++] = '0';
            writer->buffer[writer->position++] = hex_digit(((uint8_t) control_character) >> 4);
            writer->buffer[writer->position++] = hex_digit(((uint8_t) control_character) & 0x0F);
        } else {
            if (!json_writer_guarantee_remaining(writer, 2)) {
                return;
            }

            writer->buffer[writer->position++] = '\\';
            writer->buffer[writer->position++] = chunk[chunk_length];
        }

        position += chunk_length + 1;
    }

    json_writer_append_raw_char(writer, '\"');
}
