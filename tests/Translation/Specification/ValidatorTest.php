<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM\Translation\Specification;

use Innmind\Neo4j\ONM\{
    Translation\Specification\Validator,
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
    Metadata\Relationship,
    Metadata\RelationshipEdge,
    Type\DateType,
    Type\StringType
};
use Fixtures\Innmind\Neo4j\ONM\Specification\Property;
use Innmind\Immutable\{
    Collection,
    Map
};
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    private $aggregate;
    private $relationship;

    public function setUp()
    {
        $this->aggregate = (new Aggregate(
            new ClassName('FQCN'),
            new Identity('id', 'foo'),
            new Repository('foo'),
            new Factory('foo'),
            new Alias('foo'),
            ['Label']
        ))
            ->withProperty('created', new DateType)
            ->withProperty(
                'empty',
                StringType::fromConfig(
                    new Collection(['nullable' => null])
                )
            )
            ->withChild(
                (new ValueObject(
                    new ClassName('foo'),
                    ['AnotherLabel'],
                    (new ValueObjectRelationship(
                        new ClassName('foo'),
                        new RelationshipType('CHILD1_OF'),
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
        $this->relationship = (new Relationship(
            new ClassName('foo'),
            new Identity('id', 'foo'),
            new Repository('foo'),
            new Factory('foo'),
            new Alias('foo'),
            new RelationshipType('type'),
            new RelationshipEdge('start', 'foo', 'id'),
            new RelationshipEdge('end', 'foo', 'id')
        ))
            ->withProperty('created', new DateType)
            ->withProperty(
                'empty',
                StringType::fromConfig(
                    new Collection(['nullable' => null])
                )
            );
    }

    public function testValidateAggregate()
    {
        $compCreated = new Property('created', '=', null);
        $compRelCreated = new Property('rel.created', '=', null);
        $compChildContent = new Property('rel.child.content', '=', null);

        $this->assertTrue(
            (new Validator)->validate(
                $compCreated,
                $this->aggregate
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $compRelCreated,
                $this->aggregate
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $compChildContent,
                $this->aggregate
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $compCreated
                    ->and($compRelCreated)
                    ->or($compChildContent->not()),
                $this->aggregate
            )
        );
    }

    public function testDoesntValidateAggregate()
    {
        $comp1 = new Property('foo', '=', null);
        $comp2 = new Property('rel.foo', '=', null);
        $comp3 = new Property('rel.child.foo', '=', null);
        $comp4 = new Property('rel.child.foo.tooDeep', '=', null);

        $this->assertFalse(
            (new Validator)->validate(
                $comp1,
                $this->aggregate
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp2,
                $this->aggregate
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp3,
                $this->aggregate
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp1
                    ->and($comp2)
                    ->or($comp3->not())
                    ->or($comp4),
                $this->aggregate
            )
        );
    }

    public function testValidateRelationship()
    {
        $comp1 = new Property('created', '=', null);
        $comp2 = new Property('start', '=', null);
        $comp3 = new Property('end', '=', null);

        $this->assertTrue(
            (new Validator)->validate(
                $comp1,
                $this->relationship
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $comp2,
                $this->relationship
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $comp3,
                $this->relationship
            )
        );
        $this->assertTrue(
            (new Validator)->validate(
                $comp1
                    ->and($comp2)
                    ->or($comp3->not()),
                $this->relationship
            )
        );
    }

    public function testDoesntValidateRelationship()
    {
        $comp1 = new Property('foo', '=', null);
        $comp2 = new Property('foo.bar', '=', null);
        $comp3 = new Property('start.id', '=', null);

        $this->assertFalse(
            (new Validator)->validate(
                $comp1,
                $this->relationship
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp2,
                $this->relationship
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp3,
                $this->relationship
            )
        );
        $this->assertFalse(
            (new Validator)->validate(
                $comp1
                    ->and($comp2)
                    ->or($comp3->not()),
                $this->relationship
            )
        );
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\InvalidArgumentException
     */
    public function testThrowWhenInjectingInvalidValidator()
    {
        new Validator(new Map('int', 'int'));
    }
}
