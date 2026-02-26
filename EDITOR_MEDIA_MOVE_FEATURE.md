# Editor Media Move Feature

## Overview
This feature allows editors to easily move illustrations (media) to other verses directly from the scripture display page. The feature includes arrow buttons to move to the next/previous verse within the same chapter, and a text field to set a USX format ID for moving the illustration to a specific location.

## Components Implemented

### 1. Backend API Endpoints

#### MediaApiController (`app/Http/Controllers/Api/MediaApiController.php`)
- `GET /api/media/{id}` - Get media item details (editor-only)
- `POST /api/media/move` - Move media to new location (editor-only)
- `GET /api/media/{usxCode}/{chapter}/{verse}/next` - Get next verse in same chapter (editor-only)
- `GET /api/media/{usxCode}/{chapter}/{verse}/previous` - Get previous verse in same chapter (editor-only)

All endpoints require editor privileges checked via `EditorService`.

### 2. Frontend Editor Controls

#### Updated Image Macro (`resources/views/macros.twig`)
The `image` macro now accepts an `isEditor` parameter and displays editor controls when the user is an editor:

- **Toggle button**: "Szerkesztés" (Edit) button to show/hide editor controls
- **Current location display**: Shows current USX code, chapter, and verse
- **Arrow buttons**: Move to previous/next verse within same chapter
- **Text input**: USX target location (format: "USX_CODE CHAPTER:VERSE")
- **Move button**: Execute the move operation
- **Cancel button**: Hide editor controls
- **Status messages**: Success/error alerts

### 3. Editor Authentication

#### EditorService Integration
- `TextDisplayController` now injects `EditorService` and passes `isEditor` status to views
- Editor status is determined by anonymous token matching configured editor tokens
- API endpoints validate editor privileges before allowing operations

### 4. Routes

Added to `routes/web.php`:
```php
Route::prefix('api/media')->group(function () {
    Route::get('/{id}', [MediaApiController::class, 'show']);
    Route::post('/move', [MediaApiController::class, 'move']);
    Route::get('/{usxCode}/{chapter}/{verse}/next', [MediaApiController::class, 'getNextVerse']);
    Route::get('/{usxCode}/{chapter}/{verse}/previous', [MediaApiController::class, 'getPreviousVerse']);
});
```

## Usage Flow

1. **Editor Authentication**: Editors must have their anonymous token configured in the `editors.tokens` config array.

2. **Viewing Media**: When viewing scripture with media enabled (`?media` parameter), editors will see a "Szerkesztés" (Edit) button below each illustration.

3. **Editing Controls**: Clicking "Szerkesztés" reveals:
   - Current location of the media
   - Arrow buttons (← →) to move to adjacent verses
   - Text input pre-filled with current USX location
   - Move button (→) to execute relocation
   - Cancel button (×) to hide controls

4. **Moving Media**:
   - Click arrow buttons to move to adjacent verses (API call updates database)
   - Or edit the USX target text and click move button
   - Success/error messages appear below controls
   - Page does not refresh; changes are immediate via AJAX

## Technical Details

### USX Format
The USX format used is: `{usx_code} {chapter}:{verse}`
- `usx_code`: Book code (e.g., "MAT" for Matthew)
- `chapter`: Chapter number
- `verse`: Verse number

### Database Schema
Media locations are stored in the `media` table:
- `usx_code` (string): Book code
- `chapter` (integer): Chapter number  
- `verse` (integer): Verse number

### Security
- All API endpoints validate editor privileges via `EditorService::currentIsEditor()`
- CSRF protection is handled by Laravel for POST endpoints
- Input validation ensures valid USX codes and verse numbers

## Configuration

Editor tokens are configured in `config/editors.php` (if file exists) or via `config('editors.tokens', [])` defaulting to empty array.

Example configuration:
```php
// config/editors.php
return [
    'tokens' => [
        'editor_token_1',
        'editor_token_2',
    ],
];
```

## JavaScript Implementation

The frontend JavaScript (to be implemented in `public/js/editor-media-move.js`) would handle:
- Toggling editor controls visibility
- Making AJAX calls to move media
- Updating UI with success/error messages
- Parsing USX format strings

## Testing Considerations

1. **Unit Tests**: Test `EditorService` authentication logic
2. **Feature Tests**: Test API endpoints with valid/invalid editor tokens
3. **Integration Tests**: Test full flow of moving media
4. **UI Tests**: Verify editor controls only appear for editors

## Notes

- The feature is designed to not break page flow for non-editors
- Editor controls are hidden by default and only shown when toggled
- Media moves are immediately reflected in the database
- No page reload required for editors to see changes