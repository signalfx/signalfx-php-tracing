<?php

namespace DDTrace\Integrations\PDO;

use DDTrace\Configuration;
use DDTrace\Integrations\Integration;
use DDTrace\SpanData;
use DDTrace\Tag;
use DDTrace\Type;

class PDOIntegration extends Integration
{
    const NAME = 'pdo';

    /**
     * @var array
     */
    private static $connections = [];

    /**
     * @var array
     */
    private static $statements = [];

    /**
     * @return string The integration name.
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return string The truncated statement
     */
    public static function truncate($statement, $start = 0, $length = 65536)
    {
        if (isset($statement) && is_string($statement) && $statement !== '') {
            return substr($statement, $start, $length);
        }
        return $statement;
    }

    /**
     * Static method to add instrumentation to PDO requests
     * Add instrumentation to PDO requests
     */
    public function init()
    {
        if (!extension_loaded('PDO')) {
            // PDO is provided through an extension and not through a class loader.
            return Integration::NOT_AVAILABLE;
        }

        $integration = $this;

        // public PDO::__construct ( string $dsn [, string $username [, string $passwd [, array $options ]]] )
        \DDTrace\trace_method('PDO', '__construct', function (SpanData $span, array $args) {
            if (dd_trace_tracer_is_limited()) {
                return false;
            }
            $span->name = $span->resource = 'PDO.__construct';
            $span->meta = PDOIntegration::storeConnectionParams($this, $args);
            $span->meta[Tag::COMPONENT] = 'PDO';
        });

        // public int PDO::exec(string $query)
        \DDTrace\trace_method('PDO', 'exec', function (SpanData $span, array $args, $retval) use ($integration) {
            if (dd_trace_tracer_is_limited()) {
                return false;
            }
            $span->name = 'PDO.exec';
            $span->type = Type::SQL;
            $span->meta[Tag::DB_STATEMENT] = PDOIntegration::truncate($args[0]);
            $span->meta[Tag::COMPONENT] = 'PDO';
            if (is_numeric($retval)) {
                $span->meta['db.rowcount'] = $retval;
            }
            PDOIntegration::setConnectionTags($this, $span);
            PDOIntegration::detectError($this, $span);
            $integration->addTraceAnalyticsIfEnabled($span);
        });

        // public PDOStatement PDO::query(string $query)
        // public PDOStatement PDO::query(string $query, int PDO::FETCH_COLUMN, int $colno)
        // public PDOStatement PDO::query(string $query, int PDO::FETCH_CLASS, string $classname, array $ctorargs)
        // public PDOStatement PDO::query(string $query, int PDO::FETCH_INFO, object $object)
        // public int PDO::exec(string $query)
        \DDTrace\trace_method('PDO', 'query', function (SpanData $span, array $args, $retval) use ($integration) {
            if (dd_trace_tracer_is_limited()) {
                return false;
            }
            $span->name = 'PDO.query';
            $span->type = Type::SQL;
            $span->meta[Tag::DB_STATEMENT] = PDOIntegration::truncate($args[0]);
            $span->meta[Tag::COMPONENT] = 'PDO';

            if ($retval instanceof \PDOStatement) {
                PDOIntegration::storeStatementFromConnection($this, $retval);
            }

            PDOIntegration::setConnectionTags($this, $span);
            PDOIntegration::detectError($this, $span);
            $integration->addTraceAnalyticsIfEnabled($span);
        });

        // public bool PDO::commit ( void )
        \DDTrace\trace_method('PDO', 'commit', function (SpanData $span) {
            if (dd_trace_tracer_is_limited()) {
                return false;
            }
            $span->name = $span->resource = 'PDO.commit';
            $span->type = Type::SQL;
            $span->meta[Tag::COMPONENT] = 'PDO';
            PDOIntegration::setConnectionTags($this, $span);
        });

        // public PDOStatement PDO::prepare ( string $statement [, array $driver_options = array() ] )
        \DDTrace\trace_method('PDO', 'prepare', function (SpanData $span, array $args, $retval) {
            if (dd_trace_tracer_is_limited()) {
                return false;
            }
            $span->name = 'PDO.prepare';
            $span->meta[Tag::COMPONENT] = 'PDO';
            $span->meta[Tag::DB_STATEMENT] = PDOIntegration::truncate($args[0]);
            PDOIntegration::setConnectionTags($this, $span);
            PDOIntegration::storeStatementFromConnection($this, $retval);
        });

        // public bool PDOStatement::execute ([ array $input_parameters ] )
        \DDTrace\trace_method(
            'PDOStatement',
            'execute',
            function (SpanData $span, array $args, $retval) use ($integration) {
                if (dd_trace_tracer_is_limited()) {
                    return false;
                }
                $span->name = 'PDOStatement.execute';
                $span->type = Type::SQL;
                $span->meta[Tag::DB_STATEMENT] = PDOIntegration::truncate($this->queryString);
                $span->meta[Tag::COMPONENT] = 'PDO';
                PDOIntegration::setStatementTags($this, $span);
                PDOIntegration::detectError($this, $span);
                $integration->addTraceAnalyticsIfEnabled($span);
            }
        );

        return Integration::LOADED;
    }

