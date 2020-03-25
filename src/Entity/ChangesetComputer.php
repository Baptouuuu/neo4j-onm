<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Entity;

use Innmind\Neo4j\ONM\{
    Identity,
    Exception\InvalidArgumentException,
};
use Innmind\Immutable\Map;

final class ChangesetComputer
{
    private Map $sources;

    public function __construct()
    {
        $this->sources = Map::of(Identity::class, Map::class);
    }

    /**
     * Use the given collection as the original data for the given entity
     *
     * @param Map<string, mixed> $source
     */
    public function use(Identity $identity, Map $source): self
    {
        if (
            (string) $source->keyType() !== 'string' ||
            (string) $source->valueType() !== 'mixed'
        ) {
            throw new \TypeError('Argument 2 must be of type Map<string, mixed>');
        }

        $this->sources = $this->sources->put($identity, $source);

        return $this;
    }

    /**
     * Return the collection of data that has changed for the given identity
     *
     * @param Map<string, mixed> $target
     *
     * @return Map<string, mixed>
     */
    public function compute(Identity $identity, Map $target): Map
    {
        if (
            (string) $target->keyType() !== 'string' ||
            (string) $target->valueType() !== 'mixed'
        ) {
            throw new \TypeError('Argument 2 must be of type Map<string, mixed>');
        }

        if (!$this->sources->contains($identity)) {
            return $target;
        }

        $source = $this->sources->get($identity);

        return $this->diff($source, $target);
    }

    private function diff(
        Map $source,
        Map $target
    ): Map {
        $changeset = $target->filter(static function(string $property, $value) use ($source): bool {
            if (
                !$source->contains($property) ||
                $value !== $source->get($property)
            ) {
                return true;
            }

            return false;
        });

        return $source
            ->filter(static function(string $property) use ($target): bool {
                return !$target->contains($property);
            })
            ->reduce(
                $changeset,
                static function(Map $carry, string $property) use ($target): Map {
                    return $carry->put($property, null);
                }
            )
            ->map(function(string $property, $value) use ($source, $target) {
                if (!$value instanceof Map) {
                    return $value;
                }

                return $this->diff(
                    $source->get($property),
                    $target->get($property)
                );
            })
            ->filter(static function(string $property, $value) {
                if (!$value instanceof Map) {
                    return true;
                }

                return $value->size() !== 0;
            });
    }
}
