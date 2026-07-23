<?php

declare(strict_types=1);

use Model\RelationObject;
use Model\SimpleObject;
use Pachyderm\Orm\Relation\BelongsToMap;
use PHPUnit\Framework\TestCase;
use Stub\DbMock;

class BelongsToEagerLoadTest extends TestCase
{
    protected function setUp(): void
    {
        DbMock::clear();
        BelongsToMap::clearCache();
        RelationObject::setDbEngine(DbMock::class);
    }

    public function testWithBuildsLeftJoinAndAliasedSelect(): void
    {
        $sql = RelationObject::builder()
            ->with('simple')
            ->build();

        $this->assertStringContainsString(
            'LEFT JOIN `simple_object` AS `simple` ON `simple`.`simple_object_id` = `relation_object`.`simple_id`',
            $sql
        );
        $this->assertStringContainsString(
            '`simple`.`simple_object_id` AS `simple__simple_object_id`',
            $sql
        );
        $this->assertStringContainsString(
            '`simple`.`name` AS `simple__name`',
            $sql
        );
    }

    public function testWithUnknownRelationThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        RelationObject::builder()->with('missing');
    }

    public function testHydratesRelatedModelOnAssociationProperty(): void
    {
        DbMock::setNextResults([
            [
                'relation_object_id' => 1,
                'simple_id' => 2,
                'simple__simple_object_id' => 2,
                'simple__name' => 'Alice',
            ],
        ]);

        $model = RelationObject::builder()->with('simple')->first();

        $this->assertNotNull($model);
        $this->assertSame(1, $model->relation_object_id);
        $this->assertSame(2, $model->simple_id);
        $this->assertInstanceOf(SimpleObject::class, $model->simple);
        $this->assertSame(2, $model->simple->simple_object_id);
        $this->assertSame('Alice', $model->simple->name);

        // FK scalar lives in _data; association does not.
        $this->assertArrayHasKey('simple_id', $model->toArray());
        $this->assertSame(2, $model->toArray()['simple_id']);
        $this->assertArrayNotHasKey('simple', $model->toArray());
    }

    public function testHydratesNullWhenRelatedMissing(): void
    {
        DbMock::setNextResults([
            [
                'relation_object_id' => 1,
                'simple_id' => null,
                'simple__simple_object_id' => null,
                'simple__name' => null,
            ],
        ]);

        $model = RelationObject::builder()->with('simple')->first();

        $this->assertNotNull($model);
        $this->assertNull($model->simple);
    }

    public function testWithoutWithLeavesAssociationNull(): void
    {
        DbMock::setNextResults([
            [
                'relation_object_id' => 1,
                'simple_id' => 2,
            ],
        ]);

        $model = RelationObject::builder()->first();

        $this->assertNotNull($model);
        $this->assertNull($model->simple);
        $this->assertSame(2, $model->simple_id);
        $this->assertStringNotContainsString('LEFT JOIN', RelationObject::builder()->build());
    }

    public function testSaveDoesNotPersistAssociationObject(): void
    {
        DbMock::setNextResults([
            [
                'relation_object_id' => 1,
                'simple_id' => 2,
                'simple__simple_object_id' => 2,
                'simple__name' => 'Alice',
            ],
        ]);

        $model = RelationObject::builder()->with('simple')->first();
        $this->assertInstanceOf(SimpleObject::class, $model->simple);

        // Avoid refresh find() after update by throwing away next results as empty not found...
        // save() calls find() to refresh; provide a scalar-only row.
        DbMock::setNextResults([
            [
                'relation_object_id' => 1,
                'simple_id' => 2,
            ],
        ]);

        $model->simple_id = 3;
        $model->save();

        $this->assertNotNull(DbMock::$lastUpdate);
        $payload = DbMock::$lastUpdate['data'];
        $this->assertArrayNotHasKey('simple', $payload);
        foreach ($payload as $value) {
            $this->assertNotInstanceOf(SimpleObject::class, $value);
            $this->assertNotInstanceOf(\Pachyderm\Orm\Model::class, $value);
        }
        $this->assertSame(3, $payload['simple_id']);
    }
}
