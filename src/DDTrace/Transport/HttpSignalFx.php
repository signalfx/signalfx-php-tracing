<?php

namespace DDTrace\Transport;

use DDTrace\Contracts\Tracer;
use DDTrace\Log\LoggingTrait;
use DDTrace\Encoder;
use DDTrace\Transport;

final class HttpSignalFx implements Transport
{
    use LoggingTrait;

    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var array
     */
    private $headers = [];

    /**
     * @var array
     */
    protected $config;

    public function __construct(Encoder $encoder, array $config = [])
    {
        $this->configure($config);

        $this->encoder = $encoder;
    }

    /**
     * Configures this http transport.
     *
     * @param array $config
     */
    protected function configure($config)
    {
        $endpoint = \sfx_trace_config_endpoint_url();
        $accessToken = \sfx_trace_config_access_token();
        if ($accessToken !== "") {
            $this->setHeader('X-SF-Token', $accessToken);
        }


        $this->config = array_merge([
            'endpoint' => $endpoint,
            'debug' => \ddtrace_config_debug_enabled(),
        ], $config);
    }

    public function send(Tracer $tracer)
    {
        $tracesCount = $tracer->getTracesCount();
        $tracesPayload = $this->encoder->encodeTraces($tracer);
        self::logDebug("About to send ~{$tracesCount} traces");

        $this->sendRequest($this->config['endpoint'], $this->headers, $tracesPayload);
    }

    public function setHeader($key, $value)
    {
        $this->headers[(string)$key] = (string)$value;
    }

    public function getConfig()
    {
        return $this->config;
    }

    private function sendRequest($url, array $headers, $body)
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        $curlHeaders = [
            'Content-Type: ' . $this->encoder->getContentType(),
            'Content-Length: ' . strlen($body),
        ];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = "$key: $value";
        }
        curl_setopt($handle, CURLOPT_HTTPHEADER, $curlHeaders);

        if ($this->config["debug"]) {
            self::logDebug('Sending spans: {body} to {url}', ['body' => $body, 'url' => $url]);
        }

        $resp = curl_exec($handle);
        if ($resp === false) {
            self::logError('Reporting of spans failed: {num} / {error}', [
                'error' => curl_error($handle),
                'num' => curl_errno($handle),
            ]);

            return;
        }

        $statusCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($this->config["debug"]) {
            self::logDebug("Response from server: " . $resp);
        }

        if ($statusCode !== 200) {
            self::logError(
                'Reporting of spans failed, status code {code}: {respBody}\n',
                ['code' => $statusCode, 'respBody' => $resp]
            );
            return;
        }

        self::logDebug('Spans successfully sent');
    }
}
