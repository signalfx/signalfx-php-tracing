<?php

namespace DDTrace\Encoders;

use DDTrace\Contracts\Tracer;
use DDTrace\Encoder;
use DDTrace\Log\LoggingTrait;

final class Json implements Encoder
{
    use LoggingTrait;

    /**
     * {@inheritdoc}
     */
    public function encodeTraces(Tracer $tracer)
    {
        $traces = $tracer->getTracesAsArray();

        foreach ($traces as &$trace) {
            foreach ($trace as &$span) {
                $span['trace_id'] = (int)$span['trace_id'];
                $span['span_id'] = (int)$span['span_id'];
                if (isset($span['parent_id'])) {
                    $span['parent_id'] = (int)$span['parent_id'];
                }
            }
        }
        $json = json_encode($traces);
        if (false === $json) {
            self::logDebug('Failed to json-encode trace: ' . json_last_error_msg());
            return '[[]]';
        }
        return $json;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return 'application/json';
    }
}
