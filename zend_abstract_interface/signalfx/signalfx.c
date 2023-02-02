#include "signalfx.h"

#ifndef _GNU_SOURCE
#define _GNU_SOURCE
#endif

#include <dlfcn.h>

#include <stdlib.h>
#include <string.h>
#include <strings.h>

// Need this to take dladdr of it. On certain musl versions, trying to dladdr signalfx_detect_ddtrace_mode itself
// segfaults, but this option works fine.
static int dummy_static_variable = 0;

bool signalfx_detect_ddtrace_mode(void) {
    const char* env_variable = getenv("SIGNALFX_MODE");

    if (env_variable != NULL) {
        return strcmp(env_variable, "0") == 0 || strcasecmp(env_variable, "false") == 0 ||
               strcasecmp(env_variable, "off") == 0 || strcasecmp(env_variable, "no") == 0;
    }

    Dl_info lookup;
    if (dladdr(&dummy_static_variable, &lookup) != 0) {
        if (strstr(lookup.dli_fname, "ddtrace") != NULL) {
            return true;
        }
    }

    return false;
}
