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
}
