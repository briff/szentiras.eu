<?php
/**

 */

namespace SzentirasHu\Service\Text\VerseParsers;


use Log;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VerseData;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePart;
use SzentirasHu\Http\Controllers\Display\VerseParsers\VersePartType;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Data\Entity\Verse;

abstract class AbstractVerseParser implements VerseParser {

    /**
     * @param $rawVerses
     * @return VerseData
     */
    protected function initVerseData($rawVerses)
    {
        $chapter = $rawVerses[0]->chapter;
        $numv = $rawVerses[0]->numv;
        $verse = new VerseData($chapter, $numv);
        return $verse;
    }

    /**
     * parses verses corresponding to one verse
     *
     * @param Verse[] $rawVerses
     * @param Book $book
     * @return VerseData
     */
    public function parse($rawVerses, $book)
    {
        $verseData = $this->initVerseData($rawVerses);
        foreach ($rawVerses as $rawVerse) {
            $this->parseRawVerses($book, $rawVerse, $verseData);
        }
        foreach ($verseData->xrefs as $key => $xref) {
            if (!$xref->text) {
                unset($verseData->xrefs[$key]);
            }
        }
        $this->sortVerseParts($verseData);
        return $verseData;
    }

    /**
     * Sort verse parts so that headings appear first, sorted by heading level,
     * followed by other parts in their original order.
     */
    protected function sortVerseParts(VerseData $verseData): void
    {
        $parts = $verseData->verseParts;
        usort($parts, function (VersePart $a, VersePart $b) {
            // Both headings: compare headingLevel ascending, then order ascending
            if ($a->isHeading() && $b->isHeading()) {
                if ($a->headingLevel !== $b->headingLevel) {
                    return $a->headingLevel <=> $b->headingLevel;
                }
                return $a->order <=> $b->order;
            }
            // Only $a is heading: $a comes before $b
            if ($a->isHeading()) {
                return -1;
            }
            // Only $b is heading: $b comes before $a
            if ($b->isHeading()) {
                return 1;
            }
            // Neither is heading: keep original order
            return $a->order <=> $b->order;
        });
        $verseData->verseParts = $parts;
    }

    /**
     * @param Book $book
     * @param Verse $rawVerse
     * @param VerseData $verse
     */
    protected function parseRawVerses($book, $rawVerse, VerseData $verse)
    {
        $type = $rawVerse->getType();
        if ($type == 'text') {
            $this->parseTextVerse($rawVerse, $verse);
        } else if ($type == 'xref') {
            $this->parseXrefVerse($book, $rawVerse, $verse);
        } else if (strpos($type, 'heading') === 0) {
            $this->parseHeading($rawVerse, $verse);
        } else if ($type == 'footnote' ) {
            $this->parseFootnoteVerse($rawVerse, $verse);
        } else if ($type == 'poemLine') {
            $this->parsePoemLine($rawVerse, $verse);
        }
    }

    /**
     * @param Verse $rawVerse
     * @param VerseData $verseData
     * @return void
     */
    abstract protected function parseTextVerse($rawVerse, VerseData $verseData);

    abstract protected function replaceTags($rawVerse);

    /**
     * @param Book $book
     * @param Verse $rawVerse
     * @param VerseData $verse
     * @return void
     */
    abstract protected function parseXrefVerse($book, $rawVerse, VerseData $verse);

    abstract protected function parseFootnoteVerse(Verse $rawVerse, VerseData $verse);

    abstract protected function parseHeading($rawVerse, VerseData $verse);

    abstract protected function parsePoemLine($rawVerse, VerseData $verse);
}