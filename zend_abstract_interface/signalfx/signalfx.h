#ifndef ZAI_SIGNALFX_SIGNALFX_H
#define ZAI_SIGNALFX_SIGNALFX_H

#include <stdbool.h>

// SIGNALFX: detect if running with the shared library being named ddtrace.so as that means this execution is only
// for tests and SIGNALFX_MODE should be disabled by default (but can be enabled)
bool signalfx_detect_ddtrace_mode(void);

#endif
