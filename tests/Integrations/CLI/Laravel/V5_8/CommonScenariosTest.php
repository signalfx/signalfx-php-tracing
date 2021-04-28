<?php

namespace DDTrace\Tests\Integrations\CLI\Laravel\V5_8;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\CLITestCase;

class CommonScenariosTest extends CLITestCase
{
    protected function getScriptLocation()
    {
        return __DIR__ . '/../../../../Frameworks/Laravel/Version_5_8/artisan';
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

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'artisan',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED
            )->withExactTags([
                'integration.name' => 'laravel',
                'component' => 'laravel'
            ])->withChildren([
                SpanAssertion::exists(
                    'laravel.provider.load',
                    'Illuminate\Foundation\ProviderRepository::load'
                ),
            ]),
        ]);
    }

    public function testCommandWithArgument()
    {
        $traces = $this->getTracesFromCommand('route:list');

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'artisan route:list',
                'unnamed-php-service',
                SpanAssertion::NOT_TESTED,
                SpanAssertion::NOT_TESTED
            )->withExactTags([
                'integration.name' => 'laravel',
                'component' => 'laravel'
            ])->withChildren([
                SpanAssertion::exists(
                    'laravel.provider.load',
                    'Illuminate\Foundation\ProviderRepository::load'
                ),
            ]),
        ]);
    }

    public function testCommandWithError()
    {
        $traces = $this->getTracesFromCommand('foo:error');

        $this->assertFlameGraph($traces, [
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
            ])->withChildren([
                SpanAssertion::exists(
                    'laravel.provider.load',
                    'Illuminate\Foundation\ProviderRepository::load'
                ),
            ])->setError(),
        ]);
    }
}
