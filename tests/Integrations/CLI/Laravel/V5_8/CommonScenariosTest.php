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
            'SIGNALFX_SERVICE_NAME' => 'artisan_test_app',
        ]);
    }

    public function testCommandWithNoArguments()
    {
        $traces = $this->getParsedTracesFromCommand();

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'artisan',
                'artisan_test_app',
                SpanAssertion::NOT_TESTED,
                ''
            )->withExactTags([
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
        $traces = $this->getParsedTracesFromCommand('route:list');

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'artisan route:list',
                'artisan_test_app',
                SpanAssertion::NOT_TESTED,
                ''
            )->withExactTags([
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
        $traces = $this->getParsedTracesFromCommand('foo:error');

        $this->assertFlameGraph($traces, [
            SpanAssertion::build(
                'artisan foo:error',
                'artisan_test_app',
                SpanAssertion::NOT_TESTED,
                ''
            )->withExactTags([
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
