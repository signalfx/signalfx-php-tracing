<?php

namespace DDTrace\Tests\Integrations\Guzzle\V5;

use DDTrace\Configuration;
use DDTrace\Integrations\IntegrationsLoader;
use DDTrace\Sampling\PrioritySampling;
use DDTrace\Tracer;
use DDTrace\Util\HexConversion;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Ring\Client\MockHandler;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\IntegrationTestCase;
use DDTrace\GlobalTracer;
use DDTrace\Util\Versions;

final class GuzzleIntegrationTest extends IntegrationTestCase
{
    const URL = 'http://httpbin_integration';

    /** @var Client */
    private $client;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        IntegrationsLoader::load();
    }

    /**
     * @param array|null $responseStack
     * @return Client
     */
    protected function getMockedClient(array $responseStack = null)
    {
        $handler = new MockHandler(['status' => 200]);
        return new Client(['handler' => $handler]);
    }

    /**
     * @return Client
     */
    protected function getRealClient()
    {
        return new Client();
    }

    /**
     * @dataProvider providerHttpMethods
     */
    public function testAliasMethods($method)
    {
        $traces = $this->isolateTracer(function () use ($method) {
            $this->getMockedClient()->$method('http://example.com/?foo=secret');
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('GuzzleHttp\Client.send', 'guzzle', 'http', 'send')
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.method' => strtoupper($method),
                    'http.url' => 'http://example.com/',
                    'http.status_code' => '200',
                ]),
        ]);
    }

    public function providerHttpMethods()
    {
        return [
            ['get'],
            ['delete'],
            ['head'],
            ['options'],
            ['patch'],
            ['post'],
            ['put'],
        ];
    }

    public function testSend()
    {
        $traces = $this->isolateTracer(function () {
            $request = new Request('put', 'http://example.com');
            $this->getMockedClient()->send($request);
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('GuzzleHttp\Client.send', 'guzzle', 'http', 'send')
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.method' => 'PUT',
                    'http.url' => 'http://example.com',
                    'http.status_code' => '200',
                ]),
        ]);
    }

    public function testGet()
    {
        $traces = $this->isolateTracer(function () {
            $this->getMockedClient()->get('http://example.com');
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('GuzzleHttp\Client.send', 'guzzle', 'http', 'send')
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => 'http://example.com',
                    'http.status_code' => '200',
                ]),
        ]);
    }

    public function testDistributedTracingIsPropagated()
    {
        $client = new Client();
        $found = [];

        $traces = $this->isolateTracer(function () use (&$found, $client) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $tracer->setPrioritySampling(PrioritySampling::AUTO_KEEP);
            $span = $tracer->startActiveSpan('custom')->getSpan();

            $response = $client->get(self::URL . '/headers', [
                'headers' => [
                    'honored' => 'preserved_value',
                ],
            ]);

            $found = $response->json();
            $span->finish();
        });

        // trace is: some_operation
        $this->assertSame(
            HexConversion::idToHex($traces[0][0]['trace_id']),
            $found['headers']['X-B3-Traceid']
        );
        // parent is: curl_exec, used under the hood
        if (Versions::phpVersionMatches('5.4')) {
            // in 5.4 curl_exec is not included in the trace due to being run through `call_func_array`
            $this->assertSame(
                HexConversion::idToHex($traces[0][1]['span_id']),
                $found['headers']['X-B3-Spanid']
            );
        } else {
            $this->assertSame(
                HexConversion::idToHex($traces[0][2]['span_id']),
                $found['headers']['X-B3-Spanid']
            );
        }
        // existing headers are honored
        $this->assertSame('preserved_value', $found['headers']['Honored']);
    }

    public function testDistributedTracingIsNotPropagatedIfDisabled()
    {
        $client = new Client();
        $found = [];
        Configuration::replace(\Mockery::mock(Configuration::get(), [
            'isDistributedTracingEnabled' => false,
        ]));

        $this->isolateTracer(function () use (&$found, $client) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $tracer->setPrioritySampling(PrioritySampling::AUTO_KEEP);
            $span = $tracer->startActiveSpan('custom')->getSpan();

            $response = $client->get(self::URL . '/headers');

            $found = $response->json();
            $span->finish();
        });

        $this->assertArrayNotHasKey('X-B3-Traceid', $found['headers']);
        $this->assertArrayNotHasKey('X-B3-Spanid', $found['headers']);
    }

    public function testLimitedTracer()
    {
        $traces = $this->isolateLimitedTracer(function () {
            $this->getMockedClient()->get('http://example.com');

            $request = new Request('put', 'http://example.com');
            $this->getMockedClient()->send($request);
        });

        $this->assertEmpty($traces);
    }

    public function testLimitedTracerDistributedTracingIsPropagated()
    {
        $client = new Client();
        $found = [];

        $traces = $this->isolateLimitedTracer(function () use (&$found, $client) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $tracer->setPrioritySampling(PrioritySampling::AUTO_KEEP);
            $span = $tracer->startActiveSpan('custom')->getSpan();

            $response = $client->get(self::URL . '/headers', [
                'headers' => [
                    'honored' => 'preserved_value',
                ],
            ]);

            $found = $response->json();
            $span->finish();
        });

        $this->assertEquals(1, sizeof($traces[0]));

        // trace is: custom
        $this->assertSame(HexConversion::idToHex($traces[0][0]['span_id']), $found['headers']['X-B3-Spanid']);
        $this->assertSame(HexConversion::idToHex($traces[0][0]['trace_id']), $found['headers']['X-B3-Traceid']);

        // existing headers are honored
        $this->assertSame('preserved_value', $found['headers']['Honored']);
    }

    public function testAppendHostnameToServiceName()
    {
        putenv('SIGNALFX_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN=true');

        $traces = $this->isolateTracer(function () {
            $this->getMockedClient()->get('http://example.com');
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('GuzzleHttp\Client.send', 'host-example.com', 'http', 'send')
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => 'http://example.com',
                    'http.status_code' => '200',
                ]),
        ]);
    }
}
