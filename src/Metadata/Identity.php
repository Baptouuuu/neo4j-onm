<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Metadata;

use Innmind\Neo4j\ONM\Exception\DomainException;

/**
 * Holds the property name of an entity identifier
 */
class Identity
{
    private $property;
    private $type;

    public function __construct(string $property, string $type)
    {
        if (empty($property) || empty($type)) {
            throw new DomainException;
        }

        $this->property = $property;
        $this->type = $type;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function property(): string
    {
        return $this->property;
    }

    public function __toString(): string
    {
        return $this->property;
    }
}
