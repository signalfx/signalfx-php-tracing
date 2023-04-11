<?php

namespace DDTrace\Tests\DistributedTracing;

use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

// SIGNALFX: Test that SIGNALFX_TRACE_RESPONSE_HEADER_ENABLED sets Server-Timing header
class SignalFxResponseHeadersTest extends WebFrameworkTestCase
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
            'DD_DISTRIBUTED_TRACING' => 'true',
            'DD_TRACE_NO_AUTOLOADER' => 'true',
            'SIGNALFX_TRACE_RESPONSE_HEADER_ENABLED' => 'true'
        ]);
    }

    public function testResponseHeader()
    {
        $responseHeaders = null;
        $traces = $this->tracesFromWebRequest(function () use (&$responseHeaders) {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/index.php',
                [
                    'X-B3-TraceId: 100a',
                    'X-B3-SpanId: 100b',
                ]
            );
            list($response, $headers) = $this->callWithResponseHeaders($spec);
            $responseHeaders = $headers;
        });

        $this->assertSame('4106', $traces[0][0]['trace_id']);
        $this->assertSame('4107', $traces[0][0]['parent_id']);

        $serverTimingHeaders = $responseHeaders['server-timing'];
        $this->assertNotNull($serverTimingHeaders);
        $this->assertSame(1, count($serverTimingHeaders));

        $matched = preg_match('/^traceparent;desc="00-([a-z0-9]{32,32})-([a-z0-9]{16,16})-01"$/', $serverTimingHeaders[0], $matches);
        $this->assertSame(1, $matched);

        $this->assertSame($matches[1], '0000000000000000000000000000100a');
        $this->assertNotSame($matches[2], '0000000000000000000000000000100b');
    }
}
