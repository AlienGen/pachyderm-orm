<?php

declare(strict_types=1);

use Pachyderm\Orm\SQLBuilder;
use PHPUnit\Framework\TestCase;

class AdvancedQueryTest extends TestCase
{
    public function testWhereExistsCorrelated(): void
    {
        $items = (new SQLBuilder('order_items'))
            ->whereColumn('order_items.order_id', '=', 'orders.id')
            ->where('qty', '>', 0);

        $sqlBuilder = (new SQLBuilder('orders'))
            ->whereExists($items);

        $sql = $sqlBuilder->build();
        $values = $sqlBuilder->values();

        $this->assertStringContainsString(
            'WHERE EXISTS (SELECT 1 FROM `order_items` WHERE (`order_items`.`order_id` = `orders`.`id` AND `order_items`.`qty` > :value1))',
            $sql
        );
        $this->assertSame(0, $values['value1']);
        $this->assertStringNotContainsString('SQL_CALC_FOUND_ROWS', explode('EXISTS', $sql)[1]);
    }

    public function testWhereNotExists(): void
    {
        $sub = (new SQLBuilder('flags'))->where('flags.active', '=', 1);
        $sql = (new SQLBuilder('users'))->whereNotExists($sub)->build();

        $this->assertStringContainsString('WHERE NOT EXISTS (SELECT 1 FROM `flags` WHERE `flags`.`active` = :value1)', $sql);
    }

    public function testWhereColumn(): void
    {
        $sql = (new SQLBuilder('orders'))
            ->join('users', 'id', 'user_id')
            ->whereColumn('orders.user_id', '=', 'users.id')
            ->build();

        $this->assertStringContainsString('`orders`.`user_id` = `users`.`id`', $sql);
        $this->assertSame([], (new SQLBuilder('orders'))->whereColumn('a.id', '=', 'b.id')->values());
    }

    public function testInSubqueryMergesBindings(): void
    {
        $sub = (new SQLBuilder('orders'))
            ->select('user_id')
            ->where('status', '=', 'PAID');

        $builder = (new SQLBuilder('users'))
            ->where('id', 'IN', $sub)
            ->where('active', '=', 1);

        $sql = $builder->build();
        $values = $builder->values();

        $this->assertStringContainsString(
            'WHERE (`users`.`id` IN (SELECT `orders`.`user_id` FROM `orders` WHERE `orders`.`status` = :value1) AND `users`.`active` = :value2)',
            $sql
        );
        $this->assertSame('PAID', $values['value1']);
        $this->assertSame(1, $values['value2']);
        $this->assertStringNotContainsString('SQL_CALC_FOUND_ROWS', explode('IN (', $sql)[1]);
    }

    public function testSelectMaxGroupByHaving(): void
    {
        $builder = (new SQLBuilder('orders'))
            ->select('status')
            ->selectMax('amount', 'max_amount')
            ->groupBy('status')
            ->having('max_amount', '>', 100);

        $sql = $builder->build();
        $values = $builder->values();

        $this->assertSame(
            'SELECT SQL_CALC_FOUND_ROWS `orders`.`status`, MAX(`orders`.`amount`) AS `max_amount` FROM `orders` GROUP BY `orders`.`status` HAVING `max_amount` > :value1',
            $sql
        );
        $this->assertSame(100, $values['value1']);
        $this->assertStringNotContainsString('`orders`.*', $sql);
    }

    public function testSelectCountStar(): void
    {
        $sql = (new SQLBuilder('orders'))
            ->selectCount('*', 'total')
            ->build();

        $this->assertSame(
            'SELECT SQL_CALC_FOUND_ROWS COUNT(*) AS `total` FROM `orders`',
            $sql
        );
    }

    public function testRejectInvalidAggregateViaMaxOnStar(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SQLBuilder('orders'))->selectMax('*');
    }

    public function testRejectInvalidColumnOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SQLBuilder('orders'))->whereColumn('a.id', 'LIKE', 'b.id');
    }
}
