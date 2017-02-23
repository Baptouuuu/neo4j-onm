<?php
declare(strict_types = 1);

namespace Tests\Innmind\Neo4j\ONM;

use Innmind\Neo4j\ONM\{
    Metadatas,
    Metadata\Alias,
    Metadata\ClassName,
    Metadata\EntityInterface
};
use PHPUnit\Framework\TestCase;

class MetadatasTest extends TestCase
{
    public function testAdd()
    {
        $m = new Metadatas;

        $this->assertSame(0, $m->all()->size());
        $e = $this->createMock(EntityInterface::class);
        $e
            ->method('alias')
            ->willReturn(new Alias('foo'));
        $e
            ->method('class')
            ->willReturn(new ClassName('bar'));

        $this->assertSame($m, $m->register($e));
        $this->assertSame($e, $m->get('foo'));
        $this->assertSame($e, $m->get('bar'));
        $this->assertSame(1, $m->all()->size());
    }
}
