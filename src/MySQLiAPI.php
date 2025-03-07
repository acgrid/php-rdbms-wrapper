<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/16
 * Time: 15:57
 */

namespace RDB;

use \mysqli;
use \mysqli_result;

/**
 * Class MySQLiAPI
 * The implementation of ext/mysqli
 * All features are supported.
 *
 * @package RDB
 */
class MySQLiAPI implements IDB_API
{
    const DSN_REPORT = 'report';

    protected $dsn = [];
    protected $report = \MYSQLI_REPORT_STRICT | \MYSQLI_REPORT_ERROR;
    protected $charset = 'utf8mb4';
    /** @var mysqli */
    protected $connection;
    /** @var mysqli_result */
    protected $result;

    /**
     * The configuration array tells the connection string by DSN_HOST, DSN_USER, DSN_PASS, DSN_NAME, DSN_PORT
     * and DSN_SOCKET key.
     * The DSN_CHARSET key specifies the charset as you run 'SET NAMES charset'
     * The DSN_REPORT key specifiers the native mysqli driver error-reporting constants
     * All keys have default value, by ini settings and hard-coded.
     *
     * @see http://php.net/manual/en/mysqli.construct.php
     * @see http://php.net/manual/en/mysqli-driver.report-mode.php note the bitwise operation
     * @inheritDoc
     */
    public function __construct(array $config = [])
    {
        if(!is_a(mysqli_result::class, \Traversable::class, true)) throw new \RuntimeException('mysqli_result does not implement Traversable, is PHP >= 5.3');
        foreach([self::DSN_HOST, self::DSN_USER, self::DSN_PASS, self::DSN_NAME, self::DSN_PORT, self::DSN_SOCKET] as $key){
            $this->dsn[] = isset($config[$key]) ? $config[$key] : ini_get("mysqli.default_$key");
        }
        if(isset($config[self::DSN_REPORT])) $this->report = $config[self::DSN_REPORT];
        if(isset($config[self::DSN_CHARSET])) $this->charset = $config[self::DSN_CHARSET];
    }

    /**
     * @inheritDoc
     */
    public function isConnected()
    {
        return isset($this->connection) && $this->connection->ping();
    }

    /**
     * @inheritdoc
     * @return \mysqli
     */
    public function getConnection()
    {
        if(!isset($this->connection)){
            $this->connection = new mysqli(...$this->dsn);
            if($error = mysqli_connect_errno()){
                throw new \RuntimeException(mysqli_connect_error(), $error);
            }
            mysqli_report($this->report);
            $this->connection->set_charset($this->charset);
        }
        return $this->connection;
    }

    /**
     * Requires MySQL 5.6 or later
     * @inheritDoc
     * @see http://php.net/manual/en/mysqli.begin-transaction.php
     */
    public function startTransaction()
    {
        return $this->getConnection()->begin_transaction();
    }

    /**
     * @inheritDoc
     */
    public function commit()
    {
        return $this->getConnection()->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollback()
    {
        return $this->getConnection()->rollback();
    }

    /**
     * @inheritDoc
     */
    public function exec($query)
    {
        if($this->getConnection()->real_query($query)){
            return $this->getConnection()->insert_id ?: $this->getConnection()->affected_rows;
        }else{
            return false;
        }
    }

    /**
     * @return $this
     */
    public function clear()
    {
        unset($this->result);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function query($query)
    {
        if(isset($this->result) && $this->result->num_rows) $this->result->close();
        $this->result = $this->getConnection()->query($query);
        return $this;
    }
    /**
     * @inheritDoc
     * @return \mysqli_result
     */
    public function rawResult()
    {
        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function fetchCount()
    {
        return isset($this->result) ? $this->result->num_rows : 0;
    }

    /**
     * @inheritDoc
     */
    public function fetch()
    {
        return isset($this->result) ? $this->result->fetch_assoc() : [];
    }

    /**
     * @inheritDoc
     */
    public function fetchNum()
    {
        return isset($this->result) ? $this->result->fetch_row() : [];
    }

    /**
     * @inheritDoc
     */
    public function fetchValue()
    {
        if(!isset($this->result)) return null;
        return ($row = $this->result->fetch_row()) ? $row[0] : null;
    }

    /**
     * @inheritDoc
     */
    public function fetchAll()
    {
        return isset($this->result) ? $this->result->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * @inheritDoc
     * @since PHP 5.3
     */
    public function fetchGenerator()
    {
        if(isset($this->result)){
            $result = $this->result;
            unset($this->result);
            return $result;
        }
        return [];
    }

    protected function objectFactory($className, $params)
    {
        $factory = [$className];
        if(isset($params)) $factory[] = $params;
        return $factory;
    }

    public function fetchObject($className = '\stdClass', array|null $params)
    {
        $factory = $this->objectFactory($className, $params);
        return isset($this->result) ? $this->result->fetch_object(...$factory) : null;
    }

    public function fetchObjectGenerator($className = '\stdClass', array|null $params)
    {
        static $generator;
        if($this->result instanceof mysqli_result){
            if(!isset($generator)) $generator = function(mysqli_result $result, array $factory){
                while($object = $result->fetch_object(...$factory)) yield $object;
                $result->free();
            };
            $result = $this->result;
            unset($this->result);
            return $generator($result, $this->objectFactory($className, $params));
        }else{
            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function queryExclusiveCallback($query, callable $queried, callable $iterator)
    {
        if(!$result = $this->getConnection()->query($query, MYSQLI_USE_RESULT)) return false;
        call_user_func($queried, $this);
        try{
            while($row = $result->fetch_assoc()) call_user_func($iterator, $row);
            return true;
        }finally{
            $result->free();
        }
    }

    /**
     * @inheritDoc
     * @return MySQLiStatement
     */
    public function prepare($query)
    {
        $stmt = $this->getConnection()->prepare($query);
        return $stmt ? new MySQLiStatement($stmt) : null;
    }

    /**
     * @inheritDoc
     */
    public function escape($string)
    {
        return $this->getConnection()->escape_string($string);
    }
}
