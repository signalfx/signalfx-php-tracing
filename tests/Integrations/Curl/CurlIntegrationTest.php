<?php

namespace DDTrace\Tests\Integrations\Curl;

use DDTrace\Format;
use DDTrace\Integrations\IntegrationsLoader;
use DDTrace\Sampling\PrioritySampling;
use DDTrace\StartSpanOptionsFactory;
use DDTrace\Tests\Common\IntegrationTestCase;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tracer;
use DDTrace\Util\ArrayKVStore;
use DDTrace\GlobalTracer;
use DDTrace\Util\Versions;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class PrivateCallbackRequest
{
    private static function parseResponseHeaders($ch, $headers)
    {
        return strlen($headers);
    }

    public function request()
    {
        $ch = curl_init(CurlIntegrationTest::URL . '/status/200');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, __CLASS__ . '::parseResponseHeaders');
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

final class CurlIntegrationTest extends IntegrationTestCase
{
    const URL = 'http://httpbin_integration';
    const URL_NOT_EXISTS = 'http://__i_am_not_real__.invalid/';

    public function ddSetUp()
    {
        $this->cleanUp();
        parent::ddSetUp();
        IntegrationsLoader::load();
    }

    public function ddTearDown()
    {
        parent::ddTearDown();
        $this->cleanUp();
    }

    private function cleanUp()
    {
        putenv('SIGNALFX_DISTRIBUTED_TRACING');
        putenv('DD_CURL_ANALYTICS_ENABLED');
        putenv('DD_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN');
        putenv('DD_TRACE_MEMORY_LIMIT');
        putenv('DD_TRACE_SPANS_LIMIT');
    }

    private static function commonCurlInfoTags()
    {
        $tags = [
            'duration',
            'network.bytes_read',
            'network.bytes_written',
        ];
        if (\version_compare(\PHP_VERSION, '5.4.7', '>=')) {
            $tags += \array_merge(
                $tags,
                ['network.client.ip', 'network.client.port', 'network.destination.ip', 'network.destination.port']
            );
        }
        return $tags;
    }

    public function testLoad200UrlOnInit()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init(self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'GET',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testSampleExternalAgent()
    {
        $traces = $this->simulateAgent(function () {
            $ch = curl_init(self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )->withExactTags([
                'http.url' => self::URL . '/status/200',
                'http.status_code' => '200',
                'http.method' => 'GET',
                'component' => 'curl',
            ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testLoad200UrlAsOpt()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'GET',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testDifferentComponentNames()
    {
        $traces = $this->inWebServer(
            function ($execute) {
                $execute(GetSpec::create('GET', '/curl_in_web_request.php'));
            },
            __DIR__ . '/curl_in_web_request.php',
            [
                'SIGNALFX_SERVICE' => 'top_level_app',
                'DD_TRACE_NO_AUTOLOADER' => true,
            ]
        );

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'web.request',
                'top_level_app',
                SpanAssertion::NOT_TESTED,
                'GET /curl_in_web_request.php'
            )
                ->withExistingTagsNames(['http.method', 'http.url', 'http.status_code', 'component'])
                ->withChildren([
                    SpanAssertion::build(
                        'curl_exec',
                        'top_level_app',
                        SpanAssertion::NOT_TESTED,
                        'http://httpbin_integration/status/?'
                    )
                        ->setTraceAnalyticsCandidate()
                        ->withExactTags([
                            'http.url' => self::URL . '/status/200',
                            'http.status_code' => '200',
                            'http.method' => 'GET',
                            'component' => 'curl'
                        ])
                        ->withExistingTagsNames(self::commonCurlInfoTags())
                        ->skipTagsLike('/^curl\..*/'),
                ]),

        ]);
    }

    public function testPrivateCallbackForResponseHeaders()
    {
        $traces = $this->isolateTracer(function () {
            $foo = new PrivateCallbackRequest();
            $response = $foo->request();
            $this->assertEmpty($response);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'GET',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testLoad404UrlOnInit()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init(self::URL . '/status/404');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/404',
                    'http.status_code' => '404',
                    'component' => 'curl',
                    'http.method' => 'GET',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testHttpMethodPost()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'POST',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testHttpMethodCustomRequest()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'DELETE',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testLoadUnroutableIP()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://10.255.255.1/");
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 100);
            curl_exec($ch);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://10.255.255.1/'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => 'http://10.255.255.1/',
                    'http.status_code' => '0',
                    'component' => 'curl',
                    'http.method' => 'GET',
                    'sfx.error.kind' => 'curl error',
                ])
                ->setError()
                ->withExistingTagsNames(['sfx.error.message'])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testLoadOperationTimeout()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://10.255.255.1/");
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 100);
            curl_exec($ch);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://10.255.255.1/'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => 'http://10.255.255.1/',
                    'http.status_code' => '0',
                    'component' => 'curl',
                    'http.method' => 'GET',
                    'sfx.error.kind' => 'curl error',
                ])
                ->setError()
                ->withExistingTagsNames(['sfx.error.message'])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/')
        ]);
    }

    public function testNonExistingHost()
    {
        $traces = $this->isolateTracer(function () {
            $ch = curl_init(self::URL_NOT_EXISTS);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertFalse($response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED,
                'http://__i_am_not_real__.invalid/'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => 'http://__i_am_not_real__.invalid/',
                    'http.status_code' => '0',
                    'component' => 'curl',
                    'http.method' => 'GET',
                    'sfx.error.kind' => 'curl error',
                    'sfx.error.message' => 'Could not resolve host: __i_am_not_real__.invalid',
                ])
                ->setError()
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/')
        ]);
    }

    public function testDistributedTracingIsPropagated()
    {
        $found = [];
        $traces = $this->isolateTracer(function () use (&$found) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $headers = [
                'x-b3-traceid' => '75d0df27679351a4',
                'x-b3-spanid' => '75d0df27679351a3',
            ];
            $span = $tracer->startRootSpan(
                'custom',
                StartSpanOptionsFactory::createForWebRequest($tracer, [], $headers)
            )->getSpan();

            $ch = curl_init(self::URL . '/headers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'honored: preserved_value',
            ]);
            $found = json_decode(curl_exec($ch), 1);

            $span->finish();
        });

        self::assertTrue(function_exists('DDTrace\\Bridge\\curl_inject_distributed_headers'));

        // Serialized internal span trace IDs are be integers.
        // trace is: some_operation
        $this->assertSame(
            (string)$traces[0][0]['trace_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Traceid'])
        );
        // parent is: curl_exec
        $this->assertSame(
            (string)$traces[0][1]['span_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Spanid'])
        );
        // existing headers are honored
        $this->assertSame('preserved_value', $found['headers']['Honored']);
    }

    public function testDistributedTracingIsNotPropagatedIfDisabled()
    {
        putenv('SIGNALFX_DISTRIBUTED_TRACING=false');
        $found = [];

        $this->isolateTracer(function () use (&$found) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $headers = [
                'x-b3-traceid' => '75d0df27679351a4',
                'x-b3-spanid' => '75d0df27679351a3',
            ];
            $span = $tracer->startRootSpan(
                'custom',
                StartSpanOptionsFactory::createForWebRequest($tracer, [], $headers)
            )->getSpan();

            $ch = curl_init(self::URL . '/headers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $found = json_decode(curl_exec($ch), 1);
            $span->finish();
        });

        $this->assertArrayNotHasKey('X-B3-Traceid', $found['headers']);
        $this->assertArrayNotHasKey('X-B3-Spanid', $found['headers']);
    }

    public function testTracerIsRunningAtLimitedCapacityWeStillPropagateTheSpan()
    {
        putenv('DD_TRACE_SPANS_LIMIT=0');
        dd_trace_internal_fn('ddtrace_reload_config');
        $found = [];
        $traces = $this->isolateTracer(function () use (&$found) {
            /** @var Tracer $tracer */
            $tracer = GlobalTracer::get();
            $headers = [
                'x-b3-traceid' => '75d0df27679351a4',
                'x-b3-spanid' => '75d0df27679351a3',
            ];
            $span = $tracer->startRootSpan(
                'custom',
                StartSpanOptionsFactory::createForWebRequest($tracer, [], $headers)
            )->getSpan();

            $ch = curl_init(self::URL . '/headers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'honored: preserved_value',
            ]);
            $found = json_decode(curl_exec($ch), 1);

            $span->finish();
        });
        putenv('DD_TRACE_MEMORY_LIMIT');
        dd_trace_internal_fn('ddtrace_reload_config');

        // existing headers are honored
        $this->assertSame('preserved_value', $found['headers']['Honored']);

        $this->assertEquals(1, sizeof($traces[0]));

        // trace is: custom
        $this->assertSame(
            (string)$traces[0][0]['trace_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Traceid'])
        );
        // parent is: custom
        $this->assertSame(
            (string)$traces[0][0]['span_id'],
            sfx_trace_convert_hex_id($found['headers']['X-B3-Spanid'])
        );
    }

    public function testTracerRunningAtLimitedCapacityCurlWorksWithoutARootSpan()
    {
        $found = [];
        $traces = $this->isolateLimitedTracer(function () use (&$found) {
            $ch = curl_init(self::URL . '/headers');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'honored: preserved_value',
            ]);
            $found = json_decode(curl_exec($ch), 1);
        });

        // existing headers are honored
        $this->assertSame('preserved_value', $found['headers']['Honored']);

        $this->assertArrayNotHasKey('X-B3-Traceid', $found['headers']);
        $this->assertArrayNotHasKey('X-B3-Spanid', $found['headers']);

        $this->assertEmpty($traces);
    }

    public function testAppendHostnameToServiceName()
    {
        putenv('DD_TRACE_HTTP_CLIENT_SPLIT_BY_DOMAIN=true');

        $traces = $this->isolateTracer(function () {
            $ch = curl_init(self::URL . '/status/200');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            $this->assertSame('', $response);
            curl_close($ch);
        });

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'curl_exec',
                'host-httpbin_integration',
                'http',
                'http://httpbin_integration/status/?'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags([
                    'http.url' => self::URL . '/status/200',
                    'http.status_code' => '200',
                    'component' => 'curl',
                    'http.method' => 'GET',
                ])
                ->withExistingTagsNames(self::commonCurlInfoTags())
                ->skipTagsLike('/^curl\..*/'),
        ]);
    }

    public function testHttpHeadersIsCorrectlySetAgain()
    {
        $this->inRootSpan(function () {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => self::URL . '/headers',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Host: test.invalid'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FAILONERROR => false,
                CURLOPT_HEADER => false,
            ]);
            $found = json_decode(curl_exec($ch), 1);

            $this->assertSame('test.invalid', $found['headers']['Host']);
            $this->assertSame('application/json', $found['headers']['Accept']);
            $this->assertSame('1', $found['headers']['X-B3-Sampled']);
        });
    }

    /**
     * @dataProvider dataProviderTestTraceAnalytics
     */
    public function testTraceAnalytics($envsOverride, $expectedSampleRate)
    {
        $env = array_merge(['SIGNALFX_SERVICE' => 'top_level_app'], $envsOverride);

        $traces = $this->inWebServer(
            function ($execute) {
                $execute(GetSpec::create('GET', '/curl_in_web_request.php'));
            },
            __DIR__ . '/curl_in_web_request.php',
            $env
        );

        $metrics = ['_sampling_priority_v1' => 1];
        if (null !== $expectedSampleRate) {
            $metrics = array_merge($metrics, ['_dd1.sr.eausr' => $expectedSampleRate]);
        }

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'web.request',
                'top_level_app',
                SpanAssertion::NOT_TESTED,
                'GET /curl_in_web_request.php'
            )
                ->withExactTags([
                    'component' => 'web.request',
                ])
                ->withExistingTagsNames(['http.method', 'http.url', 'http.status_code'])
                ->withChildren([
                    SpanAssertion::build(
                        'curl_exec',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED,
                        'http://httpbin_integration/status/?'
                    )
                        ->setTraceAnalyticsCandidate()
                        ->withExactTags([
                            'http.url' => self::URL . '/status/200',
                            'http.status_code' => '200',
                            'http.method' => 'GET',
                            'component' => 'curl',
                        ])
                        ->withExistingTagsNames(self::commonCurlInfoTags())
                        ->withExactMetrics($metrics)
                        ->skipTagsLike('/^curl\..*/'),
                ]),
        ]);
    }

    public function dataProviderTestTraceAnalytics()
    {
        return [
            'not set' => [
                [],
                null,
            ],
            'off no rate' => [
                [
                    'DD_TRACE_CURL_ANALYTICS_ENABLED' => false,
                ],
                null,
            ],
            'off legacy name no rate' => [
                [
                    'DD_CURL_ANALYTICS_ENABLED' => false,
                ],
                null,
            ],
            'off with rate' => [
                [
                    'DD_TRACE_CURL_ANALYTICS_ENABLED' => false,
                    'DD_TRACE_CURL_ANALYTICS_SAMPLE_RATE' => 0.7,
                ],
                null,
            ],
            'off legacy name with rate' => [
                [
                    'DD_CURL_ANALYTICS_ENABLED' => false,
                    'DD_CURL_ANALYTICS_SAMPLE_RATE' => 0.7,
                ],
                null,
            ],
            // SFX: Don't care, plus B3 propagator does not do anything with sampling headers.
            /*
            'enabled default rate' => [
                [
                    'DD_TRACE_CURL_ANALYTICS_ENABLED' => true,
                ],
                1.0,
            ],
            'enabled legacy name default rate' => [
                [
                    'DD_CURL_ANALYTICS_ENABLED' => true,
                ],
                1.0,
            ],
            'enabled specific rate' => [
                [
                    'DD_TRACE_CURL_ANALYTICS_ENABLED' => true,
                    'DD_TRACE_CURL_ANALYTICS_SAMPLE_RATE' => 0.7,
                ],
                0.7,
            ],
            'enabled legacy name specific rate' => [
                [
                    'DD_CURL_ANALYTICS_ENABLED' => true,
                    'DD_CURL_ANALYTICS_SAMPLE_RATE' => 0.7,
                ],
                0.7,
            ],
            */
        ];
    }
}
