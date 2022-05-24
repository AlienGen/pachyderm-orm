<?php

declare(strict_types=1);

use Pachyderm\Orm\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
  public function testCanBuildFilter(): void
  {
    $paginator = new Paginator();
    $paginator->addOrder('expected_date', 'ASC');
    $paginator->where('field1', '=', '1');

    $filters = $paginator->filters();

    $getParams = [
      'field2' => 'value2'
    ];

    $paginator->parse($getParams);

    // TODO: Complete test
    $this->assertNotEmpty($filters);
  }
}
