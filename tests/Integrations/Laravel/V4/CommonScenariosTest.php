<?php

namespace DDTrace\Tests\Integrations\Laravel\V4;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Laravel/Version_4_2/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_TRACE_GLOBAL_TAGS' => 'some.key1:value,some.key2:value2',
            'SIGNALFX_TRACE_DEBUG' => 'true',
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
                        'HomeController@simple simple_route',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'laravel.route.name' => 'simple_route',
                        'laravel.route.action' => 'HomeController@simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'some.key1' => 'value',
                        'some.key2' => 'value2',
                        'component' => 'laravel',
                        'integration.name' => 'laravel',
                    ]),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::build(
                        'laravel.action',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'some.key1' => 'value',
                        'some.key2' => 'value2',
                        'component' => 'laravel',
                        'integration.name' => 'laravel',
                    ]),
                    SpanAssertion::exists('laravel.event.handle'),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::exists('HomeController@simple_view'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.action'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::build(
                        'laravel.view.render',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'some.key1' => 'value',
                        'some.key2' => 'value2',
                        'component' => 'laravel',
                        'integration.name' => 'laravel',
                    ]),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'HomeController@error error',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'laravel.route.name' => 'error',
                        'laravel.route.action' => 'HomeController@error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'some.key1' => 'value',
                        'some.key2' => 'value2',
                        'component' => 'laravel',
                        'integration.name' => 'laravel',
                    ])->setError(),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::exists('laravel.event.handle'),
                    SpanAssertion::build(
                        'laravel.action',
                        'unnamed-php-service',
                        SpanAssertion::NOT_TESTED,
                        SpanAssertion::NOT_TESTED
                    )->withExactTags([
                        'some.key1' => 'value',
                        'some.key2' => 'value2',
                        'component' => 'laravel',
                        'integration.name' => 'laravel',
                    ])
                    ->withExistingTagsNames(['sfx.error.stack'])
                    ->setError('Exception', 'Controller error'),
                    SpanAssertion::exists('laravel.event.handle'),
                ],
            ]
        );
    }
}
