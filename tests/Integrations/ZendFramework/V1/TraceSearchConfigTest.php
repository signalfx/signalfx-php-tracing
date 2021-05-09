<?php

namespace DDTrace\Tests\Integrations\ZendFramework\V1;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class TraceSearchConfigTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/ZendFramework/Version_1_12/public/index.php';
    }

    /**
     * @throws \Exception
     */
    public function testScenario()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Testing trace analytics config metric', '/simple'));
        });

        $this->assertExpectedSpans(
            $traces,
            [
                SpanAssertion::build(
                    'simple@index default',
                    'unnamed-php-service',
                    SpanAssertion::NOT_TESTED,
                    SpanAssertion::NOT_TESTED,
                )
                ->withExactTags([
                    'zf1.controller' => 'simple',
                    'zf1.action' => 'index',
                    'zf1.route_name' => 'default',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/simple',
                    'http.status_code' => '200',
                    'component' => 'zendframework',
                ])
            ]
        );
    }
}
