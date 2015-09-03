<?php

namespace Innmind\Neo4j\ONM\Mapping\Type;

use Innmind\Neo4j\ONM\Mapping\TypeInterface;
use Innmind\Neo4j\ONM\Mapping\Property;

class FloatType implements TypeInterface
{
    /**
     * {@inheritdoc}
     */
    public function convertToDatabaseValue($value, Property $property)
    {
        if ($property->isNullable() && $value === null) {
            return $value;
        }

        return (float) $value;
    }

    /**
     * {@inheritdoc}
     */
    public function convertToPHPValue($value, Property $property)
    {
        return (float) $value;
    }
}
