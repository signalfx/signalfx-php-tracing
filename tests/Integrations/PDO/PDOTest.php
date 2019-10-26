<?php

namespace DDTrace\Tests\Integrations\PDO;

use DDTrace\Configuration;
use DDTrace\Integrations\IntegrationsLoader;
use DDTrace\Tag;
use DDTrace\Tests\Common\IntegrationTestCase;
use DDTrace\Tests\Common\SpanAssertion;

define('MYSQL_DATABASE', 'test');
define('MYSQL_USER', 'test');
define('MYSQL_PASSWORD', 'test');
define('MYSQL_HOST', 'mysql_integration');


final class PDOTest extends IntegrationTestCase
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        IntegrationsLoader::load();
    }

    protected function setUp()
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    protected function tearDown()
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    public function testPDOContructOk()
    {
        $traces = $this->isolateTracer(function () {
                $this->pdoInstance();
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('PDO.__construct', 'PDO', 'sql', 'PDO.__construct')
                ->withExactTags([
                    Tag::COMPONENT => 'PDO',
                ]),
        ]);
    }

    public function testPDOContructError()
    {
        $traces = $this->isolateTracer(function () {
            try {
                new \PDO($this->mysqlDns(), 'wrong_user', 'wrong_password');
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::build('PDO.__construct', 'PDO', 'sql', 'PDO.__construct')
                ->withExactTags([Tag::COMPONENT => 'PDO',])
                ->setError('PDOException', 'Sql error: SQLSTATE[HY000] [1045]'),
        ]);
    }

    public function testPDOExecOk()
    {
        $query = "INSERT INTO tests (id, name) VALUES (100, 'Sam')";
        $traces = $this->isolateTracer(function () use ($query) {
            $pdo = $this->pdoInstance();
            $pdo->beginTransaction();
            $pdo->exec($query);
            $pdo->commit();
            $pdo = null;
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.exec', 'PDO', 'sql', 'PDO.exec')
                ->setTraceAnalyticsCandidate()
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                    'db.rowcount' => '1',
                ])),
            SpanAssertion::exists('PDO.commit'),
        ]);
    }

    public function testPDOExecError()
    {
        $query = "WRONG QUERY)";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $pdo->beginTransaction();
                $pdo->exec($query);
                $pdo->commit();
                $pdo = null;
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.exec', 'PDO', 'sql', 'PDO.exec')
                ->setTraceAnalyticsCandidate()
                ->setError('PDO error', 'SQL error: 42000. Driver error: 1064')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                    'db.rowcount' => '',
                ])),
            SpanAssertion::exists('PDO.commit'),
        ]);
    }

    public function testPDOExecException()
    {
        $query = "WRONG QUERY)";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->beginTransaction();
                $pdo->exec($query);
                $pdo->commit();
                $pdo = null;
                $this->fail('Should throw and exception');
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.exec', 'PDO', 'sql', 'PDO.exec')
                ->setTraceAnalyticsCandidate()
                ->setError('PDOException', 'Sql error')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::DB_STATEMENT => $query,
                    Tag::COMPONENT => 'PDO',
                ])),
        ]);
    }

    public function testPDOQuery()
    {
        $query = "SELECT * FROM tests WHERE id=1";
        $traces = $this->isolateTracer(function () use ($query) {
            $pdo = $this->pdoInstance();
            $pdo->query($query);
            $pdo = null;
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.query', 'PDO', 'sql', 'PDO.query')
                ->setTraceAnalyticsCandidate()
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::DB_STATEMENT => $query,
                    Tag::COMPONENT => 'PDO',
                    'db.rowcount' => '1',
                ])),
        ]);
    }

    public function testPDOQueryError()
    {
        $query = "WRONG QUERY";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $pdo->query($query);
                $pdo = null;
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.query', 'PDO', 'sql', 'PDO.query')
                ->setTraceAnalyticsCandidate()
                ->setError('PDO error', 'SQL error: 42000. Driver error: 1064')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::DB_STATEMENT => $query,
                    Tag::COMPONENT => 'PDO',
                    'db.rowcount' => '',
                ])),
        ]);
    }

    public function testPDOQueryException()
    {
        $query = "WRONG QUERY";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $pdo->query($query);
                $pdo = null;
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.query', 'PDO', 'sql', 'PDO.query')
                ->setTraceAnalyticsCandidate()
                ->setError('PDOException', 'Sql error')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                ])),
        ]);
    }

    public function testPDOCommit()
    {
        $query = "INSERT INTO tests (id, name) VALUES (100, 'Sam')";
        $traces = $this->isolateTracer(function () use ($query) {
            $pdo = $this->pdoInstance();
            $pdo->beginTransaction();
            $pdo->exec($query);
            $pdo->commit();
            $pdo = null;
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::exists('PDO.exec'),
            SpanAssertion::build('PDO.commit', 'PDO', 'sql', 'PDO.commit')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                ])),
        ]);
    }

    public function testPDOStatementOk()
    {
        $query = "SELECT * FROM tests WHERE id = ?";
        $traces = $this->isolateTracer(function () use ($query) {
            $pdo = $this->pdoInstance();
            $stmt = $pdo->prepare($query);
            $stmt->execute([1]);
            $results = $stmt->fetchAll();
            $this->assertEquals('Tom', $results[0]['name']);
            $stmt->closeCursor();
            $stmt = null;
            $pdo = null;
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build(
                'PDO.prepare',
                'PDO',
                'sql',
                "PDO.prepare"
            )->withExactTags(array_merge($this->baseTags(), [
                Tag::COMPONENT => 'PDO',
                Tag::DB_STATEMENT => $query,
            ])),
            SpanAssertion::build(
                'SELECT',
                'PDO',
                'sql',
                'SELECT'
            )
                ->setTraceAnalyticsCandidate()
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                    'db.rowcount' => 1,
                ])),
        ]);
    }

    public function testPDOStatementError()
    {
        $query = "WRONG QUERY";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $stmt = $pdo->prepare($query);
                $stmt->execute([1]);
                $stmt->fetchAll();
                $stmt->closeCursor();
                $stmt = null;
                $pdo = null;
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.prepare', 'PDO', 'sql', "PDO.prepare")
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                ])),
            SpanAssertion::build('WRONG', 'PDO', 'sql', 'WRONG')
                ->setTraceAnalyticsCandidate()
                ->setError('PDOStatement error', 'SQL error: 42000. Driver error: 1064')
                    ->withExactTags(array_merge($this->baseTags(), [
                        Tag::COMPONENT => 'PDO',
                        Tag::DB_STATEMENT => $query,
                        'db.rowcount' => 0,
                    ])),
        ]);
    }

    public function testPDOStatementException()
    {
        $query = "WRONG QUERY";
        $traces = $this->isolateTracer(function () use ($query) {
            try {
                $pdo = $this->pdoInstance();
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                $stmt = $pdo->prepare($query);
                $stmt->execute([1]);
                $stmt->fetchAll();
                $stmt->closeCursor();
                $stmt = null;
                $pdo = null;
            } catch (\PDOException $ex) {
            }
        });
        $this->assertSpans($traces, [
            SpanAssertion::exists('PDO.__construct'),
            SpanAssertion::build('PDO.prepare', 'PDO', 'sql', "PDO.prepare")
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                ])),
            SpanAssertion::build('WRONG', 'PDO', 'sql', 'WRONG')
                ->setTraceAnalyticsCandidate()
                ->setError('PDOException', 'Sql error')
                ->withExactTags(array_merge($this->baseTags(), [
                    Tag::COMPONENT => 'PDO',
                    Tag::DB_STATEMENT => $query,
                ])),
        ]);
    }

    public function testLimitedTracerPDO()
    {
        $query = "SELECT * FROM tests WHERE id = ?";
        $traces = $this->isolateLimitedTracer(function () use ($query) {
            $pdo = $this->pdoInstance();
            $stmt = $pdo->prepare($query);
            $stmt->execute([1]);
            $results = $stmt->fetchAll();
            $this->assertEquals('Tom', $results[0]['name']);
            $stmt->closeCursor();
            $stmt = null;
            $pdo = null;
        });

        $this->assertEmpty($traces);
    }

    private function pdoInstance()
    {
        return new \PDO($this->mysqlDns(), MYSQL_USER, MYSQL_PASSWORD);
    }

    private function setUpDatabase()
    {
        $this->isolateTracer(function () {
            $pdo = $this->pdoInstance();
            $pdo->beginTransaction();
            $pdo->exec("
                CREATE TABLE tests (
                    id integer not null primary key,
                    name varchar(100)
                )
            ");
            $pdo->exec("INSERT INTO tests (id, name) VALUES (1, 'Tom')");
            $pdo->commit();
            $dbh = null;
        });
    }

    private function clearDatabase()
    {
        $this->isolateTracer(function () {
            $pdo = $this->pdoInstance();
            $pdo->beginTransaction();
            $pdo->exec("DROP TABLE tests");
            $pdo->commit();
            $dbh = null;
        });
    }

    public function mysqlDns()
    {
        return $dsn = "mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DATABASE;
    }

    private function baseTags()
    {
        return [
            Tag::DB_TYPE => 'mysql',
            'out.host' => MYSQL_HOST,
            Tag::DB_INSTANCE => MYSQL_DATABASE,
            Tag::DB_USER => MYSQL_USER,
        ];
    }
}
