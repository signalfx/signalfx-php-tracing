<?php

namespace DDTrace\Tests\Integrations\Lumen\V5_6;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Tests\Integrations\Lumen\V5_2\CommonScenariosTest as V5_2_CommonScenariosTest;

class CommonScenariosTest extends V5_2_CommonScenariosTest
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Lumen/Version_5_6/public/index.php';
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

        $this->assertFlameGraph($traces, $spanExpectations);
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
                    ])->withChildren([
                        SpanAssertion::build(
                            'Laravel\Lumen\Application.handleFoundRoute',
                            'lumen_test_app',
                            'web',
                            'Laravel\Lumen\Application.handleFoundRoute'
                        )
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
                    ])->withChildren([
                        SpanAssertion::build(
                            'Laravel\Lumen\Application.handleFoundRoute',
                            'lumen_test_app',
                            'web',
                            'Laravel\Lumen\Application.handleFoundRoute'
                        )->withChildren([
                            SpanAssertion::build(
                                'laravel.view.render',
                                'lumen_test_app',
                                'web',
                                'simple_view'
                            )->withExactTags([])->withChildren([
                                SpanAssertion::build(
                                    'lumen.view',
                                    'lumen_test_app',
                                    'web',
                                    '*/resources/views/simple_view.blade.php'
                                )->withExactTags([]),
                            ]),
                        ]),
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
                    ])->withExistingTagsNames([
                        'sfx.error.stack',
                    ])->setError('Exception', 'Controller error')
                    ->withChildren([
                        SpanAssertion::build(
                            'Laravel\Lumen\Application.handleFoundRoute',
                            'lumen_test_app',
                            'web',
                            'Laravel\Lumen\Application.handleFoundRoute'
                        )->withExistingTagsNames([
                            'error.stack',
                        ])->setError('Exception', 'Controller error'),
                        SpanAssertion::build(
                            'Laravel\Lumen\Application.sendExceptionToHandler',
                            'lumen_test_app',
                            'web',
                            'Laravel\Lumen\Application.sendExceptionToHandler'
                        ),
                    ]),
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
