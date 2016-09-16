<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/14
 * Time: 23:01
 */

namespace RDB;

use PHProfiling\Manager;

/**
 * Class AbstractFacade
 * The universal facade for RDBMS access with support of DB API wrapper.
 *
 * Features provided:
 * Built-in `sprintf()` implicit call
 * Profiling control
 * Result in form of generator or empty array, allowing condition judgement before iteration
 * Shorthand utilities like get single value, get COUNT() and SUM()
 * Directly delegation to wrapper method by magic methods as the PHPDoc listed below (officially supported).
 *
 * For finite connections needed in your application, inherit me in real facade and configure its `getInstance()` method
 * For unknown amount of connections, use the API interfaces directly
 *
 * @package U2M\RDB
 * @method static bool isConnected()
 * @method static mixed getConnection()
 * @method static bool startTransaction()
 * @method static bool commit()
 * @method static bool rollback()
 */
abstract class AbstractFacade
{
    /** @var IDB_API */
    protected $db;
    /** @var IStatement */
    protected $stmt;
    /** @var Manager */
    protected $profiler;
    /** @var int */
    protected $lastResultCount = 0;
    /** @var AbstractFacade */
    protected static $instance;

    // Initialization, getters

    /**
     * AbstractFacade constructor.
     *
     * @param IDB_API $api The database API wrapper implements IDB_API like mysqli, PDO_mysql
     * @param Manager $profiler The profiling handler implements IProfiler
     */
    private function __construct(IDB_API $api, Manager $profiler)
    {
        $this->db = $api;
        $this->profiler = $profiler;
    }

    /**
     * Note that the latest made object will be the default object returned by `getInstance()`
     *
     * @param IDB_API $api
     * @param Manager $profiler
     * @return AbstractFacade
     */
    public static function factory(IDB_API $api, Manager $profiler)
    {
        static::$instance = new static($api, $profiler);
        return static::$instance;
    }

    /**
     * Get the default object as well as used by other static methods.
     * You should implement the real method to get the right thing from your DI container or anything else
     * @return AbstractFacade
     */
    abstract public static function getInstance();

    /**
     * Get the set API wrapper.
     * Not the native connection!
     * @return IDB_API
     */
    public static function getAPI()
    {
        return static::getInstance()->db;
    }

    /**
     * Get the set Profiling object
     * @return Manager
     */
    public static function getProfiler()
    {
        return static::getInstance()->profiler;
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([static::getInstance()->db, $name], $arguments);
    }

    // Unprepared query methods

    /**
     * SQL user data escape like previous `sqlesc()`
     * However it takes real PHP `int` and `float` variable as-is and anything else will be taken as string
     * to prevent attack on conversion of continuous-zero string
     * @param mixed $value
     * @return float|int|string
     */
    public static function esc($value)
    {
        return is_int($value) || is_float($value) ? $value : "'" . static::getInstance()->db->escape($value) . "'";
    }

    /**
     * Execute a query that do not return any result-set
     * Return value will be inserted ID if presents, or the affected rows elsewhere.
     * If exceptions is not thrown on failure, it returns false.
     * @param string $query
     * @param array ...$args
     * @return false|int
     */
    public static function exec($query, ...$args)
    {
        $self = static::getInstance();
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart($query);
        try{
            $result = $self->db->exec($query);
        }finally{
            $self->profiler->stop();
        }
        return $result;
    }

    protected static function query($query, ...$args)
    {
        $self = static::getInstance();
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart($query);
        try{
            $result = $self->db->query($query);
        }finally{
            $self->profiler->stop();
        }
        return $result;
    }

    /**
     * Return the count of results of a query
     * @param string $query
     * @param array ...$args
     * @return int
     */
    public static function exists($query, ...$args)
    {
        return static::getInstance()->lastResultCount = static::getInstance()->query($query, ...$args)->fetchCount();
    }

    /**
     * Return a native result-set object|resource of a query.
     * Warning! This will make your client code unportable
     * @param string $query
     * @param array ...$args
     * @return mixed
     */
    public static function queryRaw($query, ...$args)
    {
        return static::getInstance()->query($query, ...$args)->rawResult();
    }

    /**
     * Return the only value of first column of first result row of a query
     * @param string $query
     * @param array ...$args
     * @return mixed
     */
    public static function queryValue($query, ...$args)
    {
        return static::getInstance()->query($query, ...$args)->fetchValue();
    }

    /**
     * Return the projection (first values of every row) of a query without custom indexing
     * No check whether a row contains more than one column
     * @param string $query
     * @param array ...$args
     *
     * @return array [0 => value1, 1 => value2]
     */
    public static function queryValues($query, ...$args)
    {
        $result = static::getInstance()->query($query, ...$args);
        $data = [];
        while($value = $result->fetchValue()) $data[] = $value;
        return $data;
    }

