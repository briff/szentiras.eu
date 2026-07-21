<?php

namespace SzentirasHu\Mcp\Tools;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Mcp\TranslationResolver;
use SzentirasHu\Mcp\UnknownTranslationException;

class ListTranslationsTool extends Tool
{
    protected string $name = 'list-translations';

    protected string $title = 'List Bible translations';

    protected string $description = 'Lists the available Hungarian Bible translations with their denomination (katolikus / protestáns), and marks which one this endpoint answers with by default. Use it to check that quotes will come from the right tradition.';

    public function handle(TranslationResolver $resolver): Response
    {
        try {
            $activeAbbrev = $resolver->resolve()->abbrev;
        } catch (UnknownTranslationException $exception) {
            return Response::error($exception->getMessage());
        }

        $translations = $resolver->availableTranslations()
            ->map(fn (Translation $translation): array => [
                'abbrev' => $translation->abbrev,
                'name' => $translation->name,
                'denomination' => $translation->denom,
                'language' => $translation->lang,
                'isActiveDefault' => $translation->abbrev === $activeAbbrev,
                ...$resolver->attributionFor($translation),
            ])
            ->values()
            ->all();

        return Response::json([
            'activeDefault' => $activeAbbrev,
            'translations' => $translations,
        ]);
    }
}
