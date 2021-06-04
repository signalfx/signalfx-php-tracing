<?php

namespace DDTrace\Tests\DistributedTracing;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class SyntheticsTest extends WebFrameworkTestCase
{
    protected function ddSetUp()
    {
        parent::ddSetUp();
        /* Here we are disabling ddtrace for the test harness so that it doesn't
         * instrument the curl call and alter the x-datadog headers. */
        \dd_trace_disable_in_request();
    }

    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../Frameworks/Custom/Version_Not_Autoloaded/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            // Ensure that Synthetics requests do not get sampled
            // even with a really low sampling rate
            'DD_TRACE_SAMPLE_RATE' => '0.0',
            // Disabling priority sampling will break Synthetic requests
            'DD_PRIORITY_SAMPLING' => 'true',
            // Disabling distributed tracing will break Synthetic requests
            'SIGNALFX_DISTRIBUTED_TRACING' => 'true',
            'DD_TRACE_NO_AUTOLOADER' => 'true',
            'DD_TRACE_MEASURE_COMPILE_TIME' => 'false',
        ]);
    }

    public function testSyntheticsRequest()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                'Synthetics Request',
                'GET',
                '/index.php',
                [
                    'x-b3-traceid: e457b5a2e4d86bd1',
                    'x-b3-spanid: e457b5a2e4d86bd2',
                ]
            );
            $this->call($spec);
        });

        $this->assertOneExpectedSpan(
            $traces,
            SpanAssertion::build(
                'web.request',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                'GET /index.php'
            )->withExactTags([
                'http.method' => 'GET',
                'http.url' => '/index.php',
                'http.status_code' => '200',
                // SFX: origin not extracted at B3TextMap
                // '_dd.origin' => 'synthetics-browser',
                'component' => 'web.request',
            ])
        );

        $this->assertSame('16453819474850114513', $traces[0][0]['trace_id']);
    }
}
