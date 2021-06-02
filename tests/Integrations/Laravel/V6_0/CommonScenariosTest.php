<?php

namespace DDTrace\Tests\Integrations\Laravel\V6_0;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Laravel/Version_6_0/public/index.php';
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

        $this->assertExpectedSpans($this, $traces, $spanExpectations);
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\CommonSpecsController@simple simple_route',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'laravel.route.name' => 'simple_route',
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'integration.name' => 'laravel',
                        'component' => 'laravel'
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\CommonSpecsController@simple_view',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@simple_view',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'integration.name' => 'laravel',
                        'component' => 'laravel'
                    ])->withExistingTagsNames(['laravel.route.name']),
                    SpanAssertion::build(
                        'laravel.view',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'integration.name' => 'laravel',
                        'component' => 'laravel'
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'App\Http\Controllers\CommonSpecsController@error',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'laravel.route.name' => 'unnamed_route',
                        'laravel.route.action' => 'App\Http\Controllers\CommonSpecsController@error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'integration.name' => 'laravel',
                        'component' => 'laravel'
                    ])->setError(),
                    SpanAssertion::exists('laravel.view')
                ],
            ]
        );
    }
}
