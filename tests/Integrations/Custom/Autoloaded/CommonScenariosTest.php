<?php

namespace DDTrace\Tests\Integrations\Custom\Autoloaded;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Custom/Version_Autoloaded/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'custom_autoloaded_app',
        ]);
    }

    /**
     * @dataProvider provideSpecs
     * @param RequestSpec $spec
     * @param array $spanExpectations
     * @throws \Exception
     */
    public function testScenario(RequestSpec $spec, array $spanExpectations)
    {
        $traces = $this->tracesFromWebRequest(function () use ($spec) {
            $this->call($spec);
        });

        $this->assertExpectedSpans($traces, $spanExpectations);
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => [
                    SpanAssertion::build(
                        'web.request',
                        'custom_autoloaded_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple'
                    )->withExactTags([
                        'http.method' => 'GET',
                        'http.url' => '/simple',
                        'http.status_code' => '200',
                        'component' => 'web.request',
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'web.request',
                        'custom_autoloaded_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple_view'
                    )->withExactTags([
                        'http.method' => 'GET',
                        'http.url' => '/simple_view',
                        'http.status_code' => '200',
                        'component' => 'web.request',
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'web.request',
                        'custom_autoloaded_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /error'
                    )->withExactTags([
                        'http.method' => 'GET',
                        'http.url' => '/error',
                        'http.status_code' => '500',
                        'component' => 'web.request',
                    ])->setError(),
                ],
            ]
        );
    }
}
