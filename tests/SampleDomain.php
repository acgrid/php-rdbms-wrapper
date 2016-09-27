<?php


namespace RDBTest;


class SampleDomain
{
    private $id;
    private $name;
    private $quantity;
    private $amount;
    private $enabled;

    private $enclosed = [];

    public function __construct($id, $enabled = true)
    {
        $this->id = $id;
        $this->enabled = $enabled;
    }

    /**
     * @return boolean
     */
    public function isEnabled()
    {
        return $this->enabled;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return mixed
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return isset($this->enclosed['description']) ? $this->enclosed['description'] : '';
    }

    public function __set($name, $value)
    {
        if($name == 'amount') $this->$name = $value + 10;
        if($name == 'description') $this->enclosed[$name] = "set by __setter: $value";
    }

}