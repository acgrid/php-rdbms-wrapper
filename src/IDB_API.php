<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/14
 * Time: 22:45
 */

namespace RDB;

/**
 * Interface IDB_API
 * Wrapper for general RDBMS operations
 * Note this interface should manage the DB connection either making connection on construction or call getConnection()
 * in all actual methods to implement lazy loading.
 * The interface shall throw exception instead of returning false value or raising errors in case of failure.
 * @package RDB
 */
interface IDB_API
{
    /**
     * Test whether the connection is established already and ready to use
     * @return bool
     */
    public function isConnected();

    /**
     * Ensure the DB connection is actually connected and return it, or throws an exception on failure.
     * Use a flag to cache the status on following routines
     * @throws \RuntimeException
     * @return mixed
     */
    public function getConnection();

    /**
     * Escape the string according to the API
     * @param string $string
     * @return string
     */
    public function escape($string);

    /**
     * Start transaction without protection or detection
     * @return bool
     */
    public function startTransaction();

    /**
     * Commit transaction without checking
     * @return bool
     */
    public function commit();

    /**
     * Rollback transaction without checking
     * @return bool
     */
    public function rollback();

    /**
     * Execute on database and return inserted ID or affected rows
     * @param string $query
     * @return int|false
     */
    public function exec($query);

    /**
     * Caution: Requires escaped query string.
     * Send a query and store the result in internal and return self
     * @param string $query
     * @return $this
     */
    public function query($query);

    /**
     * Perform a unbuffered but exclusive huge query iteration with specified callback.
     * @param string $query
     * @param callable $queried The callback after query sent, before iteration
     * @param callable $iterator The callback in each iteration
     * @return bool
     */
    public function queryExclusiveCallback($query, callable $queried, callable $iterator);

    /**
     * Get native result set object
     * @return mixed
     */
    public function rawResult();

    /**
     * Get count of previous result set
     * @return int
     */
    public function fetchCount();

    /**
     * Get next row in associative array of internal result
     * @return array
     */
    public function fetch();

    /**
     * Get next row in constructing specific object of internal result
     * @param string $className
     * @param array $params
     * @return mixed
     */
    public function fetchObject($className = '\stdClass', array $params = []);

    /**
     * Get next row in number-indexed array of internal result
     * @return array
     */
    public function fetchNum();

    /**
     * Get the first element of next row of internal result
     * @return mixed
     */
    public function fetchValue();

    /**
     * Get the all rows in associative array of internal result
     * @return array
     */
    public function fetchAll();

    /**
     * Get a generator of every row in associative array of internal result
     * @return \Generator
     */
    public function fetchGenerator();

    /**
     * Get a generator of every row in object of internal result
     * @param string $className
     * @param array $params
     * @return mixed
     */
    public function fetchObjectGenerator($className = '\stdClass', $params = []);

    /**
     * Create a prepared statement that wrapped by a object implements IStatement
     * @param string $query
     * @return IStatement
     */
    public function prepare($query);

}