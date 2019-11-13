<?php

namespace DDTrace\Tests\Unit\Propagators;

use DDTrace\Propagators\B3TextMap;
use DDTrace\SpanContext;
use DDTrace\Tests\DebugTransport;
use DDTrace\Tracer;
use DDTrace\GlobalTracer;
use DDTrace\Util\HexConversion;
use PHPUnit\Framework;

final class B3TextMapTest extends Framework\TestCase
{
    const BAGGAGE_ITEM_KEY = 'test_key';
    const BAGGAGE_ITEM_VALUE = 'test_value';
    const TRACE_ID = '18446744073709551615';
    const TRACE_ID_HEX = 'ffffffffffffffff';
    const SPAN_ID = '18446744073709551614';
    const SPAN_ID_HEX = 'fffffffffffffffe';
    const PARENT_SPAN_ID = '1589331357723252211';
    const PARENT_SPAN_ID_HEX = '160e7072ff7bd5f3';

    /**
     * @var Tracer
     */
    private $tracer;

    protected function setUp()
    {
        parent::setUp();
        $this->tracer = new Tracer(new DebugTransport());
        GlobalTracer::set($this->tracer);
    }

    public function testInjectSpanContextIntoCarrier()
    {
        $context = SpanContext::createAsRoot([self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]);
        $carrier = [];
        $textMapPropagator = new B3TextMap($this->tracer);
        $textMapPropagator->inject($context, $carrier);
        $this->assertEquals([
            'x-b3-traceid' => HexConversion::idToHex($context->getTraceId()),
            'x-b3-spanid' => HexConversion::idToHex($context->getSpanId()),
            'x-b3-sampled' => '0',
            'baggage-' . self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE,
        ], $carrier);
    }

    public function testExtractSpanContextFromCarrierFailsDueToLackOfTraceId()
    {
        $carrier = [
            'x-b3-parentspanid' => self::SPAN_ID_HEX,
            'baggage-' . self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE,
        ];
        $textMapPropagator = new B3TextMap($this->tracer);
        $context = $textMapPropagator->extract($carrier);
        $this->assertNull($context);
    }

    public function testExtractSpanContextFromCarrierFailsDueToLackOfParentId()
    {
        $carrier = [
            'x-b3-traceid' => self::TRACE_ID_HEX,
            'baggage-' . self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE,
        ];
        $textMapPropagator = new B3TextMap($this->tracer);
        $context = $textMapPropagator->extract($carrier);
        $this->assertNull($context);
    }

    public function testExtractSpanContextFromCarrierSuccess()
    {
        $carrier = [
            'x-b3-traceid' => self::TRACE_ID_HEX,
            'x-b3-spanid' => self::SPAN_ID_HEX,
            'baggage-' . self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE,
        ];
        $textMapPropagator = new B3TextMap($this->tracer);
        $context = $textMapPropagator->extract($carrier);
        $sc = new SpanContext( self::TRACE_ID, self::SPAN_ID, null, [self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]);
        $this->assertTrue(
            $context->isEqual(new SpanContext(
                self::TRACE_ID,
                self::SPAN_ID,
                null,
                [self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]
            ))
        );
    }
}
