<?php

namespace DDTrace\Tests\Unit\Encoders;

use DDTrace\Encoders\JsonZipkinV2;
use DDTrace\Span;
use DDTrace\SpanContext;
use DDTrace\Tag;
use DDTrace\Tests\DebugTransport;
use DDTrace\Tests\Unit\BaseTestCase;
use DDTrace\Tracer;
use DDTrace\GlobalTracer;
use DDTrace\Type;
use Prophecy\Argument;

final class JsonZipkinV2Test extends BaseTestCase
{
    /**
     * @var Tracer
     */
    private $tracer;

    protected function setUp()
    {
        parent::setUp();
        putenv('SIGNALFX_AUTOFINISH_SPANS=true');
        $this->tracer = new Tracer(new DebugTransport());
        GlobalTracer::set($this->tracer);
    }

    protected function tearDown()
    {
        parent::tearDown();
        putenv('SIGNALFX_AUTOFINISH_SPANS=');
    }

    public function testEncodeTracesSuccess()
    {
        $expectedPayload = <<<JSON
[{"traceId":"160e7072ff7bd5f1","id":"%s","name":"test_name","timestamp":%d,"duration":%d,"parentId":"160e7072ff7bd5f2",
JSON
            .   <<<JSON
"tags":{"component":"cli"},"kind":"CLIENT",
JSON
            .   <<<JSON
"localEndpoint":{"serviceName":"unnamed-php-service"}}]
JSON;

        $context = new SpanContext('1589331357723252209', '1589331357723252210');
        $span = $this->tracer->startSpan('test_name', ['child_of' => $context]);
        $span->setTag(Tag::SPAN_TYPE, Type::HTTP_CLIENT);

        $logger = $this->prophesize('DDTrace\Log\LoggerInterface');
        $logger->debug(Argument::any())->shouldNotBeCalled();

        $jsonEncoder = new JsonZipkinV2($logger->reveal());
        $encodedTrace = $jsonEncoder->encodeTraces($this->tracer);
        $this->assertJson($encodedTrace);
        $this->assertStringMatchesFormat($expectedPayload, $encodedTrace);
    }

    public function testJEncodeIgnoreSpanWhenEncodingFails()
    {
        if (self::matchesPhpVersion('5.4')) {
            $this->markTestSkipped(
                'json_encode in php < 5.6 does not fail because of malformed string. It sets null on specific key'
            );
            return;
        }

        $expectedPayload = '[]';

        $context = new SpanContext('160e7072ff7bd5f1', '160e7072ff7bd5f2');
        $span = $this->tracer->startSpan('test_name', ['child_of' => $context]);
        // this will generate a malformed UTF-8 string
        $span->setTag('invalid', hex2bin('37f2bef0ab085308'));

        $logger = $this->prophesize('DDTrace\Log\LoggerInterface');
        $logger
            ->error(
                'Failed to json-encode span: Malformed UTF-8 characters, possibly incorrectly encoded'
            )
            ->shouldBeCalled();

        $jsonEncoder = new JsonZipkinV2($logger->reveal());

        $encodedTrace = $jsonEncoder->encodeTraces($this->tracer);
        $this->assertEquals($expectedPayload, $encodedTrace);
    }
}
