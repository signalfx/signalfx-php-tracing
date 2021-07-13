<?php

namespace DDTrace\Integrations\Drupal;

use DDTrace\GlobalTracer;
use DDTrace\SpanData;
use DDTrace\Integrations\Integration;
use DDTrace\Tag;
use DDTrace\Type;

class DrupalIntegration extends Integration
{
    const NAME = 'drupal';

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

    public function shouldRenameRootSpan()
    {
        return \sfx_trace_config_drupal_rename_span();
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
            'Drupal\Core\DrupalKernel',
            'handle',
            function (SpanData $span, $args) use ($rootSpan, $integration) {
                if (!isset($args[0])) {
                    return;
                }

                $req = $args[0];

                if ($integration->shouldRenameRootSpan()) {
                    $route = DrupalCommon::normalizeRoute($req->getPathInfo());
                    $rootSpan->overwriteOperationName($route);
                }

                $span->name = 'drupal.kernel.handle';
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\DrupalKernel',
            'handleException',
            function (SpanData $span, $args, $retval) use ($rootSpan) {
                $span->name = 'drupal.kernel.handleException';
                $span->meta[Tag::COMPONENT] = 'drupal';
                $rootSpan->setError($args[0]);
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\DrupalKernel',
            'boot',
            function (SpanData $span) {
                $span->name = 'drupal.kernel.boot';
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\Extension\ModuleHandler',
            'invokeAll',
            function (SpanData $span, $args) {
                if (!empty($args[0])) {
                    $span->name = 'drupal.hook.' . $args[0];
                } else {
                    $span->name = 'drupal.moduleHandler.invokeAll';
                }
            }
        );

        \DDTrace\trace_method(
            'Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher',
            'dispatch',
            function (SpanData $span, $args) {
                if (!empty($args[0]) && is_string($args[0])) {
                    $name = $args[0];
                } elseif (!empty($args[1]) && is_string($args[1])) {
                    $name = $args[1];
                } elseif (isset($args[1]) && is_object($args[1])) {
                    $event = $args[1];

                    if (property_exists($event, 'name')) {
                        $name = $event->name;
                    } else {
                        $name = \get_class($event);
                    }
                } else {
                    $name = 'unknown';
                }

                $span->name = 'drupal.event.' . $name;
            }
        );

        return Integration::LOADED;
    }
}
