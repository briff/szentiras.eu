<?php

namespace SzentirasHu\Service\Text\VerseParsers;

use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Http\Controllers\Display\VerseParsers\Xref;

class KNBVerseParser extends DefaultVerseParser
{
    const XREF_REGEXP = '\s*\{([^\}]+)\}';

    protected function replaceTags($rawText, $verseData = false) {
        $rawText = preg_replace('/\{\{br\}\}/', '<br>', $rawText);
        $rawText = preg_replace('/\{\{ej\}\}/', '', $rawText);
        $rawText = preg_replace('/\{\{i\}\}/', '<em>', $rawText);
        $rawText = preg_replace('/\{\{\/i\}\}/', '</em>', $rawText);
        preg_match_all("/".self::XREF_REGEXP."/u", $rawText, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE);
        $count = count($matches[1]);
        for ($i = 0; $i < $count && $verseData; $i++) {
            $match = $matches[1][$i];
            $xrefPos = $match[1];
            $xrefText = $match[0];
            $xref = new Xref();
            $xref->position = $xrefPos;
            $xref->text = $xrefText;
            $verseData->xrefs[] = $xref;
        }
        $purified = preg_replace('/\s*'.self::XREF_REGEXP.'/u', '', $rawText);
        $purified = preg_replace('/<\/?i>/', '', $purified);
        return $purified;
    }

    /**
     * @param $rawVerse
     * @param VerseData $verseData
     */
    protected function parseTextVerse($rawVerse, $verseData)
    {
        $rawText = $rawVerse->verse;
        $verseData->simpleText = $this->replaceTags($rawText);

    }

}