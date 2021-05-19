<?php

namespace DDTrace\Tests\Integrations\Laravel\V8_x;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class RouteCachingTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Laravel/Version_8_x/public/index.php';
    }

    protected function ddTearDown()
    {
        parent::ddTearDown();
        $this->routeClear();
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'laravel_test_app',
        ]);
    }

    public function testNotCached()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Testing route caching: uncached', '/unnamed-route'));
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    'App\Http\Controllers\RouteCachingController@unnamed unnamed_route',
                    'laravel_test_app',
                    SpanAssertion::NOT_TESTED,
                    'GET /unnamed-route'
                )
                    ->withExactTags([
                        'laravel.route.name' => 'unnamed_route',
                        'laravel.route.action' => 'App\Http\Controllers\RouteCachingController@unnamed',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/unnamed-route',
                        'http.status_code' => '200',
                        'component' => 'laravel',
                    ])
                    ->withChildren([
                        SpanAssertion::exists('laravel.action'),
                        SpanAssertion::exists('laravel.provider.load'),
                    ]),
            ]
        );
    }

    public function testCached()
    {
        $this->routeCache();
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Testing route caching: uncached', '/unnamed-route'));
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    'App\Http\Controllers\RouteCachingController@unnamed unnamed_route',
                    'laravel_test_app',
                    SpanAssertion::NOT_TESTED,
                    'GET /unnamed-route'
                )
                    ->withExactTags([
                        'laravel.route.name' => 'unnamed_route',
                        'laravel.route.action' => 'App\Http\Controllers\RouteCachingController@unnamed',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/unnamed-route',
                        'http.status_code' => '200',
                        'component' => 'laravel',
                    ])
                    ->withChildren([
                        SpanAssertion::exists('laravel.action'),
                        SpanAssertion::exists('laravel.provider.load'),
                    ]),
            ]
        );
    }

    private function routeCache()
    {
        $appRoot = \dirname(\dirname(self::getAppIndexScript()));
        `cd $appRoot && php artisan route:cache`;
    }

    private function routeClear()
    {
        $appRoot = \dirname(\dirname(self::getAppIndexScript()));
        `cd $appRoot && php artisan route:clear`;
    }
}
