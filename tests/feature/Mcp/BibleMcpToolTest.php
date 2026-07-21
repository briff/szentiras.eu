<?php

namespace SzentirasHu\Test\Mcp;

use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Mcp\Servers\BibleServer;
use SzentirasHu\Mcp\Tools\GetVersesTool;
use SzentirasHu\Mcp\Tools\ListTranslationsTool;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

/**
 * The MCP server exists so that an agent quotes the translation of the user's own
 * tradition. These tests pin that behaviour down: the right translation is chosen, it is
 * always labelled, and anything unrecognised fails loudly instead of silently answering
 * from another tradition.
 */
class BibleMcpToolTest extends FastDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The seeder creates both test translations with the same placeholder denomination;
        // give them distinct traditions so the denomination behaviour is observable.
        Translation::where('id', 1001)->update(['denom' => 'katolikus']);
        Translation::where('id', 1002)->update(['denom' => 'protestáns']);

        config(['settings.enabledTranslations' => [1001, 1002]]);
        config(['settings.mcpTranslationAbbrev' => null]);

        Cache::flush();
    }

    public function test_falls_back_to_the_site_default_translation(): void
    {
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3'])
            ->assertOk()
            ->assertSee('"abbrev":"TESTTRANS"')
            ->assertSee('"denomination":"katolikus"');
    }

    public function test_configured_preference_overrides_the_site_default(): void
    {
        config(['settings.mcpTranslationAbbrev' => 'TESTTRANS2']);

        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3'])
            ->assertOk()
            ->assertSee('"abbrev":"TESTTRANS2"')
            ->assertSee('"denomination":"protestáns"');
    }

    public function test_explicit_argument_overrides_the_configured_preference(): void
    {
        config(['settings.mcpTranslationAbbrev' => 'TESTTRANS2']);

        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3', 'translation' => 'TESTTRANS'])
            ->assertOk()
            ->assertSee('"abbrev":"TESTTRANS"')
            ->assertSee('"denomination":"katolikus"');
    }

    public function test_translation_abbreviation_is_case_insensitive(): void
    {
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3', 'translation' => 'testtrans2'])
            ->assertOk()
            ->assertSee('"abbrev":"TESTTRANS2"');
    }

    public function test_every_response_labels_the_translation_used(): void
    {
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3'])
            ->assertOk()
            ->assertSee(['"translation"', '"abbrev"', '"name"', '"denomination"']);
    }

    public function test_returns_the_verbatim_verse_text(): void
    {
        // The seeder stores a predictable body for each verse.
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3'])
            ->assertOk()
            ->assertSee('verse 1001002003');
    }

    public function test_unknown_translation_errors_instead_of_falling_back(): void
    {
        // The wrong-tradition failure mode: it must not quietly answer from the default.
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3', 'translation' => 'XYZ'])
            ->assertHasErrors()
            ->assertSee('Unknown translation')
            ->assertDontSee('"abbrev":"TESTTRANS"');
    }

    public function test_unknown_translation_error_lists_the_valid_options(): void
    {
        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3', 'translation' => 'XYZ'])
            ->assertHasErrors()
            ->assertSee(['katolikus', 'protestáns', 'TESTTRANS', 'TESTTRANS2']);
    }

    public function test_malformed_reference_returns_a_clear_error(): void
    {
        BibleServer::tool(GetVersesTool::class, ['reference' => 'nonsense!!'])
            ->assertHasErrors()
            ->assertSee('Could not parse the reference');
    }

    public function test_list_translations_reports_denominations_and_active_default(): void
    {
        BibleServer::tool(ListTranslationsTool::class)
            ->assertOk()
            ->assertSee(['"abbrev":"TESTTRANS"', '"abbrev":"TESTTRANS2"'])
            ->assertSee(['"denomination":"katolikus"', '"denomination":"protestáns"'])
            ->assertSee('"activeDefault":"TESTTRANS"');
    }

    public function test_list_translations_marks_only_the_active_translation_as_default(): void
    {
        config(['settings.mcpTranslationAbbrev' => 'TESTTRANS2']);

        BibleServer::tool(ListTranslationsTool::class)
            ->assertOk()
            ->assertSee('"activeDefault":"TESTTRANS2"')
            ->assertSee('"abbrev":"TESTTRANS2","name":"Translation Name 2","denomination":"protestáns","language":"hu","isActiveDefault":true')
            ->assertSee('"abbrev":"TESTTRANS","name":"Translation Name 1","denomination":"katolikus","language":"hu","isActiveDefault":false');
    }

    public function test_translations_without_books_are_not_offered(): void
    {
        // GNT is enabled site-wide for the separate Greek reader, but has no books in the
        // tables TextService reads, so such a translation must not be advertised or quotable.
        Verse::whereIn('book_id', Book::where('translation_id', 1002)->pluck('id'))->delete();
        Book::where('translation_id', 1002)->delete();
        Cache::flush();

        BibleServer::tool(ListTranslationsTool::class)
            ->assertOk()
            ->assertSee('"abbrev":"TESTTRANS"')
            ->assertDontSee('"abbrev":"TESTTRANS2"');
    }

    public function test_translation_without_books_cannot_be_quoted(): void
    {
        Verse::whereIn('book_id', Book::where('translation_id', 1002)->pluck('id'))->delete();
        Book::where('translation_id', 1002)->delete();
        Cache::flush();

        BibleServer::tool(GetVersesTool::class, ['reference' => 'Ter 2,3', 'translation' => 'TESTTRANS2'])
            ->assertHasErrors()
            ->assertSee('Unknown translation');
    }
}
