<?php

namespace DDTrace\Tests\Unit;

use DDTrace\Configuration;

final class ConfigurationTest extends BaseTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->cleanEnv();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->cleanEnv();
    }

    private function cleanEnv()
    {
        putenv('SIGNALFX_DISTRIBUTED_TRACING');
        putenv('SIGNALFX_ENDPOINT_HOST');
        putenv('SIGNALFX_ENDPOINT_HTTPS');
        putenv('SIGNALFX_ENDPOINT_PATH');
        putenv('SIGNALFX_ENDPOINT_PORT');
        putenv('SIGNALFX_ENDPOINT_URL');
        putenv('SIGNALFX_INTEGRATIONS_DISABLED');
        putenv('SIGNALFX_PRIORITY_SAMPLING');
        putenv('SIGNALFX_SERVICE_NAME');
        putenv('SIGNALFX_TRACE_APP_NAME');
        putenv('SIGNALFX_TRACE_ANALYTICS_ENABLED');
        putenv('SIGNALFX_TRACE_DEBUG');
        putenv('SIGNALFX_TRACING_ENABLED');
        putenv('ddtrace_app_name');
    }

    public function testTracerEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isEnabled());
    }

    public function testTracerDisabled()
    {
        putenv('SIGNALFX_TRACING_ENABLED=false');
        $this->assertFalse(Configuration::get()->isEnabled());
    }

    public function testDebugModeDisabledByDefault()
    {
        $this->assertFalse(Configuration::get()->isDebugModeEnabled());
    }

    public function testDebugModeCanBeEnabled()
    {
        putenv('SIGNALFX_TRACE_DEBUG=true');
        $this->assertTrue(Configuration::get()->isDebugModeEnabled());
    }

    public function testDistributedTracingEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isDistributedTracingEnabled());
    }

    public function testDistributedTracingDisabled()
    {
        putenv('SIGNALFX_DISTRIBUTED_TRACING=false');
        $this->assertFalse(Configuration::get()->isDistributedTracingEnabled());
    }

    public function testPrioritySamplingEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isPrioritySamplingEnabled());
    }

    public function testPrioritySamplingDisabled()
    {
        putenv('SIGNALFX_PRIORITY_SAMPLING=false');
        $this->assertFalse(Configuration::get()->isPrioritySamplingEnabled());
    }

    public function testAllIntegrationsEnabledByDefault()
    {
        $this->assertTrue(Configuration::get()->isIntegrationEnabled('any_one'));
    }

    public function testIntegrationsDisabled()
    {
        putenv('SIGNALFX_INTEGRATIONS_DISABLED=one,two');
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('one'));
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('two'));
        $this->assertTrue(Configuration::get()->isIntegrationEnabled('three'));
    }

    public function testIntegrationsDisabledIfGlobalDisabled()
    {
        putenv('SIGNALFX_INTEGRATIONS_DISABLED=one');
        putenv('SIGNALFX_TRACING_ENABLED=false');
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('one'));
        $this->assertFalse(Configuration::get()->isIntegrationEnabled('two'));
    }

    public function testAppNameFallbackPriorities()
    {
        putenv('SIGNALFX_SERVICE_NAME=bar_app');
        $this->assertSame('bar_app', Configuration::get()->appName());
    }

    public function testEndpointURLTakesPrecedence()
    {
        putenv('SIGNALFX_ENDPOINT_URL=https://ingest.signalfx.com/asdf');
        $this->assertSame("https://ingest.signalfx.com/asdf", Configuration::get()->getEndpointURL());
    }

    public function testEndpointURLMadeFromDefaultParts()
    {
        putenv('SIGNALFX_ENDPOINT_URL');
        $this->assertSame("http://localhost:9080/v1/trace", Configuration::get()->getEndpointURL());
    }

    public function testEndpointURLMadeFromOverriddenParts()
    {
        putenv('SIGNALFX_ENDPOINT_HTTPS=true');
        putenv('SIGNALFX_ENDPOINT_HOST=example.com');
        putenv('SIGNALFX_ENDPOINT_PORT=500');
        putenv('SIGNALFX_ENDPOINT_PATH=/asdf');
        $this->assertSame("https://example.com:500/asdf", Configuration::get()->getEndpointURL());
    }

    public function testServiceName()
    {
        putenv('SIGNALFX_SERVICE_NAME');
        putenv('SIGNALFX_TRACE_APP_NAME');
        putenv('ddtrace_app_name');
        Configuration::clear();

        $this->assertSame('__default__', Configuration::get()->appName('__default__'));

        putenv('SIGNALFX_SERVICE_NAME=my_app');
        $this->assertSame('my_app', Configuration::get()->appName('my_app'));
    }

    public function testServiceNameHasPrecedenceOverDeprecatedMethods()
    {
        Configuration::clear();

        putenv('SIGNALFX_SERVICE_NAME=my_app');
        putenv('SIGNALFX_TRACE_APP_NAME=wrong_app');
        putenv('ddtrace_app_name=wrong_app');
        $this->assertSame('my_app', Configuration::get()->appName('my_app'));
    }

    public function testAnalyticsDisabledByDefault()
    {
        $this->assertFalse(Configuration::get()->isAnalyticsEnabled());
    }

    public function testAnalyticsCanBeGloballyEnabled()
    {
        putenv('SIGNALFX_TRACE_ANALYTICS_ENABLED=true');
        $this->assertTrue(Configuration::get()->isAnalyticsEnabled());
    }
}
