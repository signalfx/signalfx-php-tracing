<?php

namespace DDTrace\Tests\Integration;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

class RequestHeaderCaptureTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/ResponseStatusCodeTest_files/index.php';
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'DD_TRACE_NO_AUTOLOADER' => '1',
            'SIGNALFX_CAPTURE_REQUEST_HEADERS' => 'X-Foo',
        ]);
    }

    public function testCapture()
    {
        $traces = $this->tracesFromWebRequest(
            function () {
                $this->call(GetSpec::create('Root', '/success', [
                    'X-Foo: 42',
                ]));
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
                    'http.request.header.x_foo' => '42',
                ]),
            ]
        );
    }
}
