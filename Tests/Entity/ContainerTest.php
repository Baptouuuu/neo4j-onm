<?php
declare(strict_types = 1);

namespace Innmind\Neo4j\ONM\Tests\Entity;

use Innmind\Neo4j\ONM\{
    Entity\Container,
    IdentityInterface
};

class ContainerTest extends \PHPUnit_Framework_TestCase
{
    public function testInterface()
    {
        $c = new Container;
        $i = $this->getMock(IdentityInterface::class);

        $this->assertSame(0, $c->state(Container::STATE_MANAGED)->size());
        $this->assertSame(0, $c->state(Container::STATE_NEW)->size());
        $this->assertSame(0, $c->state(Container::STATE_TO_BE_REMOVED)->size());
        $this->assertSame(0, $c->state(Container::STATE_REMOVED)->size());
        $this->assertSame(
            $c,
            $c->push($i, $e = new \stdClass, Container::STATE_NEW)
        );
        $this->assertSame(0, $c->state(Container::STATE_MANAGED)->size());
        $this->assertSame(1, $c->state(Container::STATE_NEW)->size());
        $this->assertSame(0, $c->state(Container::STATE_TO_BE_REMOVED)->size());
        $this->assertSame(0, $c->state(Container::STATE_REMOVED)->size());
        $this->assertSame(Container::STATE_NEW, $c->stateFor($i));
        $this->assertSame($e, $c->get($i));
        $this->assertSame($c, $c->detach($i));
        $this->assertSame(0, $c->state(Container::STATE_MANAGED)->size());
        $this->assertSame(0, $c->state(Container::STATE_NEW)->size());
        $this->assertSame(0, $c->state(Container::STATE_TO_BE_REMOVED)->size());
        $this->assertSame(0, $c->state(Container::STATE_REMOVED)->size());
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\IdentityNotManagedException
     */
    public function testThrowWhenGettingStateForNotManagedIdentity()
    {
        (new Container)->stateFor($this->getMock(IdentityInterface::class));
    }

    /**
     * @expectedException Innmind\Neo4j\ONM\Exception\IdentityNotManagedException
     */
    public function testThrowWhenGettingEntityForNotManagedEntity()
    {
        (new Container)->get($this->getMock(IdentityInterface::class));
    }
}