    /**
     * Return the first result row of a query in associative array
     * @param string $query
     * @param array ...$args
     * @return array
     */
    public static function queryRow($query, ...$args)
    {
        return static::getInstance()->query($query, ...$args)->fetch();
    }

    /**
     * Return the first result row of a query in indexed array
     * @param string $query
     * @param array ...$args
     * @return array
     */
    public static function queryIndexedRow($query, ...$args)
    {
        return static::getInstance()->query($query, ...$args)->fetchNum();
    }

    /**
     * Return all result rows in associative array of a query
     * Do not use me on huge result for better memory performance,
     * consider replacing with iteration or `batch()`
     * @param string $query
     * @param array ...$args
     * @return array
     */
    public static function queryRows($query, ...$args)
    {
        return static::getInstance()->query($query, ...$args)->fetchAll();
    }

    /**
     * Return all result rows of a query with indexed by specific column
     * If only one column besides the indexed key is selected, the element will be the plain value with key stripped,
     * otherwise an associative array as usual
     * @param string $query
     * @param string $column
     * @param array ...$args
     *
     * @return array like ['mine' => '233', 'yours' => '894'] or ['mine' => ['id' => '233', 'qty' => '1', 'yours' => '894', 'qty' => '9']
     */
    public static function queryMappedRows($query, $column, ...$args)
    {
        $data = [];
        foreach(static::getInstance()->iterate($query, ...$args) as $row){
            if(isset($row[$column])){
                $key = $row[$column];
                if(count($row) > 2){
                    $data[$key] = $row;
                }else{
                    unset($row[$column]);
                    $data[$key] = count($row) > 1 ? $row : current($row);
                }
            }else{
                throw new \InvalidArgumentException("No column named '$column' in result row.'");
            }
        }
        return $data;
    }

    /**
     * Get a Generator object that can be iterated by `foreach`.
     * Return [] in case of empty result, giving client code a chance of judgement before iteration
     * because Generator objects are always evaluated to be true.
     * @param string $query
     * @param array ...$args
     * @return array|\Generator
     */
    public static function iterate($query, ...$args)
    {
        $self = static::getInstance();
        if($self->lastResultCount = $self->query($query, ...$args)->fetchCount()){
            return $self->db->fetchGenerator();
        }else{
            return [];
        }
    }

    /**
     * Return the number of rows of previous query.
     * NOTE: This value is set only after iterate() or exists()
     * @return int
     */
    public static function latestCount()
    {
        return static::getInstance()->lastResultCount;
    }

    /**
     * Do huge query by callback on every result row.
     * IMPORTANT: This query is exclusive, that you can not send any query to server until all rows are fetched!
     * However it saves memory because the rows are not buffered to PHP any more.
     * This method ensures release result resource correctly after use.
     * You need a callback for processing where contains no any database operations in the same client connection,
     * otherwise you have to make another connection.
     * @param callable $callback
     * @param string $query
     * @param array ...$args
     * @return mixed
     */
    public static function batch(callable $callback, $query, ...$args)
    {
        $self = static::getInstance();
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart($query);
        return $self->db->queryExclusiveCallback($query, function() use ($self){
            $self->profiler->stop();
        }, $callback);
    }

    // Prepared query methods

    /**
     * Get a prepared statement wrapper from current connection
     * @see IStatement
     * @param string $query
     * @param array ...$args
     * @return IStatement
     */
    public static function prepare($query, ...$args)
    {
        $self = static::getInstance();
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart("[Prepared] $query");
        try{
            $stmt = $self->db->prepare($query);
        }finally{
            $self->profiler->stop();
        }
        return $stmt;
    }

    // Shorthand Methods

    /**
     * Get the field value from a table with optional suffix after FROM clause
     * @param string $table
     * @param string $field
     * @param string $suffix
     * @return string
     */
    public static function tableValue($table, $field, $suffix = '')
    {
        return self::queryValue('SELECT %s FROM `%s`%s', $field, $table, $suffix);
    }

    /**
     * Get the count of table with optional suffix and field specifier
     * @param $table
     * @param string $suffix
     * @param string $field
     * @return int
     */
    public static function tableCount($table, $suffix = '', $field = '*')
    {
        return self::queryValue('SELECT COUNT(%s) FROM %s %s', $field, $table, $suffix) ?: 0;
    }

    /**
     * Get the sum of table with optional suffix and field specifier
     * @param string $table
     * @param string $field
     * @param string $suffix
     * @return int|float
     */
    public static function tableSum($table, $field, $suffix = '')
    {
        return self::queryValue('SELECT SUM(%s) FROM `%s`%s', $field, $table, $suffix) ?: 0;
    }
}