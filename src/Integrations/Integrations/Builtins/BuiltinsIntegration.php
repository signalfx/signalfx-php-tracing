<?php

namespace DDTrace\Integrations\Builtins;

use DDTrace\Integrations\Integration;
use DDTrace\SpanData;
use DDTrace\Tag;

// SIGNALFX: this combines all automatic tracing of builtin functions
class BuiltinsIntegration extends Integration
{
    const NAME = 'builtins';

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    public function init()
    {
        $integration = $this;

        if (\sfx_trace_config_trace_file_get_contents()) {
            \DDTrace\trace_function('file_get_contents', function (SpanData $span, $args, $result) {
                $span->name = 'file_get_contents';
                $span->meta['file.name'] = $args[0];
                $span->type = 'custom';
                if ($result === false) {
                    $span->meta[Tag::ERROR] = 'true';
                    $err = \error_get_last();
                    if ($err) {
                        $span->meta[Tag::ERROR_MSG] = $err['message'];
                    }
                }
            });
        }

        if (\sfx_trace_config_trace_json()) {
            \DDTrace\trace_function('json_encode', function (SpanData $span) {
                $span->name = 'json_encode';
                $span->type = 'custom';
            });

            \DDTrace\trace_function('json_decode', function (SpanData $span) {
                $span->name = 'json_decode';
                $span->type = 'custom';
            });
        }

        return Integration::LOADED;
    }
}
