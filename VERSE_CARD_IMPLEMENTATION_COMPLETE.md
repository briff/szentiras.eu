# Verse Card Creator - Complete Implementation Guide

## Overview

This document provides a complete overview of the VerseCard creator implementation, including all UI components, JavaScript logic, and backend endpoints.

## Files Created/Modified

### Frontend Files

#### 1. **[`resources/views/textDisplay/verseCardDialog.twig`](resources/views/textDisplay/verseCardDialog.twig)** (Modified)
Extended the existing dialog to support a two-step workflow:
- **Step 1: Verse Selection** - Select verses and find themes
- **Step 2: Theme Selection** - Choose a theme to create the card
- **Loading State** - Shows spinner during processing
- **Error State** - Displays errors with retry option

#### 2. **[`resources/assets/js/verseCardDialog.js`](resources/assets/js/verseCardDialog.js)** (New)
Handles the dialog workflow:
- Verse selection and theme finding
- Theme selection and session creation
- State management (verse selection → theme selection → loading → session creation)
- Error handling with retry

**Key Methods:**
- `handleFindThemes()` - Calls `/verse-card/find-themes` endpoint
- `handleThemeSelect(theme)` - Calls `/verse-card/create` endpoint
- `displayThemes(themes)` - Renders theme list
- State transitions between steps

#### 3. **[`resources/views/textDisplay/verseCardCreator.twig`](resources/views/textDisplay/verseCardCreator.twig)** (New)
Full-page creator interface with four states:
- **Loading State** - Polls for initial 4 candidates
- **Candidate Chooser** - 4 thumbnails with Pixabay attribution
- **Final Preview** - Rendered card with download button
- **Error State** - Error messages with retry

#### 4. **[`resources/assets/js/verseCardCreator.js`](resources/assets/js/verseCardCreator.js)** (New)
Core polling and interaction logic:
- Polls `/verse-card/status/{sessionId}` every 2 seconds
- Handles "More" button → `/verse-card/more/{sessionId}`
- Handles candidate selection → `/verse-card/select/{sessionId}`
- Best-effort session cleanup on page unload → `/verse-card/end/{sessionId}`

**Key Methods:**
- `startInitialPolling()` - Begin polling for candidates
- `pollForCandidates()` - Recursive polling until 4 ready
- `handleMoreClick()` - Request more candidates
- `handleCandidateSelect(candidateId)` - Select and render
- `pollForFinalPreview()` - Poll until final image ready
- `displayCandidates(candidates)` - Render thumbnail grid
- `displayFinalPreview(finalUrl, downloadUrl)` - Show final image

#### 5. **[`resources/assets/css/verseCardCreator.css`](resources/assets/css/verseCardCreator.css)** (New)
Tailwind CSS styling:
- Responsive candidate grid (1/2/4 columns)
- Card hover effects and transitions
- Loading and error state styling
- Accessibility features (focus-visible)
- Fade-in animations

#### 6. **[`resources/assets/js/pages/verses.js`](resources/assets/js/pages/verses.js)** (Modified)
Added import for dialog JS:
```javascript
import '../verseCardDialog.js';
```

### Backend Files

#### 7. **[`app/Http/Controllers/Display/VerseCardController.php`](app/Http/Controllers/Display/VerseCardController.php)** (Modified)
Implemented all endpoints:

**Existing Methods:**
- `getDialog()` - Returns dialog with verses
- `findThemes()` - Finds themes for selected verses

**New Methods:**
- `createSession()` - POST `/verse-card/create` - Creates session and dispatches job
- `showCreator()` - GET `/verse-card/creator/{sessionId}` - Shows creator page
- `getStatus()` - GET `/verse-card/status/{sessionId}` - Returns current status
- `requestMore()` - POST `/verse-card/more/{sessionId}` - Requests more candidates
- `selectCandidate()` - POST `/verse-card/select/{sessionId}` - Selects candidate
- `download()` - GET `/verse-card/download/{sessionId}` - Downloads final image
- `endSession()` - POST `/verse-card/end/{sessionId}` - Ends session
- `getAssetUrl()` - Helper to get asset URLs

#### 8. **[`routes/web.php`](routes/web.php)** (Modified)
Added routes:
```php
Route::post('/verse-card/create', ...)->name('verse-card.create');
Route::get('/verse-card/creator/{sessionId}', ...)->name('verse-card.creator');
Route::get('/verse-card/status/{sessionId}', ...)->name('verse-card.status');
Route::post('/verse-card/more/{sessionId}', ...)->name('verse-card.more');
Route::post('/verse-card/select/{sessionId}', ...)->name('verse-card.select');
Route::post('/verse-card/end/{sessionId}', ...)->name('verse-card.end');
Route::get('/verse-card/download/{sessionId}', ...)->name('verse-card.download');
```

