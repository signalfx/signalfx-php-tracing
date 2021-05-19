<?php

namespace DDTrace\Tests\Integrations\Slim\V4;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Slim/Version_4/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'slim_test_app',
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

    private function wrapMiddleware(array $children, array $setError = []): SpanAssertion
    {
        if (!empty($setError)) {
            return SpanAssertion::build(
                'slim.middleware',
                'slim_test_app',
                SpanAssertion::NOT_TESTED,
                'Slim\\Middleware\\ErrorMiddleware'
            )->withExactTags(['component' => 'slim'])
                ->withChildren([
                    SpanAssertion::build(
                        'slim.middleware',
                        'slim_test_app',
                        SpanAssertion::NOT_TESTED,
                        'Slim\Middleware\RoutingMiddleware'
                    )->withExactTags(['component' => 'slim'])
                        ->withChildren([
                            SpanAssertion::build(
                                'slim.middleware',
                                'slim_test_app',
                                SpanAssertion::NOT_TESTED,
                                'Slim\\Views\\TwigMiddleware'
                            )
                                ->withExactTags(['component' => 'slim'])
                                ->withChildren($children)
                                ->withExistingTagsNames(['sfx.error.stack'])
                                ->setError(...$setError)
                        ])->withExistingTagsNames(['sfx.error.stack'])->setError(...$setError),
                ])/* ->setError(...$setError) ; no error on ErrorMiddleware*/ ;
        } else {
            return SpanAssertion::build(
                'slim.middleware',
                'slim_test_app',
                SpanAssertion::NOT_TESTED,
                'Slim\\Middleware\\ErrorMiddleware'
            )->withExactTags(['component' => 'slim'])
                ->withChildren([
                    SpanAssertion::build(
                        'slim.middleware',
                        'slim_test_app',
                        SpanAssertion::NOT_TESTED,
                        'Slim\Middleware\RoutingMiddleware'
                    )->withExactTags(['component' => 'slim'])
                        ->withChildren([
                            SpanAssertion::build(
                                'slim.middleware',
                                'slim_test_app',
                                SpanAssertion::NOT_TESTED,
                                'Slim\\Views\\TwigMiddleware'
                            )->withChildren($children)
                                ->withExactTags(['component' => 'slim'])
                        ]),
                ]);
        }
    }

    public function provideSpecs()
    {
        return $this->buildDataProvider(
            [
                'A simple GET request returning a string' => [
                    SpanAssertion::build(
                        'web.request',
                        'slim_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple'
                    )->withExactTags([
                        'slim.route.name' => 'simple-route',
                        'slim.route.handler' => 'Closure::__invoke',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'component' => 'slim',
                    ])->withChildren([
                        $this->wrapMiddleware([
                            SpanAssertion::build(
                                'slim.route',
                                'slim_test_app',
                                SpanAssertion::NOT_TESTED,
                                'Closure::__invoke'
                            )->withExactTags([
                                'slim.route.name' => 'simple-route',
                                'component' => 'slim',
                            ])
                        ]),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'web.request',
                        'slim_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple_view'
                    )->withExactTags([
                        'slim.route.handler' => 'Closure::__invoke',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'component' => 'slim',
                    ])->withChildren([
                        $this->wrapMiddleware([
                            SpanAssertion::build(
                                'slim.route',
                                'slim_test_app',
                                SpanAssertion::NOT_TESTED,
                                'Closure::__invoke'
                            )->withExactTags(['component' => 'slim'])
                                ->withChildren([
                                    SpanAssertion::build(
                                        'slim.view',
                                        'slim_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'simple_view.phtml'
                                    )->withExactTags([
                                        'slim.view' => 'simple_view.phtml',
                                        'component' => 'slim',
                                    ]),
                                ]),
                        ]),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'web.request',
                        'slim_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /error'
                    )->withExactTags([
                        'slim.route.handler' => 'Closure::__invoke',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'component' => 'slim',
                    ])
                        ->setError(null, null)
                        ->withChildren([
                            $this->wrapMiddleware(
                                [
                                    SpanAssertion::build(
                                        'slim.route',
                                        'slim_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'Closure::__invoke'
                                    )->withExactTags(['component' => 'slim'])
                                        ->withExistingTagsNames([
                                            'sfx.error.stack'
                                        ])->setError(null, 'Foo error')
                                ],
                                [null, 'Foo error']
                            )
                        ]),
                ],
            ]
        );
    }
}
