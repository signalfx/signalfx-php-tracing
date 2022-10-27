<?php

namespace SignalFx;

use DDTrace\GlobalTracer as DDGlobalTracer;

/**
 * GlobalTracer proxy
 */
final class GlobalTracer
{
    public static function get()
    {
        return DDGlobalTracer::get();
    }
}
