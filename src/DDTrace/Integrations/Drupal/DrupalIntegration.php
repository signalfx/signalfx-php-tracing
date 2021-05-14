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
            function (SpanData $span, $args) use ($rootSpan) {
                if (!isset($args[0])) {
                    return;
                }

                $req = $args[0];

                $route = $req->getPathInfo();
                $rootSpan->overwriteOperationName($route);
                $rootSpan->setTag(Tag::COMPONENT, 'drupal');

                $span->name = 'drupal.kernel.handle';
                $span->type = Type::WEB_SERVLET;
                $span->meta[Tag::COMPONENT] = 'drupal';
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\DrupalKernel',
            'handleException',
            function (SpanData $span, $args, $retval) use ($rootSpan) {
                $span->name = $span->resource = 'drupal.kernel.handleException';
                $span->meta[Tag::COMPONENT] = 'drupal';
                if (!(isset($retval) && \method_exists($retval, 'getStatusCode') && $retval->getStatusCode() < 500)) {
                    $rootSpan->setError($args[0]);
                }
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\DrupalKernel',
            'boot',
            function (SpanData $span) {
                $span->name = $span->resource = 'drupal.kernel.boot';
                $span->meta[Tag::COMPONENT] = 'drupal';
            }
        );

        \DDTrace\trace_method(
            'Drupal\Core\Extension\ModuleHandler',
            'invokeAll',
            function (SpanData $span) {
                $span->name = $span->resource = 'drupal.moduleHandler.invokeAll';
                $span->meta[Tag::COMPONENT] = 'drupal';
            }
        );

        \DDTrace\trace_method(
            'Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher',
            'dispatch',
            function (SpanData $span, $args) {
							if (!empty($args[0]) && is_string($args[0])) {
								$name = $args[0];
              } else if (!empty($args[1]) && is_string($args[1])) {
								$name = $args[1];
              } else if (isset($args[1]) && is_object($args[1])) {
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
							$span->meta[Tag::COMPONENT] = 'drupal';
            }
        );

        return Integration::LOADED;
    }
}
