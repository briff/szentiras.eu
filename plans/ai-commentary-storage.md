# AI Commentary Storage Design

## Overview

This document describes the database schema and services for storing AI-generated Bible commentary with support for multiple verse ranges per commentary.

## Database Schema

### `commentaries` table
- `id` (bigint, primary key)
- `translation_id` (foreign key to `translations`)
- `usx_code` (varchar(3)) – book identifier (e.g., "MAT")
- `commentary_text` (text) – AI-generated commentary content
- `metadata` (jsonb) – AI model, prompt version, generation timestamp, etc.
- `created_at`, `updated_at` (timestamps)

### `commentary_ranges` table
- `id` (bigint, primary key)
- `commentary_id` (foreign key to `commentaries`, cascade delete)
- `start_chapter` (integer)
- `start_verse` (integer)
- `end_chapter` (integer)
- `end_verse` (integer)

**Constraints**:
- All ranges belong to the same book (usx_code inherited from parent commentary).
- Verse numbers are integers (no suffixes).
- Ranges can be single verses (`start = end`) or spans across chapters.
- No overlapping ranges within same commentary (but different commentaries can overlap).

## Models

### `SzentirasHu\Models\Commentary`
- Relationships: `translation`, `ranges`
- Methods: `coversVerse()`, `addRange()`

### `SzentirasHu\Models\CommentaryRange`
- Relationships: `commentary`
- Methods: `coversVerse()`, `toString()`

## Services

### `SzentirasHu\Service\Ai\CommentaryService`

#### Key Methods

1. `findForVerse(string $usxCode, int $chapter, int $verse, Translation $translation): Collection`
   - Returns all commentaries covering the given verse.

2. `findForReference(CanonicalReference $reference, Translation $translation): Collection`
   - Returns commentaries overlapping with any part of the reference.

3. `store(Translation $translation, string $usxCode, string $commentaryText, array $ranges, array $metadata = []): Commentary`
   - Creates a new commentary with its ranges.

4. `generateCommentaryText(CanonicalReference $reference, Translation $translation, AiPromptService $aiPromptService, array $additionalPlaceholders = []): string`
   - Uses AI to generate commentary text for a reference.

5. `parseRangesFromReference(string $referenceString, string $usxCode): array`
   - Parses reference strings like `"MAT_1_2-MAT_1_6,MAT_1_12,MAT_1_23-MAT_2_5"` into range arrays.

## Artisan Command

### `ai:generate-commentary`

**Usage**:
```bash
php artisan ai:generate-commentary "MAT_1_2-MAT_1_6,MAT_1_12,MAT_1_23-MAT_2_5" KNB
```

**Options**:
- `--dry-run` – Generate but don't save
- `--force` – Overwrite existing commentary
- `--metadata` – JSON metadata to attach

**Process**:
1. Parses reference and translation
2. Checks for existing commentary (unless `--force`)
3. Generates commentary text via AI (using `ai.configurations.commentary`)
4. Stores commentary with ranges
5. Outputs success message with coverage

## Integration Points

### AI Configuration
- Uses existing `config/ai.php` configuration
- `commentary` configuration section for prompt and model settings
- Environment variables: `AI_COMMENTARY_PROVIDER`, `AI_COMMENTARY_MODEL`, etc.

### Service Provider
- `AiServiceProvider` registers `CommentaryService` and `AiPromptService`
- Auto‑injected via Laravel's container

## Query Examples

### Find commentary for a specific verse
```php
$commentaries = $commentaryService->findForVerse('MAT', 1, 3, $translation);
```

### Check if a commentary covers a verse
```php
if ($commentary->coversVerse(1, 3)) {
    // ...
}
```

### Get all commentaries for a reference
```php
$ref = CanonicalReference::fromString('MAT_1_1-MAT_1_10');
$commentaries = $commentaryService->findForReference($ref, $translation);
```

## Testing

Run the test suite:
```bash
php artisan test tests/Feature/AiCommentaryServiceTest.php
```

## Future Considerations

1. **Performance**: Add composite indexes for range queries.
2. **Caching**: Cache commentary lookups by verse.
3. **Versioning**: Support multiple versions of commentary for same range.
4. **User‑generated commentary**: Extend schema for user‑authored notes.
5. **Bulk operations**: Batch generation across entire books/chapters.