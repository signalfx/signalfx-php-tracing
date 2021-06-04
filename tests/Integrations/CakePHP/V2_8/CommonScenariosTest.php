<?php

namespace DDTrace\Tests\Integrations\CakePHP\V2_8;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/CakePHP/Version_2_8/app/webroot/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'cakephp_test_app',
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
                        'cakephp.request',
                        'cakephp_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET SimpleController@index'
                    )->withExactTags([
                        'cakephp.route.controller' => 'simple',
                        'cakephp.route.action' => 'index',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'component' => 'cakephp',
                    ])->withChildren([
                        SpanAssertion::exists('Controller.invokeAction'),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'cakephp.request',
                        'cakephp_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET SimpleViewController@index'
                    )->withExactTags([
                        'cakephp.route.controller' => 'simple_view',
                        'cakephp.route.action' => 'index',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'component' => 'cakephp',
                    ])->withChildren([
                        SpanAssertion::exists('Controller.invokeAction'),
                        SpanAssertion::build(
                            'cakephp.view',
                            'cakephp_test_app',
                            SpanAssertion::NOT_TESTED,
                            'SimpleView/index.ctp'
                        )->withExactTags([
                            'cakephp.view' => 'SimpleView/index.ctp',
                            'component' => 'cakephp',
                        ]),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'cakephp.request',
                        'cakephp_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET ErrorController@index'
                    )->withExactTags([
                        'cakephp.route.controller' => 'error',
                        'cakephp.route.action' => 'index',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'component' => 'cakephp',
                    ])->withExistingTagsNames([
                        'sfx.error.stack',
                        'sfx.error.kind',
                    ])->setError(
                        null,
                        'Foo error'
                    )->withChildren([
                        SpanAssertion::exists('Controller.invokeAction')
                            ->withExistingTagsNames([
                                'sfx.error.stack',
                                'sfx.error.kind',
                            ])->setError(null, 'Foo error'),
                        SpanAssertion::build(
                            'cakephp.view',
                            'cakephp_test_app',
                            SpanAssertion::NOT_TESTED,
                            'Errors/index.ctp'
                        )->withExactTags([
                            'cakephp.view' => 'Errors/index.ctp',
                            'component' => 'cakephp',
                        ]),
                    ]),
                ],
            ]
        );
    }
}
