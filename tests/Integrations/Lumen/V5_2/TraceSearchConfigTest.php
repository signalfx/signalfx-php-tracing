<?php

namespace DDTrace\Tests\Integrations\Lumen\V5_2;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class TraceSearchConfigTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Lumen/Version_5_2/public/index.php';
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
                    'App\Http\Controllers\ExampleController@simple',
                    'unnamed-php-service',
                    SpanAssertion::NOT_TESTED,
                    'GET simple_route'
                )->withExactTags([
                    'lumen.route.name' => 'simple_route',
                    'lumen.route.action' => 'App\Http\Controllers\ExampleController@simple',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/simple',
                    'http.status_code' => '200',
                    'component' => 'lumen',
                ])->withChildren([
                    SpanAssertion::exists('Laravel\Lumen\Application.handleFoundRoute'),
                ])
            ]
        );
    }
}