    /**
     * @param \PDO|\PDOStatement $pdoOrStatement
     * @param SpanData $span
     */
    public static function detectError($pdoOrStatement, SpanData $span)
    {
        $errorCode = $pdoOrStatement->errorCode();
        // Error codes follows the ANSI SQL-92 convention of 5 total chars:
        //   - 2 chars for class value
        //   - 3 chars for subclass value
        // Non error class values are: '00', '01', 'IM'
        // @see: http://php.net/manual/en/pdo.errorcode.php
        if (strlen($errorCode) !== 5) {
            return;
        }

        $class = strtoupper(substr($errorCode, 0, 2));
        if (in_array($class, ['00', '01', 'IM'], true)) {
            // Not an error
            return;
        }
        $errorInfo = $pdoOrStatement->errorInfo();
        $span->meta[Tag::ERROR_MSG] = 'SQL error: ' . $errorCode . '. Driver error: ' . $errorInfo[1];
        $span->meta[Tag::ERROR_TYPE] = get_class($pdoOrStatement) . ' error';
    }

    private static function parseDsn($dsn)
    {
        $engine = substr($dsn, 0, strpos($dsn, ':'));
        $tags = [TAG::DB_TYPE => $engine];
        $valStrings = explode(';', substr($dsn, strlen($engine) + 1));
        foreach ($valStrings as $valString) {
            if (!strpos($valString, '=')) {
                continue;
            }
            list($key, $value) = explode('=', $valString);
            switch ($key) {
                case 'charset':
                    $tags['db.charset'] = $value;
                    break;
                case 'dbname':
                    $tags[TAG::DB_INSTANCE] = $value;
                    break;
                case 'host':
                    $tags[Tag::TARGET_HOST] = $value;
                    break;
                case 'port':
                    $tags[Tag::TARGET_PORT] = $value;
                    break;
            }
        }

        return $tags;
    }

    public static function storeConnectionParams($pdo, array $constructorArgs)
    {
        $tags = self::parseDsn($constructorArgs[0]);
        if (isset($constructorArgs[1])) {
            $tags[Tag::DB_USER] = $constructorArgs[1];
        }
        self::$connections[spl_object_hash($pdo)] = $tags;
        return $tags;
    }

    public static function storeStatementFromConnection($pdo, $stmt)
    {
        if (!$stmt instanceof \PDOStatement) {
            // When an error occurs 'FALSE' will be returned in place of the statement.
            return;
        }
        $pdoHash = spl_object_hash($pdo);
        if (isset(self::$connections[$pdoHash])) {
            self::$statements[spl_object_hash($stmt)] = $pdoHash;
        }
    }

    public static function setConnectionTags($pdo, SpanData $span)
    {
        $hash = spl_object_hash($pdo);
        if (!isset(self::$connections[$hash])) {
            return;
        }
        foreach (self::$connections[$hash] as $tag => $value) {
            $span->meta[$tag] = $value;
        }
    }

    public static function setStatementTags($stmt, SpanData $span)
    {
        $stmtHash = spl_object_hash($stmt);
        if (!isset(self::$statements[$stmtHash])) {
            return;
        }
        if (!isset(self::$connections[self::$statements[$stmtHash]])) {
            return;
        }
        foreach (self::$connections[self::$statements[$stmtHash]] as $tag => $value) {
            $span->meta[$tag] = $value;
        }
    }
}
