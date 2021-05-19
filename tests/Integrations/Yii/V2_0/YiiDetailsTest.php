<?php

namespace DDTrace\Tests\Integrations\Yii\V2_0;

use DDTrace\Tag;
use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use DDTrace\Type;

class LazyLoadingIntegrationsFromYiiTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Yii/Version_2_0/web/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE' => 'yii2_test_app',
        ]);
    }

    public function testRootIndexRoute()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = GetSpec::create('root', '/');
            $this->call($spec);
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    '/site/index',
                    'yii2_test_app',
                    SpanAssertion::NOT_TESTED,
                    'GET /site/index'
                )->withExactTags([
                    Tag::HTTP_METHOD => 'GET',
                    Tag::HTTP_URL => 'http://localhost:9999/site/index',
                    Tag::HTTP_STATUS_CODE => '200',
                    'app.route.path' => '/site/index',
                    'app.endpoint' => 'app\controllers\SiteController::actionIndex',
                    'component' => 'yii',
                ])->withChildren([
                    SpanAssertion::exists('yii\web\Application.run')
                        ->withExactTags(['component' => 'yii'])
                        ->withChildren([
                            SpanAssertion::build(
                                'yii\web\Application.runAction',
                                'yii2_test_app',
                                SpanAssertion::NOT_TESTED,
                                'index'
                            )
                                ->withExactTags(['component' => 'yii'])
                                ->withChildren([
                                    SpanAssertion::build(
                                        'app\controllers\SiteController.runAction',
                                        'yii2_test_app',
                                        SpanAssertion::NOT_TESTED,
                                        'index'
                                    )->withExactTags(['component' => 'yii'])
                                ]),
                        ])
                ])
            ]
        );
    }
}
