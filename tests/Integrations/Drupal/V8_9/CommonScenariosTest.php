<?php

namespace DDTrace\Tests\Integrations\Drupal\V8_9;

use DDTrace\Tests\Common\SpanAssertion;
use DDTrace\Tests\Common\WebFrameworkTestCase;
use DDTrace\Tests\Frameworks\Util\Request\RequestSpec;
use DDTrace\Tests\Frameworks\Util\Request\GetSpec;
use Exception;

final class CommonScenariosTest extends WebFrameworkTestCase
{
    protected static function getAppIndexScript()
    {
        return __DIR__ . '/../../../Frameworks/Drupal/Version_8_9/index.php';
    }

    public static function ddSetUpBeforeClass()
    {
        parent::ddSetUpBeforeClass();
        $pdo = new \PDO('mysql:host=mysql_integration;dbname=test', 'test', 'test');
        $pdo->exec(file_get_contents(__DIR__ . '/../../../Frameworks/Drupal/Version_8_9/db.sql'));
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
                    '/user/login',
                    'drupal_app',
                    SpanAssertion::NOT_TESTED,
                    'user.login'
                )->withExactTags([
                    // Drupal 8+ is based on symfony
                    'symfony.route.name' => 'user.login',
                    'http.method' => 'GET',
                    'http.url' => 'http://localhost:9999/user/login',
                    'http.status_code' => '200',
                    'component' => 'drupal',
                ])->withChildren([
                    SpanAssertion::exists('drupal.event.kernel.terminate'),
                    SpanAssertion::exists('drupal.kernel.handle')->withChildren([
                        SpanAssertion::exists('PDO.prepare'),
                        SpanAssertion::exists('PDO.prepare'),
                        SpanAssertion::exists('PDO.prepare'),
                        SpanAssertion::exists('PDOStatement.execute'),
                        SpanAssertion::exists('PDOStatement.execute'),
                        SpanAssertion::exists('PDOStatement.execute'),
                        SpanAssertion::exists('drupal.kernel.boot')->withChildren([
                            SpanAssertion::exists('PDO.__construct'),
                            SpanAssertion::exists('PDO.exec'),
                            SpanAssertion::exists('PDO.exec'),
                            SpanAssertion::exists('PDO.prepare'),
                            SpanAssertion::exists('PDO.prepare'),
                            SpanAssertion::exists('PDO.prepare'),
                            SpanAssertion::exists('PDO.prepare'),
                            SpanAssertion::exists('PDO.prepare'),
                            SpanAssertion::exists('PDOStatement.execute'),
                            SpanAssertion::exists('PDOStatement.execute'),
                            SpanAssertion::exists('PDOStatement.execute'),
                            SpanAssertion::exists('PDOStatement.execute'),
                            SpanAssertion::exists('PDOStatement.execute'),
                        ]),
                        SpanAssertion::exists('symfony.kernel.handle')->withChildren([
                            SpanAssertion::exists('drupal.event.kernel.finish_request'),
                            SpanAssertion::exists('drupal.event.kernel.request')->withChildren([
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                            ]),
                            SpanAssertion::exists('drupal.event.kernel.response')->withChildren([
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDO.prepare'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                                SpanAssertion::exists('PDOStatement.execute'),
                            ]),
                        ]),
                    ]),
                ]),
            ]
        );
    }
}
