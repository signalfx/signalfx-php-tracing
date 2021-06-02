<?php

namespace DDTrace\Tests\Integrations\CLI\Custom\Autoloaded;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\CLITestCase;

final class NoRootSpanTest extends CLITestCase
{
    protected function ddSetUp()
    {
        parent::ddSetUp();
        if (PHP_MAJOR_VERSION === 5) {
            $this->markTestSkipped('Auto flushing not supported on PHP 5');
        }
    }

    protected function getScriptLocation()
    {
        return __DIR__ . '/../../../../Frameworks/Custom/Version_Autoloaded/no-root-span';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_TRACE_GENERATE_ROOT_SPAN' => '0',
            'DD_TRACE_AUTO_FLUSH_ENABLED' => '1',
            'DD_TRACE_SANDBOX_ENABLED' => '1',
        ]);
    }

    public function testCommandWillAutoFlush()
    {
        $traces = $this->getParsedTracesFromCommand();

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'my_app',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                'foo_resource'
            )->withExactTags([
                'foo' => 'bar',
                'component' => 'foo_service',
            ])->withChildren([
                SpanAssertion::exists('mysqli_connect'),
                SpanAssertion::exists(
                    'curl_exec',
                    'http://httpbin_integration/status/?'
                ),
            ])
        ]);
    }
}
