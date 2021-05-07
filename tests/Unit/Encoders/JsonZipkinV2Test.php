<?php

namespace DDTrace\Tests\Unit\Encoders;

use DDTrace\Encoders\JsonZipkinV2;
use DDTrace\Span;
use DDTrace\SpanContext;
use DDTrace\Tag;
use DDTrace\Tests\DebugTransport;
use DDTrace\Tests\Common\BaseTestCase;
use DDTrace\Tracer;
use DDTrace\GlobalTracer;
use DDTrace\Type;
use DDTrace\Util\HexConversion;
use Prophecy\Argument;

final class JsonZipkinV2Test extends BaseTestCase
{
    /**
     * @var Tracer
     */
    private $tracer;

    protected function ddSetUp()
    {
        parent::ddSetUp();
        putenv('DD_AUTOFINISH_SPANS=true');
        $this->tracer = new Tracer(new DebugTransport());
        GlobalTracer::set($this->tracer);
    }

    protected function ddTearDown()
    {
        parent::ddTearDown();
        putenv('DD_AUTOFINISH_SPANS=');
    }

    public function testEncodeClientSpanTracesSuccess()
    {
        $context = SpanContext::createAsRoot();
        $traceId = HexConversion::idToHex($context->getTraceId());
        $parentId = HexConversion::idToHex($context->getSpanId());
        $expectedPayload = <<<JSON
[{"traceId":"$traceId","id":"%s","name":"test_name","timestamp":%d,"duration":%d,"parentId":"$parentId",
JSON
            .   <<<JSON
"tags":{"component":"icurl"},"kind":"CLIENT",
JSON
            .   <<<JSON
"remoteEndpoint":{"serviceName":"remote-service"},"localEndpoint":{"serviceName":"unnamed-php-service"}}]
JSON;

        $span = $this->tracer->startSpan('test_name', ['child_of' => $context]);
        $span->setTag(Tag::SPAN_TYPE, Type::HTTP_CLIENT);
        $span->setTag(Tag::COMPONENT, 'icurl');
        $span->service = 'remote-service';

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

        $context = SpanContext::createAsRoot();
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
