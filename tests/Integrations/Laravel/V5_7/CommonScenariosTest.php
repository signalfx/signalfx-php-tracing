<?php

namespace DDTrace\Tests\Integrations\Laravel\V5_7;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Laravel/Version_5_7/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'laravel_test_app',
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
                        'App\Http\Controllers\CommonSpecsController@simple simple_route',
                        'laravel_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple'
                    )->withExactTags([
                        'laravel.route.name' => 'simple_route',
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'component' => 'laravel',
                    ])
                    ->withChildren([
                        SpanAssertion::build('laravel.action', 'laravel_test_app', SpanAssertion::NOT_TESTED, 'simple')
                            ->withExactTags(['component' => 'laravel']),
                        SpanAssertion::exists(
                            'laravel.provider.load',
                            'Illuminate\Foundation\ProviderRepository::load'
                        ),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\CommonSpecsController@simple_view unnamed_route',
                        'laravel_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple_view'
                    )->withExactTags([
                        'laravel.route.name' => 'unnamed_route',
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@simple_view',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'component' => 'laravel'
                    ])->withChildren([
                        SpanAssertion::build('laravel.action', 'laravel_test_app', SpanAssertion::NOT_TESTED, 'simple_view')
                            ->withExactTags([
                                'component' => 'laravel',
                            ]),
                        SpanAssertion::exists(
                            'laravel.provider.load',
                            'Illuminate\Foundation\ProviderRepository::load'
                        ),
                        SpanAssertion::build(
                            'laravel.view.render',
                            'laravel_test_app',
                            SpanAssertion::NOT_TESTED,
                            'simple_view'
                        )->withExactTags([
                            'component' => 'laravel',
                        ])->withChildren([
                            SpanAssertion::build(
                                'laravel.view',
                                'laravel_test_app',
                                SpanAssertion::NOT_TESTED,
                                '*/resources/views/simple_view.blade.php'
                            )->withExactTags([
                                'component' => 'laravel',
                            ]),
                        ]),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\CommonSpecsController@error unnamed_route',
                        'laravel_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /error'
                    )->withExactTags([
                        'laravel.route.name' => 'unnamed_route',
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'component' => 'laravel'
                    ])->setError()->withChildren([
                        SpanAssertion::exists('laravel.action'),
                        SpanAssertion::exists(
                            'laravel.provider.load',
                            'Illuminate\Foundation\ProviderRepository::load'
                        ),
                    ]),
                ],
            ]
        );
    }
}
