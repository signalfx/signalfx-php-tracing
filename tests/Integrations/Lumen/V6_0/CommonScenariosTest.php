<?php

namespace DDTrace\Tests\Integrations\Lumen\V6_0;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Lumen/Version_6_0/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'lumen_test_app',
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

        $this->assertExpectedSpans($this, $traces, $spanExpectations);
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => [
                    SpanAssertion::build(
                        'simple_route',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'lumen.route.name' => 'simple_route',
                        'lumen.route.action' => 'App\Http\Controllers\ExampleController@simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'integration.name' => 'lumen',
                        'component' => 'lumen',
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\ExampleController@simpleView',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'lumen.route.action' => 'App\Http\Controllers\ExampleController@simpleView',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'integration.name' => 'lumen',
                        'component' => 'lumen',
                    ]),
                    SpanAssertion::build(
                        'lumen.view',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'integration.name' => 'lumen',
                        'component' => 'lumen',
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\ExampleController@error',
                        'lumen_test_app',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'lumen.route.action' => 'App\Http\Controllers\ExampleController@error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'integration.name' => 'lumen',
                        'component' => 'lumen',
                    ])->setError(),
                ],
            ]
        );
    }

    public function testQuery()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('A GET request with a query parameter', '/api/v1/user/3'));
        });

        $this->assertExpectedSpans(
            $this,
            $traces,
            [
                SpanAssertion::build(
                    'App\Http\Controllers\ExampleController@query',
                    'lumen_test_app',
                    SpanAssertion::NOT_TESTED,
                    SpanAssertion::NOT_TESTED
                )->withExactTags([
                    'lumen.route.action' => 'App\Http\Controllers\ExampleController@query',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/api/v1/user/3',
                    'http.status_code' => '200',
                    'integration.name' => 'lumen',
                    'component' => 'lumen',
                ]),
            ]
        );
    }
}
