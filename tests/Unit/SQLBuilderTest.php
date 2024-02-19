<?php

declare(strict_types=1);

use Pachyderm\Orm\SQLBuilder;
use PHPUnit\Framework\TestCase;

class SQLBuilderTest extends TestCase
{
    public function testBuildBasicFetchAllSQLQuery(): void
    {
        $sqlBuilder = new SQLBuilder('table');
        $sql = $sqlBuilder->build();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.* FROM `table`', $sql);
    }

    public function testBuildBasicFetch2FieldsSQLQuery(): void
    {
        $sqlBuilder = (new SQLBuilder('table'))
            ->select('column_1', 'column_2');
        $sql = $sqlBuilder->build();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.`column_1`, `table`.`column_2` FROM `table`', $sql);
    }

    public function testBuildBasicFetchSQLQuery(): void
    {
        $sqlBuilder = (new SQLBuilder('table'))
            ->where('key_id', '=', 1);

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.* FROM `table` WHERE `table`.`key_id` = :value1', $sql);
        $this->assertNotEmpty($values);
        $this->assertArrayHasKey('value1', $values);
        $this->assertEquals(1, $values['value1']);
    }

    public function testBuildBasicFetchFrom2ParametersSQLQuery(): void
    {
        $sqlBuilder = (new SQLBuilder('table'))
            ->where('key_id', '=', 1)
            ->where('param_2', '=', 'val');

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.* FROM `table` WHERE (`table`.`key_id` = :value1 AND `table`.`param_2` = :value2)', $sql);
        $this->assertNotEmpty($values);
        $this->assertArrayHasKey('value1', $values);
        $this->assertEquals(1, $values['value1']);
        $this->assertArrayHasKey('value2', $values);
        $this->assertEquals('val', $values['value2']);
    }

    public function testBuildBasicFetchWithJoinSQLQuery(): void
    {
        $sqlBuilder = (new SQLBuilder('table'))
            ->where('key_id', '=', 1)
            ->join('table2', 'field_id');

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.* FROM `table` INNER JOIN `table2` ON `table2`.`field_id` = `table`.`field_id` WHERE `table`.`key_id` = :value1', $sql);
        $this->assertNotEmpty($values);
        $this->assertArrayHasKey('value1', $values);
        $this->assertEquals(1, $values['value1']);
    }

    public function testBuildBasicFetchWithJoinAndFilterSQLQuery(): void
    {
        $sqlBuilder = (new SQLBuilder('table'))
            ->join('table2', 'field_id', 'key_id')
            ->where('table2.key_id', '=', 1);

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.* FROM `table` INNER JOIN `table2` ON `table2`.`field_id` = `table`.`key_id` WHERE `table2`.`key_id` = :value1', $sql);
        $this->assertNotEmpty($values);
        $this->assertArrayHasKey('value1', $values);
        $this->assertEquals(1, $values['value1']);
    }

    public function testBuildFetchWithJoinAndFilterSQLQuery(): void
    {
        // Table 1
        $sqlBuilder = (new SQLBuilder('table'))
            ->where('key_id', '=', 1);

        // Table 2
        $table2 = (new SQLBuilder('table2'))
            ->select('column_1', 'column_2')
            ->where('key_id', '=', 42);

        $sqlBuilder->join($table2, 'table2_id', 'parent_id');

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertNotEmpty($sql);
        $this->assertEquals('SELECT SQL_CALC_FOUND_ROWS `table`.*, `table2`.`column_1`, `table2`.`column_2` FROM `table` INNER JOIN `table2` ON `table2`.`table2_id` = `table`.`parent_id` WHERE (`table`.`key_id` = :value1 AND `table2`.`key_id` = :value2)', $sql);
        $this->assertNotEmpty($values);
        $this->assertArrayHasKey('value1', $values);
        $this->assertEquals(1, $values['value1']);
        $this->assertArrayHasKey('value2', $values);
        $this->assertEquals(42, $values['value2']);
    }
}
