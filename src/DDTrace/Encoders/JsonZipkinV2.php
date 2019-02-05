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
        $this->serviceName = $config->appName("unnamed_php_app");
    }

    /**
     * {@inheritdoc}
     */
    public function encodeTraces(array $traces)
    {
        /** @var Tracer $tracer */
        $tracer = GlobalTracer::get();
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
     * @param Span $span
     * @return string
     */
    private function encodeSpan(Span $span)
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
            '"timestamp":' . $span->getStartTime(),
            '"duration":' . $span->getDuration(),
        ], $json);
    }

    /**
     * @param Span $span
     * @return array
     */
    private function spanToArray(Span $span)
    {
        $arraySpan = [
            'traceId' => HexConversion::idToHex($span->getTraceId()),
            'id' => HexConversion::idToHex($span->getSpanId()),
            'name' => $span->getOperationName(),
            // This gets filled in by string substitution to avoid exponential formats.
            'start_micro' => '-',
        ];

        if ($span->isFinished()) {
            $arraySpan['duration_micro'] = '-';
        }

        if ($span->getParentId() !== null) {
            $arraySpan['parentId'] = HexConversion::idToHex($span->getParentId());
        }

        $arraySpan['tags'] = $span->getAllTags();

        if ($span->getTag('component') === null) {
            $arraySpan['tags']['component'] = $span->getService();
        }

        if ($span->getTag(Tag::RESOURCE_NAME) === null) {
            $arraySpan['tags'][Tag::RESOURCE_NAME] = $span->getResource();
        }

        if ($span->getType() !== null) {
            switch ($span->getType()) {
                case Type::HTTP_CLIENT:
                    $arraySpan['kind'] = "CLIENT";
                    break;
                case Type::CLI:
                case Type::WEB_SERVLET:
                    $arraySpan['kind'] = "SERVER";
                    break;
            }
            $arraySpan['tags'][Tag::SPAN_TYPE] = $span->getType();
        }

        if ($span->hasError()) {
            $arraySpan['tags'][Tag::ERROR] = "true";
        }

        $arraySpan['localEndpoint'] = [
            'serviceName' => $this->serviceName,
        ];

        return $arraySpan;
    }
}
