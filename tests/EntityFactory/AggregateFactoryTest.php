<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM\EntityFactory;

use Innmind\Neo4j\ONM\{
    EntityFactory\AggregateFactory,
    Metadata\Aggregate,
    Metadata\ClassName,
    Metadata\Identity,
    Metadata\ValueObject,
    Metadata\ValueObjectRelationship,
    Metadata\RelationshipType,
    Metadata\Entity,
    Type\DateType,
    Type\StringType,
    Identity\Uuid,
    Identity as IdentityInterface,
    Type,
    EntityFactory,
};
use Innmind\Immutable\{
    SetInterface,
    Set,
    MapInterface,
    Map,
    Stream,
};
use PHPUnit\Framework\TestCase;

class AggregateFactoryTest extends TestCase
{
    public function testInterface()
    {
        $this->assertInstanceOf(
            EntityFactory::class,
            new AggregateFactory
        );
    }

    public function testMake()
    {
        $make = new AggregateFactory;

        $entity = new class {
            public $uuid;
            public $created;
            public $empty;
            public $rel;
        };
        $rel = new class {
            public $created;
            public $empty;
            public $child;
        };
        $child = new class {
            public $content;
            public $empty;
        };
        $meta = Aggregate::of(
            new ClassName(get_class($entity)),
            new Identity('uuid', 'foo'),
            Set::of('string', 'Label'),
            Map::of('string', Type::class)
                ('created', new DateType)
                ('empty', StringType::nullable()),
            Set::of(
                ValueObject::class,
                ValueObject::of(
                    new ClassName(get_class($child)),
                    Set::of('string', 'AnotherLabel'),
                    ValueObjectRelationship::of(
                        new ClassName(get_class($rel)),
                        new RelationshipType('foo'),
                        'rel',
                        'child',
                        Map::of('string', Type::class)
                            ('created', new DateType)
                            ('empty', StringType::nullable())
                    ),
                    Map::of('string', Type::class)
                        ('content', new StringType)
                        ('empty', StringType::nullable())
                )
            )
        );

        $ar = $make(
            $identity = new Uuid('11111111-1111-1111-1111-111111111111'),
            $meta,
            (new Map('string', 'mixed'))
                ->put('uuid', 24)
                ->put('created', '2016-01-01T00:00:00+0200')
                ->put('rel', (new Map('string', 'mixed'))
                    ->put('created', '2016-01-01T00:00:00+0200')
                    ->put('child', (new Map('string', 'mixed'))
                        ->put('content', 'foo')
                    )
                )
        );

        $this->assertInstanceOf(get_class($entity), $ar);
        $this->assertSame($identity, $ar->uuid);
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $ar->created
        );
        $this->assertSame(
            '2016-01-01T00:00:00+02:00',
            $ar->created->format('c')
        );
        $this->assertNull($ar->empty);
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $ar->rel->created
        );
        $this->assertSame(
            '2016-01-01T00:00:00+02:00',
            $ar->rel->created->format('c')
        );
        $this->assertNull($ar->rel->empty);
        $this->assertInstanceOf(
            get_class($child),
            $ar->rel->child
        );
        $this->assertSame('foo', $ar->rel->child->content);
        $this->assertNull($ar->rel->child->empty);
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\InvalidArgumentException
     */
    public function testThrowWhenTryingToBuildNonAggregate()
    {
        (new AggregateFactory)(
            $this->createMock(IdentityInterface::class),
            $this->createMock(Entity::class),
            new Map('string', 'mixed')
        );
    }

    /**
     * @expectedException TypeError
     * @expectedExceptionMessage Argument 3 must be of type MapInterface<string, mixed>
     */
    public function testThrowWhenTryingToBuildWithInvalidData()
    {
        (new AggregateFactory)(
            $this->createMock(IdentityInterface::class),
            Aggregate::of(
                new ClassName('foo'),
                new Identity('uuid', 'foo'),
                Set::of('string', 'Label')
            ),
            new Map('string', 'variable')
        );
    }
}
