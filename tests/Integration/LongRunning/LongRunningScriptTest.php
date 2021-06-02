<?php

namespace DDTrace\Tests\Integration\LongRunning;

use DDTrace\Tests\Common\CLITestCase;

final class LongRunningScriptTest extends CLITestCase
{
    protected function getScriptLocation()
    {
        return __DIR__ . '/long_running_script_manual.php';
    }

    public function testMultipleTracesFromLongRunningScriptSetCorrectTraceCountHeader()
    {
        if (5 === \PHP_MAJOR_VERSION) {
            $this->markTestSkipped('We do not officially support and test long running scripts on PHP 5');
            return;
        }
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
}
