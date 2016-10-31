<?php


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
 * @method bool isConnected()
 * @method mixed getConnection()
 * @method bool startTransaction()
 * @method bool commit()
 * @method bool rollback()
 */
class DBInstance
{
    /** @var IDB_API */
    protected $db;
    /** @var array */
    protected $stmt = [];
    /** @var Manager */
    protected $profiler;
    /** @var int */
    protected $lastResultCount = 0;

    /**
     * AbstractFacade constructor.
     *
     * @param IDB_API $api The database API wrapper implements IDB_API like mysqli, PDO_mysql
     * @param Manager $profiler The profiling handler implements IProfiler
     */
    public function __construct(IDB_API $api, Manager $profiler)
    {
        $this->db = $api;
        $this->profiler = $profiler;
    }

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this->db, $name], $arguments);
    }

    /**
     * Get the set API wrapper.
     * Not the native connection!
     * @return IDB_API
     */
    public function getAPI()
    {
        return $this->db;
    }

    /**
     * Get the set Profiling object
     * @return Manager
     */
    public function getProfiler()
    {
        return $this->profiler;
    }

    // Unprepared query methods

    /**
     * SQL user data escape like previous `sqlesc()`
     * However it takes real PHP `int` and `float` variable as-is and anything else will be taken as string
     * to prevent attack on conversion of continuous-zero string
     * @param mixed $value
     * @return float|int|string
     */
    public function esc($value)
    {
        return is_int($value) || is_float($value) ? $value : "'" . $this->db->escape($value) . "'";
    }

    /**
     * Execute a query that do not return any result-set
     * Return value will be inserted ID if presents, or the affected rows elsewhere.
     * If exceptions is not thrown on failure, it returns false.
     * @param string $query
     * @param array ...$args
     * @return false|int
     */
    public function exec($query, ...$args)
    {
        $self = $this;
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart($query);
        try{
            $result = $self->db->exec($query);
        }finally{
            $self->profiler->stop();
        }
        return $result;
    }

    protected function query($query, ...$args)
    {
        if(count($args)) $query = vsprintf($query, $args);
        $this->profiler->namedStart($query);
        try{
            $result = $this->db->query($query);
        }finally{
            $this->profiler->stop();
        }
        return $result;
    }

    /**
     * Return the count of results of a query
     * @param string $query
     * @param array ...$args
     * @return int
     */
    public function exists($query, ...$args)
    {
        return $this->lastResultCount = $this->query($query, ...$args)->fetchCount();
    }

    /**
     * Return a native result-set object|resource of a query.
     * Warning! This will make your client code unportable
     * @param string $query
     * @param array ...$args
     * @return mixed
     */
    public function queryRaw($query, ...$args)
    {
        return $this->query($query, ...$args)->rawResult();
    }

    /**
     * Return the only value of first column of first result row of a query
     * @param string $query
     * @param array ...$args
     * @return mixed
     */
    public function queryValue($query, ...$args)
    {
        return $this->query($query, ...$args)->fetchValue();
    }

    /**
     * Return the projection (first values of every row) of a query without custom indexing
     * No check whether a row contains more than one column
     * @param string $query
     * @param array ...$args
     *
     * @return array [0 => value1, 1 => value2]
     */
    public function queryValues($query, ...$args)
    {
        $result = $this->query($query, ...$args);
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
    public function queryRow($query, ...$args)
    {
        return $this->query($query, ...$args)->fetch();
    }

    /**
     * Return the first result row of a query in indexed array
     * @param string $query
     * @param array ...$args
     * @return array
     */
    public function queryIndexedRow($query, ...$args)
    {
        return $this->query($query, ...$args)->fetchNum();
    }

    /**
     * Return all result rows in associative array of a query
     * Do not use me on huge result for better memory performance,
     * consider replacing with iteration or `batch()`
     * @param string $query
     * @param array ...$args
     * @return array
     */
    public function queryRows($query, ...$args)
    {
        return $this->query($query, ...$args)->fetchAll();
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
    public function queryMappedRows($query, $column, ...$args)
    {
        $data = [];
        foreach($this->iterate($query, ...$args) as $row){
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
     * @return \Traversable|array
     */
    public function iterate($query, ...$args)
    {
        $self = $this;
        if($self->lastResultCount = $self->query($query, ...$args)->fetchCount()){
            return $self->db->fetchGenerator();
        }
        return [];
    }

    /**
     * Return the first result row of a query as constructed object
     * @param string $query
     * @param $className
     * @param array $constructorParam
     * @param array ...$args
     * @return object
     */
    public function queryObject($query, $className, $constructorParam, ...$args)
    {
        return $this->query($query, ...$args)->fetchObject($className, $constructorParam);
    }

    /**
     * Get a Generator object that can be iterated by `foreach`.
     * Return [] in case of empty result, giving client code a chance of judgement before iteration
     * because Generator objects are always evaluated to be true.
     * @param string $query
     * @param string $className
     * @param array $constructors
     * @param array ...$args
     * @return \Traversable|array
     */
    public function iterateObject($query, $className, $constructors, ...$args)
    {
        $self = $this;
        if($self->lastResultCount = $self->query($query, ...$args)->fetchCount()){
            return $self->db->fetchObjectGenerator($className, $constructors);
        }
        return [];
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
     * @return bool
     */
    public function batch(callable $callback, $query, ...$args)
    {
        $self = $this;
        if(count($args)) $query = vsprintf($query, $args);
        $self->profiler->namedStart($query);
        return $self->db->queryExclusiveCallback($query, function() use ($self){
            $self->profiler->stop();
        }, $callback);
    }

    /**
     * Return the number of rows of previous query.
     * NOTE: This value is set only after iterate() or exists()
     * @return int
     */
    public function latestCount()
    {
        return $this->lastResultCount;
    }

    // Prepared query methods

    /**
     * Get a prepared statement wrapper from current connection for the same query
     * It will check for native stmt object before returning it
     * @see IStatement
     * @param string $query
     * @param array ...$args
     * @return IStatement
     */
    public function prepare($query, ...$args)
    {
        if(count($args)) $query = vsprintf($query, $args);
        if(isset($this->stmt[$query])){
            /** @var IStatement $stmt */
            $stmt = $this->stmt[$query];
            if($stmt->native()) return $stmt;
        }
        $this->profiler->namedStart("[Prepared] $query");
        try{
            return $this->stmt[$query] = $this->db->prepare($query);
        }finally{
            $this->profiler->stop();
        }
    }

    /**
     * Close all cached prepared statements
     */
    public function clearStmtCache()
    {
        foreach($this->stmt as $stmt){
            /** @var IStatement $stmt */
            $stmt->close();
        }
        $this->stmt = [];
    }

    // Shorthand Methods

    /**
     * Get the field value from a table with optional suffix after FROM clause
     * @param string $table
     * @param string $field
     * @param string $suffix
     * @return string
     */
    public function tableValue($table, $field, $suffix = '')
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
    public function tableCount($table, $suffix = '', $field = '*')
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
    public function tableSum($table, $field, $suffix = '')
    {
        return self::queryValue('SELECT SUM(%s) FROM `%s`%s', $field, $table, $suffix) ?: 0;
    }
    
}