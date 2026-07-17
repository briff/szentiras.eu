<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SzentirasHu\Data\ProtestantBookNames;
use SzentirasHu\Data\UsxCodes;

class ProtestantBookNamesTest extends TestCase
{
    public function test_both_translations_cover_all_66_protestant_books(): void
    {
        foreach (['RUF', 'KG'] as $translationAbbrev) {
            $this->assertCount(66, ProtestantBookNames::NAMES[$translationAbbrev], "{$translationAbbrev} should have 39 + 27 books");
        }
    }

    public function test_every_usx_code_is_valid_and_every_name_is_non_empty(): void
    {
        $validUsxCodes = UsxCodes::allUsx();
        foreach (ProtestantBookNames::NAMES as $translationAbbrev => $names) {
            foreach ($names as $usxCode => $name) {
                $this->assertContains($usxCode, $validUsxCodes, "Unknown USX code {$usxCode} in {$translationAbbrev}");
                $this->assertNotSame('', trim($name), "Empty name for {$usxCode} in {$translationAbbrev}");
            }
        }
    }

    public function test_get_name_returns_protestant_names_and_null_for_unknown(): void
    {
        $this->assertSame('Mózes első könyve', ProtestantBookNames::getName('RUF', 'GEN'));
        $this->assertSame('Ésaiás próféta könyve', ProtestantBookNames::getName('KG', 'ISA'));
        $this->assertSame('Máté evangéliuma', ProtestantBookNames::getName('RUF', 'MAT'));
        $this->assertNull(ProtestantBookNames::getName('SZIT', 'GEN'));
        $this->assertNull(ProtestantBookNames::getName('RUF', 'NONEXISTENT'));
    }
}
