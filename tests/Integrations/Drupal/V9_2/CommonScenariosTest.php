<?php

namespace DDTrace\Tests\Integrations\Drupal\V9_2;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use Exception;
use DDTrace\Log\Logger;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Drupal/drupal-9.2.10/index.php';
    }

    public static function ddSetUpBeforeClass()
    {
        parent::ddSetUpBeforeClass();
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $ret = $pdo->exec(file_get_contents(__DIR__ . '/../../../Frameworks/Drupal/drupal-9.2.10/db.sql'));
        Logger::get()->error("PDO exec: " . $ret);
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE' => 'drupal_app',
            'SIGNALFX_TRACE_DEBUG' => 'true',
            'DD_TRACE_PDO_ENABLED' => 'false',
        ]);
    }

    public function testSuccessfulRequest()
    {
        $traces = $this->tracesFromWebRequest(function () {
            $this->call(GetSpec::create('Test simple page', '/user/login'));
        });

        $this->assertFlameGraph(
            $traces,
            [
                SpanAssertion::build(
                    '/user/login',
                    'drupal_app',
                    SpanAssertion::NOT_TESTED,
                    'user.login'
                )->withExactTags([
                    'symfony.route.name' => 'user.login',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/user/login',
                    'http.status_code' => '200',
                    'component' => 'symfony',
                ])->withChildren([
                    SpanAssertion::exists('drupal.event.kernel.terminate')->withChildren([
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                        SpanAssertion::exists('drupal.hook.cron'),
                    ]),
                    SpanAssertion::exists('drupal.kernel.handle')->withChildren([
                        SpanAssertion::exists('drupal.kernel.boot')->withChildren([
                        ]),
                        SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                            SpanAssertion::forOperation('drupal.event.kernel.controller')
                                ->resource('')
                                ->withExactTags([
                                    'drupal.controller' => 'controller.form:getContentResult',
                                ]),
                            SpanAssertion::exists('drupal.event.kernel.controller_arguments'),
                            SpanAssertion::exists('drupal.event.kernel.finish_request'),
                            SpanAssertion::exists('drupal.event.kernel.request')->withChildren([
                                SpanAssertion::exists('drupal.hook.language_types_info'),
                            ]),
                            SpanAssertion::exists('drupal.event.kernel.response')->withChildren([
                                SpanAssertion::exists('drupal.hook.theme_suggestions_block'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_links'),
                            ]),
                            SpanAssertion::exists('drupal.event.kernel.view')->withChildren([
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_access'),
                                SpanAssertion::exists('drupal.hook.block_content_access'),
                                SpanAssertion::exists('drupal.hook.block_content_access'),
                                SpanAssertion::forOperation('drupal.hook.entity_access')
                                    ->resource('')
                                    ->withExactTags([
                                        'drupal.module' => 'content_moderation,media'
                                    ]),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_access'),
                                SpanAssertion::exists('drupal.hook.entity_preload'),
                                SpanAssertion::exists('drupal.hook.entity_preload'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_block'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_block'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_block'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_block'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_breadcrumb'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_container'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_form'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_form_element'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_form_element'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_form_element_label'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_form_element_label'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_html'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_input'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_input'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_input'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_input'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_input'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_off_canvas_page_wrapper'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_page'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_page_title'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_page_title'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                                SpanAssertion::exists('drupal.hook.theme_suggestions_region'),
                            ]),
                        ]),
                    ]),
                ]),
            ]
        );
    }
}
