<?php

namespace DDTrace\Integrations\Curl;

use DDTrace\Http\Urls;
use DDTrace\Integrations\Integration;
use DDTrace\SpanData;
use DDTrace\Tag;
use DDTrace\Type;

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

    public function getName()
    {
        return self::NAME;
    }

    public function init()
    {
        if (!extension_loaded('curl')) {
            return Integration::NOT_AVAILABLE;
        }

        // Waiting for refactoring from static to singleton.
        $integration = new self();
        $globalConfig = Configuration::get();

        dd_trace('curl_exec', function ($ch) use ($integration, $globalConfig) {
            $tracer = GlobalTracer::get();
            if ($tracer->limited()) {
                CurlIntegration::injectDistributedTracingHeaders($ch);

                return dd_trace_forward_call();
            }

            $scope = $tracer->startIntegrationScopeAndSpan($integration, 'curl_exec');
            $span = $scope->getSpan();
            $span->setTraceAnalyticsCandidate();
            $span->setTag(Tag::SPAN_TYPE, Type::HTTP_CLIENT);
            CurlIntegration::injectDistributedTracingHeaders($ch);

            $result = dd_trace_forward_call();
            if ($result === false && $span instanceof Span) {
                $span->setRawError(curl_error($ch), 'curl error');
            }

            $info = curl_getinfo($ch);
            $sanitizedUrl = Urls::sanitize($info['url']);
            if ($globalConfig->isHttpClientSplitByDomain()) {
                $span->setTag(Tag::SERVICE_NAME, Urls::hostnameForTag($sanitizedUrl));
            }
            $httpMethod = ArrayKVStore::getForResource($ch, "HTTP_METHOD", "GET");
            $span->setTag(Tag::HTTP_METHOD, $httpMethod);
            $span->setTag(Tag::COMPONENT, 'curl');
            $span->setTag(Tag::RESOURCE_NAME, $sanitizedUrl);
            $span->setTag(Tag::HTTP_URL, $sanitizedUrl);
            $span->setTag(Tag::HTTP_STATUS_CODE, $info['http_code']);

            $scope->close();
            return $result;
        });

        dd_trace('curl_setopt', function ($ch, $option, $value) use ($globalConfig) {
            // Note that curl_setopt with option CURLOPT_HTTPHEADER overwrite data instead of appending it if called
            // multiple times on the same resource.
            if ($option === CURLOPT_HTTPHEADER
                    && $globalConfig->isDistributedTracingEnabled()
                    && is_array($value)
            ) {
                // Storing data to be used during exec as it cannot be retrieved at then.
                ArrayKVStore::putForResource($ch, Format::CURL_HTTP_HEADERS, $value);
            }

            switch ($option) {
                case CURLOPT_CUSTOMREQUEST:
                    ArrayKVStore::putForResource($ch, "HTTP_METHOD", $value);
                    ArrayKVStore::putForResource($ch, "CUSTOMREQUEST_SET", true);
                    break;
                case CURLOPT_PUT:
                    if ($value && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                        ArrayKVStore::putForResource($ch, "HTTP_METHOD", "PUT");
                    }
                    break;
                case CURLOPT_POST:
                    if ($value && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                        ArrayKVStore::putForResource($ch, "HTTP_METHOD", "POST");
                    }
                    break;
                case CURLOPT_HTTPGET:
                    if ($value && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                        ArrayKVStore::putForResource($ch, "HTTP_METHOD", "GET");
                    }
                    break;
                case CURLOPT_NOBODY:
                    if ($value && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                        ArrayKVStore::putForResource($ch, "HTTP_METHOD", "HEAD");
                        break;
                    }
            }

            return dd_trace_forward_call();
        });

        dd_trace('curl_setopt_array', function ($ch, $options) use ($globalConfig) {
            // Note that curl_setopt with option CURLOPT_HTTPHEADER overwrite data instead of appending it if called
            // multiple times on the same resource.
            if ($globalConfig->isDistributedTracingEnabled()
                    && array_key_exists(CURLOPT_HTTPHEADER, $options)
            ) {
                // Storing data to be used during exec as it cannot be retrieved at then.
                ArrayKVStore::putForResource($ch, Format::CURL_HTTP_HEADERS, $options[CURLOPT_HTTPHEADER]);
            }

            if (array_key_exists(CURLOPT_CUSTOMREQUEST, $options)) {
                ArrayKVStore::putForResource($ch, "CUSTOMREQUEST_SET", true);
                ArrayKVStore::putForResource($ch, "HTTP_METHOD", $options[CURLOPT_CUSTOMREQUEST]);
            } elseif (array_key_exists(CURLOPT_PUT, $options)
                    && $options[CURLOPT_PUT]
                    && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                ArrayKVStore::putForResource($ch, "HTTP_METHOD", "PUT");
            } elseif (array_key_exists(CURLOPT_POST, $options)
                    && $options[CURLOPT_POST]
                    && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                ArrayKVStore::putForResource($ch, "HTTP_METHOD", "POST");
            } elseif (array_key_exists(CURLOPT_HTTPGET, $options)
                    && $options[CURLOPT_HTTPGET]
                    && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                ArrayKVStore::putForResource($ch, "HTTP_METHOD", "GET");
            } elseif (array_key_exists(CURLOPT_NOBODY, $options)
                    && $options[CURLOPT_NOBODY]
                    && !(ArrayKVStore::getForResource($ch, "CUSTOMREQUEST_SET", false))) {
                ArrayKVStore::putForResource($ch, "HTTP_METHOD", "HEAD");
            }
            return dd_trace_forward_call();
        });

        dd_trace('curl_close', function ($ch) use ($globalConfig) {
            ArrayKVStore::deleteResource($ch);
            return dd_trace_forward_call();
        });

        return Integration::LOADED;
    }

    /**
     * @param resource $ch
     */
    public static function injectDistributedTracingHeaders($ch)
    {
        if (!Configuration::get()->isDistributedTracingEnabled()) {
            return;
        }

        $integration = $this;

        \DDTrace\trace_function('curl_exec', [
            // the ddtrace extension will handle distributed headers
            'instrument_when_limited' => 0,
            'posthook' => function (SpanData $span, $args, $retval) use ($integration) {
                $span->name = $span->resource = 'curl_exec';
                $span->type = Type::HTTP_CLIENT;
                $span->service = 'curl';
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
                $sanitizedUrl = Urls::sanitize($info['url']);
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

        return Integration::LOADED;
    }
}
