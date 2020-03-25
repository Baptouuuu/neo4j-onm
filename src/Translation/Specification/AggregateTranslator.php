<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Translation\Specification;

use Innmind\Neo4j\ONM\{
    Translation\SpecificationTranslator,
    Translation\Specification\Visitor\PropertyMatch\AggregateVisitor as AggregatePropertyMatchVisitor,
    Translation\Specification\Visitor\Cypher\AggregateVisitor as AggregateCypherVisitor,
    Metadata\Aggregate\Child,
    Metadata\Entity,
    IdentityMatch,
    Exception\SpecificationNotApplicableAsPropertyMatch,
};
use Innmind\Neo4j\DBAL\Query\Query;
use Innmind\Immutable\{
    Map,
    Set,
    Str,
};
use function Innmind\Immutable\unwrap;
use Innmind\Specification\Specification;

final class AggregateTranslator implements SpecificationTranslator
{
    /**
     * {@inheritdoc}
     */
    public function __invoke(
        Entity $meta,
        Specification $specification
    ): IdentityMatch {
        $variables = Set::strings();

        try {
            $mapping = (new AggregatePropertyMatchVisitor($meta))($specification);

            $query = $this
                ->addProperties(
                    (new Query)->match(
                        'entity',
                        ...unwrap($meta->labels()),
                    ),
                    'entity',
                    $mapping
                )
                ->with('entity');

            $meta
                ->children()
                ->foreach(function(
                    string $property,
                    Child $child
                ) use (
                    &$query,
                    $mapping,
                    &$variables
                ): void {
                    $relName = Str::of('entity_')->append($property);
                    $childName = $relName
                        ->append('_')
                        ->append($child->relationship()->childProperty());
                    $variables = $variables
                        ->add($relName->toString())
                        ->add($childName->toString());

                    $query = $this->addProperties(
                        $this
                            ->addProperties(
                                $query
                                    ->match('entity')
                                    ->linkedTo(
                                        $childName->toString(),
                                        ...unwrap($child->labels()),
                                    ),
                                $childName->toString(),
                                $mapping
                            )
                            ->through(
                                (string) $child->relationship()->type(),
                                $relName->toString(),
                                'left'
                            ),
                        $relName->toString(),
                        $mapping
                    );
                });
        } catch (SpecificationNotApplicableAsPropertyMatch $e) {
            $query = (new Query)
                ->match(
                    'entity',
                    ...unwrap($meta->labels()),
                )
                ->with('entity');

            $meta
                ->children()
                ->foreach(function(
                    string $property,
                    Child $child
                ) use (
                    &$query,
                    &$variables
                ): void {
                    $relName = Str::of('entity_')->append($property);
                    $childName = $relName
                        ->append('_')
                        ->append($child->relationship()->childProperty());
                    $variables = $variables
                        ->add($relName->toString())
                        ->add($childName->toString());

                    $query = $query
                        ->match('entity')
                        ->linkedTo(
                            $childName->toString(),
                            ...unwrap($child->labels()),
                        )
                        ->through(
                            (string) $child->relationship()->type(),
                            $relName->toString(),
                            'left'
                        );
                });
            $condition = (new AggregateCypherVisitor($meta))($specification);
            $query = $query->where($condition->cypher());
            $query = $condition->parameters()->reduce(
                $query,
                static function(Query $query, string $key, $value): Query {
                    return $query->withParameter($key, $value);
                }
            );
        }

        return new IdentityMatch(
            $query->return('entity', ...unwrap($variables)),
            Map::of('string', Entity::class)
                ('entity', $meta)
        );
    }

    /**
     * @param Map<string, PropertiesMatch> $mapping
     */
    private function addProperties(
        Query $query,
        string $name,
        Map $mapping
    ): Query {
        if ($mapping->contains($name)) {
            $match = $mapping->get($name);
            $query = $match->properties()->reduce(
                $query,
                static function(Query $query, string $property, string $cypher): Query {
                    return $query->withProperty($property, $cypher);
                }
            );
            $query = $match->parameters()->reduce(
                $query,
                static function(Query $query, string $key, $value): Query {
                    return $query->withParameter($key, $value);
                }
            );
        }

        return $query;
    }
}
