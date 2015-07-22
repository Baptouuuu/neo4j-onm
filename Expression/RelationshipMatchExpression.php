<?php

namespace Innmind\Neo4j\ONM\Expression;

use Innmind\Neo4j\ONM\ExpressionInterface;

class RelationshipMatchExpression implements ParametrableExpressionInterface, VariableAwareInterface, ExpressionInterface
{
    use MatcherTrait;

    const DIRECTION_RIGHT = 'right';
    const DIRECTION_LEFT = 'left';
    const DIRECTION_NONE = 'none';

    protected $node;
    protected $direction = 'right';

    /**
     * @param string $variable
     * @param string $alias
     * @param array $params
     */
    public function __construct($variable = null, $alias = null, array $params = null)
    {
        if (!empty($variable) && empty($alias)) {
            throw new \LogicException(
                'A relationship match can\'t be specified without the entity alias'
            );
        }

        if (!empty($params) && empty($variable)) {
            throw new \LogicException(
                'Parameters to be matched can\'t be specified without a variable'
            );
        }

        $this->variable = (string) $variable;
        $this->alias = (string) $alias;
        $this->params = $params;

        if ($params !== null) {
            $this->references = [];

            foreach ($params as $key => $value) {
                $this->references[$key] = sprintf(
                    '%s.%s',
                    $variable,
                    $key
                );
            }
        }

        $this->node = new NodeMatchExpression;
    }

    /**
     * Set the node matcher that will be rendered on the right relationship's side
     *
     * @param NodeMatchExpression $node
     *
     * @return RelationshipMatchExpression self
     */
    public function setNodeMatcher(NodeMatchExpression $node)
    {
        $this->node = $node;

        return $this;
    }

    /**
     * Set the relationship direction
     *
     * @param string $direction
     *
     * @return RelationshipMatchExpression self
     */
    public function setDirection($direction)
    {
        if (!in_array(
            (string) $direction,
            [self::DIRECTION_LEFT, self::DIRECTION_RIGHT, self::DIRECTION_NONE],
            true
        )) {
            throw new \InvalidArgumentException(
                'Relationship direction can be either right or left'
            );
        }

        $this->direction = (string) $direction;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        $string = $this->direction === self::DIRECTION_LEFT ? '<-' : '-';
        $match = '';

        if ($this->hasVariable()) {
            $match = $this->variable;
        }

        if ($this->hasAlias()) {
            $match .= sprintf(
                ':%s',
                $this->alias
            );
        }

        if ($this->hasParameters()) {
            $match .= ' {';
            $props = [];

            foreach ($this->params as $key => $value) {
                $props[] = sprintf(
                    '%s: {%s}.%s',
                    $key,
                    $this->getParametersKey(),
                    $key
                );
            }

            $match .= implode(', ', $props);
            $match .= '}';
        }

        if (!empty($match)) {
            $string .= sprintf(
                '[%s]',
                $match
            );
        }

        $string .= $this->direction === self::DIRECTION_RIGHT ? '->' : '-';
        $string .= (string) $this->node;

        return $string;
    }

    /**
     * Return the node matcher
     *
     * @return NodeMatchExpression
     */
    public function getNodeMatcher()
    {
        return $this->node;
    }
}