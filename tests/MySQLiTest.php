<?php
/**
 * Created by PhpStorm.
 * Date: 2016/1/16
 * Time: 19:52
 */

namespace RDBTest;

use RDB\MySQLiAPI;
use RDB\MySQLiStatement;

class MySQLiTest extends AbstractTest
{
    /** @var  MySQLiAPI */
    private $api;
    private $table = [
        ['id' => 1, 'name' => 'A', 'quantity' => 10, 'description' => null, 'amount' => 1553.1],
        ['id' => 2, 'name' => 'B', 'quantity' => 0, 'description' => 'Bad', 'amount' => 0.0],
    ];

    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->api = new MySQLiAPI([
            MySQLiAPI::DSN_HOST => $GLOBALS['DB_HOST'],
            MySQLiAPI::DSN_USER => $GLOBALS['DB_USER'],
            MySQLiAPI::DSN_PASS => $GLOBALS['DB_PASS'],
            MySQLiAPI::DSN_NAME => $GLOBALS['DB_NAME'],
        ]);
    }

    public function testSimpleQuery()
    {
        $this->assertInstanceOf('\mysqli', $this->api->getConnection());
        $this->assertEquals(2, $this->getConnection()->getRowCount('test'));
        $this->assertTrue($this->api->isConnected());
        $this->assertEquals(3, $this->api->exec("INSERT INTO `test` (name, quantity, amount) VALUES ('C', 3, 999)"));
        $this->assertEquals(2, $this->api->exec("UPDATE `test` SET description = 'Silent is gold' WHERE description IS NULL"));
        $this->assertSame(0, $this->api->exec("DELETE FROM `test` WHERE `name` = 'D'"));
        $this->assertSame($this->api, $this->api->query('SELECT `name`, `description` FROM `test` ORDER BY `id` DESC'));
        $this->table[0]['description'] = 'Silent is gold';
        $this->table[] = ['id' => 3, 'name' => 'C', 'quantity' => 3, 'description' => 'Silent is gold', 'amount' => 999.0];
        $this->assertInstanceOf('\mysqli_result', $this->api->rawResult());
        $this->assertEquals(3, $this->api->fetchCount());
        $this->assertEquals(['name' => 'C', 'description' => 'Silent is gold'], $this->api->fetch());
        $this->assertCount(3, $this->api->query('SELECT `name`, `description` FROM `test`')->fetchAll());
        $this->assertSame([0 => 'B', 1 => 'Bad'], $this->api->query('SELECT `name`, `description` FROM `test` WHERE `id` = 2')->fetchNum());
        $result = $this->api->query('SELECT `name`, `amount` FROM `test` WHERE `amount` > 0')->fetchGenerator();
        $this->assertInstanceOf('\Generator', $result);
        foreach($result as $item){
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('amount', $item);
            $this->assertGreaterThan(0, $item['amount']);
        }
        $this->assertInstanceOf('\Generator', $this->api->query('SELECT `name` FROM `test` WHERE `id` = 999')->fetchGenerator());
        $this->api->queryExclusiveCallback('SELECT `id`, `quantity` FROM `test` WHERE `quantity` = 0', function($mysqli){
            $this->assertInstanceOf(MySQLiAPI::class, $mysqli);
        }, function($row){
            $this->assertEquals(0, $row['quantity']);
        });
        $this->assertEquals(time(), $this->api->query('SELECT UNIX_TIMESTAMP()')->fetchValue(), 'Query after use-result mode failed', 1);
        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
        $this->assertInstanceOf(MostSampleDomain::class, $object0 = $this->api->query('SELECT * FROM `test` WHERE `id` = 1')->fetchObject(MostSampleDomain::class, null));
        /** @var MostSampleDomain $object0 */
        $this->assertEquals(1, $object0->getId());
        $this->assertSame('A', $object0->getName());
        $this->assertEquals(1553.1, $object0->getAmount());
        $this->assertInstanceOf(SampleDomain::class, $object = $this->api->query('SELECT * FROM `test` WHERE `id` = 2')->fetchObject(SampleDomain::class, [9, false]));
        /** @var SampleDomain $object */
        $this->assertSame(9, $object->getId());
        $this->assertSame('B', $object->getName());
        $this->assertEquals(0, $object->getAmount());
        $this->assertSame('set by __setter: Bad', $object->getDescription());
        $this->assertFalse($object->isEnabled());
        $object = $this->api->query('SELECT * FROM `test` WHERE `id` = 1')->fetchObject(SampleDomain::class, [5]);
        $this->assertTrue($object->isEnabled());
    }

    /**
     * @depends testSimpleQuery
     */
    public function testTransaction()
    {
        $this->assertTrue($this->api->startTransaction());
        $this->api->exec("INSERT INTO `test` (name, quantity, description, amount) VALUES ('D', 5, 'Four', 123.5)");
        $this->assertTrue($this->api->commit());
        $this->assertEquals('1', $this->api->query('SELECT @@autocommit')->fetchValue());
        $this->assertTrue($this->api->startTransaction());
        $this->api->exec('DELETE FROM `test`');
        $this->assertTrue($this->api->rollback());
        $this->table[] = ['id' => 3, 'name' => 'D', 'quantity' => 5, 'description' => 'Four', 'amount' => 123.5];
        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
    }

    /**
     * @depends testTransaction
     */
    public function testPreparedQuery()
    {
        $stmt = $this->api->prepare('SELECT `quantity`, `description`, `amount` FROM `test` WHERE `name` = ?')
            ->bindIn($name)->bindOut($quantity, $description, $amount);
        $this->assertInstanceOf(MySQLiStatement::class, $stmt);
        $this->assertInstanceOf('\mysqli_stmt', $stmt->native());
        foreach(['A', 'B'] as $index => $name){
            $this->assertTrue($stmt->execute());
            $this->assertTrue($stmt->next() !== false);
            $this->assertInternalType('int', $quantity);
            $this->assertEquals($this->table[$index]['quantity'], $quantity);
            $this->assertEquals($this->table[$index]['description'], $description);
            $this->assertEquals($this->table[$index]['amount'], $amount);
        }
        $stmt->close();
        $stmt->close(); // Double free
        $description = 'wtf';
        $amount = 4.5;
        $stmt = $this->api->prepare('INSERT INTO `test` (name, quantity, description, amount) VALUES (?, 0, ?, ?)')
            ->bindIn($name, $description, $amount);
        $id = count($this->table);
        foreach(['E', 'F'] as $index => $name){
            $this->assertEquals(++$id, $stmt->execute());
        }
        $this->table[] = ['id' => 3, 'name' => 'E', 'quantity' => 0, 'description' => $description, 'amount' => $amount];
        $this->table[] = ['id' => 4, 'name' => 'F', 'quantity' => 0, 'description' => $description, 'amount' => $amount];
        $this->assertTablesEqual(
            (new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]))->getTable('test'),
            $this->getConnection()->createQueryTable('test', 'SELECT * FROM `test`')
        );
    }

    /**
     * @depends testPreparedQuery
     */
    public function testException()
    {
        $this->setExpectedException('\mysqli_sql_exception');
        $this->api->exec('DO EVIL');
    }

    /**
     * @inheritDoc
     */
    protected function getDataSet()
    {
        return new \PHPUnit_Extensions_Database_DataSet_ArrayDataSet(['test' => $this->table]);
    }

}
