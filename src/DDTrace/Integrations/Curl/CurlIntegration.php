<?php

namespace DDTrace\Integrations\Curl;

use DDTrace\Http\Urls;
use DDTrace\Integrations\Integration;
use DDTrace\SpanData;
use DDTrace\Tag;
use DDTrace\Type;
use DDTrace\Util\ArrayKVStore;

/**
 * @param \DDTrace\SpanData $span
 * @param array &$info
 * @param string $tagName
 * @param mixed $curlInfoOpt
 */
function addSpanDataTagFromCurlInfo($span, &$info, $tagName, $curlInfoOpt)
{
    if (isset($info[$curlInfoOpt]) && !\trim($info[$curlInfoOpt]) !== '') {
        $span->meta[$tagName] = $info[$curlInfoOpt];
        unset($info[$curlInfoOpt]);
    }
}

final class CurlIntegration extends Integration
{
    const NAME = 'curl';

    private $weakMap = null;

    public function getName()
    {
        return self::NAME;
    }

    public function init()
    {
        if (!extension_loaded('curl')) {
            return Integration::NOT_AVAILABLE;
        }

        if (!Integration::shouldLoad(self::NAME)) {
            return Integration::NOT_LOADED;
        }

        $integration = $this;

        \DDTrace\trace_function('curl_exec', [
            // the ddtrace extension will handle distributed headers
            'instrument_when_limited' => 0,
            'posthook' => function (SpanData $span, $args, $retval) use ($integration) {
                $span->name = $span->resource = 'curl_exec';
                $span->type = Type::HTTP_CLIENT;
                $integration->addTraceAnalyticsIfEnabled($span);

                if (!isset($args[0])) {
                    return;
                }

                $ch = $args[0];
                if (isset($retval) && $retval === false) {
                    $span->meta[Tag::ERROR_MSG] = \curl_error($ch);
                    $span->meta[Tag::ERROR_TYPE] = 'curl error';
                }

                $info = \curl_getinfo($ch);
                $sanitizedUrl = \DDtrace\Private_\util_url_sanitize($info['url']);
                $normalizedPath = \DDtrace\Private_\util_uri_normalize_outgoing_path($info['url']);
                unset($info['url']);

                if (\ddtrace_config_http_client_split_by_domain_enabled()) {
                    $span->service = Urls::hostnameForTag($sanitizedUrl);
                }

                $span->resource = $normalizedPath;

                /* Special case the Datadog Standard Attributes
                 * See https://docs.datadoghq.com/logs/processing/attributes_naming_convention/
                 */
                $span->meta[Tag::HTTP_URL] = $sanitizedUrl;
                $span->meta[Tag::HTTP_METHOD] = $integration->fetchCurlValue($ch, Tag::HTTP_METHOD, 'GET');
                $span->meta[Tag::COMPONENT] = 'curl';

                addSpanDataTagFromCurlInfo($span, $info, Tag::HTTP_STATUS_CODE, 'http_code');

                // Datadog sets durations in nanoseconds - convert from seconds
                $span->meta['duration'] = $info['total_time'] * 1000000000;
                unset($info['duration']);

                addSpanDataTagFromCurlInfo($span, $info, 'network.client.ip', 'local_ip');
                addSpanDataTagFromCurlInfo($span, $info, 'network.client.port', 'local_port');

                addSpanDataTagFromCurlInfo($span, $info, 'network.destination.ip', 'primary_ip');
                addSpanDataTagFromCurlInfo($span, $info, 'network.destination.port', 'primary_port');

                addSpanDataTagFromCurlInfo($span, $info, 'network.bytes_read', 'size_download');
                addSpanDataTagFromCurlInfo($span, $info, 'network.bytes_written', 'size_upload');

                // Add the rest to a 'curl.' object
                foreach ($info as $key => $val) {
                    // Datadog doesn't support arrays in tags
                    if (\is_scalar($val) && $val !== '') {
                        // Datadog sets durations in nanoseconds - convert from seconds
                        if (\substr_compare($key, '_time', -5) === 0) {
                            $val *= 1000000000;
                        }
                        $span->meta["curl.{$key}"] = $val;
                    }
                }
            },
        ]);

        \DDTrace\hook_function('curl_setopt', null, function ($args) use ($integration) {
            if (!isset($args[0])) {
                return;
            }

            $ch = $args[0];
            $option = $args[1];
            $value = $args[2];

            switch ($option) {
                case CURLOPT_POST:
                    $integration->storeCurlValue($ch, Tag::HTTP_METHOD, 'POST');
                    break;
                case CURLOPT_CUSTOMREQUEST:
                    $integration->storeCurlValue($ch, Tag::HTTP_METHOD, $value);
                    break;
                case CURLOPT_PUT:
                    $integration->storeCurlValue($ch, Tag::HTTP_METHOD, 'PUT');
                    break;
                case CURLOPT_HTTPGET:
                    $integration->storeCurlValue($ch, Tag::HTTP_METHOD, 'GET');
                    break;
                case CURLOPT_NOBODY:
                    $integration->storeCurlValue($ch, Tag::HTTP_METHOD, 'HEAD');
                    break;
            }
        });


        return Integration::LOADED;
    }

    private function getWeakStorage($ch)
    {
        if (null == $this->weakMap) {
            $this->weakMap = new \WeakMap();
        }

        if (!isset($this->weakMap[$ch])) {
            $this->weakMap[$ch] = [];
        }

        return $this->weakMap;
    }

    public function storeCurlValue($ch, $key, $value)
    {
        if (PHP_MAJOR_VERSION < 8) {
            ArrayKVStore::putForResource($ch, $key, $value);
            return;
        }

        $wm = $this->getWeakStorage($ch);
        $wm[$ch][$key] = $value;
    }

    public function fetchCurlValue($ch, $key, $default)
    {
        if (PHP_MAJOR_VERSION < 8) {
            return ArrayKVStore::getForResource($ch, $key, $default);
        }

        $wm = $this->getWeakStorage($ch);
        return isset($wm[$ch][$key]) ? $wm[$ch][$key] : $default;
    }
}
