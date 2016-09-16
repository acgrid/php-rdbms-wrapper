<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/16
 * Time: 20:04
 */

namespace RDBTest;


abstract class AbstractTest extends \PHPUnit_Extensions_Database_TestCase
{
    private static $pdo;
    /** @var  \PHPUnit_Extensions_Database_DB_IDatabaseConnection */
    private $conn;

    /**
     * @inheritDoc
     */
    protected function getConnection()
    {
        if($this->conn === null){
            if(self::$pdo === null){
                self::$pdo = new \PDO($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASS']);
            }
            self::$pdo->exec('CREATE TABLE IF NOT EXISTS `test` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(48) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT \'0\',
  `description` text,
  `amount` decimal(10,2) NOT NULL DEFAULT \'0.00\',
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_name_uindex` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT=\'Used for database API test\';');
            $this->conn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_NAME']);
        }
        return $this->conn;
    }

}