<?php

namespace DDTrace\Tests\Integrations\Guzzle\V5;

use DDTrace\Integrations\IntegrationsLoader;
use DDTrace\Sampling\PrioritySampling;
use DDTrace\Tracer;
use GuzzleHttp\Client;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Ring\Client\CurlMultiHandler;
use GuzzleHttp\Ring\Client\MockHandler;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\IntegrationTestCase;
use DDTrace\GlobalTracer;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

function find_span_name(array $trace, $name)
{
    foreach ($trace as $span) {
        if ($span['name'] == $name) {
            return $span;
        }
    }
    return null;
}

class GuzzleIntegrationTest extends IntegrationTestCase
{
    const URL = 'http://httpbin_integration';

    public static function ddSetUpBeforeClass()
    {
        parent::ddSetUpBeforeClass();
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

    protected function ddTearDown()
    {
        parent::ddTearDown();
        putenv('SIGNALFX_DISTRIBUTED_TRACING');
        putenv('DD_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN');
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
            SpanAssertion::build(
                'GuzzleHttp\Client.send',
                SpanAssertion::NOT_TESTED,
                'http',
                SpanAssertion::NOT_TESTED
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.method' => strtoupper($method),
                    'http.url' => 'http://example.com/',
                    'http.status_code' => '200',
                    'component' => 'guzzle',
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
                    'component' => 'guzzle',
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
                    'component' => 'guzzle',
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


        // Find either the guzzle or curl span; prefer the latter
        $guzzleSpan = find_span_name($traces[0], 'GuzzleHttp\\Client.send');
        $curlSpan = find_span_name($traces[0], 'curl_exec');

        $span = $curlSpan !== null ? $curlSpan : $guzzleSpan;

        if ($span === null) {
            self::fail('Unable to find a guzzle or curl span!');
        }

        self::assertSame(
            (string)$span['span_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Spanid'])
        );

        self::assertSame(
            (string)$span['trace_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Traceid'])
        );

        // existing headers are honored
        self::assertSame('preserved_value', $found['headers']['Honored']);
    }

    public function testDistributedTracingIsNotPropagatedIfDisabled()
    {
        putenv('SIGNALFX_DISTRIBUTED_TRACING=false');
        $client = new Client();
        $found = [];

        $this->isolateTracer(function () use (&$found, $client) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $tracer->setPrioritySampling(PrioritySampling::AUTO_KEEP);
            $span = $tracer->startActiveSpan('custom')->getSpan();

            $response = $client->get(self::URL . '/headers');

            $found = $response->json();
            $span->finish();
        });

        self::assertArrayNotHasKey('X-B3-Traceid', $found['headers']);
        self::assertArrayNotHasKey('X-B3-Spanid', $found['headers']);
    }

    public function testDistributedTracingIsPropagatedForMultiHandler()
    {
        $headers1 = [];
        $headers2 = [];

        $traces = $this->isolateTracer(function () use (&$headers1, &$headers2) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $tracer->setPrioritySampling(PrioritySampling::AUTO_KEEP);
            $span = $tracer->startActiveSpan('custom')->getSpan();

            $curl = new CurlMultiHandler();
            $client = new Client(['handler' => $curl]);

            $future1 = $client->get(self::URL . '/headers', [
                'future' => true,
                'headers' => [
                    'honored' => 'preserved_value',
                ],
            ]);
            $future1->then(function ($response) use (&$headers1) {
                $headers1 = $response->json();
            });

            $future2 = $client->get(self::URL . '/headers', [
                'future' => true,
                'headers' => [
                    'honored' => 'preserved_value',
                ],
            ]);
            $future2->then(function ($response) use (&$headers2) {
                $headers2 = $response->json();
            });

            $future1->wait();
            $future2->wait();

            $span->finish();
        });

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('custom', 'cli', '', 'custom')
                ->withChildren([
                    SpanAssertion::exists('GuzzleHttp\Client.send'),
                    SpanAssertion::exists('GuzzleHttp\Client.send'),
                ]),
        ]);

        /*
         * Unlike Guzzle 6, async requests in Guzzle 5 are not truly async
         * without an event loop.
         * @see https://github.com/guzzle/guzzle/issues/1439
         */
        self::assertDistributedTracingSpan($traces[0][2], $headers1['headers']);
        self::assertDistributedTracingSpan($traces[0][1], $headers2['headers']);
    }

    private static function assertDistributedTracingSpan($span, $headers)
    {
        self::assertSame(
            (string)$span['span_id'],
            sfx_trace_convert_hex_id($headers['X-B3-Spanid'])
        );
        self::assertSame(
            (string)$span['trace_id'],
            sfx_trace_convert_hex_id($headers['X-B3-Traceid'])
        );
        self::assertSame('preserved_value', $headers['Honored']);
    }

    public function testLimitedTracer()
    {
        $traces = $this->isolateLimitedTracer(function () {
            $this->getMockedClient()->get('http://example.com');

            $request = new Request('put', 'http://example.com');
            $this->getMockedClient()->send($request);
        });

        self::assertEmpty($traces);
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

        self::assertEquals(1, sizeof($traces[0]));

        // trace is: custom
        self::assertSame(
            (string)$traces[0][0]['span_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Spanid'])
        );
        self::assertSame(
            (string)$traces[0][0]['trace_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Traceid'])
        );

        // existing headers are honored
        self::assertSame('preserved_value', $found['headers']['Honored']);
    }

    public function testAppendHostnameToServiceName()
    {
        putenv('DD_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN=true');

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
                    'component' => 'guzzle',
                ]),
        ]);
    }

    public function testDoesNotInheritTopLevelAppName()
    {
        $traces = $this->inWebServer(
            function ($execute) {
                $execute(GetSpec::create('GET', '/guzzle_in_web_request.php'));
            },
            __DIR__ . '/guzzle_in_web_request.php',
            [
                'SIGNALFX_SERVICE' => 'top_level_app',
                'DD_TRACE_NO_AUTOLOADER' => true,
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build('web.request', 'top_level_app', SpanAssertion::NOT_TESTED, 'GET /guzzle_in_web_request.php')
                ->withExistingTagsNames(['http.method', 'http.url', 'http.status_code', 'component'])
                ->withChildren([
                    SpanAssertion::build('GuzzleHttp\Client.send', 'guzzle', SpanAssertion::NOT_TESTED, 'send')
                        ->setTraceAnalyticsCandidate()
                        ->withExactTags([
                            'http.method' => 'GET',
                            'http.url' => self::URL . '/status/200',
                            'http.status_code' => '200',
                            'component' => 'guzzle',
                        ])
                        ->withChildren([
                            SpanAssertion::exists('curl_exec')->skipIf(\PHP_VERSION_ID < 50500),
                        ])
                ]),
        ]);
    }
}
