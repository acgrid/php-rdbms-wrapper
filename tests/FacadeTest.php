<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/17
 * Time: 0:25
 */

namespace RDBTest;

use PHProfiling\Manager;
use PHPUnit_Extensions_Database_DataSet_ArrayDataSet;
use RDB\AbstractFacade;
use RDB\DBInstance;
use RDB\MySQLiAPI;


class FacadeTest extends AbstractTest
{
    private $table = [
        ['id' => 1, 'name' => 'A', 'quantity' => 10, 'description' => null, 'amount' => 1553.1],
        ['id' => 2, 'name' => 'B', 'quantity' => 0, 'description' => 'Bad', 'amount' => 0.0],
    ];

    public function testQuery()
    {
        $this->assertInstanceOf(MySQLiAPI::class, MainDB::getAPI());
        $this->assertInstanceOf(Manager::class, MainDB::getProfiler());
        $this->assertInstanceOf('\mysqli', MainDB::getConnection());
        $this->assertEquals(3, MainDB::exec("INSERT INTO `test` (name, quantity, amount) VALUES (%s, %u, %.2f)", MainDB::esc('C'), 2, 2.33));
        $this->assertEquals(2, MainDB::exec('UPDATE `test` SET `description` = %s WHERE `description` IS NULL', MainDB::esc('Filled')));
        $this->table[0]['description'] = 'Filled';
        $this->table[] = ['id' => 3, 'name' => 'C', 'quantity' => 2, 'description' => 'Filled', 'amount' => 2.33];
        $this->assertSame(0, MainDB::exec('DELETE FROM `test` WHERE `name` = %s', MainDB::esc('D')));
        $this->assertInstanceOf('\mysqli_result', MainDB::queryRaw('SELECT UNIX_TIMESTAMP()'));
        $this->assertEquals('A', MainDB::queryValue('SELECT `name` FROM `test` WHERE `id` = %u', 1));
        $this->assertSame(['A', 'B', 'C'], MainDB::queryValues('SELECT `name` FROM `test` ORDER BY `id` ASC'));
        $this->assertEquals($this->table[1], MainDB::queryRow('SELECT * FROM `test` WHERE `id` = %u', 2));
        $this->assertCount(3, MainDB::queryRows('SELECT * FROM `test`'));
        $this->assertSame(['B' => 'Bad', 'C' => 'Filled'], MainDB::queryMappedRows('SELECT `name`, `description` FROM `test` WHERE `id` > %u', 'name', 1));
        $this->assertSame(['2' => ['id' => '2', 'name' => 'B', 'description' => 'Bad'], '3' => ['id' => '3', 'name' => 'C', 'description' => 'Filled']],
            MainDB::queryMappedRows('SELECT `id`, `name`, `description` FROM `test` WHERE `id` > %u', 'id', 1));
        $this->assertEquals(1, MainDB::exists('SELECT * FROM `test` WHERE `id` = 3'));
        $this->assertEquals(1, MainDB::latestCount());
        $this->assertEquals(0, MainDB::exists('SELECT * FROM `test` WHERE `id` = 999'));
        $this->assertEmpty(MainDB::iterate('SELECT * FROM `test` WHERE `id` = 999'));
        if($objects = MainDB::iterate('SELECT `name`, `amount` FROM `test` WHERE `amount` > 0')){
            $this->assertEquals(2, MainDB::latestCount());
            $objects2 = MainDB::iterate('SELECT `name`, `amount` FROM `test` WHERE `amount` = 0');
            $iteration = 0;
            foreach($objects2 as $object2){
                $iteration++;
                $this->assertEquals(0, $object2['amount']);
            }
            $this->assertEquals(1, $iteration);
            foreach($objects as $object){
                $iteration++;
                $this->assertArrayHasKey('name', $object);
                $this->assertArrayHasKey('amount', $object);
                $this->assertGreaterThan(0, $object['amount']);
            }
            $this->assertEquals(3, $iteration);
        }else{
            $this->fail('Iterator should return object that evaluated to be true.');
        }
        $row = MainDB::queryIndexedRow('SELECT `name`, `description` FROM `test` WHERE `id` = 2');
        $this->assertSame('B', $row[0]);
        $this->assertSame('Bad', $row[1]);
        /** @var SampleDomain $object */
        $object = MainDB::queryObject('SELECT * FROM `test` WHERE `id` = 1', SampleDomain::class, [1]);
        $this->assertInstanceOf(SampleDomain::class, $object);
        $this->assertEquals(10, $object->getQuantity());
        $this->assertEmpty(MainDB::iterateObject('SELECT * FROM `test` WHERE `id` = 999', SampleDomain::class, []));
        if($objects = MainDB::iterateObject('SELECT * FROM `test` WHERE `amount` > 0', SampleDomain::class, [5])){
            $this->assertEquals(2, MainDB::latestCount());
            $objects2 = MainDB::iterateObject('SELECT * FROM `test` WHERE `amount` = 0', SampleDomain::class, [10, false]);
            $iteration = 0;
            foreach($objects2 as $object2){
                $iteration++;
                /** @var SampleDomain $object2 */
                $this->assertInstanceOf(SampleDomain::class, $object2);
                $this->assertSame(10, $object2->getId());
                $this->assertEquals(0, $object2->getAmount());
                $this->assertFalse($object2->isEnabled());
            }
            $this->assertEquals(1, $iteration);
            foreach($objects as $object){
                /** @var SampleDomain $object */
                $iteration++;
                $this->assertSame(5, $object->getId());
                $this->assertTrue($object->isEnabled());
                $this->assertGreaterThan(0, $object->getAmount());
            }
            $this->assertEquals(3, $iteration);
        }else{
            $this->fail('Iterator should return object that evaluated to be true.');
        }
        MainDB::batch(function($row){
            $this->assertEquals(0, $row['quantity']);
        }, 'SELECT `id`, `quantity` FROM `test` WHERE `quantity` = %u', 0);

        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
        $this->assertEquals(2, MainDB::tableCount('test', 'WHERE `quantity` > 0'));
        $this->assertEquals('C', MainDB::tableValue('test', 'name', 'WHERE `id` = 3'));
        $this->assertNull(MainDB::tableValue('test', 'name', 'WHERE `id` = 999'));
        $this->assertEquals(12, MainDB::tableSum('test', 'quantity'));
        $description = 'wtf';
        $amount = 4.5;
        $stmt = MainDB::prepare('INSERT INTO %s (name, quantity, description, amount) VALUES (?, 0, ?, ?)', 'test')->bindIn($name, $description, $amount);
        $id = count($this->table);
        foreach(['E', 'F'] as $index => $name){
            $this->assertEquals(++$id, $stmt->execute());
            $this->table[] = ['id' => $id, 'name' => $name, 'quantity' => 0, 'description' => $description, 'amount' => $amount];
        }
        $stmt->close();
        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
    }

