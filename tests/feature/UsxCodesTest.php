<?php

namespace SzentirasHu\Test;

use App;
use Mockery;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Data\UsxCodes;

class UsxCodesTest extends TestCase {

    public function testNewTestament(): void {
        $newTestamentBooks = UsxCodes::newTestamentUsx();
        $this->assertCount(
            27,
            $newTestamentBooks,
            "The New Testament books count should be 27."
        );
    }

    public function testOldTestament(): void {
        $oldTestamentBooks = UsxCodes::oldTestamentUsx();
        $this->assertCount(
            46,
            $oldTestamentBooks,
            "The Old Testament books count should be 46."
        );
    }

    public function testAllUsx(): void {
        $allUsx = UsxCodes::allUsx();
        $this->assertCount(
            73,
            $allUsx,
            "All Holy Scripture books count should be 73."
        );
    }

    public function testGetUsxFromBookAbbrevAndTranslation(): void {
        $this->assertEquals(
            'GEN',
            UsxCodes::getUsxFromBookAbbrevAndTranslation('Ter'),
            "Ter should be mapped to GEN by default."
        );
    }

} 
