<?php

namespace SzentirasHu\Mcp;

use Exception;

/**
 * Thrown when a requested translation abbreviation does not match any translation
 * that actually serves verse text.
 *
 * Deliberately never falls back to the default translation: silently answering with
 * another tradition's text is the exact failure this MCP server exists to prevent.
 */
class UnknownTranslationException extends Exception
{
    public function __construct(public readonly string $requestedAbbrev, string $availableDescription)
    {
        parent::__construct("Unknown translation '{$requestedAbbrev}'. Available translations: {$availableDescription}.");
    }
}
