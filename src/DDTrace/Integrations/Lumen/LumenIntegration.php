<?php

namespace DDTrace\Integrations\Lumen;

use DDTrace\GlobalTracer;
use DDTrace\SpanData;
use DDTrace\Integrations\Integration;
use DDTrace\Tag;

/**
 * Lumen Sandboxed integration
 */
class LumenIntegration extends Integration
{
    const NAME = 'lumen';

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * {@inheritdoc}
     */
    public function requiresExplicitTraceAnalyticsEnabling()
    {
        return false;
    }

    /**
     * @return int
     */
    public function init()
    {
        if (!self::shouldLoad(self::NAME)) {
            return Integration::NOT_LOADED;
        }

        $rootScope = GlobalTracer::get()->getRootScope();
        $rootSpan = null;

        if (null === $rootScope || null === ($rootSpan = $rootScope->getSpan())) {
            return Integration::NOT_LOADED;
        }

        $integration = $this;

        \DDTrace\trace_method(
            'Laravel\Lumen\Application',
            'prepareRequest',
            function (SpanData $span, $args) use ($rootSpan, $integration) {
                $request = $args[0];
                $rootSpan->overwriteOperationName('lumen.request');
                $integration->addTraceAnalyticsIfEnabledLegacy($rootSpan);
                $rootSpan->setTag(Tag::HTTP_URL, $request->getUri());
                $rootSpan->setTag(Tag::HTTP_METHOD, $request->getMethod());
                $rootSpan->setTag(Tag::COMPONENT, 'lumen');
                return false;
            }
        );

        // convert to non-tracing API
        $hook = 'posthook';
        // Extracting resource name as in legacy integration
        \DDTrace\trace_method(
            'Laravel\Lumen\Application',
            'handleFoundRoute',
            [
                $hook => function (SpanData $span, $args) use ($rootSpan) {
                    $span->type = 'web';
                    $span->meta[Tag::COMPONENT] = 'lumen';
                    if (count($args) < 1 || !\is_array($args[0])) {
                        return;
                    }
                    $routeInfo = $args[0];
                    $resourceName = null;
                    if (isset($routeInfo[1]['uses'])) {
                        $action = $routeInfo[1]['uses'];
                        $rootSpan->setTag('lumen.route.action', $action);
                        $rootSpan->overwriteOperationName($action);
                        $resourceName = $action;
                    }
                    if (isset($routeInfo[1]['as'])) {
                        $rootSpan->setTag('lumen.route.name', $routeInfo[1]['as']);
                        $resourceName = $routeInfo[1]['as'];
                    }

                    if (null !== $resourceName) {
                        $rootSpan->setTag(
                            Tag::RESOURCE_NAME,
                            $rootSpan->getTag(Tag::HTTP_METHOD) . ' ' . $resourceName
                        );
                    }
                },
            ]
        );

        $exceptionRender = function (SpanData $span, $args) use ($rootSpan) {
            $span->type = 'web';
            $span->meta[Tag::COMPONENT] = 'lumen';
            if (count($args) < 1 || !\is_a($args[0], 'Throwable')) {
                return;
            }
            $exception = $args[0];
            $rootSpan->setError($exception);
        };

        \DDTrace\trace_method('Laravel\Lumen\Application', 'handleUncaughtException', [$hook => $exceptionRender]);
        \DDTrace\trace_method('Laravel\Lumen\Application', 'sendExceptionToHandler', [$hook => $exceptionRender]);

        // View is rendered in laravel as the method name overlaps

        return Integration::LOADED;
    }
}
