<?php

declare(strict_types=1);

use Model\CompositeKeyObject;
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
        CompositeKeyObject::setDbEngine(DbMock::class);
    }

    public function testCreateStripsScalarPrimaryKey(): void
    {
        SimpleObject::create([
            'simple_object_id' => 99,
            'name' => 'Alice',
        ]);

        $this->assertNotNull(DbMock::$lastInsert);
        $this->assertArrayNotHasKey('simple_object_id', DbMock::$lastInsert['data']);
        $this->assertSame('Alice', DbMock::$lastInsert['data']['name']);
    }

    public function testCreateKeepsCompositePrimaryKeys(): void
    {
        CompositeKeyObject::create([
            'left_id' => 10,
            'right_id' => 20,
            'label' => 'link',
        ]);

        $this->assertNotNull(DbMock::$lastInsert);
        $payload = DbMock::$lastInsert['data'];
        $this->assertSame(10, $payload['left_id']);
        $this->assertSame(20, $payload['right_id']);
        $this->assertSame('link', $payload['label']);
    }

    public function testSetStillStripsCompositePrimaryKeys(): void
    {
        $model = new CompositeKeyObject([
            'left_id' => 10,
            'right_id' => 20,
            'label' => 'old',
        ]);
        $model->set([
            'left_id' => 99,
            'right_id' => 88,
            'label' => 'new',
        ]);

        $this->assertSame(10, $model->left_id);
        $this->assertSame(20, $model->right_id);
        $this->assertSame('new', $model->label);
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
