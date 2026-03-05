<?php

namespace SzentirasHu\Test;

use App;
use Mockery;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Data\UsxCodes;

class UsxCodesTest extends TestCase
{

    public function testNewTestament(): void
    {
        $newTestamentBooks = UsxCodes::newTestamentUsx();
        $this->assertCount(
            27,
            $newTestamentBooks,
            "The New Testament books count should be 27."
        );
    }

    public function testOldTestament(): void
    {
        $oldTestamentBooks = UsxCodes::oldTestamentUsx();
        $this->assertCount(
            54,
            $oldTestamentBooks,
            "The Old Testament books count should be 54."
        );
    }

    public function testAllUsx(): void
    {
        $allUsx = UsxCodes::allUsx();
        $this->assertCount(
            81,
            $allUsx,
            "All Holy Scripture books count should be 81."
        );
    }

    public function testGetUsxFromBookAbbrevAndTranslation(): void
    {
        $this->checkReturnedkUsxCode('default', 'Ter', 'GEN');
        $this->checkReturnedkUsxCode('SZIT', 'Ter', 'GEN');
        $this->checkReturnedkUsxCode('default', '1Móz', 'GEN');
        $this->checkReturnedkUsxCode('default', '1móz', 'GEN');
        $this->checkReturnedkUsxCode('default', 'Sirák', 'SIR');
        $this->checkReturnedkUsxCode('RUF', 'Sir', 'LAM');
        $this->checkReturnedkUsxCode('RUF', 'sir', 'LAM');
        $this->checkReturnedkUsxCode('RUF', 'Sirák', null);
        $this->checkReturnedkUsxCode('SZIT', 'Sir', 'SIR');
        $this->checkReturnedkUsxCode('STL', 'Sir', 'SIR');
        $this->checkReturnedkUsxCode('STL', 'sir', 'SIR');
        $this->checkReturnedkUsxCode('default', 'Zsuzs', 'SUS');
        $this->checkReturnedkUsxCode('STL', 'Zsuzs', 'SUS');
        $this->checkReturnedkUsxCode('default', 'Jud', 'JUD');
        $this->checkReturnedkUsxCode('default', 'Júd', 'JUD');
        $this->checkReturnedkUsxCode('default', 'Judit', 'JDT');
        $this->checkReturnedkUsxCode('SZIT', 'Jud', 'JDT');
        $this->checkReturnedkUsxCode('SZIT', 'Júd', 'JUD');
        $this->checkReturnedkUsxCode('SZIT', 'Jo', 'JOL');
        $this->checkReturnedkUsxCode('SZIT', 'Job', 'JOB');
    }

    public function testGetUsxFromBookAbbrevAndTranslationEdgeCases(): void
    {
        $this->checkReturnedkUsxCode('noSuchTranslation', 'Ter', 'GEN');
        $this->checkReturnedkUsxCode('default', 'noSuchAbbrev', null);
        $this->checkReturnedkUsxCode('noSuchTranslation', 'noSuchAbbrev', null);
    }

    public function testGetPreferredAbbreviation(): void
    {
        $this->checkReturnedAbbrev('SZIT', 'GEN', 'Ter');
        $this->checkReturnedAbbrev('RUF', 'GEN', '1Móz');

        $this->checkReturnedAbbrev('default', 'SIR', 'Sirák');
        $this->checkReturnedAbbrev('SZIT', 'SIR', 'Sir');
        $this->checkReturnedAbbrev('RUF', 'SIR', null);
        $this->checkReturnedAbbrev('KNB', 'SIR', 'Sirák');

        $this->checkReturnedAbbrev('KNB', 'LAM', 'Siralm');
        $this->checkReturnedAbbrev('RUF', 'LAM', 'Sir');
        $this->checkReturnedAbbrev('SZIT', 'LAM', 'Siralm');
    }

    public function testGetPreferredAbbreviationEdgeCases(): void
    {
        $this->checkReturnedAbbrev('noSuchTranslation', 'GEN', 'Ter');
        $this->checkReturnedAbbrev('default', 'noSuchUsx', null);
        $this->checkReturnedAbbrev('noSuchTranslation', 'noSuchUsx', null);
    }

    public function testGetUsxFromBookAbbrevAndTranslationCaseInsensitive(): void
    {
        // Test uppercase versions that are not in mapping
        $this->checkReturnedkUsxCode('default', '1KIR', '1KI');
        $this->checkReturnedkUsxCode('default', 'TER', 'GEN');
        $this->checkReturnedkUsxCode('default', '1MÓZ', 'GEN');
        $this->checkReturnedkUsxCode('default', 'SIRÁK', 'SIR');
        // Test mixed case
        $this->checkReturnedkUsxCode('default', 'TeR', 'GEN');
        $this->checkReturnedkUsxCode('default', '1KiR', '1KI');
        // Ensure translation-specific case insensitivity
        $this->checkReturnedkUsxCode('RUF', 'SIR', 'LAM');
        $this->checkReturnedkUsxCode('RUF', 'sIr', 'LAM');
        $this->checkReturnedkUsxCode('SZIT', 'SIR', 'SIR');
        $this->checkReturnedkUsxCode('SZIT', 'sIr', 'SIR');
    }

    private function checkReturnedkUsxCode(string $translation, string $bookAbbrev, ?string $expectedUsxCode): void
    {
        $this->assertEquals(
            $expectedUsxCode,
            UsxCodes::getUsxFromBookAbbrevAndTranslation($bookAbbrev, $translation),
            "The book abbreviation $bookAbbrev should be mapped to $expectedUsxCode for $translation."
        );
    }

    private function checkReturnedAbbrev(string $translation, string $usxCode, ?string $expectedAbbrev): void
    {
        $this->assertEquals(
            $expectedAbbrev,
            UsxCodes::getPreferredAbbreviation($usxCode, $translation),
            "The usx $usxCode should have the preferred abbreviation $expectedAbbrev for $translation."
        );
    }
}
