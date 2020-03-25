<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Persister;

use Innmind\Neo4j\ONM\{
    Persister,
    Entity\Container,
    Entity\Container\State,
    Entity\ChangesetComputer,
    Identity,
    Event\EntityAboutToBeRemoved,
    Event\EntityRemoved,
    Metadatas,
    Metadata\Relationship,
    Metadata\Aggregate,
    Metadata\Aggregate\Child,
};
use Innmind\Neo4j\DBAL\{
    Connection,
    Query,
};
use Innmind\EventBus\EventBus;
use Innmind\Immutable\{
    Map,
    Sequence,
    Str,
};
use function Innmind\Immutable\unwrap;

final class RemovePersister implements Persister
{
    private ChangesetComputer $changeset;
    private EventBus $dispatch;
    private Metadatas $metadata;
    private Str $name;
    private ?Sequence $variables = null;

    public function __construct(
        ChangesetComputer $changeset,
        EventBus $dispatch,
        Metadatas $metadata
    ) {
        $this->changeset = $changeset;
        $this->dispatch = $dispatch;
        $this->metadata = $metadata;
        $this->name = Str::of('e%s');
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(Connection $connection, Container $container): void
    {
        $entities = $container->state(State::toBeRemoved());
        $entities->foreach(function(Identity $identity, object $object): void {
            ($this->dispatch)(new EntityAboutToBeRemoved($identity, $object));
        });

        if ($entities->size() === 0) {
            return;
        }

        $connection->execute($this->queryFor($entities));

        $entities->foreach(function(Identity $identity, object $object) use ($container): void {
            $container->push($identity, $object, State::removed());
            $this->changeset->use($identity, Map::of('string', 'mixed')); //in case the identity is reused later on
            ($this->dispatch)(new EntityRemoved($identity, $object));
        });
    }

    /**
     * Build the query to delete all entities at once
     *
     * @param Map<Identity, object> $entities
     */
    private function queryFor(Map $entities): Query
    {
        $query = new Query\Query;
        $this->variables = Sequence::strings();
        $partitions = $entities->partition(function(Identity $identity, object $entity): bool {
            $meta = ($this->metadata)(\get_class($entity));

            return $meta instanceof Relationship;
        });

        $query = $partitions
            ->get(true)
            ->reduce(
                $query,
                function(Query $carry, Identity $identity, object $entity): Query {
                    return $this->matchRelationship($identity, $entity, $carry);
                }
            );
        $query = $partitions
            ->get(false)
            ->reduce(
                $query,
                function(Query $carry, Identity $identity, object $entity): Query {
                    return $this->matchAggregate($identity, $entity, $carry);
                }
            );
        $query = $this
            ->variables
            ->reduce(
                $query,
                static function(Query $carry, string $variable): Query {
                    return $carry->delete($variable);
                }
            );
        $this->variables = null;

        return $query;
    }

    /**
     * Add clause to match the relationship we want to delete
     */
    private function matchRelationship(
        Identity $identity,
        object $entity,
        Query $query
    ): Query {
        $meta = ($this->metadata)(\get_class($entity));
        $name = $this->name->sprintf(\md5($identity->value()));
        $this->variables = $this->variables->add($name->toString());

        return $query
            ->match()
            ->linkedTo()
            ->through(
                (string) $meta->type(),
                $name->toString(),
            )
            ->withProperty(
                $meta->identity()->property(),
                $name
                    ->prepend('{')
                    ->append('_identity}')
                    ->toString(),
            )
            ->withParameter(
                $name->append('_identity')->toString(),
                $identity->value()
            );
    }

    /**
     * Add clause to match the node we want to delete and all of its children
     */
    private function matchAggregate(
        Identity $identity,
        object $entity,
        Query $query
    ): Query {
        $meta = ($this->metadata)(\get_class($entity));
        $name = $this->name->sprintf(\md5($identity->value()));
        $this->variables = $this->variables->add($name->toString());

        $query = $query
            ->match(
                $name->toString(),
                ...unwrap($meta->labels()),
            )
            ->withProperty(
                $meta->identity()->property(),
                $name
                    ->prepend('{')
                    ->append('_identity}')
                    ->toString()
            )
            ->withParameter(
                $name->append('_identity')->toString(),
                $identity->value()
            );

        return $meta
            ->children()
            ->reduce(
                $query,
                function(Query $carry, string $property, Child $child) use ($name): Query {
                    $carry = $carry
                        ->match($name->toString())
                        ->linkedTo(
                            $childName = $name
                                ->append('_')
                                ->append($child->relationship()->property())
                                ->append('_')
                                ->append($child->relationship()->childProperty())
                                ->toString(),
                            ...unwrap($child->labels()),
                        )
                        ->through(
                            (string) $child->relationship()->type(),
                            $relName = $name
                                ->append('_')
                                ->append($child->relationship()->property())
                                ->toString(),
                        );
                    $this->variables = $this->variables
                        ->add($childName)
                        ->add($relName);

                    return $carry;
                }
            );
    }
}
