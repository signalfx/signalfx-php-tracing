<?php

namespace SignalFx;

use DDTrace\Bootstrap;
use SignalFx\GlobalTracer;
/**
 * Tracing helpers
 */
final class Tracing
{
    /**
     * Bootstrap the tracer and load all the integrations.
     */
    public static function autoInstrument()
    {
        Bootstrap::tracerAndIntegrations();
    }

    /**
     * Call idempotent tracer creator and return registered global tracer.
     */
    public static function createTracer()
    {
        Bootstrap::tracerOnce();
        return GlobalTracer::get();
    }
}
