<?php

namespace SzentirasHu\Mcp\Servers;

use Laravel\Mcp\Server;
use SzentirasHu\Mcp\Tools\GetVersesTool;
use SzentirasHu\Mcp\Tools\ListTranslationsTool;

class BibleServer extends Server
{
    protected string $name = 'Szentírás';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Provides verbatim Hungarian Bible text from szentiras.eu.

    Always call `get-verses` instead of quoting scripture from memory: the text returned is
    the exact wording of a specific published translation.

    When you quote a verse, always name the translation (its `abbrev`, e.g. RUF or SZIT).
    The translation this endpoint answers with reflects the user's own tradition, so never
    substitute a different one. Only pass the `translation` argument when the user explicitly
    asks for another translation.

    References use Hungarian notation: a comma separates chapter and verse (`Jn 3,16`), a
    hyphen marks a range (`1Kor 13,4-7`), and a semicolon separates books or chapters (`Jn 1;3`).
    MARKDOWN;

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>|\Laravel\Mcp\Server\Tool>
     */
    protected array $tools = [
        GetVersesTool::class,
        ListTranslationsTool::class,
    ];
}
