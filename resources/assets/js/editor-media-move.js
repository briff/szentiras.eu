/**
 * Editor Media Move Functionality
 * Handles moving media illustrations between verses for editors
 */

(function() {
    'use strict';
    
    // CSRF token for Laravel - try multiple sources
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!csrfToken) {
        // Try to get from cookies (XSRF-TOKEN)
        const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (match) {
            csrfToken = decodeURIComponent(match[1]);
        }
    }
    
    /**
     * Parse USX format string (e.g., "MAT 1:1")
     */
    function parseUsxFormat(usxString) {
        const match = usxString.match(/^(\w+)\s+(\d+):(\d+)$/);
        if (!match) {
            return null;
        }
        return {
            usxCode: match[1],
            chapter: parseInt(match[2], 10),
            verse: parseInt(match[3], 10)
        };
    }
    
    /**
     * Format USX location object to string
     */
    function formatUsxLocation(usxCode, chapter, verse) {
        return `${usxCode} ${chapter}:${verse}`;
    }
    
    /**
     * Show status message in the editor controls
     */
    function showStatus(container, message, type = 'info') {
        const statusEl = container.querySelector('.status-message');
        statusEl.textContent = message;
        statusEl.className = `status-message alert alert-${type}`;
        statusEl.style.display = 'block';
        
        // Auto-hide success messages after 3 seconds
        if (type === 'success') {
            setTimeout(() => {
                statusEl.style.display = 'none';
            }, 3000);
        }
    }
    
    /**
     * Hide status message
     */
    function hideStatus(container) {
        const statusEl = container.querySelector('.status-message');
        statusEl.style.display = 'none';
    }
    
    /**
     * Make API call to move media
     */
    async function moveMedia(mediaId, usxCode, chapter, verse) {
        try {
            const response = await fetch(`/api/media/move`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    _token: csrfToken,
                    media_id: mediaId,
                    usx_code: usxCode,
                    chapter: chapter,
                    verse: verse
                })
            });
            
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `HTTP error ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error('Error moving media:', error);
            throw error;
        }
    }
    
    /**
     * Reload page and scroll to media element
     */
    function reloadAndScrollToMedia(mediaId) {
        // Store the media ID to scroll to after reload
        sessionStorage.setItem('scrollToMediaId', mediaId);
        
        // Add a small delay to ensure the database is updated
        setTimeout(() => {
            // Force reload from server
            window.location.reload(true);
        }, 500);
    }
    
    /**
     * Check if we need to scroll to a media element after page load
     */
    function checkScrollToMediaAfterLoad() {
        const mediaId = sessionStorage.getItem('scrollToMediaId');
        if (mediaId) {
            // Clear the stored ID
            sessionStorage.removeItem('scrollToMediaId');
            
            // Wait a bit for the page to fully render
            setTimeout(() => {
                const mediaElement = document.getElementById('media-' + mediaId);
                if (mediaElement) {
                    mediaElement.scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        }
    }
    
    /**
     * Get adjacent verse (next or previous)
     */
    async function getAdjacentVerse(usxCode, chapter, verse, direction) {
        try {
            const endpoint = direction === 'next' ? 'next' : 'previous';
            const response = await fetch(`/api/media/${usxCode}/${chapter}/${verse}/${endpoint}`, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP error ${response.status}`);
            }
            
            return await response.json();
        } catch (error) {
            console.error(`Error getting ${direction} verse:`, error);
            throw error;
        }
    }
    
    /**
     * Update UI after successful move
     */
    function updateUiAfterMove(container, usxCode, chapter, verse) {
        // Update current location display
        const currentLocationEl = container.querySelector('.current-location');
        currentLocationEl.textContent = formatUsxLocation(usxCode, chapter, verse);
        
        // Update input field
        const inputEl = container.querySelector('.usx-target');
        inputEl.value = formatUsxLocation(usxCode, chapter, verse);
        
        // Update data attributes
        container.setAttribute('data-usx-code', usxCode);
        container.setAttribute('data-chapter', chapter);
        container.setAttribute('data-verse', verse);
    }
    
    /**
     * Get the verse anchor ID for a given USX location
     * Note: This would need to query the server or have the data available
     * For now, we'll construct a likely anchor ID pattern
     */
    function getVerseAnchorId(usxCode, chapter, verse) {
        // In a real implementation, we would need to get the gepi from the server
        // For now, return a placeholder - the actual implementation would need
        // to be coordinated with the backend
        return `v_${usxCode}_${chapter}_${verse}`;
    }
    
    /**
     * Initialize editor controls for a media item
     */
    function initEditorControls(container) {
        const toggleBtn = container.querySelector('.toggle-editor-controls');
        const controlsContainer = container.querySelector('.editor-controls-container');
        const prevBtn = container.querySelector('.move-prev-verse');
        const nextBtn = container.querySelector('.move-next-verse');
        const moveBtn = container.querySelector('.move-to-target');
        const deleteBtn = container.querySelector('.delete-media');
        const cancelBtn = container.querySelector('.cancel-edit');
        const inputEl = container.querySelector('.usx-target');
        
        const mediaId = container.getAttribute('data-media-id');
        let currentUsxCode = container.getAttribute('data-usx-code');
        let currentChapter = parseInt(container.getAttribute('data-chapter'), 10);
        let currentVerse = parseInt(container.getAttribute('data-verse'), 10);
        
        // Toggle editor controls visibility
        toggleBtn.addEventListener('click', function() {
            const isVisible = controlsContainer.style.display !== 'none';
            controlsContainer.style.display = isVisible ? 'none' : 'block';
            toggleBtn.innerHTML = isVisible 
                ? '<i class="bi-pencil"></i> Szerkesztés'
                : '<i class="bi-eye-slash"></i> Elrejtés';
            hideStatus(container);
        });
        
        // Move to previous verse
        prevBtn.addEventListener('click', async function() {
            hideStatus(container);
            showStatus(container, 'Előző vers betöltése...', 'info');
            
            try {
                const result = await getAdjacentVerse(currentUsxCode, currentChapter, currentVerse, 'previous');
                
                if (result.success && result.previous_verse && result.previous_verse.usx_code && result.previous_verse.chapter && result.previous_verse.verse) {
                    const verseData = result.previous_verse;
                    
                    // Check if verse actually changed (not the same verse)
                    if (verseData.verse === currentVerse && verseData.chapter === currentChapter && verseData.usx_code === currentUsxCode) {
                        showStatus(container, 'Nincs előző vers ebben a fejezetben', 'warning');
                        return;
                    }
                    
                    // Move the media to the previous verse
                    const moveResult = await moveMedia(mediaId, verseData.usx_code, verseData.chapter, verseData.verse);
                    
                    if (moveResult.success) {
                        // Update UI with new location
                        currentUsxCode = verseData.usx_code;
                        currentChapter = verseData.chapter;
                        currentVerse = verseData.verse;
                        updateUiAfterMove(container, currentUsxCode, currentChapter, currentVerse);
                        showStatus(container, 'Sikeresen áthelyezve az előző versre! Oldal újratöltése...', 'success');
                        
                        // Reload page and scroll to media after a short delay
                        setTimeout(() => {
                            reloadAndScrollToMedia(mediaId);
                        }, 300);
                    } else {
                        showStatus(container, moveResult.message || 'Hiba az áthelyezés során', 'danger');
                    }
                } else {
                    showStatus(container, result.message || 'Nincs előző vers ebben a fejezetben', 'warning');
                }
            } catch (error) {
                showStatus(container, `Hiba: ${error.message}`, 'danger');
            }
        });
        
        // Move to next verse
        nextBtn.addEventListener('click', async function() {
            hideStatus(container);
            showStatus(container, 'Következő vers betöltése...', 'info');
            
            try {
                const result = await getAdjacentVerse(currentUsxCode, currentChapter, currentVerse, 'next');
                
                if (result.success && result.next_verse && result.next_verse.usx_code && result.next_verse.chapter && result.next_verse.verse) {
                    const verseData = result.next_verse;
                    
                    // Check if verse actually changed (not the same verse)
                    if (verseData.verse === currentVerse && verseData.chapter === currentChapter && verseData.usx_code === currentUsxCode) {
                        showStatus(container, 'Nincs következő vers ebben a fejezetben', 'warning');
                        return;
                    }
                    
                    // Move the media to the next verse
                    const moveResult = await moveMedia(mediaId, verseData.usx_code, verseData.chapter, verseData.verse);
                    
                    if (moveResult.success) {
                        // Update UI with new location
                        currentUsxCode = verseData.usx_code;
                        currentChapter = verseData.chapter;
                        currentVerse = verseData.verse;
                        updateUiAfterMove(container, currentUsxCode, currentChapter, currentVerse);
                        showStatus(container, 'Sikeresen áthelyezve a következő versre! Oldal újratöltése...', 'success');
                        
                        // Reload page and scroll to media after a short delay
                        setTimeout(() => {
                            reloadAndScrollToMedia(mediaId);
                        }, 300);
                    } else {
                        showStatus(container, moveResult.message || 'Hiba az áthelyezés során', 'danger');
                    }
                } else {
                    showStatus(container, result.message || 'Nincs következő vers ebben a fejezetben', 'warning');
                }
            } catch (error) {
                showStatus(container, `Hiba: ${error.message}`, 'danger');
            }
        });
        
        // Move to target location
        moveBtn.addEventListener('click', async function() {
            const usxString = inputEl.value.trim();
            const parsed = parseUsxFormat(usxString);
            
            if (!parsed) {
                showStatus(container, 'Érvénytelen USX formátum. Használd: "USX_CODE CHAPTER:VERSE" (pl. "MAT 1:1")', 'danger');
                return;
            }
            
            hideStatus(container);
            showStatus(container, 'Áthelyezés folyamatban...', 'info');
            
            try {
                const result = await moveMedia(mediaId, parsed.usxCode, parsed.chapter, parsed.verse);
                
                if (result.success) {
                    // Update UI with new location
                    currentUsxCode = parsed.usxCode;
                    currentChapter = parsed.chapter;
                    currentVerse = parsed.verse;
                    updateUiAfterMove(container, currentUsxCode, currentChapter, currentVerse);
                    showStatus(container, 'Sikeresen áthelyezve! Oldal újratöltése...', 'success');
                    
                    // Reload page and scroll to media after a short delay
                    setTimeout(() => {
                        reloadAndScrollToMedia(mediaId);
                    }, 300);
                } else {
                    showStatus(container, result.message || 'Hiba az áthelyezés során', 'danger');
                }
            } catch (error) {
                showStatus(container, `Hiba: ${error.message}`, 'danger');
            }
        });

        deleteBtn.addEventListener('click', async function() {
            if (!confirm('Biztosan törölni szeretnéd ezt a médiaelemet? Ez a művelet nem visszavonható.')) {
                return;
            }
            
            hideStatus(container);
            showStatus(container, 'Média törlése folyamatban...', 'info');
            
            try {
                const response = await fetch(`/api/media/${mediaId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    }
                });
                
                if (!response.ok) {
                    const errorData = await response.json();
                    throw new Error(errorData.message || `HTTP error ${response.status}`);
                }
                
                showStatus(container, 'Média sikeresen törölve! Oldal újratöltése...', 'success');
                
                // Reload page after a short delay
                setTimeout(() => {
                    window.location.reload(true);
                }, 300);
            } catch (error) {
                showStatus(container, `Hiba a média törlése során: ${error.message}`, 'danger');
            }
        });
        
        // Cancel edit (hide controls)
        cancelBtn.addEventListener('click', function() {
            controlsContainer.style.display = 'none';
            toggleBtn.innerHTML = '<i class="bi-pencil"></i> Szerkesztés';
            hideStatus(container);
            
            // Reset input to current location
            inputEl.value = formatUsxLocation(currentUsxCode, currentChapter, currentVerse);
        });
        
        // Allow Enter key in input field to trigger move
        inputEl.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                moveBtn.click();
            }
        });
    }
    
    /**
     * Initialize all editor controls on the page
     */
    function initAllEditorControls() {
        const editorControls = document.querySelectorAll('.editor-media-controls');
        
        editorControls.forEach(container => {
            initEditorControls(container);
        });
        
        if (editorControls.length > 0) {
            console.log(`Editor media move controls initialized for ${editorControls.length} media item(s)`);
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initAllEditorControls();
            checkScrollToMediaAfterLoad();
        });
    } else {
        initAllEditorControls();
        checkScrollToMediaAfterLoad();
    }
    
    // Export for potential manual initialization
    window.EditorMediaMove = {
        init: initAllEditorControls,
        parseUsxFormat,
        formatUsxLocation
    };
    
})();