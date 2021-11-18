<?php

namespace DDTrace\Integrations\Drupal;

use DDTrace\GlobalTracer;
use DDTrace\Http\Urls;
use DDTrace\SpanData;
use DDTrace\Integrations\Integration;
use DDTrace\Tag;
use DDTrace\Type;

class DrupalIntegration extends Integration
{
    const NAME = 'drupal';

    protected $drupalVersion = null;

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

        \DDTrace\hook_function('drupal_bootstrap', function () use ($integration, $rootSpan) {
            if (
                $integration->drupalVersion ||
                !defined('DRUPAL_CORE_COMPATIBILITY') ||
                DRUPAL_CORE_COMPATIBILITY !== '7.x'
            ) {
                return false;
            }

            // Only load integration once
            $integration->drupalVersion = DRUPAL_CORE_COMPATIBILITY;
            $this->drupal7($rootSpan);
        });

        $this->drupalSymfony($rootSpan);

        return Integration::LOADED;
    }

    protected function drupal7($rootSpan)
    {
        $integration = $this;

        \DDTrace\trace_function('menu_execute_active_handler', function (SpanData $span) use ($rootSpan) {
            if (!empty($GLOBALS['user'])) {
                $user = $GLOBALS['user'];
                $rootSpan->setTag('drupal.user.id', $user->uid);
                $rootSpan->setTag('drupal.user.roles', implode(',', array_values($user->roles)));
            }
            $span->name = 'menu_execute_active_handler';
            $span->meta[Tag::COMPONENT] = 'drupal';
        });

        $methods = array(
            '_drupal_bootstrap_full', '_drupal_bootstrap_page_cache', '_drupal_bootstrap_database',
            '_drupal_bootstrap_variables', 'drupal_session_initialize', '_drupal_bootstrap_page_header',
            'drupal_language_initialize', 'drupal_deliver_page', 'drupal_cron_run',
        );
        foreach ($methods as $method) {
            \DDTrace\trace_function($method, function (SpanData $span) use ($method) {
                $span->name = $method;
                $span->meta[Tag::COMPONENT] = 'drupal';
            });
        }

        \DDTrace\trace_function('drupal_http_request', [
            'posthook' => function (SpanData $span, $args, $retval) {
                $span->name = 'drupal_http_request';
                $span->meta[Tag::COMPONENT] = 'drupal';

                $url = $args[0];
                $options = (count($args) > 1) ? $args[1] : [];

                // Based on CurlIntegration
                $sanitizedUrl = Urls::sanitize($url);
                $normalizedPath = \DDtrace\Private_\util_uri_normalize_outgoing_path($url);

                $span->resource = $normalizedPath;
                $span->meta[Tag::HTTP_URL] = $sanitizedUrl;
                $span->meta[Tag::HTTP_METHOD] = array_key_exists('method', $options) ? $options['method'] : 'GET';

                if (\ddtrace_config_http_client_split_by_domain_enabled()) {
                    $span->service = Urls::hostnameForTag($sanitizedUrl);
                }

                $span->meta[Tag::HTTP_STATUS_CODE] = $retval->code;
                if ($retval->error) {
                    $span->meta[Tag::ERROR_KIND] = 'drupal_http_request error';
                    $span->meta[Tag::ERROR_MSG] = $retval->error;
                }
            }
        ]);

        \DDTrace\trace_function('module_invoke', function (SpanData $span, $args) {
            $span->name = 'module_invoke';
            $span->resource = $args[0] . '_' . $args[1];
            $span->meta[Tag::COMPONENT] = 'drupal';
        });

        \DDTrace\trace_function('module_invoke_all', function (SpanData $span, $args) {
            $span->name = 'module_invoke_all';
            $span->resource = $args[0];
            $span->meta[Tag::COMPONENT] = 'drupal';
        });

        // Extract route
        \DDTrace\hook_function('menu_get_item', null, function ($args, $retval) use ($integration, $rootSpan) {
            if (count($args) > 0 && $args[0] !== null) {
                return;
            }

            if ($integration->shouldRenameRootSpan()) {
                $path = $retval['path'];
                $rootSpan->overwriteOperationName($path);
            }
        });

        // Can't directly trace functions called by set_error_handler & set_exception_handler
        \DDTrace\trace_function('_drupal_error_handler_real', function (SpanData $span, $args) {
            $span->name = '_drupal_error_handler';
            $span->meta[Tag::COMPONENT] = 'drupal';
            $span->meta[Tag::ERROR_MSG] = $args[1];
            $span->meta[Tag::ERROR_TYPE] = 'error handler';
            $span->meta[Tag::ERROR_STACK] = $args[2] . ':' . $args[3];
        });

        \DDTrace\trace_function(
            '_drupal_decode_exception',
            function (SpanData $span, $args) use ($integration, $rootSpan) {
                $span->name = '_drupal_exception_handler';
                $span->meta[Tag::COMPONENT] = 'drupal';
                $integration->setError($span, $args[0]);
                $rootSpan->setError($args[0]);
            }
        );
    }

    protected function drupalSymfony($rootSpan)
    {
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

                $user = \Drupal::currentUser();

                if ($user->isAuthenticated()) {
                    $rootSpan->setTag('drupal.user.id', $user->id());
                    $rootSpan->setTag('drupal.user.roles', implode(',', $user->getRoles()));
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
    }
}
