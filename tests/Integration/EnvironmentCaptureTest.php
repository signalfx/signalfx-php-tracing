<?php

namespace DDTrace\Tests\Integration;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class EnvironmentCaptureTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/ResponseStatusCodeTest_files/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_TRACE_NO_AUTOLOADER' => '1',
            'MY_FOO' => '42',
            'MY_BAR' => 'xyz',
            'SIGNALFX_CAPTURE_ENV_VARS' => 'MY_FOO,MY_BAR',
        ]);
    }

    public function testCapture()
    {
        $traces = $this->tracesFromWebRequest(
            function () {
                $this->call(GetSpec::create('Root', '/success'));
            }
        );

        $this->assertExpectedSpans(
            $traces,
            [
                SpanAssertion::build('web.request', 'unnamed-php-service', '', 'GET /success')->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/success',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                    'php.env.my_foo' => '42',
                    'php.env.my_bar' => 'xyz',
                ]),
            ]
        );
    }
}
