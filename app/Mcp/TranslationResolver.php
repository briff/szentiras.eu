<?php

namespace SzentirasHu\Mcp;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Service\Text\TranslationService;

/**
 * Decides which translation an MCP request should be answered from.
 *
 * The whole point of the MCP server is that a Protestant user gets RUF and a Catholic
 * user gets SZIT, so the chosen translation is resolved from the most explicit signal
 * available and every unknown abbreviation is rejected rather than silently replaced.
 */
class TranslationResolver
{
    public function __construct(
        private TranslationRepository $translationRepository,
        private TranslationService $translationService
    ) {
    }

    /**
     * Translations that actually serve verse text, in denomination order.
     *
     * Enabled translations without books are skipped: GNT is enabled for the site's separate
     * Greek reader (which reads greek_verses), but has no books or verses in the tables
     * TextService uses, so quoting it through this server is impossible.
     *
     * @return Collection<int, Translation>
     */
    public function availableTranslations(): Collection
    {
        $translationIdsWithBooks = Cache::rememberForever(
            'mcp_translation_ids_with_books',
            fn (): array => Book::query()->distinct()->pluck('translation_id')->all()
        );

        return $this->translationRepository->getAllOrderedByDenom()
            ->filter(fn (Translation $translation): bool => in_array($translation->id, $translationIdsWithBooks))
            ->values();
    }

    /**
     * Resolve the translation to answer with, most explicit signal first:
     * tool argument, then the URL segment, then the configured fallback, then the site default.
     *
     * @throws UnknownTranslationException
     */
    public function resolve(?string $requestedAbbrev = null): Translation
    {
        $abbrev = $requestedAbbrev
            ?? $this->abbrevFromUrl()
            ?? config('settings.mcpTranslationAbbrev');

        if (blank($abbrev)) {
            return $this->translationService->getDefaultTranslation();
        }

        return $this->findAvailable($abbrev);
    }

    /**
     * Human readable list of valid abbreviations grouped by denomination, used in error messages
     * so an agent can correct itself without a second round trip.
     */
    public function describeAvailable(): string
    {
        return $this->availableTranslations()
            ->groupBy('denom')
            ->map(fn (Collection $translations, string $denom): string => $denom.': '.$translations->pluck('abbrev')->implode(', '))
            ->implode('; ');
    }

    /**
     * The translation segment of the MCP endpoint URL, e.g. /mcp/bible/RUF.
     */
    private function abbrevFromUrl(): ?string
    {
        // There is no route when the server runs over stdio, so only the route itself is nullable.
        $parameter = request()->route()?->parameter('translation');

        return is_string($parameter) && $parameter !== '' ? $parameter : null;
    }

    /**
     * @throws UnknownTranslationException
     */
    private function findAvailable(string $abbrev): Translation
    {
        $normalized = mb_strtoupper(trim($abbrev));

        $translation = $this->availableTranslations()
            ->first(fn (Translation $candidate): bool => mb_strtoupper($candidate->abbrev) === $normalized);

        if (! $translation) {
            throw new UnknownTranslationException($normalized, $this->describeAvailable());
        }

        return $translation;
    }

    /**
     * Publisher and copyright metadata so every quote can be attributed correctly.
     *
     * @return array{copyright: ?string, publisher: ?string}
     */
    public function attributionFor(Translation $translation): array
    {
        $definition = config("translations.definitions.{$translation->abbrev}");

        return [
            'copyright' => $definition['copyright'] ?? null,
            'publisher' => $definition['publisher']['name'] ?? null,
        ];
    }
}
