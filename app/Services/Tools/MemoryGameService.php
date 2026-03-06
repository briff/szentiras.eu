<?php

namespace SzentirasHu\Services\Tools;

use SzentirasHu\Service\Reference\CanonicalReference;
use SzentirasHu\Service\Reference\ParsingException;

/**
 * Service for Memory Game Creator tool
 */
class MemoryGameService
{
    public function __construct(
        protected ToolsService $toolsService
    ) {
    }

    /**
     * Process references and generate memory game cards
     */
    public function processReferences(string $input, $translation): array
    {
        $verses = [];
        $errors = [];
        
        // Parse reference lines
        $lines = array_filter(array_map('trim', explode("\n", $input)));
        
        foreach ($lines as $line) {
            // skip empty lines
            if (empty($line)) {
                continue;
            }
            try {
                $canonicalRef = CanonicalReference::fromString($line);
                $verseContainers = $this->toolsService->getTranslatedVerses($canonicalRef, $translation);
                $count = array_sum(array_map(fn($vc) => count($vc->rawVerses), $verseContainers));
                
                // Check if more than 5 verses are included in this reference
                if ($count > 5) {
                    $errors[] = "Legfeljebb 5 vers adható meg. A '{$line}' referencia " . $count . " versből áll.";
                    continue;
                }
                
                $fullText = '';
                $reference = '';
                
                foreach ($verseContainers as $verseContainer) {
                    foreach ($verseContainer->getParsedVerses() as $verse) {
                        // Get text without headings ('none' parameter) and strip any remaining HTML tags
                        $text = strip_tags($verse->getText('none'));
                        $fullText .= $text . ' ';
                        if (empty($reference)) {
                            $reference = $verse->book->abbrev . " " . $verse->chapter . ',' . $verse->numv;
                        }
                    }
                }
                
                $fullText = trim($fullText);
                
                // Skip if no text was found
                if (empty($fullText)) {
                    $errors[] = "Nem található szöveg: {$line}";
                    continue;
                }
                
                // Split text into two parts at word boundary
                $words = explode(' ', $fullText);
                $wordCount = count($words);
                $halfPoint = (int)($wordCount / 2);
                
                $firstHalf = implode(' ', array_slice($words, 0, $halfPoint));
                $secondHalf = implode(' ', array_slice($words, $halfPoint));
                
                // Normalize card text: capitalize first letter and remove trailing punctuation
                $firstHalf = $this->normalizeCardText($firstHalf);
                $secondHalf = $this->normalizeCardText($secondHalf);
                
                $verses[] = [
                    'reference' => $reference,
                    'original_input' => $line,
                    'full_text' => $fullText,
                    'first_half' => $firstHalf,
                    'second_half' => $secondHalf
                ];
                    
            } catch (ParsingException $e) {
                $errors[] = "Nem sikerült értelmezni: {$line} - {$e->getMessage()}";
            } catch (\Exception $e) {
                $errors[] = "Hiba történt: {$line} - {$e->getMessage()}";
            }
        }
        
        return [
            'verses' => $verses,
            'errors' => $errors
        ];
    }

    /**
     * Normalize card text: capitalize first letter and remove trailing punctuation
     */
    private function normalizeCardText(string $text): string
    {
        // Remove trailing punctuation marks
        $text = rtrim($text, '.,;:!?"\'…');
        
        // Capitalize first letter (UTF-8 safe)
        $text = mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1);
        
        return $text;
    }
}
