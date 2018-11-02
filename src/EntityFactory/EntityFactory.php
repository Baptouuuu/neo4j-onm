<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\EntityFactory;

use Innmind\Neo4j\ONM\{
    Translation\ResultTranslator,
    Identity\Generators,
    Metadata\Entity,
    Entity\Container,
    Entity\Container\State,
};
use Innmind\Neo4j\DBAL\Result;
use Innmind\Immutable\{
    Map,
    Set,
    SetInterface,
    MapInterface,
};

final class EntityFactory
{
    private $translate;
    private $generators;
    private $resolve;
    private $entities;

    public function __construct(
        ResultTranslator $translate,
        Generators $generators,
        Resolver $resolve,
        Container $entities
    ) {
        $this->translate = $translate;
        $this->generators = $generators;
        $this->resolve = $resolve;
        $this->entities = $entities;
    }

    /**
     * Translate the dbal result into a set of entities
     *
     * @param MapInterface<string, Entity> $variables
     *
     * @return SetInterface<object>
     */
    public function __invoke(
        Result $result,
        MapInterface $variables
    ): SetInterface {
        if (
            (string) $variables->keyType() !== 'string' ||
            (string) $variables->valueType() !== Entity::class
        ) {
            throw new \TypeError(sprintf(
                'Argument 2 must be of type MapInterface<string, %s>',
                Entity::class
            ));
        }

        $structuredData = ($this->translate)($result, $variables);
        $entities = new Set('object');

        return $variables
            ->filter(static function(string $variable) use ($structuredData): bool {
                return $structuredData->contains($variable);
            })
            ->reduce(
                new Set('object'),
                function(SetInterface $carry, string $variable, Entity $meta) use ($structuredData): SetInterface {
                    return $structuredData
                        ->get($variable)
                        ->reduce(
                            $carry,
                            function(SetInterface $carry, MapInterface $data) use ($meta): SetInterface {
                                return $carry->add(
                                    $this->makeEntity($meta, $data)
                                );
                            }
                        );
                }
            );
    }

    /**
     * @param MapInterface<string, mixed> $data
     */
    private function makeEntity(Entity $meta, MapInterface $data)
    {
        $identity = $this
            ->generators
            ->get($meta->identity()->type())
            ->for(
                $data->get($meta->identity()->property())
            );

        if ($this->entities->contains($identity)) {
            return $this->entities->get($identity);
        }

        $entity = ($this->resolve)($meta)($identity, $meta, $data);

        $this->entities = $this->entities->push(
            $identity,
            $entity,
            State::managed()
        );

        return $entity;
    }
}
