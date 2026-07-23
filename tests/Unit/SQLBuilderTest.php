<?php

declare(strict_types=1);

use Pachyderm\Orm\QueryBuilder;
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

    public function testOrderNormalizesDirection(): void
    {
        $sql = (new SQLBuilder('table'))
            ->order('created_at', 'desc')
            ->build();

        $this->assertStringContainsString('ORDER BY `table`.`created_at` DESC', $sql);
    }

    public function testNestedQueryBuilderQualifiesColumnsWithJoin(): void
    {
        $pipelineOpen = (new QueryBuilder())
            ->where('won_date', 'IS NULL')
            ->orWhere('imputation_id', 'IS NULL');

        $sql = (new SQLBuilder('opportunities'))
            ->where('active', '=', 1)
            ->where($pipelineOpen)
            ->leftJoin('customers', 'id', 'customer_id', 'customer')
            ->build();

        $this->assertStringContainsString('`opportunities`.`won_date` IS NULL', $sql);
        $this->assertStringContainsString('`opportunities`.`imputation_id` IS NULL', $sql);
        $this->assertStringNotContainsString('(`won_date` IS NULL', $sql);
        $this->assertStringNotContainsString('OR `imputation_id` IS NULL', $sql);
    }

    public function testNestedQueryBuilderPreservesAlreadyQualifiedColumns(): void
    {
        $nested = (new QueryBuilder())
            ->where('opportunities.imputation_id', 'IS NULL')
            ->orWhere('customer.imputation_id', 'IS NULL');

        $sql = (new SQLBuilder('opportunities'))
            ->where($nested)
            ->leftJoin('customers', 'id', 'customer_id', 'customer')
            ->build();

        $this->assertStringContainsString('`opportunities`.`imputation_id` IS NULL', $sql);
        $this->assertStringContainsString('`customer`.`imputation_id` IS NULL', $sql);
    }

    public function testDirectWhereStillPrefixesWithOwnerTable(): void
    {
        $sql = (new SQLBuilder('opportunities'))
            ->where('imputation_id', 'IS NULL')
            ->leftJoin('customers', 'id', 'customer_id', 'customer')
            ->build();

        $this->assertStringContainsString('WHERE `opportunities`.`imputation_id` IS NULL', $sql);
    }

    public function testOrderQualifiesColumnWithJoin(): void
    {
        $sql = (new SQLBuilder('opportunities'))
            ->order('expected_date', 'ASC')
            ->leftJoin('customers', 'id', 'customer_id', 'customer')
            ->build();

        $this->assertStringContainsString('ORDER BY `opportunities`.`expected_date` ASC', $sql);
    }

    public function testOrderRejectsInvalidDirection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SQLBuilder('table'))->order('id', 'DESC; DROP TABLE users');
    }

    public function testOrderRejectsInvalidIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SQLBuilder('table'))->order('id` DESC, sleep(1), `id', 'ASC');
    }

    public function testRejectsInvalidOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SQLBuilder('table'))
            ->where(['OR 1=1--' => ['id', 1]])
            ->build();
    }

    public function testAcceptsAllowedOperator(): void
    {
        $sql = (new SQLBuilder('table'))
            ->where('name', 'LIKE', '%x%')
            ->build();

        $this->assertStringContainsString('`table`.`name` LIKE :value1', $sql);
    }

    public function testToStatementReturnsPlaceholders(): void
    {
        $builder = (new SQLBuilder('table'))->where('id', '=', 5);
        [$sql, $params] = $builder->toStatement();

        $this->assertStringContainsString(':value1', $sql);
        $this->assertSame(['value1' => 5], $params);
    }

    public function testLeftJoinWithAlias(): void
    {
        $sql = (new SQLBuilder('orders'))
            ->leftJoin('customers', 'id', 'customer_id', 'customer')
            ->selectAs('customer.name', 'customer__name')
            ->build();

        $this->assertSame(
            'SELECT SQL_CALC_FOUND_ROWS `orders`.*, `customer`.`name` AS `customer__name` FROM `orders` LEFT JOIN `customers` AS `customer` ON `customer`.`id` = `orders`.`customer_id`',
            $sql
        );
    }

    public function testInnerJoinUnchangedWithoutAlias(): void
    {
        $sql = (new SQLBuilder('table'))
            ->join('table2', 'field_id')
            ->build();

        $this->assertStringContainsString('INNER JOIN `table2` ON `table2`.`field_id` = `table`.`field_id`', $sql);
        $this->assertStringNotContainsString('AS', $sql);
    }
}
