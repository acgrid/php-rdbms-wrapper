<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/19
 * Time: 12:13
 */

namespace RDB;

/**
 * Interface IStatement
 * Universal facade of prepared statement with fluent grammar support and automatic variable type detection.
 *
 * Usage Example:
 * Usually instantiated by xxxDB facade, suppose you have got something like $stmt.
 * Write to DB:
 * `$stmt->bindIn($a, $b, $c)->execute();`
 * Once you modifies $a, $b or $c, you can call `execute()` repeatably without binding again.
 * Read from DB with input param:
 * Step1: `$stmt->bindIn($where)->bindOut($field1, $field2)->execute();`
 * If you want to change the input condition, do not forget to call `execute()` again with discarding previous result.
 * Step2: `$stmt->next();` and you can access the row data through previously bound variable $field1 and $field2
 * You can test the return value of `next()` to determine whether there is more rows.
 *
 * Keep in mind that all variables bound must be passed by reference, i.e. not expressions.
 * Do not forget to call $stmt->close(), or you might not be able to prepare another statement.
 *
 * @package RDB
 */
interface IStatement
{
    /**
     * Get native statement handle
     * @return mixed
     */
    public function native();

    /**
     * Bind client variables passed by reference to SQL input (param)
     * The client needs explicit type casting to integer and float to conform the field data type.
     * @param array ...$binds
     * @return $this
     */
    public function bindIn(&...$binds);

    /**
     * Bind client variables passed by reference to SQL output (result)
     * @param array ...$binds
     * @return $this
     */
    public function bindOut(&...$binds);

    /**
     * Fetch next row to bound variables
     * @return bool|null
     */
    public function next();

    /**
     * Execute the prepared statement
     * Return inserted ID or affected rows if present
     * Return boolean of operation result else
     * @return int|bool
     */
    public function execute();

    /**
     * Close statement and unset the inside stmt object to prevent further access
     * @return void
     */
    public function close();
}