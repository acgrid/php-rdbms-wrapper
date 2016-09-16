<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/19
 * Time: 12:14
 */

namespace RDB;

use mysqli_stmt;

class MySQLiStatement implements IStatement
{
    protected $stmt;

    /**
     * @inheritDoc
     */
    public function __construct(mysqli_stmt $stmt)
    {
        $this->stmt = $stmt;
    }

    /**
     * @inheritDoc
     */
    function __destruct()
    {
        $this->close();
    }

    /**
     * @inheritDoc
     * @return mysqli_stmt
     */
    public function native()
    {
        return $this->stmt;
    }

    /**
     * @inheritDoc
     */
    public function bindIn(&...$binds)
    {
        if(!isset($this->stmt)) return false;
        $types = '';
        foreach($binds as $bind){
            if(is_int($bind)) {
                $types .= 'i';
            }elseif(is_float($bind)){
                $types .= 'd';
            }else{
                $types .= strlen($bind) > 1048576 ? 'b' : 's';
            }
        }
        return $this->stmt->bind_param($types, ...$binds) ? $this : false;
    }

    /**
     * @inheritDoc
     */
    public function bindOut(&...$binds)
    {
        if(!isset($this->stmt)) return false;
        return $this->stmt->bind_result(...$binds) ? $this : false;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        if(!isset($this->stmt)) return null;
        return $this->stmt->fetch();
    }

    /**
     * @inheritDoc
     */
    public function execute()
    {
        if(!isset($this->stmt) || !$this->stmt->execute()) return false;
        if($this->stmt->insert_id){
            return $this->stmt->insert_id;
        }elseif($this->stmt->affected_rows > 0){
            return $this->stmt->affected_rows;
        }else{
            return true;
        }
    }

    public function close()
    {
        if(isset($this->stmt)) $this->stmt->close();
        unset($this->stmt);
    }

}