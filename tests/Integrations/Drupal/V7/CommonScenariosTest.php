<?php

namespace DDTrace\Tests\Integrations\Drupal\V7;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use Exception;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Drupal/Version_7/index.php';
    }

    public static function ddSetUpBeforeClass()
    {
        parent::ddSetUpBeforeClass();
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $pdo->exec(file_get_contents(__DIR__ . '/../../../Frameworks/Drupal/Version_7/db.sql'));
    }

    protected static function getEnvs()
    {
        return array_merge(parent::getEnvs(), [
            'SIGNALFX_SERVICE' => 'drupal_app',
            'SIGNALFX_TRACE_DEBUG' => 'true',
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
                    'user/login',
                    'drupal_app',
                    SpanAssertion::NOT_TESTED,
                    'GET /user/login'
                )->withExactTags([
                    'http.method' => 'GET',
                    'http.url' => '/user/login',
                    'http.status_code' => '200',
                    'component' => 'web.request',
                    'drupal.user.id' => '0',
                    'drupal.user.roles' => 'anonymous user',
                ])->withChildren([
                    SpanAssertion::exists('PDO.prepare'),
                    SpanAssertion::exists('PDO.prepare'),
                    SpanAssertion::exists('PDOStatement.execute'),
                    SpanAssertion::exists('PDOStatement.execute'),
                    SpanAssertion::exists('_drupal_bootstrap_full')->withChildren(array_merge(
                            array_fill(0, 5, SpanAssertion::exists('PDO.prepare')),
                            array_fill(0, 5, SpanAssertion::exists('PDOStatement.execute'))
                    )),
                    SpanAssertion::exists('_drupal_bootstrap_page_cache')->withChildren([
                        SpanAssertion::exists('PDO.prepare'),
                        SpanAssertion::exists('PDOStatement.execute'),
                        SpanAssertion::exists('_drupal_bootstrap_database'),
                        SpanAssertion::exists('_drupal_bootstrap_variables')->withChildren(array_merge(
                            [
                                SpanAssertion::exists('PDO.__construct'),
                                SpanAssertion::exists('PDO.exec'),
                                SpanAssertion::exists('PDO.exec'),
                            ],
                            array_fill(0, 7, SpanAssertion::exists('PDO.prepare')),
                            array_fill(0, 7, SpanAssertion::exists('PDOStatement.execute'))
                        )),
                    ]),
                    SpanAssertion::exists('_drupal_bootstrap_page_header')->withChildren([
                        SpanAssertion::exists('module_invoke', 'overlay_boot'),
                        SpanAssertion::exists('module_invoke', 'dblog_boot'),
                    ]),
                    SpanAssertion::exists('drupal_language_initialize'),
                    SpanAssertion::exists('drupal_session_initialize'),
                    SpanAssertion::exists('menu_execute_active_handler')->withChildren([
                        SpanAssertion::exists('PDO.prepare'),
                        SpanAssertion::exists('PDOStatement.execute'),
                        SpanAssertion::exists('drupal_deliver_page')->withChildren(array_merge(
                            array_fill(0, 26, SpanAssertion::exists('PDO.prepare')),
                            array_fill(0, 26, SpanAssertion::exists('PDOStatement.execute')),
                            [
                                SpanAssertion::exists('drupal_cron_run')->withChildren(array_merge(
                                    array_fill(0, 5, SpanAssertion::exists('PDO.prepare')),
                                    array_fill(0, 5, SpanAssertion::exists('PDOStatement.execute')),
                                    [
                                        SpanAssertion::exists('module_invoke', 'system_cron')->withChildren(array_merge(
                                            array_fill(0, 15, SpanAssertion::exists('PDO.prepare')),
                                            array_fill(0, 15, SpanAssertion::exists('PDOStatement.execute'))
                                        )),
                                        SpanAssertion::exists('module_invoke', 'search_cron')->withChildren([
                                            SpanAssertion::exists('PDO.prepare'),
                                            SpanAssertion::exists('PDOStatement.execute'),
                                        ]),
                                        SpanAssertion::exists('module_invoke', 'node_cron')->withChildren([
                                            SpanAssertion::exists('PDO.prepare'),
                                            SpanAssertion::exists('PDOStatement.execute'),
                                        ]),
                                        SpanAssertion::exists('module_invoke', 'field_cron')->withChildren(array_merge(
                                            array_fill(0, 11, SpanAssertion::exists('PDO.prepare')),
                                            array_fill(0, 11, SpanAssertion::exists('PDOStatement.execute'))
                                        )),
                                        SpanAssertion::exists('module_invoke', 'dblog_cron')->withChildren([
                                            SpanAssertion::exists('PDO.prepare'),
                                            SpanAssertion::exists('PDOStatement.execute'),
                                        ]),
                                        SpanAssertion::exists('module_invoke_all', 'watchdog')->withChildren([
                                            SpanAssertion::exists('PDO.prepare'),
                                            SpanAssertion::exists('PDOStatement.execute'),
                                        ]),
                                        SpanAssertion::exists('module_invoke_all', 'cron_queue_info'),
                                    ]
                                )),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke'),
                                SpanAssertion::exists('module_invoke_all'),
                                SpanAssertion::exists('module_invoke_all'),
                                SpanAssertion::exists('module_invoke_all'),
                            ]
                        )),
                        SpanAssertion::exists('module_invoke_all'),
                    ]),
                ]),
            ]
        );
    }
}
