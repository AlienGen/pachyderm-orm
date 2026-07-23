<?php

declare(strict_types=1);

use Pachyderm\Orm\QueryBuilder;
use PHPUnit\Framework\TestCase;

class QueryBuilderTest extends TestCase
{
    public function testBuildFilters(): void
    {
        $queryBuilder = new QueryBuilder();

        $queryBuilder->where('field1', '=', 'value1');

        $this->assertSame(['=' => ['field1', 'value1']], $queryBuilder->build());
    }

    public function testColumnNamedWhereIsTreatedAsField(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->where('where', '=', 'clause');

        $this->assertSame(['=' => ['where', 'clause']], $queryBuilder->build());
    }

    public function testClosureIsInvoked(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->where(function (QueryBuilder $q): void {
            $q->where('status', '=', 'ACTIVE');
        });

        $this->assertSame(['=' => ['status', 'ACTIVE']], $queryBuilder->build());
    }

    public function testPrependQualifiesExistingFilters(): void
    {
        $queryBuilder = (new QueryBuilder())
            ->where('won_date', 'IS NULL')
            ->orWhere('imputation_id', 'IS NULL');

        $queryBuilder->prepend('opportunities');

        $this->assertSame(
            [
                'OR' => [
                    ['IS NULL' => ['opportunities.won_date']],
                    ['IS NULL' => ['opportunities.imputation_id']],
                ],
            ],
            $queryBuilder->build()
        );
    }

    public function testNestedBuilderInheritsParentTable(): void
    {
        $inner = (new QueryBuilder())
            ->where('name', 'LIKE', '%john%')
            ->orWhere('name', 'LIKE', '%jane%');

        $outer = (new QueryBuilder());
        $outer->prepend('users');
        $outer->where('status', '=', 'ACTIVE');
        $outer->where($inner);

        $built = $outer->build();

        $this->assertSame(
            [
                'AND' => [
                    ['=' => ['users.status', 'ACTIVE']],
                    [
                        'OR' => [
                            ['LIKE' => ['users.name', '%john%']],
                            ['LIKE' => ['users.name', '%jane%']],
                        ],
                    ],
                ],
            ],
            $built
        );
    }

    public function testPrependLeavesAlreadyQualifiedColumns(): void
    {
        $queryBuilder = (new QueryBuilder())
            ->where('other.col', '=', 1)
            ->where('bare', '=', 2);

        $queryBuilder->prepend('owner');

        $this->assertSame(
            [
                'AND' => [
                    ['=' => ['other.col', 1]],
                    ['=' => ['owner.bare', 2]],
                ],
            ],
            $queryBuilder->build()
        );
    }

    public function testPrependQualifiesColumnComparisons(): void
    {
        $queryBuilder = new QueryBuilder();
        $queryBuilder->where(['COLUMN' => ['left_col', '=', 'right_col']]);
        $queryBuilder->prepend('t');

        $this->assertSame(
            ['COLUMN' => ['t.left_col', '=', 't.right_col']],
            $queryBuilder->build()
        );
    }
}
