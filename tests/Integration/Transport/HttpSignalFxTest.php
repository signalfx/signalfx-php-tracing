<?php

namespace DDTrace\Tests\Integration\Transport;

use DDTrace\Encoders\JsonZipkinV2;
use DDTrace\Tests\Common\AgentReplayerTrait;
use DDTrace\Tests\Common\BaseTestCase;
use DDTrace\Tracer;
use DDTrace\Transport\HttpSignalFx;
use DDTrace\GlobalTracer;

final class HttpSignalFxTest extends BaseTestCase
{
    use AgentReplayerTrait;

    public function agentUrl()
    {
        return 'http://' . (isset($_SERVER["AGENT_HOSTNAME"]) ? $_SERVER["AGENT_HOSTNAME"] :  "localhost") . ':9080';
    }

    public function agentTracesUrl()
    {
        return $this->agentUrl() . '/v1/trace';
    }

    public function testSpanReportingFailsOnUnavailableAgent()
    {
        $httpTransport = new HttpSignalFx(new JsonZipkinV2(), [
            'endpoint' => 'http://0.0.0.0:8127/v1/trace',
            'debug' => true,
        ]);
        $tracer = new Tracer($httpTransport);
        GlobalTracer::set($tracer);

        $span = $tracer->startSpan('test', [
            'tags' => [
                'key1' => 'value1',
            ]
        ]);

        $span->finish();

        $logger = $this->withDebugLogger();
        $httpTransport->send($tracer);
        $this->assertTrue($logger->has(
            'error',
            'Reporting of spans failed: 7 / Failed to connect to 0.0.0.0 port 8127: Connection refused'
        ));
    }

    public function testSpanReportingSuccess()
    {
        $httpTransport = new HttpSignalFx(new JsonZipkinV2(), [
            'endpoint' => $this->agentTracesUrl()
        ]);

        $tracer = new Tracer($httpTransport);
        GlobalTracer::set($tracer);

        $span = $tracer->startSpan('test', [
            'tags' => [
                'key1' => 'value1',
            ]
        ]);

        $childSpan = $tracer->startSpan('child_test', [
            'child_of' => $span,
            'tags' => [
                'key2' => 'value2',
            ]
        ]);

        $childSpan->finish();

        $span->finish();

        $logger = $this->withDebugLogger();
        $httpTransport->send($tracer);
        $this->assertTrue($logger->has('debug', 'About to send ~1 traces'));
        $this->assertTrue($logger->has('debug', 'Spans successfully sent'));
    }

    public function testSetHeader()
    {
        $httpTransport = new HttpSignalFx(new JsonZipkinV2(), [
            'endpoint' => $this->getAgentReplayerEndpoint(),
        ]);
        $tracer = new Tracer($httpTransport);
        GlobalTracer::set($tracer);

        $span = $tracer->startSpan('test');
        $span->finish();

        $httpTransport->setHeader('X-my-custom-header', 'my-custom-value');
        $httpTransport->send($tracer);

        $traceRequest = $this->getLastAgentRequest();

        $this->assertEquals('my-custom-value', $traceRequest['headers']['X-my-custom-header']);
    }
}
