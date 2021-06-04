<?php

namespace DDTrace\Tests\Integrations\CLI\CakePHP\V2_8;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\CLITestCase;

class CommonScenariosTest extends CLITestCase
{
    protected function getScriptLocation()
    {
        return __DIR__ . '/../../../../Frameworks/CakePHP/Version_2_8/app/Console/cake.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'cake_console_test_app',
        ]);
    }

    public function testCommandWithNoArguments()
    {
        $traces = $this->getParsedAgentRequestFromCommand();

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'cakephp.console',
                'cake_console_test_app',
                SpanAssertion::NOT_TESTED,
                'cake_console'
            )->withExactTags([
                'component' => 'cakephp',
            ])
        ]);
    }

    public function testCommandWithArgument()
    {
        $traces = $this->getParsedAgentRequestFromCommand('command_list');

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'cakephp.console',
                'cake_console_test_app',
                SpanAssertion::NOT_TESTED,
                'cake_console command_list'
            )->withExactTags([
                'component' => 'cakephp',
            ])
        ]);
    }

    // We can uncomment this when we auto-trace exceptions and errors
    /*
    public function testCommandWithError()
    {
        // This error generates a lot of output to the CLI so we mute it
        $this->setOutputCallback(function() {});

        $traces = $this->getTracesFromCommand('foo_error');

        $this->assertSpans($traces, [
            SpanAssertion::build(
                'cakephp.console',
                'cake_console_test_app',
                'cli',
                'cake_console foo_error'
            )->withExistingTagsNames([
                'sfx.error.message',
                'sfx.error.stack'
            ])->setError()
        ]);
    }
    */
}
