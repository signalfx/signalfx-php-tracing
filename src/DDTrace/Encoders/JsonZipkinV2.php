<?php

namespace DDTrace\Encoders;

use DDTrace\Configuration;
use DDTrace\Contracts\Span;
use DDTrace\Contracts\Tracer;
use DDTrace\Encoder;
use DDTrace\GlobalTracer;
use DDTrace\Log\Logger;
use DDTrace\Log\LoggerInterface;
use DDTrace\Tag;
use DDTrace\Type;
use DDTrace\Util\HexConversion;

/**
 * This converts from the DD span format to the Zipkin V2 JSON span format.
 */
final class JsonZipkinV2 implements Encoder
{

    /**
     * @var LoggerInterface
     */
    private $logger;
    private $serviceName;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger ?: Logger::get();
        $config = Configuration::get();
        $this->serviceName = $config->appName("unnamed-php-service");
    }

    /**
     * {@inheritdoc}
     */
    public function encodeTraces(Tracer $tracer)
    {
        $traces = $tracer->getTracesAsArray();
        return '[' . implode(',', array_map(function ($trace) {
                return implode(',', array_filter(array_map(function ($span) {
                    return $this->encodeSpan($span);
                }, $trace)));
        }, $traces)) . ']';
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return 'application/json';
    }

    /**
     * @param array $span
     * @return string
     */
    private function encodeSpan(array $span)
    {
        $json = json_encode($this->spanToArray($span), JSON_FORCE_OBJECT);
        if (false === $json) {
            $this->logger->error("Failed to json-encode span: " . json_last_error_msg());
            return "";
        }

        return str_replace([
            '"start_micro":"-"',
            '"duration_micro":"-"',
        ], [
            '"timestamp":' . $span['start'],
            '"duration":' . $span['duration'],
        ], $json);
    }

    /**
     * @param array $span
     * @return array
     */
    private function spanToArray(array $span)
    {
        $arraySpan = [
            'traceId' => HexConversion::idToHex($span['trace_id']),
            'id' => HexConversion::idToHex($span['span_id']),
            'name' => $span['name'],
            // This gets filled in by string substitution to avoid exponential formats.
            'start_micro' => '-',
        ];

        if (isset($span['duration'])) {
            $arraySpan['duration_micro'] = '-';
        }

        if (isset($span['parent_id'])) {
            $arraySpan['parentId'] = HexConversion::idToHex($span['parent_id']);
        }

        $span['tags'] = [];

        if (isset($span['meta'])) {
            foreach ($span['meta'] as $key => $value) {
                $arraySpan['tags'][$key] = $value;
            }
        }

        if (!isset($span['meta']['component'])) {
            $arraySpan['tags']['component'] = $span['service'];
        }

        if (!isset($span['meta'][Tag::RESOURCE_NAME])) {
            $arraySpan['tags'][Tag::RESOURCE_NAME] = $span['resource'];
        }

        if (isset($span['type'])) {
            switch ($span['type']) {
                case Type::HTTP_CLIENT:
                    $arraySpan['kind'] = "CLIENT";
                    break;
                case Type::CLI:
                case Type::WEB_SERVLET:
                    $arraySpan['kind'] = "SERVER";
                    break;
            }
            $arraySpan['tags'][Tag::SPAN_TYPE] = $span['type'];
        }

        if (isset($span['error']) && $span['error']) {
            $arraySpan['tags'][Tag::ERROR] = "true";
        }

        $arraySpan['localEndpoint'] = [
            'serviceName' => $this->serviceName,
        ];

        return $arraySpan;
    }
}
