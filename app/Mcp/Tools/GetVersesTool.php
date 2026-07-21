<?php

namespace SzentirasHu\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Mcp\TranslationResolver;
use SzentirasHu\Mcp\UnknownTranslationException;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;
use SzentirasHu\Service\Text\TextService;

class GetVersesTool extends Tool
{
    protected string $name = 'get-verses';

    protected string $title = 'Get Bible verses';

    protected string $description = 'Returns the exact, verbatim text of a Bible reference in a specific Hungarian translation. Use this instead of quoting scripture from memory. References use Hungarian notation: a comma separates chapter and verse (Jn 3,16), a hyphen marks a range (1Kor 13,4-7), and a semicolon separates books or chapters (Jn 1;3).';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'reference' => $schema->string()
                ->description('Bible reference in Hungarian notation, e.g. "Jn 3,16" or "1Kor 13,4-7".')
                ->required(),
            'translation' => $schema->string()
                ->description('Optional translation abbreviation (e.g. RUF, SZIT, KNB). Defaults to the translation this endpoint is configured for. Only pass this to deliberately override the user\'s own tradition.'),
        ];
    }

    public function handle(Request $request, TranslationResolver $resolver, TextService $textService): Response
    {
        $validated = $request->validate([
            'reference' => ['required', 'string'],
            'translation' => ['nullable', 'string'],
        ]);

        try {
            $translation = $resolver->resolve($validated['translation'] ?? null);
        } catch (UnknownTranslationException $exception) {
            return Response::error($exception->getMessage());
        }

        try {
            $canonicalReference = CanonicalReference::fromString($validated['reference']);
        } catch (ParsingException $exception) {
            return Response::error("Could not parse the reference '{$validated['reference']}'. Use Hungarian notation, for example 'Jn 3,16', '1Kor 13,4-7' or 'Jn 1;3'.");
        }

        $verses = $this->collectVerses($canonicalReference, $translation, $textService);

        if ($verses === []) {
            return Response::error("No verses found for '{$canonicalReference->toString()}' in {$translation->abbrev}.");
        }

        return Response::json([
            'reference' => $canonicalReference->toString(),
            'translation' => $this->describeTranslation($translation, $resolver),
            'verses' => $verses,
        ]);
    }

    /**
     * @return array<int, array{reference: string, gepi: string, text: string}>
     */
    private function collectVerses(CanonicalReference $reference, Translation $translation, TextService $textService): array
    {
        $verses = [];

        foreach ($textService->getTranslatedVerses($reference, $translation) as $verseContainer) {
            foreach ($verseContainer->getParsedVerses() as $verse) {
                $verses[] = [
                    'reference' => "{$verse->book->abbrev} {$verse->chapter},{$verse->numv}",
                    'gepi' => $verse->gepi,
                    'text' => $verse->getText(),
                ];
            }
        }

        return $verses;
    }

    /**
     * @return array<string, ?string>
     */
    private function describeTranslation(Translation $translation, TranslationResolver $resolver): array
    {
        return [
            'abbrev' => $translation->abbrev,
            'name' => $translation->name,
            'denomination' => $translation->denom,
            'language' => $translation->lang,
            ...$resolver->attributionFor($translation),
        ];
    }
}