    /**
     * @depends testQuery
     */
    public function testMultiStmt()
    {
        $stmt1 = MainDB::prepare('INSERT INTO test (name, quantity, description, amount) VALUES (?, 0, ?, 15)');
        $stmt2 = MainDB::prepare('DELETE FROM test WHERE `id` = ?');
        $name = 'F';
        $description = 'stmt';
        $id = $stmt1->bindIn($name, $description)->execute();
        $this->assertGreaterThan(0, $id);
        $stmt2->bindIn($id)->execute();
        $name = 'M';
        $description = 'foo';
        $id = $stmt1->execute();
        $this->assertSame($name, MainDB::queryValue('SELECT `name` FROM `test` WHERE `id` = %u', $id));
        $this->assertSame($description, MainDB::queryValue('SELECT `description` FROM `test` WHERE `id` = %u', $id));
        $stmt2->execute();
        $stmt1->close();
        $stmt2->close();
        $this->assertNull($stmt2->native());
        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
    }

    public function testSubClass()
    {
        $this->assertNotSame(ArchiveDB::getInstance(), MainDB::getInstance());
    }

    /**
     * @inheritDoc
     */
    protected function getDataSet()
    {
        return new PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]);
    }

}

class MainDB extends AbstractFacade
{
    protected static $instance;

    public static function getInstance()
    {
        if(!isset(static::$instance)) static::factory(new DBInstance(new MySQLiAPI([
            MySQLiAPI::DSN_HOST => $GLOBALS['DB_HOST'],
            MySQLiAPI::DSN_USER => $GLOBALS['DB_USER'],
            MySQLiAPI::DSN_PASS => $GLOBALS['DB_PASS'],
            MySQLiAPI::DSN_NAME => $GLOBALS['DB_NAME'],
        ]), new Manager()));
        return static::$instance;
    }

}

class ArchiveDB extends AbstractFacade
{
    protected static $instance;

    public static function getInstance()
    {
        if(!isset(static::$instance)) static::factory(new DBInstance(new MySQLiAPI([
            MySQLiAPI::DSN_HOST => $GLOBALS['DB_HOST'],
            MySQLiAPI::DSN_USER => $GLOBALS['DB_USER'],
            MySQLiAPI::DSN_PASS => $GLOBALS['DB_PASS'],
            MySQLiAPI::DSN_NAME => $GLOBALS['DB_NAME'],
        ]), new Manager()));
        return static::$instance;
    }
}