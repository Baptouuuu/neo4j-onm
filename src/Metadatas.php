<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM;

use Innmind\Neo4j\ONM\Metadata\Entity;
use Innmind\Immutable\{
    MapInterface,
    Map,
};

final class Metadatas
{
    private $mapping;

    public function __construct(Entity ...$metas)
    {
        $this->mapping = new Map('string', Entity::class);

        foreach ($metas as $meta) {
            $this->register($meta);
        }
    }

    public static function build(
        MetadataBuilder $builder,
        array $metas
    ): self {
        return $builder->inject($metas)->container();
    }

    /**
     * Return the metadata for an entity
     */
    public function __invoke(string $class): Entity
    {
        return $this->mapping->get($class);
    }

    /**
     * Register a new entity metadata
     */
    private function register(Entity $meta): self
    {
        $this->mapping = $this->mapping->put((string) $meta->class(), $meta);

        return $this;
    }
}
