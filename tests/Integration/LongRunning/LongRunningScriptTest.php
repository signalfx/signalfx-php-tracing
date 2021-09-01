<?php

namespace DDTrace\Tests\Integration\LongRunning;

use DDTrace\Tests\Common\CLITestCase;
use DDTrace\Tests\Common\SpanAssertion;

final class LongRunningScriptTest extends CLITestCase
{
    private $script = '';

    protected function getScriptLocation()
    {
        return __DIR__ . '/' . $this->script;
    }

    public function testMultipleTracesFromLongRunningScriptSetCorrectTraceCountHeader()
    {
        if (5 === \PHP_MAJOR_VERSION) {
            $this->markTestSkipped('We do not officially support and test long running scripts on PHP 5');
            return;
        }

        $this->script = 'long_running_script_manual.php';
        $agentRequests = $this->getAllAgentRequestsFromCommand('', [
            'DD_TRACE_AUTO_FLUSH_ENABLED' => 'true',
            'DD_TRACE_GENERATE_ROOT_SPAN' => 'false',
            'DD_TRACE_BGS_TIMEOUT' => 3000,
        ]);

        $traces = [];

        foreach ($agentRequests as $req) {
            $traces[] = json_decode($req['body'], true);
        }

        $this->assertCount(3, $traces);
    }

    public function testTracesFromLongRunningFunctionWithMixedTracing()
    {
        if (5 === \PHP_MAJOR_VERSION) {
            $this->markTestSkipped('We do not officially support and test long running scripts on PHP 5');
            return;
        }

        $this->script = 'long_running_script_with_trace_function.php';
        $traces = $this->getTracesFromCommand('', [
            'DD_TRACE_AUTO_FLUSH_ENABLED' => 'true',
            'DD_TRACE_GENERATE_ROOT_SPAN' => 'false',
            'DD_TRACE_BGS_TIMEOUT' => 3000,
            'DD_TRACE_TRACED_INTERNAL_FUNCTIONS' => 'array_sum',
        ]);

        $this->assertNotEquals($traces[1][0]["trace_id"], $traces[2][0]["trace_id"], "The trace id is reused");

        $rootTraceAssertion = function ($i) {
            return SpanAssertion::exists("do_manual_instrumentation_within_root_trace_function", "run $i")
                ->withChildren([
                    SpanAssertion::exists("first-sub-operation")->withChildren(
                        SpanAssertion::exists("array_sum")
                    )->withExactTags(["result" => "42"]),
                    SpanAssertion::exists("second-sub-operation")->withChildren(
                        SpanAssertion::exists("array_sum")
                    )->withExactTags(["result" => "42"]),
                ]);
        };

        $this->assertFlameGraph($traces, [
            SpanAssertion::exists("custom-root-operation")->withChildren(
                SpanAssertion::exists("do_manual_instrumentation_subspan")->withChildren(
                    SpanAssertion::exists("sub-operation")
                )
            ),
            $rootTraceAssertion(1),
            $rootTraceAssertion(2),
        ]);
    }
}
