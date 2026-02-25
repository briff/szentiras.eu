<?php

namespace SzentirasHu\Test\Service\Reference;

use SzentirasHu\Service\Reference\NumberingSchemeService;
use SzentirasHu\Data\Repository\TranslationRepository;
use SzentirasHu\Data\Entity\Translation;
use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\TestCase;
use SzentirasHu\Service\Text\TextService;
use SzentirasHu\Service\VerseContainer;
use SzentirasHu\Data\Entity\Verse;
use SzentirasHu\Data\Entity\Book;
use SzentirasHu\Service\Reference\ChapterRange;
use SzentirasHu\Service\Reference\VerseRange;
use SzentirasHu\Service\Reference\VerseRef;
use Mockery;

class NumberingSchemeServiceTest extends TestCase
{
    private TranslationRepository $translationRepository;
    private TextService $textService;
    private NumberingSchemeService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationRepository = Mockery::mock(TranslationRepository::class);
        $this->textService = Mockery::mock(TextService::class);
        $this->service = new NumberingSchemeService($this->translationRepository, $this->textService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Expand a CanonicalReference into a flat list of individual verses
     * using the same logic as the old expandToVerseList.
     */
    private function expandReferenceToVerseData(CanonicalReference $ref): array
    {
        $verses = [];
        foreach ($ref->bookRefs as $bookRef) {
            foreach ($bookRef->chapterRanges as $chapterRange) {
                $startChapter = $chapterRange->chapterRef->chapterId;
                $endChapter = $chapterRange->untilChapterRef
                    ? $chapterRange->untilChapterRef->chapterId
                    : $startChapter;

                for ($chapter = $startChapter; $chapter <= $endChapter; $chapter++) {
                    $verseRanges = $this->getVerseRangesForChapter($chapterRange, $chapter);
                    if (empty($verseRanges)) {
                        continue;
                    }

                    foreach ($verseRanges as $verseRange) {
                        $startVerse = $verseRange->verseRef ? $verseRange->verseRef->verseId : 1;
                        $endVerse = $verseRange->untilVerseRef ? $verseRange->untilVerseRef->verseId : $startVerse;
                        for ($verse = $startVerse; $verse <= $endVerse; $verse++) {
                            $verses[] = [
                                'bookId' => $bookRef->bookId,
                                'chapter' => $chapter,
                                'verse' => $verse,
                            ];
                        }
                    }
                }
            }
        }
        return $verses;
    }

    private function getVerseRangesForChapter(ChapterRange $chapterRange, int $chapter): array
    {
        if ($chapter === $chapterRange->chapterRef->chapterId) {
            return $chapterRange->chapterRef->verseRanges;
        }
        if ($chapterRange->untilChapterRef && $chapter === $chapterRange->untilChapterRef->chapterId) {
            return $chapterRange->untilChapterRef->verseRanges;
        }
        return [];
    }

    private function mockTextServiceForReference(CanonicalReference $ref, Translation $translation): void
    {
        $verseData = $this->expandReferenceToVerseData($ref);
        if (empty($verseData)) {
            $this->textService->shouldReceive('getTranslatedVerses')
                ->with($ref, $translation)
                ->andReturn([]);
            return;
        }
        // Group by bookId (simplify: assume one book)
        $containers = [];
        foreach ($verseData as $data) {
            $verse = Mockery::mock(Verse::class);
            $verse->shouldReceive('getAttribute')->with('chapter')->andReturn($data['chapter']);
            $verse->shouldReceive('getAttribute')->with('numv')->andReturn($data['verse']);
            $verse->shouldReceive('__get')->with('chapter')->andReturn($data['chapter']);
            $verse->shouldReceive('__get')->with('numv')->andReturn($data['verse']);
            $book = Mockery::mock(Book::class);
            $book->shouldReceive('getAttribute')->with('abbrev')->andReturn($data['bookId']);
            $book->shouldReceive('__get')->with('abbrev')->andReturn($data['bookId']);
            $verse->shouldReceive('getAttribute')->with('book')->andReturn($book);
            $verse->shouldReceive('__get')->with('book')->andReturn($book);
            $container = Mockery::mock(VerseContainer::class);
            $container->rawVerses = ['dummy' => [$verse]];
            $containers[] = $container;
        }
        $this->textService->shouldReceive('getTranslatedVerses')
            ->with($ref, $translation)
            ->andReturn($containers);
    }

    public function testConvertsPsalmsFromVulgataToDefault()
    {
        // Mock translation
        $translation = new Translation();
        $translation->id = 1;
        $translation->abbrev = 'katolikus';

        $this->translationRepository->shouldReceive('getById')
            ->with(1)
            ->andReturn($translation);

        // Create a reference to PSA 50:1 (Vulgata)
        $ref = CanonicalReference::fromString('Zsolt 50,1', 1);
        $this->mockTextServiceForReference($ref, $translation);
        // Convert
        $converted = $this->service->convertReference($ref, 'vulgata', 'default');

        // Should be PSA 51:1 (standard)
        $this->assertEquals('Zsolt 51,1', $converted->toString());
    }

    public function testConvertsPsalms9_22To10_1()
    {
        $translation = new Translation();
        $translation->id = 1;
        $translation->abbrev = 'katolikus';

        $this->translationRepository->shouldReceive('getById')
            ->with(1)
            ->andReturn($translation);

        $ref = CanonicalReference::fromString('Zsolt 9,22', 1);
        $this->mockTextServiceForReference($ref, $translation);
        $converted = $this->service->convertReference($ref, 'vulgata', 'default');
        $this->assertEquals('Zsolt 10,1', $converted->toString());
    }

    public function testDoesNothingForUnknownScheme()
    {
        $ref = CanonicalReference::fromString('Zsolt 50,1', 1);
        $converted = $this->service->convertReference($ref, 'unknown', 'default');
        $this->assertEquals($ref->toString(), $converted->toString());
    }

    public function testHandlesMultipleVerses()
    {
        $translation = new Translation();
        $translation->id = 1;
        $translation->abbrev = 'katolikus';

        $this->translationRepository->shouldReceive('getById')
            ->with(1)
            ->andReturn($translation);

        // Reference with verse range
        $ref = CanonicalReference::fromString('Zsolt 50,1-5', 1);
        $this->mockTextServiceForReference($ref, $translation);
        $converted = $this->service->convertReference($ref, 'vulgata', 'default');
        // Should map each verse individually
        // Expect Zsolt 51,1-5
        $this->assertEquals('Zsolt 51,1-5', $converted->toString());
    }

    public function testWholeChapterReference()
    {
        $translation = new Translation();
        $translation->id = 1;
        $translation->abbrev = 'katolikus';

        $this->translationRepository->shouldReceive('getById')
            ->with(1)
            ->andReturn($translation);

        // Whole chapter reference (no verses)
        $ref = CanonicalReference::fromString('Zsolt 50', 1);
        // No need to mock textService because chapter mapping will be used
        $converted = $this->service->convertReference($ref, 'vulgata', 'default');
        // Should convert from PSA 50 (vulgata) to PSA 51 (default) with all verses
        $this->assertEquals('Zsolt 51,1-21', $converted->toString());
    }
}