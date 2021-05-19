<?php

namespace DDTrace\Tests\DistributedTracing;

use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class DistributedTraceTest extends WebFrameworkTestCase
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
        ]);
    }

    public function testDistributedTrace()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/index.php',
                [
                    'x-b3-traceid: 160e7072ff7bd5fa',
                    'x-b3-spanid: af5db7ff2707e061',
                ]
            );
            $this->call($spec);
        });

        $this->assertSame('1589331357723252218', $traces[0][0]['trace_id']);
        $this->assertSame('12636458435970850913', $traces[0][0]['parent_id']);
    }

    // Synthetics requests have "0" as the parent ID
    // SFX: Don't care for synthetics, a new span id is generated
    public function testDistributedTraceWithNoParent()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/index.php',
                [
                    'x-b3-traceid: 160e7072ff7bd5fa',
                    'x-b3-spanid: 0',
                ]
            );
            $this->call($spec);
        });

        $this->assertSame('1589331357723252218', $traces[0][0]['trace_id']);
        $this->assertArrayHasKey('parent_id', $traces[0][0]);
    }

    public function testInvalidTraceId()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/index.php',
                [
                    'x-b3-traceid: this-is-not-valid',
                    'x-b3-spanid: af5db7ff2707e061',
                ]
            );
            $this->call($spec);
        });

        $this->assertNotSame('this-is-not-valid', $traces[0][0]['trace_id']);
        $this->assertNotSame(0, $traces[0][0]['trace_id']);
        $this->assertArrayNotHasKey('parent_id', $traces[0][0]);
    }

    public function testInvalidParentId()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = new RequestSpec(
                __FUNCTION__,
                'GET',
                '/index.php',
                [
                    'x-b3-traceid: af5db7ff2707e061',
                    'x-b3-spanid: this-is-not-valid',
                ]
            );
            $this->call($spec);
        });
        $this->assertSame('12636458435970850913', $traces[0][0]['trace_id']);
        // SFX: new span id
        $this->assertArrayHasKey('parent_id', $traces[0][0]);
    }
}
