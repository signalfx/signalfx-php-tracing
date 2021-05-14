<?php

namespace DDTrace\Tests\Integrations\Symfony\V4_0;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Tag;

class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Symfony/Version_4_0/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'test_symfony_40',
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
        $this->markTestSkipped('Symfony version 4.0 app cannot be updated. Skipping this test while investigating.');
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
                        'symfony.request',
                        'test_symfony_40',
                        SpanAssertion::NOT_TESTED,
                        'simple'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@simpleAction',
                        'symfony.route.name' => 'simple',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple',
                        'http.status_code' => '200',
                        'component' => 'symfony',
                    ])->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request'),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_40',
                                    SpanAssertion::NOT_TESTED,
                                    'App\Controller\CommonScenariosController::simpleAction'
                                )->withExactTags(['component' => 'symfony']),
                                SpanAssertion::exists('symfony.kernel.response'),
                                SpanAssertion::exists('symfony.kernel.finish_request'),
                            ]),
                        ]),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_40',
                        SpanAssertion::NOT_TESTED,
                        'simple_view'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@simpleViewAction',
                        'symfony.route.name' => 'simple_view',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/simple_view',
                        'http.status_code' => '200',
                        'component' => 'symfony',
                    ])->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request'),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_40',
                                    SpanAssertion::NOT_TESTED,
                                    'App\Controller\CommonScenariosController::simpleViewAction'
                                )
                                ->withExactTags(['component' => 'symfony'])
                                ->withChildren([
                                    SpanAssertion::build(
                                        'symfony.templating.render',
                                        'test_symfony_40',
                                        SpanAssertion::NOT_TESTED,
                                        'Twig\Environment twig_template.html.twig'
                                    )->withExactTags(['component' => 'symfony']),
                                ]),
                                SpanAssertion::exists('symfony.kernel.response'),
                                SpanAssertion::exists('symfony.kernel.finish_request'),
                            ]),
                        ]),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_40',
                        SpanAssertion::NOT_TESTED,
                        'error'
                    )->withExactTags([
                        'symfony.route.action' => 'App\Controller\CommonScenariosController@errorAction',
                        'symfony.route.name' => 'error',
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/error',
                        'http.status_code' => '500',
                        'component' => 'symfony',
                    ])
                    ->setError('Exception', 'An exception occurred')
                    ->withExistingTagsNames([Tag::ERROR_STACK])
                    ->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.request'),
                                SpanAssertion::exists('symfony.kernel.controller'),
                                SpanAssertion::exists('symfony.kernel.controller_arguments'),
                                SpanAssertion::build(
                                    'symfony.controller',
                                    'test_symfony_40',
                                    SpanAssertion::NOT_TESTED,
                                    'App\Controller\CommonScenariosController::errorAction'
                                )
                                ->withExactTags(['component' => 'symfony'])
                                ->setError('Exception', 'An exception occurred')
                                ->withExistingTagsNames([Tag::ERROR_STACK]),
                                SpanAssertion::exists('symfony.kernel.handleException')->withChildren([
                                    SpanAssertion::exists('symfony.kernel.exception')->withChildren([
                                        SpanAssertion::exists('symfony.templating.render'),
                                    ]),
                                    SpanAssertion::exists('symfony.kernel.response'),
                                    SpanAssertion::exists('symfony.kernel.finish_request'),
                                ]),
                            ]),
                        ]),
                    ]),
                ],
                'A GET request to a missing route' => [
                    SpanAssertion::build(
                        'symfony.request',
                        'test_symfony_40',
                        SpanAssertion::NOT_TESTED,
                        'GET /does_not_exist'
                    )->withExactTags([
                        'http.method' => 'GET',
                        'http.url' => 'http://localhost:9999/does_not_exist',
                        'http.status_code' => '404',
                        'component' => 'symfony',
                    ])
                    ->withChildren([
                        SpanAssertion::exists('symfony.kernel.terminate'),
                        SpanAssertion::exists('symfony.httpkernel.kernel.handle')->withChildren([
                            SpanAssertion::exists('symfony.httpkernel.kernel.boot'),
                            SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                                SpanAssertion::exists('symfony.kernel.handleException')->withChildren([
                                    SpanAssertion::exists('symfony.kernel.finish_request'),
                                    SpanAssertion::exists('symfony.kernel.response'),
                                    SpanAssertion::exists('symfony.kernel.exception')->withChildren([
                                        SpanAssertion::exists('symfony.templating.render'),
                                    ]),
                                ]),
                                SpanAssertion::exists('symfony.kernel.request')
                                ->setError(
                                    'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
                                    'No route found for "GET /does_not_exist"'
                                )
                                ->withExistingTagsNames([Tag::ERROR_STACK]),
                            ]),
                        ]),
                    ]),
                ],
            ]
        );
    }
}
