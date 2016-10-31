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
 * @method static IDB_API getAPI()
 * @method static Manager getProfiler()
 * @method static float|int|string esc($value)
 * @method static int|false exec(string $query, ...$args)
 * @method static int exists($query, ...$args)
 * @method static mixed queryRaw($query, ...$args)
 * @method static mixed queryValue($query, ...$args)
 * @method static array queryValues($query, ...$args)
 * @method static array queryRow($query, ...$args)
 * @method static array queryIndexedRow($query, ...$args)
 * @method static array queryRows($query, ...$args)
 * @method static array queryMappedRows($query, $column, ...$args)
 * @method static \Traversable|array iterate($query, ...$args)
 * @method static object queryObject($query, $className, array $constructorParam, ...$args)
 * @method static \Traversable|array iterateObject($query, $className, array $constructors, ...$args)
 * @method static bool batch(callable $callback, $query, ...$args)
 * @method static int latestCount()
 * @method static IStatement prepare($query, ...$args)
 * @method static string tableValue($table, $field, $suffix = '')
 * @method static int tableCount($table, $suffix = '', $field = '*')
 * @method static int|float tableSum($table, $suffix = '', $field = '*')
 * @method static bool isConnected()
 * @method static mixed getConnection()
 * @method static bool startTransaction()
 * @method static bool commit()
 * @method static bool rollback()
 */
abstract class AbstractFacade
{
    /** @var DBInstance */
    protected static $instance;

    /**
     * Note that the latest made object will be the default object returned by `getInstance()`
     *
     * @param DBInstance $instance
     * @return DBInstance
     */
    public static function factory(DBInstance $instance)
    {
        return static::$instance = $instance;
    }

    /**
     * Get the default object as well as used by other static methods.
     * You should implement the real method to get the right thing from your DI container or anything else
     * @return DBInstance
     */
    abstract public static function getInstance();

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([static::getInstance(), $name], $arguments);
    }

}