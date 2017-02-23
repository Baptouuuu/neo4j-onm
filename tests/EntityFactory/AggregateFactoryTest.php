<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM\EntityFactory;

use Innmind\Neo4j\ONM\{
    EntityFactory\AggregateFactory,
    Metadata\Aggregate,
    Metadata\ClassName,
    Metadata\Identity,
    Metadata\Repository,
    Metadata\Factory,
    Metadata\Alias,
    Metadata\ValueObject,
    Metadata\ValueObjectRelationship,
    Metadata\RelationshipType,
    Metadata\EntityInterface,
    Type\DateType,
    Type\StringType,
    Identity\Uuid,
    IdentityInterface
};
use Innmind\Reflection\{
    InstanciatorInterface,
    InjectionStrategy\InjectionStrategyInterface,
    InjectionStrategy\InjectionStrategies
};
use Innmind\Immutable\{
    Collection,
    CollectionInterface,
    TypedCollection,
    SetInterface
};
use PHPUnit\Framework\TestCase;

class AggregateFactoryTest extends TestCase
{
    /**
     * @dataProvider reflection
     */
    public function testMake($instanciator, $injectionStrategies)
    {
        $f = new AggregateFactory($instanciator, $injectionStrategies);

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
        $meta = new Aggregate(
            new ClassName(get_class($entity)),
            new Identity('uuid', 'foo'),
            new Repository('foo'),
            new Factory('foo'),
            new Alias('foo'),
            ['Label']
        );
        $meta = $meta
            ->withProperty('created', new DateType)
            ->withProperty(
                'empty',
                StringType::fromConfig(
                    new Collection(['nullable' => null])
                )
            )
            ->withChild(
                (new ValueObject(
                    new ClassName(get_class($child)),
                    ['AnotherLabel'],
                    (new ValueObjectRelationship(
                        new ClassName(get_class($rel)),
                        new RelationshipType('foo'),
                        'rel',
                        'child'
                    ))
                        ->withProperty('created', new DateType)
                        ->withProperty(
                            'empty',
                            StringType::fromConfig(
                                new Collection(['nullable' => null])
                            )
                        )
                ))
                    ->withProperty('content', new StringType)
                    ->withProperty(
                        'empty',
                        StringType::fromConfig(
                            new Collection(['nullable' => null])
                        )
                    )
            );

        $ar = $f->make(
            $identity = new Uuid('11111111-1111-1111-1111-111111111111'),
            $meta,
            new Collection([
                'uuid' => 24,
                'created' => '2016-01-01T00:00:00+0200',
                'rel' => new Collection([
                    'created' => '2016-01-01T00:00:00+0200',
                    'child' => new Collection([
                        'content' => 'foo',
                    ]),
                ]),
            ])
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
        $this->assertSame(null, $ar->empty);
        $this->assertInstanceOf(
            \DateTimeImmutable::class,
            $ar->rel->created
        );
        $this->assertSame(
            '2016-01-01T00:00:00+02:00',
            $ar->rel->created->format('c')
        );
        $this->assertSame(null, $ar->rel->empty);
        $this->assertInstanceOf(
            get_class($child),
            $ar->rel->child
        );
        $this->assertSame('foo', $ar->rel->child->content);
        $this->assertSame(null, $ar->rel->child->empty);
    }

    /**
     * @expectedException Innmind\neo4j\ONM\Exception\InvalidArgumentException
     */
    public function testThrowWhenTryingToBuildNonAggregate()
    {
        (new AggregateFactory)->make(
            $this->createMock(IdentityInterface::class),
            $this->createMock(EntityInterface::class),
            new Collection([])
        );
    }

    public function reflection(): array
    {
        return [
            [null, null],
            [
                new class implements InstanciatorInterface {
                    public function build(string $class, CollectionInterface $properties)
                    {
                        return new $class;
                    }

                    public function getParameters(string $class): CollectionInterface
                    {
                        return new Collection([]);
                    }
                },
                null,
            ],
            [
                new class implements InstanciatorInterface {
                    public function build(string $class, CollectionInterface $properties)
                    {
                        $object = new $class;
                        $properties->each(function($name, $value) use ($object) {
                            $object->$name = $value;
                        });

                        return $object;
                    }

                    public function getParameters(string $class): CollectionInterface
                    {
                        return new Collection([]);
                    }
                },
                new InjectionStrategies(
                    new TypedCollection(
                        InjectionStrategyInterface::class,
                        []
                    )
                ),
            ],
        ];
    }
}
