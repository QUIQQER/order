<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Comments;

class CommentsUnitTest extends TestCase
{
    public function testAddCommentSerializeAndUnserialize(): void
    {
        $Comments = new Comments();
        $Comments->addComment('a');
        $Comments->addComment('<b>b</b>', 123456);

        $this->assertFalse($Comments->isEmpty());
        $this->assertCount(2, $Comments->toArray());

        $serialized = $Comments->serialize();
        $this->assertJson($serialized);

        $Unserialized = Comments::unserialize($serialized);
        $this->assertCount(2, $Unserialized->toArray());
        $this->assertSame('<b>b</b>', $Unserialized->toArray()[1]['message']);
        $this->assertSame(123456, $Unserialized->toArray()[1]['time']);
    }

    public function testClearAndImportAndSort(): void
    {
        $A = new Comments();
        $A->addComment('late', 20);
        $A->addComment('early', 10);
        $A->sort();

        $this->assertSame('early', $A->toArray()[0]['message']);
        $this->assertSame('late', $A->toArray()[1]['message']);

        $B = new Comments();
        $B->addComment('middle', 15);

        $A->import($B);
        $this->assertCount(3, $A->toArray());
        $this->assertSame('middle', $A->toArray()[1]['message']);

        $A->clear();
        $this->assertTrue($A->isEmpty());
    }
}
