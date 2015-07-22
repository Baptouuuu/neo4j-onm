<?php

namespace Innmind\Neo4j\ONM\Tests\Mapping\Type;

use Innmind\Neo4j\ONM\Mapping\Type\FloatType;
use Innmind\Neo4j\ONM\Mapping\Property;

class FloatTypeTest extends \PHPUnit_Framework_TestCase
{
    public function testConvertToDatabaseValue()
    {
        $t = new FloatType;
        $p = new Property;

        $this->assertEquals(
            42.01,
            $t->convertToDatabaseValue('42.01', $p)
        );
    }

    public function testConvertToPHPValue()
    {
        $t = new FloatType;
        $p = new Property;

        $this->assertEquals(
            42.01,
            $t->convertToPHPValue('42.01', $p)
        );
    }
}