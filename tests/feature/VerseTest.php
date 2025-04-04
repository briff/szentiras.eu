<?php

namespace SzentirasHu\Test;

use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Test\Common\TestCase;

class VerseTest extends TestCase {

	public function testType()
	{
        $verse = new Verse();
        $verse->tip=901;
            $verse->trans = 4;
        $this->assertEquals('text', $verse->getType());

        $verse = new Verse();
        $verse->tip=301;
        $verse->trans = 3;

        $this->assertEquals('heading0', $verse->getType());

        $verse = new Verse();
        $verse->tip=9999;
            $verse->trans = 4;

        $this->assertEquals('unknown', $verse->getType());
	}

}