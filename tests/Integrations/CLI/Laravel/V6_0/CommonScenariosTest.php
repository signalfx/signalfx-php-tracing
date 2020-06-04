<?php

namespace DDTrace\Tests\Integrations\CLI\Laravel\V6_0;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Integrations\CLI\CLITestCase;

final class CommonScenariosTest extends CLITestCase
{
    protected function getScriptLocation()
    {
        return __DIR__ . '/../../../../Frameworks/Laravel/Version_6_0/artisan';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'APP_NAME' => 'artisan_test_app',
        ]);
    }

    public function testCommandWithNoArguments()
    {
        $traces = $this->getTracesFromCommand();

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'artisan',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED
            )->withExactTags([
                'integration.name' => 'laravel',
                'component' => 'laravel'
            ])
        ]);
    }

    public function testCommandWithArgument()
    {
        $traces = $this->getTracesFromCommand('route:list');

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'artisan route:list',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED
            )->withExactTags([
                'integration.name' => 'laravel',
                'component' => 'laravel'
            ])
        ]);
    }

    public function testCommandWithError()
    {
        $traces = $this->getTracesFromCommand('foo:error');

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'artisan foo:error',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED
            )->withExactTags([
                'integration.name' => 'laravel',
                'component' => 'laravel'
            ])->withExistingTagsNames([
                'sfx.error.message',
                'sfx.error.stack'
            ])->setError()
        ]);
    }
}
