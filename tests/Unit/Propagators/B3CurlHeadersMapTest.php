<?php

namespace DDTrace\Tests\Unit\Propagators;

use DDTrace\Propagators\B3CurlHeadersMap;
use DDTrace\SpanContext;
use DDTrace\Tests\DebugTransport;
use DDTrace\Tracer;
use DDTrace\GlobalTracer;
use DDTrace\Util\HexConversion;
use PHPUnit\Framework;

final class B3CurlHeadersMapTest extends Framework\TestCase
{
    const BAGGAGE_ITEM_KEY = 'test_key';
    const BAGGAGE_ITEM_VALUE = 'test_value';
    const TRACE_ID = '1589331357723252209';
    const TRACE_ID_HEX = '160e7072ff7bd5f1';
    const SPAN_ID = '1589331357723252210';
    const SPAN_ID_HEX = '160e7072ff7bd5f2';

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

        $rootContext = SpanContext::createAsRoot([self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]);
        $context = SpanContext::createAsChild($rootContext);

        $carrier = [];

        (new B3CurlHeadersMap($this->tracer))->inject($context, $carrier);

        $this->assertEquals([
            'x-b3-traceid: ' . HexConversion::idToHex($rootContext->getTraceId()),
            'x-b3-spanid: ' . HexConversion::idToHex($context->getSpanId()),
            'x-b3-parentspanid: ' . HexConversion::idToHex($context->getParentId()),
            'x-b3-sampled: 0',
            'baggage-' . self::BAGGAGE_ITEM_KEY . ': ' . self::BAGGAGE_ITEM_VALUE,
        ], array_values($carrier));
    }

    public function testExistingUserHeadersAreHonored()
    {

        $rootContext = SpanContext::createAsRoot([self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]);
        $context = SpanContext::createAsChild($rootContext);

        $carrier = [
            'existing: headers',
        ];

        (new B3CurlHeadersMap($this->tracer))->inject($context, $carrier);

        $this->assertEquals([
            'existing: headers',
            'x-b3-traceid: ' . HexConversion::idToHex($rootContext->getTraceId()),
            'x-b3-spanid: ' . HexConversion::idToHex($context->getSpanId()),
            'x-b3-parentspanid: ' . HexConversion::idToHex($context->getParentId()),
            'x-b3-sampled: 0',
            'baggage-' . self::BAGGAGE_ITEM_KEY . ': ' . self::BAGGAGE_ITEM_VALUE,
        ], array_values($carrier));
    }

    public function testExistingDistributedTracingHeadersAreReplaced()
    {

        $rootContext = SpanContext::createAsRoot([self::BAGGAGE_ITEM_KEY => self::BAGGAGE_ITEM_VALUE]);
        $context = SpanContext::createAsChild($rootContext);

        $carrier = [
            'existing: headers',
            'x-b3-traceid: trace',
            'x-b3-spanid: parent',
            'baggage-' . self::BAGGAGE_ITEM_KEY . ': baggage',
        ];

        (new B3CurlHeadersMap($this->tracer))->inject($context, $carrier);

        $this->assertEquals([
            'existing: headers',
            'x-b3-traceid: ' . HexConversion::idToHex($rootContext->getTraceId()),
            'x-b3-spanid: ' . HexConversion::idToHex($context->getSpanId()),
            'x-b3-parentspanid: ' . HexConversion::idToHex($context->getParentId()),
            'x-b3-sampled: 0',
            'baggage-' . self::BAGGAGE_ITEM_KEY . ': ' . self::BAGGAGE_ITEM_VALUE,
        ], array_values($carrier));
    }
}
