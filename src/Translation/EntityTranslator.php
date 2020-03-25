<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Translation;

use Innmind\Neo4j\ONM\Metadata\Entity;
use Innmind\Neo4j\DBAL\Result;
use Innmind\Immutable\Set;

interface EntityTranslator
{
    /**
     * Translate the wished variable from the result
     *
     * @return Set<MapInterface<string, mixed>>
     */
    public function __invoke(
        string $variable,
        Entity $meta,
        Result $result
    ): Set;
}
