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
        // Internal ids are strings, and schema requires ints
        $to_cast = ['trace_id', 'span_id', 'parent_id'];
        foreach($traces as $t_key => $trace) {
            foreach($trace as $s_key => $span) {
                foreach ($to_cast as $item){
                    if (isset($span[$item])) {
                        $traces[$t_key][$s_key][$item] = (int) $span[$item];
                    }
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
