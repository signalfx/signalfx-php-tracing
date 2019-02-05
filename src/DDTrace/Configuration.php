<?php

namespace DDTrace;

use DDTrace\Configuration\AbstractConfiguration;

/**
 * DDTrace global configuration object.
 */
class Configuration extends AbstractConfiguration
{
    /**
     * Whether or not tracing is enabled.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->boolValue('trace.enabled', true);
    }

    /**
     * Whether or not debug mode is enabled.
     *
     * @return bool
     */
    public function isDebugModeEnabled()
    {
        return $this->boolValue('trace.debug', false);
    }

    /**
     * Whether or not distributed tracing is enabled globally.
     *
     * @return bool
     */
    public function isDistributedTracingEnabled()
    {
        return $this->boolValue('distributed.tracing', true);
    }

    /**
     * Whether or not priority sampling is enabled globally.
     *
     * @return bool
     */
    public function isPrioritySamplingEnabled()
    {
        return $this->isDistributedTracingEnabled()
            && $this->boolValue('priority.sampling', true);
    }

    /**
     * Whether or not also unfinished spans should be finished (and thus sent) when tracer is flushed.
     * Motivation: We had users reporting that in some cases they have manual end-points that `echo` some content and
     * then just `exit(0)` at the end of action's method. While the shutdown hook that flushes traces would still be
     * called, many spans would be unfinished and thus discarded. With this option enabled spans are automatically
     * finished (if not finished yet) when the tracer is flushed.
     *
     * @return bool
     */
    public function isAutofinishSpansEnabled()
    {
        return $this->boolValue('autofinish.spans', false);
    }

    /**
     * Returns the sampling rate provided by the user. Default: 1.0 (keep all).
     *
     * @return float
     */
    public function getSamplingRate()
    {
        return $this->floatValue('sampling.rate', 1.0, 0.0, 1.0);
    }

    /**
     * Whether or not a specific integration is enabled.
     *
     * @param string $name
     * @return bool
     */
    public function isIntegrationEnabled($name)
    {
        return $this->isEnabled() && !$this->inArray('integrations.disabled', $name);
    }

    /**
     * The name of the application.
     *
     * @param string $default
     * @return string
     */
    public function appName($default = '')
    {
        $appName = $this->stringValue('service.name');
        if ($appName) {
            return $appName;
        }
        $appName = getenv('ddtrace_app_name');
        if (false !== $appName) {
            return trim($appName);
        }
        return $default;
    }

    public function getEndpointURL()
    {
        $endpoint = $this->stringValue("endpoint.url", '');
        if ($endpoint === "") {
            $parts = [
                "scheme" => $this->getEndpointHTTPS() === true ? "https" : "http",
                "host" => $this->getEndpointHost(),
                "port" => $this->getEndpointPort(),
                "path" => $this->getEndpointPath(),
            ];
        } else {
            $parts = parse_url($endpoint);
        }

        $portPart = "";
        if (isset($parts['port']) &&
            (($parts['scheme'] === "https" && $parts['port'] !== "443") ||
                ($parts['scheme'] === "http" && $parts['port'] !== "80"))) {
            $portPart = ":" . $parts['port'];
        }

        return "${parts['scheme']}://${parts['host']}${portPart}${parts['path']}";
    }

    private function getEndpointHost()
    {
        return $this->stringValue("endpoint.host", 'localhost');
    }

    private function getEndpointPort()
    {
        return $this->stringValue("endpoint.port", '9080');
    }

    private function getEndpointPath()
    {
        return $this->stringValue("endpoint.path", '/v1/trace');
    }

    private function getEndpointHTTPS()
    {
        return $this->boolValue("endpoint.https", false);
    }

    public function getAccessToken()
    {
        return $this->stringValue("access.token");
    }
}
