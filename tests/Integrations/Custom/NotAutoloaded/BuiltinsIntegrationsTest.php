<?php

namespace DDTrace\Tests\Integrations\Custom\NotAutoloaded;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;

final class BuiltinsIntegrationsTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Custom/Version_Not_Autoloaded/Builtins/index.php';
    }

    protected function ddSetUp()
    {
        parent::ddSetUp();
        if (PHP_VERSION_ID >= 70100 && PHP_VERSION_ID < 70200) {
            // https://bugs.php.net/bug.php?id=72724
            // https://bugs.php.net/bug.php?id=72734
            $this->markTestSkipped('This test triggers leak within PHP 7.1 which cause test to fail');
        }
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_TRACE_FILE_GET_CONTENTS' => '1',
            'SIGNALFX_TRACE_JSON' => '1',
            'DD_SERVICE' => 'my-service'
        ]);
    }

    public function testFileGetContents()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = GetSpec::create('', '/?file_get_contents');
            $this->call($spec);
        });

        $tags = [
            'http.method' => 'GET',
            'http.url' => 'http://localhost:' . self::PORT . '/',
            'http.status_code' => 200,
            'component' => 'web.request'
        ];

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::exists('web.request')->withChildren(
                    SpanAssertion::build(
                        'file_get_contents',
                        'my-service',
                        'web',
                        'file_get_contents'
                    )->withExactTags([
                        'file.name' => '/proc/self/exe'
                    ])
                )
            ]
        );
    }

    public function testJsonEncodeDecode()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $spec = GetSpec::create('', '/?json_encode&json_decode');
            $this->call($spec);
        });

        $tags = [
            'http.method' => 'GET',
            'http.url' => 'http://localhost:' . self::PORT . '/',
            'http.status_code' => 200,
            'component' => 'web.request'
        ];

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::exists('web.request')->withChildren([
                    SpanAssertion::build(
                        'json_encode',
                        'my-service',
                        'web',
                        'json_encode'
                    ),
                    SpanAssertion::build(
                        'json_decode',
                        'my-service',
                        'web',
                        'json_decode'
                    )
                ])
            ]
        );
    }
}
