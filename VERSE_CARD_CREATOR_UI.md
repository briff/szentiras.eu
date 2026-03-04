# Verse Card Creator UI Implementation

## Overview

This document describes the UI/UX implementation for the VerseCard creator feature. The implementation follows a state-based approach with polling for asynchronous operations.

## Files Created

### 1. **resources/views/textDisplay/verseCardCreator.twig**
Main page template that displays the verse card creator interface.

**Features:**
- Full-page layout extending the standard application layout
- Four distinct UI states:
  - **Loading State**: Shows spinner while initial candidates are being prepared
  - **Candidate Chooser State**: Displays 4 candidate image thumbnails in a responsive grid
  - **Final Preview State**: Shows the rendered verse card with download option
  - **Error State**: Displays error messages with retry option

**Key Elements:**
- Session ID and CSRF token passed to JavaScript
- Responsive grid layout (1 column on mobile, 2 on tablet, 4 on desktop)
- Attribution links to Pixabay for each candidate
- Download button with direct URL support
- "New Card" button to start over

### 2. **resources/assets/js/verseCardCreator.js**
Core JavaScript class handling all polling, state management, and user interactions.

**Class: VerseCardCreator**

**Constructor:**
- Initializes all DOM elements
- Attaches event listeners
- Starts initial polling for candidates

**Key Methods:**

#### Polling Methods
- `startInitialPolling()`: Begins polling for the first 4 candidates
- `pollForCandidates()`: Recursive polling until 4 candidates are ready
- `pollForMoreCandidates()`: Polling after "More" button is clicked
- `pollForFinalPreview()`: Polling after candidate selection until final image is ready

#### User Interaction Methods
- `handleMoreClick()`: Triggers `/more` endpoint and starts polling
- `handleCandidateSelect(candidateId)`: Triggers `/select` endpoint with candidate ID
- `handleNewCardClick()`: Navigates back to theme selection
- `handleRetryClick()`: Restarts the process from loading state

#### API Methods
- `fetchSessionStatus()`: GET `/verse-card/status/{sessionId}`
- `fetchMoreCandidates()`: POST `/verse-card/more/{sessionId}`
- `fetchSelectCandidate(candidateId)`: POST `/verse-card/select/{sessionId}`
- `endSession()`: POST `/verse-card/end/{sessionId}` (best-effort, fire-and-forget)

#### UI Methods
- `displayCandidates(candidates)`: Renders candidate thumbnails with attribution
- `displayFinalPreview(finalUrl, downloadUrl)`: Shows final image and download link
- `setState(state)`: Manages visibility of UI states
- `showError(message)`: Displays error message
- `stopPolling()`: Clears polling interval

**Polling Configuration:**
- Poll interval: 2000ms (2 seconds)
- Configurable via `this.pollInterval`

**Error Handling:**
- Network errors show user-friendly messages
- Graceful degradation with retry option
- Best-effort session cleanup on page unload

### 3. **resources/assets/css/verseCardCreator.css**
Tailwind CSS styling for the verse card creator interface.

**Key Styles:**
- `.candidate-card`: Card styling with hover effects and transitions
- `#candidatesGrid`: Responsive grid layout
- `#finalPreviewImage`: Image container with proper aspect ratio
- Loading and error state styling
- Accessibility features (focus-visible outlines)
- Fade-in animations for state transitions

## API Endpoints Required

The following endpoints must be implemented in the backend:

### 1. Create Session
**Endpoint:** `POST /verse-card/create`
**Request:**
```json
{
  "verse_ref": "John 3:16",
  "theme_slug": "hope",
  "keywords": ["love", "faith"]
}
```
**Response:**
```json
{
  "session_id": "uuid",
  "status": "initializing"
}
```

### 2. Get Session Status
**Endpoint:** `GET /verse-card/status/{sessionId}`
**Response (Candidates Ready):**
```json
{
  "status": "candidates_ready",
  "candidates": [
    {
      "id": "candidate-1",
      "thumb_url": "https://...",
      "pixabay_id": 12345,
      "pixabay_user": "username",
      "pixabay_page_url": "https://pixabay.com/photos/..."
    },
    // ... 3 more candidates
  ]
}
```
**Response (Final Ready):**
```json
{
  "status": "final_ready",
  "final_url": "https://...",
  "download_url": "https://..." or "/verse-card/download/{sessionId}"
}
```
**Response (Error):**
```json
{
  "status": "error",
  "message": "Error description"
}
```

### 3. Request More Candidates
**Endpoint:** `POST /verse-card/more/{sessionId}`
**Response:** Same as status endpoint (candidates_ready)

### 4. Select Candidate
**Endpoint:** `POST /verse-card/select/{sessionId}`
**Request:**
```json
{
  "candidate_id": "candidate-1"
}
```
**Response:** Same as status endpoint (final_ready or polling status)

### 5. End Session
**Endpoint:** `POST /verse-card/end/{sessionId}`
**Response:**
```json
{
  "status": "ended"
}
```

## User Flow

1. **Theme Selection** (existing dialog)
   - User selects a theme
   - Backend creates VerseCardSession
   - Redirects to `/verse-card/creator/{sessionId}`

2. **Loading State**
   - Page loads with loading spinner
   - JavaScript polls `/verse-card/status/{sessionId}` every 2 seconds
   - Continues until 4 candidates are ready

3. **Candidate Chooser**
   - Displays 4 thumbnail images in a grid
   - Each thumbnail shows:
     - Image preview (200px height)
     - Attribution: "Pixabay • {username}" (linked to Pixabay page)
   - "More" button is enabled (disabled while loading)
   - User clicks an image to select it

4. **Selection & Rendering**
   - Selected candidate is disabled (opacity 0.6)
   - "More" button is disabled
   - JavaScript polls `/verse-card/status/{sessionId}` every 2 seconds
   - Continues until final image is ready

5. **Final Preview**
   - Displays rendered verse card image
   - Shows "Download" button (direct URL or signed route)
   - Shows "New Card" button to start over
   - On page unload: best-effort call to `/verse-card/end/{sessionId}`

## State Diagram

```
┌─────────────┐
│   Loading   │ (polling for candidates)
└──────┬──────┘
       │ (4 candidates ready)
       ▼
┌──────────────────┐
│ Candidate Chooser│ (user selects image)
└──────┬───────────┘
       │ (polling for final)
       ▼
┌──────────────────┐
│ Final Preview    │ (user downloads or starts new)
└──────────────────┘

Error State (accessible from any state via error handling)
```

## Responsive Design

- **Mobile (< 768px)**: 1 column grid
- **Tablet (768px - 1024px)**: 2 column grid
- **Desktop (> 1024px)**: 4 column grid

## Accessibility Features

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
- LocalStorage for theme preference (existing feature)

## Performance Considerations

- Polling interval: 2 seconds (configurable)
- Thumbnail images should be optimized (< 100KB each)
- Final image should be reasonably sized for download
- Session cleanup via best-effort `/end` endpoint

## Security

- CSRF token validation on all POST requests
- Session ID in URL (not in query string for POST requests)
- XSS protection via HTML escaping in JavaScript
- Signed download URLs recommended for final image

## Future Enhancements

- WebSocket support for real-time updates (instead of polling)
- Progress indicator showing rendering progress
- Image preview before final rendering
- Batch candidate loading (infinite scroll)
- Undo/redo functionality
- Share functionality for generated cards
