<?php

namespace DDTrace\Tests\Integrations\Custom\Autoloaded;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

final class FatalErrorTest extends WebFrameworkTestCase
{

    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Custom/Version_Autoloaded/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'autoload',
            'SIGNALFX_TRACE_DEBUG' => true,
            'SIGNALFX_TRACING_ENABLED' => true,
            'DD_TRACE_GENERATE_ROOT_SPAN' => true,
        ]);
    }

    public function testScenario()
    {
        if (\PHP_VERSION_ID < 50500) {
            self::markTestSkipped('Fatal errors on the root span only work on PHP 5.5+');
        }
        $traces = $this->tracesFromWebRequest(function () {
            $spec = GetSpec::create('Fatal error tracking', '/fatal');
            $this->call($spec);
        });

        $this->assertExpectedSpans(
            $traces,
            [
                SpanAssertion::build(
                    'web.request',
                    'autoload',
                    SpanAssertion::NOT_TESTED,
                    'GET /fatal'
                )->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/fatal',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                ])
                ->setError("E_ERROR", "Intentional E_ERROR")
                ->withExistingTagsNames(['sfx.error.stack']),
            ]
        );
    }
}
