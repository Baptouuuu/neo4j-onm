<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM;

use Innmind\Neo4j\ONM\{
    Types,
    TypeInterface,
    Type\ArrayType,
    Type\SetType,
    Type\BooleanType,
    Type\DateType,
    Type\FloatType,
    Type\IntType,
    Type\StringType,
    Type\PointInTimeType
};
use Innmind\Immutable\Map;
use PHPUnit\Framework\TestCase;

class TypesTest extends TestCase
{
    public function testAll()
    {
        $types = new Types;

        $defaults = [
            'array' => ArrayType::class,
            'set' => SetType::class,
            'bool' => BooleanType::class,
            'boolean' => BooleanType::class,
            'date' => DateType::class,
            'datetime' => DateType::class,
            'float' => FloatType::class,
            'int' => IntType::class,
            'integer' => IntType::class,
            'string' => StringType::class,
        ];

        foreach ($defaults as $key => $value) {
            $this->assertInstanceOf($value, $types->build(
                $key,
                (new Map('string', 'mixed'))
                    ->put('inner', 'string')
            ));
        }
    }

    public function testRegisterCustomType()
    {
        $types = new Types(PointInTimeType::class);

        $this->assertInstanceOf(
            PointInTimeType::class,
            $types->build(
                'point_in_time',
                new Map('string', 'mixed')
            )
        );
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\InvalidArgumentException
     * @expectedExceptionMessage The type "stdClass" must implement TypeInterface
     */
    public function testThrowWhenRegisteringingInvalidType()
    {
        new Types('stdClass');
    }

    public function testBuild()
    {
        $types = new Types;

        $this->assertInstanceOf(
            StringType::class,
            $types->build('string', new Map('string', 'mixed'))
        );
        $this->assertInstanceOf(
            ArrayType::class,
            $types->build(
                'array',
                (new Map('string', 'mixed'))
                    ->put('inner', 'string')
            )
        );
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\InvalidArgumentException
     */
    public function testThrowWhenInvalidConfigMap()
    {
        (new Types)->build('string', new Map('string', 'variable'));
    }
}
