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

        // TODO: Complete test
        $this->assertNotEmpty($queryBuilder->build());
    }
}
