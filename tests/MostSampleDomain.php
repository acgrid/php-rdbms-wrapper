<?php


namespace RDBTest;


class MostSampleDomain
{
    private $id;
    private $name;
    private $quantity;
    private $amount;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        return $this->amount;
    }


}