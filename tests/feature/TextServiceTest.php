<?php

namespace SzentirasHu\Test;

use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Test\Common\FastDatabaseTestCase;

class TextServiceTest extends FastDatabaseTestCase
{
    public function testSameChapterRangeText() {
        
        /** @var \SzentirasHu\Service\Text\TextService $service */
        $service = \App::make(\SzentirasHu\Service\Text\TextService::class);
        /** @var \SzentirasHu\Service\Text\TranslationService $translationService*/
        $translationService = \App::make(\SzentirasHu\Service\Text\TranslationService::class);
        $defaultTranslation = $translationService->getDefaultTranslation();
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-4'), $defaultTranslation);
        $this->assertEquals("verse 100100200300 verse 100100200400", $text);
        $text = $service->getPureText(CanonicalReference::fromString('Ter 2,3-2,3'), $defaultTranslation);
        $this->assertEquals("verse 100100200300", $text);
    }
}
