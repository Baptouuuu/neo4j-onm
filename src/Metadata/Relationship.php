<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Metadata;

use Innmind\Neo4j\ONM\EntityFactory\RelationshipFactory;

final class Relationship extends AbstractEntity implements Entity
{
    private $type;
    private $startNode;
    private $endNode;

    public function __construct(
        ClassName $class,
        Identity $id,
        Repository $repository,
        RelationshipType $type,
        RelationshipEdge $startNode,
        RelationshipEdge $endNode
    ) {
        parent::__construct(
            $class,
            $id,
            $repository,
            new Factory(RelationshipFactory::class)
        );

        $this->type = $type;
        $this->startNode = $startNode;
        $this->endNode = $endNode;
    }

    public function type(): RelationshipType
    {
        return $this->type;
    }

    public function startNode(): RelationshipEdge
    {
        return $this->startNode;
    }

    public function endNode(): RelationshipEdge
    {
        return $this->endNode;
    }
}