## User Flow

### Step 1: Dialog - Verse Selection
1. User opens verse card dialog
2. Selects verses from the list
3. Clicks "Témák keresése" button
4. Dialog shows loading spinner
5. Calls `/verse-card/find-themes` endpoint

### Step 2: Dialog - Theme Selection
1. Dialog displays found themes
2. User clicks a theme
3. Dialog shows loading spinner
4. Calls `/verse-card/create` endpoint with:
   - `verse_ref` - Selected verse reference
   - `verse_text` - Selected verse text
   - `theme_id` - Selected theme ID
   - `keywords` - Theme keywords for Pixabay search
5. Backend creates `VerseCardSession` and dispatches `SearchAndPrepareCandidates` job
6. Returns `session_id`
7. Dialog closes and navigates to `/verse-card/creator/{sessionId}`

### Step 3: Creator Page - Loading
1. Page loads with loading spinner
2. JavaScript polls `/verse-card/status/{sessionId}` every 2 seconds
3. Continues until status is `candidates_ready` with 4+ candidates

### Step 4: Creator Page - Candidate Selection
1. Page displays 4 candidate thumbnails in grid
2. Each shows:
   - Image preview (200px height)
   - Attribution: "Pixabay • {username}" (linked to Pixabay)
3. "More" button is enabled
4. User clicks an image to select it
5. JavaScript calls `/verse-card/select/{sessionId}` with `candidate_id`
6. Backend dispatches `RenderVerseCardJob`
7. JavaScript polls `/verse-card/status/{sessionId}` until status is `final_ready`

### Step 5: Creator Page - Final Preview
1. Page displays rendered verse card image
2. Shows "Download" button (links to `/verse-card/download/{sessionId}`)
3. Shows "New Card" button (navigates back)
4. On page unload: best-effort call to `/verse-card/end/{sessionId}`

## API Endpoints

### 1. Find Themes
**Endpoint:** `POST /verse-card/find-themes`
**Request:**
```json
{
  "selectedVerses": "John 3:16;John 3:17",
  "translationAbbrev": "KG"
}
```
**Response:**
```json
{
  "success": true,
  "themes": [
    {
      "id": 1,
      "hungarian_keyword": "szeretet",
      "photo_keywords": "love, heart, compassion"
    }
  ]
}
```

