<?php

namespace DDTrace\Tests\Unit\Transport;

use DDTrace\Encoders\JsonZipkinV2;
use DDTrace\Tests\Common\BaseTestCase;
use DDTrace\Tests\Unit\CleanEnvTrait;
use DDTrace\Transport\HttpSignalFx;

final class HttpSignalFxTest extends BaseTestCase
{
    use CleanEnvTrait;

    public function getCleanEnvs()
    {
        return ['SIGNALFX_ENDPOINT_HOST', 'SIGNALFX_ENDPOINT_PORT'];
    }

    public function testConfigWithDefaultValues()
    {
        $httpTransport = new HttpSignalFx(new JsonZipkinV2());
        $this->assertEquals('http://localhost:9080/v1/trace', $httpTransport->getConfig()['endpoint']);
    }

    public function testConfig()
    {
        $endpoint = '__end_point___';
        $httpTransport = new HttpSignalFx(new JsonZipkinV2(), ['endpoint' => $endpoint]);
        $this->assertEquals($endpoint, $httpTransport->getConfig()['endpoint']);
    }

    public function testConfigPortFromEnv()
    {
        putenv('SIGNALFX_ENDPOINT_PORT=8888');
        $httpTransport = new HttpSignalFx(new JsonZipkinV2());
        $this->assertEquals('http://localhost:8888/v1/trace', $httpTransport->getConfig()['endpoint']);
    }

    public function testConfigHostFromEnv()
    {
        putenv('SIGNALFX_ENDPOINT_HOST=other_host');
        $httpTransport = new HttpSignalFx(new JsonZipkinV2());
        $this->assertEquals('http://other_host:9080/v1/trace', $httpTransport->getConfig()['endpoint']);
    }
}
