<?php

namespace SzentirasHu\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class LocalRedirectRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        if (!$this->isLocalPath($value)) {
            $fail("A redirect URL csak helyi útvonal lehet (pl. /SZIT/Mt4,2-6?media).");
        }
    }

    public static function isLocalPath(string $value): bool
    {
        // Must start with / but not // (protocol-relative) or /\ (browser-normalized to //)
        if (!str_starts_with($value, '/') || str_starts_with($value, '//') || str_starts_with($value, '/\\')) {
            return false;
        }

        // Decode all percent-encoding layers to catch double-encoded attacks like /%2F%2Fevil.com
        $decoded = $value;
        do {
            $previous = $decoded;
            $decoded = rawurldecode($decoded);
        } while ($decoded !== $previous);

        // Re-check after full decode
        if (str_starts_with($decoded, '//') || str_starts_with($decoded, '/\\')) {
            return false;
        }

        return true;
    }
}
