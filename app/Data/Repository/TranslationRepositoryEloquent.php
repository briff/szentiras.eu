<?php
/**

 */

namespace SzentirasHu\Data\Repository;

use Illuminate\Support\Collection;
use SzentirasHu\Data\Entity\Translation;

class TranslationRepositoryEloquent implements TranslationRepository
{

    public function getAll() : Collection
    {
        $allTranslations = \Cache::rememberForever(
           'getAllTranslations', function () {
            return Translation::orderBy('order')->orderBy('name')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
        });
        return $allTranslations;
    }

    public function getByDenom($denom = "katolikus") : Collection
    {
        $q = $denom ? Translation::where('denom', $denom) : Translation::all();
        return $q->orderBy('denom')->orderBy('order')->orderBy('name')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
    }


    public function getAllOrderedByDenom() : Collection
    {
        $allTranslations = \Cache::rememberForever(
            'translations_allOrderedByDenom', function () {
            return Translation::orderBy('denom')->orderBy('order')->whereIn('id', \Config::get('settings.enabledTranslations'))->get();
        });
        return $allTranslations;

    }

    public function getBooks($translation)
    {
        return $translation->books()->orderBy('order')->get();
    }

    public function getByAbbrev($abbrev)
    {
        return \Cache::rememberForever(
            "translations_getByAbbrev_{$abbrev}", function() use ($abbrev) {
            return Translation::byAbbrev($abbrev);
            });
    }

    public function getById($id)
    {
        return \Cache::rememberForever(
            "translations_getById_{$id}", function() use ($id) {
            return Translation::find($id);
        });
    }

}