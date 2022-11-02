<?php

namespace SignalFx;

use DDTrace\Integrations\IntegrationsLoader;
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
        IntegrationsLoader::load();
    }

    /**
     * Call idempotent tracer creator and return registered global tracer.
     */
    public static function createTracer()
    {
        return GlobalTracer::get();
    }
}
