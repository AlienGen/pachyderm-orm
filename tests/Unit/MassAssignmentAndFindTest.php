<?php

declare(strict_types=1);

use Model\FillableObject;
use Model\GuardedObject;
use Model\SimpleObject;
use Pachyderm\Orm\Exception\ModelNotFoundException;
use PHPUnit\Framework\TestCase;
use Stub\DbMock;

class MassAssignmentAndFindTest extends TestCase
{
    protected function setUp(): void
    {
        DbMock::clear();
        SimpleObject::setDbEngine(DbMock::class);
        FillableObject::setDbEngine(DbMock::class);
        GuardedObject::setDbEngine(DbMock::class);
    }

    public function testFillableFiltersSet(): void
    {
        $model = new FillableObject(['id' => 1, 'name' => 'Alice', 'email' => 'a@example.com', 'is_admin' => true]);
        $this->assertSame(1, $model->id);
        $this->assertSame('Alice', $model->name);

        $model->set(['name' => 'Bob', 'is_admin' => false, 'id' => 99]);
        $this->assertSame('Bob', $model->name);
        $this->assertSame(1, $model->id);
        $this->assertTrue($model->is_admin);
    }

    public function testGuardedFiltersSet(): void
    {
        $model = new GuardedObject(['id' => 1, 'name' => 'Alice', 'is_admin' => true]);
        $model->set(['name' => 'Bob', 'is_admin' => false]);

        $this->assertSame('Bob', $model->name);
        $this->assertTrue($model->is_admin);
    }

    public function testForceSetBypassesFillable(): void
    {
        $model = new FillableObject();
        $model->forceSet(['id' => 7, 'is_admin' => true, 'name' => 'X']);

        $this->assertSame(7, $model->id);
        $this->assertTrue($model->is_admin);
        $this->assertSame('X', $model->name);
    }

    public function testFindRejectsEmptyString(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('require an id not empty');
        SimpleObject::find('');
    }

    public function testFindAllowsZeroString(): void
    {
        try {
            SimpleObject::find('0');
            $this->fail('Expected ModelNotFoundException from missing row');
        } catch (ModelNotFoundException $e) {
            $this->assertStringContainsString('not found', $e->getMessage());
            $this->assertStringNotContainsString('require an id not empty', $e->getMessage());
        }
    }
}
