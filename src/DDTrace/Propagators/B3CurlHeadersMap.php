<?php

namespace DDTrace\Propagators;

use DDTrace\Propagator;
use DDTrace\Sampling\PrioritySampling;
use DDTrace\Contracts\SpanContext;
use DDTrace\Contracts\Tracer;
use DDTrace\Util\HexConversion;

const B3_TRACE_ID_HEADER = "x-b3-traceid";
const B3_SPAN_ID_HEADER = "x-b3-spanid";
const B3_PARENT_SPAN_ID_HEADER = "x-b3-parentspanid";
const B3_SAMPLED_HEADER = "x-b3-sampled";
const B3_FLAGS_HEADER = "x-b3-flags";
const B3_BAGGAGE_HEADER_PREFIX = "baggage-";

/**
 * A propagator that injects distributed tracing context in curl like indexed headers arrays using the B3 headers:
 * ['header1: value1', 'header2: value2']
 */
final class B3CurlHeadersMap implements Propagator
{
    /**
     * @var Tracer
     */
    private $tracer;

    /**
     * @param Tracer $tracer
     */
    public function __construct(Tracer $tracer)
    {
        $this->tracer = $tracer;
    }

    /**
     * {@inheritdoc}
     */
    public function inject(SpanContext $spanContext, &$carrier)
    {
        foreach ($carrier as $index => $value) {
            if (substr($value, 0, strlen(B3_TRACE_ID_HEADER))
                    === B3_TRACE_ID_HEADER
            ) {
                unset($carrier[$index]);
            } elseif (substr($value, 0, strlen(B3_PARENT_SPAN_ID_HEADER))
                    === B3_PARENT_SPAN_ID_HEADER
            ) {
                unset($carrier[$index]);
            } elseif (substr($value, 0, strlen(B3_SPAN_ID_HEADER))
                === B3_SPAN_ID_HEADER
            ) {
                unset($carrier[$index]);
            } elseif (substr($value, 0, strlen(B3_BAGGAGE_HEADER_PREFIX))
                    === B3_BAGGAGE_HEADER_PREFIX
            ) {
                unset($carrier[$index]);
            }
        }

        $carrier[] = B3_TRACE_ID_HEADER . ': ' . HexConversion::idToHex($spanContext->getTraceId());
        $carrier[] = B3_SPAN_ID_HEADER . ': ' . HexConversion::idToHex($spanContext->getSpanId());
        if ($spanContext->getParentId() !== null) {
            $carrier[] = B3_PARENT_SPAN_ID_HEADER . ': ' . HexConversion::idToHex($spanContext->getParentId());
        }

        $prioritySampling = $this->tracer->getPrioritySampling();
        if ($prioritySampling === PrioritySampling::USER_KEEP) {
            $carrier[] = B3_FLAGS_HEADER . ": 1";
        } elseif ($prioritySampling === PrioritySampling::AUTO_KEEP) {
            $carrier[] = B3_SAMPLED_HEADER . ": 1";
        } else {
            $carrier[] = B3_SAMPLED_HEADER . ": 0";
        }

        foreach ($spanContext as $key => $value) {
            $carrier[] = B3_BAGGAGE_HEADER_PREFIX . $key . ': ' . $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function extract($carrier)
    {
        // This use case is not implemented as we haven't found any framework returning headers in curl style so far.
        return NoopSpanContext::create();
    }
}
