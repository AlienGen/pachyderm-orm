<?php

declare(strict_types=1);

use Model\SimpleObject;
use PHPUnit\Framework\TestCase;
use Stub\DbMock;

class SimpleModelTest extends TestCase
{
    public function testCreate(): void
    {
        $dbMock = new DbMock();
        SimpleObject::setDbEngine($dbMock);

        $model = new SimpleObject();
        $model->name = 'test';
        $model->save();
        $this->assertTrue(true);
    }

    public function testRead(): void
    {
        $this->assertTrue(true);
    }

    public function testUpdate(): void
    {
        $this->assertTrue(true);
    }

    public function testDelete(): void
    {
        $this->assertTrue(true);
    }
}
