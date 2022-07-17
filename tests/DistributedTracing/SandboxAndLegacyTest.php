<?php

namespace DDTrace\Tests\DistributedTracing;

use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class SandboxAndLegacyTest extends WebFrameworkTestCase
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
        return __DIR__ . '/../Frameworks/Custom/Version_Not_Autoloaded/sandbox.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_DISTRIBUTED_TRACING' => 'true',
            'DD_TRACE_NO_AUTOLOADER' => 'true',
        ]);
    }

    public function testDistributedTrace()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/sandbox.php',
                [
                    'x-b3-traceid: 160e7072ff7bd5f3',
                    'x-b3-spanid: 160e7072ff7bd5f2',
                ]
            );
            $this->call($spec);
        });

        $this->assertCount(1, $traces);
        $this->assertCount(2, $traces[0]);
        // Root span (userland)
        $rootSpan = $traces[0][0];
        $this->assertSame('1589331357723252211', $rootSpan['trace_id']);
        $this->assertSame('1589331357723252210', $rootSpan['parent_id']);
        // Child span (internal)
        $childSpan = $traces[0][1];
        $this->assertSame('1589331357723252211', $childSpan['trace_id']);
        $this->assertSame($rootSpan['span_id'], $childSpan['parent_id']);
    }

    // Synthetics requests have "0" as the parent ID
    public function testDistributedTraceWithNoParent()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/sandbox.php',
                [
                    'x-b3-traceid: 160e7072ff7bd5fa',
                    'x-b3-spanid: 0',
                ]
            );
            $this->call($spec);
        });

        // Root span (userland)
        $rootSpan = $traces[0][0];
        $this->assertSame('1589331357723252218', $rootSpan['trace_id']);
        // New span id is generated in case of an invalid span id (0)
        $this->assertArrayHasKey('parent_id', $rootSpan);
        // Child span (internal)
        $childSpan = $traces[0][1];
        $this->assertSame('1589331357723252218', $childSpan['trace_id']);
        $this->assertSame($rootSpan['span_id'], $childSpan['parent_id']);
    }

    public function testNonDistributedTest()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(__FUNCTION__, 'GET', '/sandbox.php');
            $this->call($spec);
        });

        // Root span (userland)
        $rootSpan = $traces[0][0];
        $this->assertArrayNotHasKey('parent_id', $rootSpan);
        // Child span (internal)
        $childSpan = $traces[0][1];
        $this->assertSame($rootSpan['trace_id'], $childSpan['trace_id']);
        $this->assertSame($rootSpan['span_id'], $childSpan['parent_id']);
    }
}
