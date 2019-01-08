<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM\Metadata;

use Innmind\Neo4j\ONM\{
    Metadata\Aggregate,
    Metadata\ClassName,
    Metadata\Identity,
    Metadata\Entity,
    Metadata\Aggregate\Child,
    Metadata\RelationshipType,
    Type,
    EntityFactory\AggregateFactory,
    Repository\Repository,
};
use Innmind\Immutable\{
    SetInterface,
    Set,
    MapInterface,
    Map,
};
use PHPUnit\Framework\TestCase;

class AggregateTest extends TestCase
{
    public function testInterface()
    {
        $aggregateRoot = Aggregate::of(
            $className = new ClassName('foo'),
            $identity = new Identity('uuid', 'UUID'),
            Set::of('string', 'LabelA'),
            Map::of('string', Type::class)
                ('foo', $this->createMock(Type::class)),
            Set::of(
                Child::class,
                $vo = Child::of(
                    new ClassName('whatever'),
                    Set::of('string', 'whatever'),
                    Child\Relationship::of(
                        new ClassName('whatever'),
                        new RelationshipType('whatever'),
                        'foo',
                        'bar'
                    )
                )
            )
        );

        $this->assertInstanceOf(Entity::class, $aggregateRoot);
        $this->assertSame($className, $aggregateRoot->class());
        $this->assertSame($identity, $aggregateRoot->identity());
        $this->assertSame(Repository::class, (string) $aggregateRoot->repository());
        $this->assertSame(AggregateFactory::class, (string) $aggregateRoot->factory());
        $this->assertInstanceOf(SetInterface::class, $aggregateRoot->labels());
        $this->assertSame('string', (string) $aggregateRoot->labels()->type());
        $this->assertSame(['LabelA'], $aggregateRoot->labels()->toPrimitive());
        $this->assertInstanceOf(MapInterface::class, $aggregateRoot->children());
        $this->assertSame('string', (string) $aggregateRoot->children()->keyType());
        $this->assertSame(Child::class, (string) $aggregateRoot->children()->valueType());
        $this->assertCount(1, $aggregateRoot->children());
        $this->assertSame($vo, $aggregateRoot->children()->current());
        $this->assertCount(1, $aggregateRoot->properties());
        $this->assertTrue($aggregateRoot->properties()->contains('foo'));
    }
}

