<?php

namespace DDTrace\Tests\Integrations\Symfony\V5_0;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class TraceSearchConfigTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Symfony/Version_5_0/public/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE' => 'symfony',
        ]);
    }
    /**
     * @throws \Exception
     */
    public function testScenario()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Testing trace analytics config metric', '/simple'));
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    'symfony.request',
                    'symfony',
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
                                'symfony',
                                SpanAssertion::NOT_TESTED,
                                'App\Controller\CommonScenariosController::simpleAction'
                            )
                            ->withExactTags([
                                'component' => 'symfony',
                            ]),
                            SpanAssertion::exists('symfony.kernel.response'),
                            SpanAssertion::exists('symfony.kernel.finish_request'),
                        ]),
                    ]),
                ]),
            ]
        );
    }
}