### 2. Create Session
**Endpoint:** `POST /verse-card/create`
**Request:**
```json
{
  "verse_ref": "John 3:16",
  "verse_text": "For God so loved the world...",
  "theme_id": 1,
  "keywords": ["love", "heart", "compassion"]
}
```
**Response:**
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "initializing"
}
```

### 3. Get Status
**Endpoint:** `GET /verse-card/status/{sessionId}`
**Response (Processing):**
```json
{
  "status": "processing"
}
```
**Response (Candidates Ready):**
```json
{
  "status": "candidates_ready",
  "candidates": [
    {
      "id": 1,
      "thumb_url": "https://...",
      "pixabay_id": 12345,
      "pixabay_user": "username",
      "pixabay_page_url": "https://pixabay.com/photos/..."
    }
  ]
}
```
**Response (Final Ready):**
```json
{
  "status": "final_ready",
  "final_url": "https://...",
  "download_url": "/verse-card/download/{sessionId}"
}
```
**Response (Error):**
```json
{
  "status": "error",
  "message": "Error description"
}
```

### 4. Request More Candidates
**Endpoint:** `POST /verse-card/more/{sessionId}`
**Response:**
```json
{
  "status": "processing"
}
```

### 5. Select Candidate
**Endpoint:** `POST /verse-card/select/{sessionId}`
**Request:**
```json
{
  "candidate_id": 1
}
```
**Response:**
```json
{
  "status": "processing"
}
```

### 6. Download Final Image
**Endpoint:** `GET /verse-card/download/{sessionId}`
**Response:** File download (PNG image)

### 7. End Session
**Endpoint:** `POST /verse-card/end/{sessionId}`
**Response:**
```json
{
  "status": "ended"
}
```

## Database Schema

### VerseCardSession
```
id (UUID, primary key)
user_id (nullable, foreign key to users)
verse_ref (string)
verse_text (nullable, text)
theme_slug (string or integer)
keywords (JSON array)
status (enum: initializing, searching_more, candidates_ready, rendering, final_ready, error, ended)
pixabay_page (integer)
pixabay_offset (integer)
expires_at (timestamp)
created_at (timestamp)
updated_at (timestamp)
```

### VerseCardAsset
```
id (integer, primary key)
session_id (UUID, foreign key)
kind (enum: candidate, final)
state (enum: pending, ready, selected, unselected)
pixabay_id (nullable, integer)
pixabay_user (nullable, string)
pixabay_page_url (nullable, string)
remote_url (nullable, string)
disk (string: local, s3)
path (nullable, string)
thumb_path (nullable, string)
bytes (nullable, integer)
expires_at (nullable, timestamp)
created_at (timestamp)
updated_at (timestamp)
```

## Job Classes

### SearchAndPrepareCandidates
Dispatched when:
- Session is created
- "More" button is clicked

Responsibilities:
1. Search Pixabay API for images matching keywords
2. Download images
3. Create thumbnails
4. Store as `VerseCardAsset` records with `kind=candidate`
5. Update session status to `candidates_ready`

### RenderVerseCardJob
Dispatched when:
- Candidate is selected

Responsibilities:
1. Load candidate image
2. Render verse text overlay
3. Save final image
4. Create `VerseCardAsset` record with `kind=final`
5. Update session status to `final_ready`

## Polling Configuration

- **Poll Interval:** 2000ms (2 seconds)
- **Configurable in:** `VerseCardCreator.pollInterval`
- **Timeout:** None (continues until status changes)

## Error Handling

### Frontend
- Network errors show user-friendly messages
- Retry button available in error state
- Graceful degradation if polling fails

### Backend
- HTTP 410 Gone - Session expired
- HTTP 404 Not Found - Session not found
- HTTP 403 Forbidden - Unauthorized access
- HTTP 422 Unprocessable Entity - Invalid request

## Security

1. **CSRF Protection** - All POST requests require CSRF token
2. **Authorization** - Session ownership verified for authenticated users
3. **Input Validation** - All inputs validated on backend
4. **XSS Prevention** - HTML escaping in JavaScript
5. **File Storage** - Files stored outside public directory
6. **Signed URLs** - S3 downloads use temporary signed URLs
7. **Session Expiration** - Sessions expire after 24 hours

## Performance Considerations

1. **Image Optimization**
   - Candidate thumbnails: < 100KB each
   - Final image: Reasonably sized for download

2. **Async Processing**
   - Image downloads queued
   - Rendering queued
   - No blocking operations

3. **Caching**
   - Pixabay search results cached
   - Theme data cached

4. **Database**
   - Indexes on `session_id`, `status`, `expires_at`
   - Automatic cleanup of expired sessions

## Responsive Design

| Screen Size | Columns |
|-------------|---------|
| < 768px    | 1       |
| 768-1024px | 2       |
| > 1024px   | 4       |

## Accessibility

- ARIA labels for spinners
- Focus-visible outlines on interactive elements
- Semantic HTML structure
- Proper heading hierarchy
- Alt text for images
- Error messages clearly associated with error state

## Browser Compatibility

- Modern browsers with ES6 support
- Fetch API support
- CSS Grid support
- LocalStorage support

## Testing Checklist

- [ ] Dialog verse selection works
- [ ] Theme finding returns results
- [ ] Theme selection creates session
- [ ] Creator page loads with session ID
- [ ] Initial polling fetches 4 candidates
- [ ] Candidates display with correct attribution
- [ ] "More" button requests additional candidates
- [ ] Candidate selection disables UI and polls
- [ ] Final preview displays rendered image
- [ ] Download button works
- [ ] "New Card" button navigates back
- [ ] Error states show appropriate messages
- [ ] Retry buttons work
- [ ] Session cleanup on page unload
- [ ] Responsive design on mobile/tablet/desktop

## Future Enhancements

1. **WebSocket Support** - Real-time updates instead of polling
2. **Progress Indicator** - Show rendering progress
3. **Image Preview** - Preview before final rendering
4. **Batch Loading** - Infinite scroll for candidates
5. **Undo/Redo** - Revert selections
6. **Share Functionality** - Share generated cards
7. **Custom Text** - Allow editing verse text
8. **Multiple Themes** - Support multiple theme overlays
9. **Filters** - Filter candidates by color, style, etc.
10. **History** - Save previously created cards

## Troubleshooting

### Session Not Found
- Check session ID in URL
- Verify session hasn't expired (24 hours)
- Check database for session record

### Candidates Not Loading
- Check Pixabay API key configuration
- Verify keywords are valid
- Check job queue is processing
- Review application logs

### Final Image Not Rendering
- Check image rendering library (Imagine) is installed
- Verify candidate image is valid
- Check storage permissions
- Review application logs

### Download Not Working
- Verify final asset exists in database
- Check file storage path
- Verify S3 credentials if using S3
- Check browser download settings

## Support

For issues or questions, refer to:
- Application logs: `storage/logs/`
- Database: Check `verse_card_sessions` and `verse_card_assets` tables
- Job queue: Check failed jobs in queue
