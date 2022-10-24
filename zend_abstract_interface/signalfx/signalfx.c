#include "signalfx.h"

#define _GNU_SOURCE
#include <dlfcn.h>

#include <stdlib.h>
#include <string.h>

bool signalfx_detect_ddtrace_mode(void) {
    const char* env_variable = getenv("SIGNALFX_MODE");

    if (env_variable != NULL) {
        return strcmp(env_variable, "0") == 0 || strcmp(env_variable, "false") == 0;
    }
    
    Dl_info lookup;
    if (dladdr(&signalfx_detect_ddtrace_mode, &lookup) != 0) {
        if (strstr(lookup.dli_fname, "ddtrace") != NULL) {
            return true;
        }
    }

    return false;
}
