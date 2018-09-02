<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM;

use Innmind\Neo4j\ONM\{
    Metadata\Aggregate,
    Metadata\Relationship,
};
use Innmind\Neo4j\DBAL\Connection;
use Innmind\EventBus\{
    EventBusInterface,
    NullEventBus,
};
use Innmind\CommandBus\CommandBusInterface;
use Innmind\Reflection\{
    ExtractionStrategyInterface,
    InjectionStrategyInterface,
    InstanciatorInterface,
};
use Innmind\Immutable\{
    MapInterface,
    Map,
    SetInterface,
    Set,
};
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @param  SetInterface<string>|null $additionalTypes
 * @param  MapInterface<string, Generator>|null $additionalGenerators
 * @param  MapInterface<Identity, Repository>|null $repositories
 * @param  SetInterface<EntityFactory>|null $entityFactories
 * @param  MapInterface<string, EntityTranslator>|null $resultTranslators
 * @param  MapInterface<string, IdentityMatchTranslator>|null $identityMatchTranslators
 * @param  MapInterface<string, MatchTranslator>|null $matchTranslators
 * @param  MapInterface<string, SpecificationTranslator>|null $specificationTranslators
 * @param  MapInterface<string, MetadataFactory>|null $metadataFactories
 * @param  MapInterface<string, DataExtractor>|null $dataExtractors
 */
function bootstrap(
    Connection $connection,
    array $metas,
    SetInterface $additionalTypes = null,
    MapInterface $additionalGenerators = null,
    ExtractionStrategyInterface $extractionStrategy = null,
    InjectionStrategyInterface $injectionStrategy = null,
    InstanciatorInterface $instanciator = null,
    EventBusInterface $eventBus = null,
    MapInterface $repositories = null,
    Persister $persister = null,
    ConfigurationInterface $configuration = null,
    SetInterface $entityFactories = null,
    MapInterface $resultTranslators = null,
    MapInterface $identityMatchTranslators = null,
    MapInterface $matchTranslators = null,
    MapInterface $specificationTranslators = null,
    MapInterface $metadataFactories = null,
    MapInterface $dataExtractors = null
): array {
    $eventBus = $eventBus ?? new NullEventBus;

    $types = new Types(...($additionalTypes ?? []));

    $configuration = $configuration ?? new Configuration;
    $resultTranslators = $resultTranslators ?? (new Map('string', Translation\EntityTranslator::class))
        ->put(Aggregate::class, new Translation\Result\AggregateTranslator)
        ->put(Relationship::class, new Translation\Result\RelationshipTranslator);
    $identityMatchTranslators = $identityMatchTranslators ?? (new Map('string', Translation\IdentityMatchTranslator::class))
        ->put(Aggregate::class, new Translation\IdentityMatch\AggregateTranslator)
        ->put(Relationship::class, new Translation\IdentityMatch\RelationshipTranslator);
    $matchTranslators = $matchTranslators ?? (new Map('string', Translation\MatchTranslator::class))
        ->put(Aggregate::class, new Translation\Match\AggregateTranslator)
        ->put(Relationship::class, new Translation\Match\RelationshipTranslator);
    $specificationTranslators = $specificationTranslators ?? (new Map('string', Translation\SpecificationTranslator::class))
        ->put(Aggregate::class, new Translation\Specification\AggregateTranslator)
        ->put(Relationship::class, new Translation\Specification\RelationshipTranslator);
    $metadataFactories = $metadataFactories ?? (new Map('string', MetadataFactory::class))
        ->put('aggregate', new MetadataFactory\AggregateFactory($types))
        ->put('relationship', new MetadataFactory\RelationshipFactory($types));
    $dataExtractors = $dataExtractors ?? (new Map('string', Entity\DataExtractor::class))
        ->put(Aggregate::class, new Entity\DataExtractor\AggregateExtractor($extractionStrategy))
        ->put(Relationship::class, new Entity\DataExtractor\RelationshipExtractor($extractionStrategy));

    $identityGenerators = new Identity\Generators($additionalGenerators);

    $entityFactories = $entityFactories ?? Set::of(
        EntityFactory::class,
        new EntityFactory\AggregateFactory(
            $instanciator,
            $injectionStrategy
        ),
        new EntityFactory\RelationshipFactory(
            $identityGenerators,
            $instanciator,
            $injectionStrategy
        )
    );

    $metadatas = Metadatas::build(
        new MetadataBuilder(
            $types,
            $metadataFactories,
            $configuration
        ),
        $metas
    );

    $entityChangeset = new Entity\ChangesetComputer;
    $dataExtractor = new Entity\DataExtractor\DataExtractor(
        $metadatas,
        $dataExtractors
    );

    $persister = $persister ?? new Persister\DelegationPersister(
        new Persister\InsertPersister(
            $entityChangeset,
            $eventBus,
            $dataExtractor,
            $metadatas
        ),
        new Persister\UpdatePersister(
            $entityChangeset,
            $eventBus,
            $dataExtractor,
            $metadatas
        ),
        new Persister\RemovePersister(
            $entityChangeset,
            $eventBus,
            $metadatas
        )
    );

    $entityContainer = new Entity\Container;

    $unitOfWork = new UnitOfWork(
        $connection,
        $entityContainer,
        new EntityFactory\EntityFactory(
            new Translation\ResultTranslator($resultTranslators),
            $identityGenerators,
            new EntityFactory\Resolver(...$entityFactories),
            $entityContainer
        ),
        new Translation\IdentityMatch\DelegationTranslator($identityMatchTranslators),
        $metadatas,
        $persister,
        $identityGenerators
    );

    $manager = new Manager\Manager(
        $unitOfWork,
        $metadatas,
        new RepositoryFactory(
            $unitOfWork,
            new Translation\Match\DelegationTranslator($matchTranslators),
            new Translation\Specification\DelegationTranslator($specificationTranslators),
            $repositories
        ),
        $identityGenerators
    );

    return [
        'manager' => $manager,
        'commandBus' => [
            'clear_domain_events' => static function(CommandBusInterface $bus) use ($entityContainer): CommandBusInterface {
                return new CommandBus\ClearDomainEvents($bus, $entityContainer);
            },
            'dispatch_domain_events' => static function(CommandBusInterface $bus) use ($eventBus, $entityContainer): CommandBusInterface {
                return new CommandBus\DispatchDomainEvents($bus, $eventBus, $entityContainer);
            },
            'flush' => static function(CommandBusInterface $bus) use ($manager): CommandBusInterface {
                return new CommandBus\Flush($bus, $manager);
            },
            'transaction' => static function(CommandBusInterface $bus) use ($manager): CommandBusInterface {
                return new CommandBus\Transaction($bus, $manager);
            },
        ],
    ];
}