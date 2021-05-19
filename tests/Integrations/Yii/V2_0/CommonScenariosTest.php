<?php

namespace DDTrace\Tests\Integrations\Yii\V2_0;

use DDTrace\Tag;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Type;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Yii/Version_2_0/web/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE_NAME' => 'yii2_test_app',
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
                        '/simple',
                        'yii2_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple'
                    )->withExactTags([
                        Tag::HTTP_METHOD => 'GET',
                        Tag::HTTP_URL => 'http://localhost:9999/simple',
                        Tag::HTTP_STATUS_CODE => '200',
                        'app.endpoint' => 'app\controllers\SimpleController::actionIndex',
                        'app.route.path' => '/simple',
                        'component' => 'yii',
                    ])->withChildren([
                        SpanAssertion::exists('yii\web\Application.run')
                            ->withExactTags(['component' => 'yii'])
                            ->withChildren([
                                SpanAssertion::build(
                                    'yii\web\Application.runAction',
                                    'yii2_test_app',
                                    SpanAssertion::NOT_TESTED,
                                    'simple/index'
                                )->withExactTags([
                                    'component' => 'yii',
                                ])->withChildren([
                                    SpanAssertion::build(
                                        'app\controllers\SimpleController.runAction',
                                        'yii2_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'index'
                                    )->withExactTags(['component' => 'yii']),
                                ]),
                            ]),
                    ]),
                ],
                'A simple GET request with a view' => [
                    SpanAssertion::build(
                        '/simple_view',
                        'yii2_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /simple_view'
                    )->withExactTags([
                        Tag::HTTP_METHOD => 'GET',
                        Tag::HTTP_URL => 'http://localhost:9999/simple_view',
                        Tag::HTTP_STATUS_CODE => '200',
                        'app.endpoint' => 'app\controllers\SimpleController::actionView',
                        'app.route.path' => '/simple_view',
                        'component' => 'yii',
                    ])->withChildren([
                        SpanAssertion::exists('yii\web\Application.run')
                            ->withExactTags([
                                'component' => 'yii',
                            ])->withChildren([
                                SpanAssertion::build(
                                    'yii\web\Application.runAction',
                                    'yii2_test_app',
                                    SpanAssertion::NOT_TESTED,
                                    'simple/view'
                                )->withExactTags([
                                    'component' => 'yii',
                                ])->withChildren([
                                    SpanAssertion::build(
                                        'app\controllers\SimpleController.runAction',
                                        'yii2_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'view'
                                    )->withExactTags([
                                        'component' => 'yii',
                                    ])->withChildren([
                                        SpanAssertion::exists('yii\web\View.renderFile'),
                                        SpanAssertion::exists('yii\web\View.renderFile'),
                                    ]),
                                ]),
                            ]),
                    ]),
                ],
                'A GET request with an exception' => [
                    SpanAssertion::build(
                        '/error',
                        'yii2_test_app',
                        SpanAssertion::NOT_TESTED,
                        'GET /error'
                    )->withExactTags([
                        Tag::HTTP_METHOD => 'GET',
                        Tag::HTTP_URL => 'http://localhost:9999/error',
                        Tag::HTTP_STATUS_CODE => '500',
                        'app.endpoint' => 'app\controllers\SimpleController::actionError',
                        'app.route.path' => '/error',
                        'component' => 'yii',
                    ])
                        ->setError()
                        ->withChildren([
                            SpanAssertion::build(
                                'yii\web\Application.runAction',
                                'yii2_test_app',
                                SpanAssertion::NOT_TESTED,
                                'site/error'
                            )->withExactTags([
                                'component' => 'yii',
                            ])->withChildren([
                                SpanAssertion::build(
                                    'app\controllers\SiteController.runAction',
                                    'yii2_test_app',
                                    SpanAssertion::NOT_TESTED,
                                    'error'
                                )->withExactTags([
                                    'component' => 'yii'
                                ])->withChildren([
                                    SpanAssertion::exists('yii\web\View.renderFile'),
                                    SpanAssertion::exists('yii\web\View.renderFile'),
                                ]),
                            ]),
                            SpanAssertion::exists('yii\web\Application.run')
                                ->withExactTags([
                                    'component' => 'yii',
                                ])->setError('Exception', 'datadog', true)
                                ->withChildren([
                                    SpanAssertion::build(
                                        'yii\web\Application.runAction',
                                        'yii2_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'simple/error'
                                    )->withExactTags([
                                        'component' => 'yii',
                                    ])->setError('Exception', 'datadog', true)
                                        ->withChildren([
                                            SpanAssertion::build(
                                                'app\controllers\SimpleController.runAction',
                                                'yii2_test_app',
                                                SpanAssertion::NOT_TESTED,
                                                'error'
                                            )->withExactTags([
                                                'component' => 'yii',
                                            ])->setError('Exception', 'datadog', true),
                                        ]),
                                ]),
                        ]),
                ],
            ]
        );
    }
}
