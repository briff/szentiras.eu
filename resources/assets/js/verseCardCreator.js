/**
 * Verse Card Creator - Polling and interaction logic
 */

class VerseCardCreator {
    constructor() {
        this.sessionId = window.verseCardData.sessionId;
        this.csrfToken = window.verseCardData.csrfToken;
        this.pollInterval = 2000; // 2 seconds
        this.pollingIntervalId = null;
        this.currentState = 'loading'; // loading, candidates, preview, error
        this.isLoadingMore = false;

        this.initializeElements();
        this.attachEventListeners();
        this.startInitialPolling();
    }

    initializeElements() {
        this.container = document.getElementById('verseCardCreator');
        this.loadingState = document.getElementById('loadingState');
        this.candidateChooserState = document.getElementById('candidateChooserState');
        this.finalPreviewState = document.getElementById('finalPreviewState');
        this.errorState = document.getElementById('errorState');
        this.candidatesGrid = document.getElementById('candidatesGrid');
        this.moreButton = document.getElementById('moreButton');
        this.moreButtonText = document.getElementById('moreButtonText');
        this.moreSpinner = document.getElementById('moreSpinner');
        this.downloadButton = document.getElementById('downloadButton');
        this.newCardButton = document.getElementById('newCardButton');
        this.finalPreviewImage = document.getElementById('finalPreviewImage');
        this.errorMessage = document.getElementById('errorMessage');
        this.retryButton = document.getElementById('retryButton');
        this.statusMessage = document.getElementById('statusMessage');
        this.verseRefInput = document.getElementById('verseRefInput');
        this.verseTextInput = document.getElementById('verseTextInput');
        this.saveEditsButton = document.getElementById('saveEditsButton');
        this.cancelEditsButton = document.getElementById('cancelEditsButton');
        this.editFieldsSection = document.getElementById('editFieldsSection');
        this.selectionProgressState = document.getElementById('selectionProgressState');
        this.selectionProgressMessage = document.getElementById('selectionProgressMessage');

        // Ensure buttons are enabled on initialization
        this.enableEditButtons();
    }

    attachEventListeners() {
        this.moreButton.addEventListener('click', () => this.handleMoreClick());
        this.newCardButton.addEventListener('click', () => this.handleNewCardClick());
        this.retryButton.addEventListener('click', () => this.handleRetryClick());
        this.saveEditsButton.addEventListener('click', () => this.handleSaveEdits());
        this.cancelEditsButton.addEventListener('click', () => this.handleCancelEdits());

        // We don't end session, to let the user come back
    }

    /**
     * Start polling for initial 4 candidates
     */
    startInitialPolling() {
        this.setState('loading');
        this.pollForCandidates();
    }

    /**
     * Poll for candidates until 4 are ready
     */
    pollForCandidates() {
        this.fetchSessionStatus()
            .then(data => {
                this.updateStatusDisplay(data.status);
                if (data.status === 'downloading' && data.candidates && data.candidates.length > 0) {
                    // Show placeholders with metadata while images download
                    this.displayCandidatePlaceholders(data.candidates);
                    this.setState('candidates');
                    this.disableCandidateSelection();
                    this.pollingIntervalId = setTimeout(() => this.pollForCandidates(), this.pollInterval);
                } else if (data.status === 'choosing') {
                    this.stopPolling();
                    this.displayCandidates(data.candidates);
                    this.setState('candidates');
                    this.enableCandidateSelection();
                } else if (data.status === 'ready') {
                    this.stopPolling();
                    this.displayFinalPreview(
                        data.final_url,
                        data.download_url,
                        data.width,
                        data.height,
                        data.pixabay_page_url,
                        data.pixabay_user
                    );
                    this.setState('preview');
                } else if (data.status === 'failed') {
                    this.stopPolling();
                    this.showError(data.message || 'Hiba történt a jelöltek keresése közben');
                    this.setState('error');
                } else {
                    // Continue polling
                    this.pollingIntervalId = setTimeout(() => this.pollForCandidates(), this.pollInterval);
                }
            })
            .catch(error => {
                console.error('Error polling for candidates:', error);
                this.stopPolling();
                this.showError('Hálózati hiba történt. Kérjük, próbálja újra.');
                this.setState('error');
            });
    }

    /**
     * Handle "More" button click - request more candidates
     */
    handleMoreClick() {
        if (this.isLoadingMore) return;

        this.isLoadingMore = true;
        this.moreButton.disabled = true;
        this.moreSpinner.style.display = 'inline-block';
        this.updateMoreButtonStatus('Keresés...');

        this.fetchMoreCandidates()
            .then(data => {
                if (data.status === 'choosing' && data.candidates && data.candidates.length >= 4) {
                    this.displayCandidates(data.candidates);
                    this.isLoadingMore = false;
                    this.moreButton.disabled = false;
                    this.moreSpinner.style.display = 'none';
                    this.updateMoreButtonStatus('Több jelölt');
                } else {
                    // Keep existing images, just start polling for new candidates
                    this.pollForMoreCandidates();
                }
            })
            .catch(error => {
                console.error('Error fetching more candidates:', error);
                this.isLoadingMore = false;
                this.moreButton.disabled = false;
                this.moreSpinner.style.display = 'none';
                this.updateMoreButtonStatus('Több jelölt');
                this.showError('Hiba történt a további jelöltek keresése közben');
            });
    }

    /**
     * Poll for more candidates after /more request
     */
    pollForMoreCandidates() {
        this.fetchSessionStatus()
            .then(data => {
                this.updateStatusDisplay(data.status);
                this.updateMoreButtonStatus(this.getStatusLabel(data.status));
                if (data.status === 'downloading' && data.candidates && data.candidates.length > 0) {
                    // Show placeholders while new batch downloads
                    this.displayCandidatePlaceholders(data.candidates);
                    this.disableCandidateSelection();
                    this.pollingIntervalId = setTimeout(() => this.pollForMoreCandidates(), this.pollInterval);
                } else if (data.status === 'choosing' && data.candidates && data.candidates.length >= 4) {
                    this.displayCandidates(data.candidates);
                    this.enableCandidateSelection();
                    this.isLoadingMore = false;
                    this.moreButton.disabled = false;
                    this.moreSpinner.style.display = 'none';
                    this.updateMoreButtonStatus('Több jelölt');
                } else if (data.status === 'failed') {
                    this.isLoadingMore = false;
                    this.moreButton.disabled = false;
                    this.moreSpinner.style.display = 'none';
                    this.updateMoreButtonStatus('Több jelölt');
                    this.showError(data.message || 'Hiba történt a jelöltek keresése közben');
                } else {
                    // Continue polling
                    this.pollingIntervalId = setTimeout(() => this.pollForMoreCandidates(), this.pollInterval);
                }
            })
            .catch(error => {
                console.error('Error polling for more candidates:', error);
                this.isLoadingMore = false;
                this.moreButton.disabled = false;
                this.moreSpinner.style.display = 'none';
                this.updateMoreButtonStatus('Több jelölt');
                this.showError('Hálózati hiba történt. Kérjük, próbálja újra.');
            });
    }

    /**
     * Display candidate placeholders while images are still downloading.
     * Shows Pixabay attribution immediately; image area shows a spinner.
     */
    displayCandidatePlaceholders(candidates) {
        const existingCards = this.candidatesGrid.querySelectorAll('.candidate-card');

        candidates.forEach((candidate, index) => {
            let card = existingCards[index];

            // If no existing card, create a new one
            if (!card) {
                card = document.createElement('div');
                card.className = 'candidate-card border border-gray-200 rounded-lg overflow-hidden transition-all duration-200 hover:shadow-lg hover:border-blue-600';
                this.candidatesGrid.appendChild(card);
            }

            // Update card attributes
            card.setAttribute('data-candidate-id', candidate.id);

            // Find image wrapper (first child div)
            let imgWrapper = card.querySelector('div:first-child');
            if (!imgWrapper) {
                imgWrapper = document.createElement('div');
                imgWrapper.className = 'flex items-center justify-center bg-gray-100';
                imgWrapper.style.height = '200px';
                card.insertBefore(imgWrapper, card.firstChild);
            }

            // Clear wrapper and add spinner (remove any existing images)
            imgWrapper.innerHTML = '';
            const spinner = document.createElement('div');
            spinner.className = 'spinner-border text-secondary';
            spinner.setAttribute('role', 'status');
            spinner.innerHTML = '<span class="visually-hidden">Letöltés...</span>';
            imgWrapper.appendChild(spinner);

            // Find or create card body
            let cardBody = card.querySelector('.p-3');
            if (!cardBody) {
                cardBody = document.createElement('div');
                cardBody.className = 'p-3';
                card.appendChild(cardBody);
            }

            // Clear card body and rebuild
            cardBody.innerHTML = '';

            // Add attribution
            const attribution = document.createElement('p');
            attribution.className = 'text-sm text-gray-600 mb-2';
            if (candidate.pixabay_user && candidate.pixabay_page_url) {
                attribution.innerHTML = `Pixabay • <a href="${candidate.pixabay_page_url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">${this.escapeHtml(candidate.pixabay_user)}</a>`;
            } else {
                attribution.textContent = 'Pixabay';
            }
            cardBody.appendChild(attribution);

            // Add orientation indicator if available
            if (candidate.orientation) {
                const orientationDiv = document.createElement('div');
                orientationDiv.className = 'flex items-center gap-1 text-xs text-gray-500';
                const icon = this.getOrientationIcon(candidate.orientation);
                orientationDiv.innerHTML = `${icon} <span>${this.getOrientationLabel(candidate.orientation)}</span>`;
                cardBody.appendChild(orientationDiv);
            }
        });
    }

    /**
     * Display candidate thumbnails (all ready)
     * Reuses existing placeholder cards and updates their images
     */
    displayCandidates(candidates) {
        let existingCards = this.candidatesGrid.querySelectorAll('.candidate-card');

        // if no existingCards yet, we create the placeholders first
        if (existingCards.length === 0) {
            this.displayCandidatePlaceholders(candidates);
            existingCards = this.candidatesGrid.querySelectorAll('.candidate-card');            
        }
               
        candidates.forEach((candidate, index) => {
            let card = existingCards[index];

            // Update card attributes
            card.setAttribute('data-candidate-id', candidate.id);
            card.setAttribute('data-candidate-index', index);

            // Find image wrapper (first child div)
            let imgWrapper = card.querySelector('div:first-child');
            if (!imgWrapper) {
                imgWrapper = document.createElement('div');
                imgWrapper.className = 'flex items-center justify-center bg-gray-100';
                imgWrapper.style.height = '200px';
                card.insertBefore(imgWrapper, card.firstChild);
            }

            // Clear wrapper and add image
            imgWrapper.innerHTML = '';
            const img = document.createElement('img');
            img.src = candidate.thumb_url;
            img.alt = `Jelölt ${index + 1}`;
            img.style.height = '200px';
            img.style.width = '100%';
            img.style.objectFit = 'cover';
            imgWrapper.appendChild(img);

            // Find or create card body
            let cardBody = card.querySelector('.p-3');
            if (!cardBody) {
                cardBody = document.createElement('div');
                cardBody.className = 'p-3';
                card.appendChild(cardBody);
            }

            // Clear card body and rebuild
            cardBody.innerHTML = '';

            // Add attribution
            const attribution = document.createElement('p');
            attribution.className = 'text-sm text-gray-600 mb-2';
            attribution.innerHTML = `Pixabay • <a href="${candidate.pixabay_page_url}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">${this.escapeHtml(candidate.pixabay_user)}</a>`;
            cardBody.appendChild(attribution);

            // Add orientation indicator if available
            if (candidate.orientation) {
                const orientationDiv = document.createElement('div');
                orientationDiv.className = 'flex items-center gap-1 text-xs text-gray-500';
                const icon = this.getOrientationIcon(candidate.orientation);
                orientationDiv.innerHTML = `${icon} <span>${this.getOrientationLabel(candidate.orientation)}</span>`;
                cardBody.appendChild(orientationDiv);
            }

            // Add event listeners (safe to add multiple times)
            card.removeEventListener('click', card._clickHandler);
            card._clickHandler = () => this.handleCandidateSelect(candidate.id);
            card.addEventListener('click', card._clickHandler);

            card.removeEventListener('mouseenter', card._mouseEnterHandler);
            card._mouseEnterHandler = () => card.classList.add('shadow-lg');
            card.addEventListener('mouseenter', card._mouseEnterHandler);

            card.removeEventListener('mouseleave', card._mouseLeaveHandler);
            card._mouseLeaveHandler = () => card.classList.remove('shadow-lg');
            card.addEventListener('mouseleave', card._mouseLeaveHandler);
        });
    }

    /**
     * Handle candidate selection
     */
    handleCandidateSelect(candidateId) {
        // Disable all candidates and more button during selection
        this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
            card.style.pointerEvents = 'none';
            card.style.opacity = '0.6';
        });
        this.moreButton.disabled = true;

        // Show progress indicator
        this.showSelectionProgress();

        this.fetchSelectCandidate(candidateId)
            .then(data => {
                if (data.status === 'ready') {
                    this.stopPolling();
                    this.displayFinalPreview(
                        data.final_url,
                        data.download_url,
                        data.width,
                        data.height,
                        data.pixabay_page_url,
                        data.pixabay_user
                    );
                    this.setState('preview');
                    this.hideSelectionProgress();
                } else {
                    // Start polling for final preview
                    this.pollForFinalPreview();
                }
            })
            .catch(error => {
                console.error('Error selecting candidate:', error);
                // Re-enable candidates
                this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
                    card.style.pointerEvents = 'auto';
                    card.style.opacity = '1';
                });
                this.moreButton.disabled = false;
                this.hideSelectionProgress();
                this.showError('Hiba történt a kép kiválasztása közben');
            });
    }

    /**
     * Poll for final preview after /select request
     */
    pollForFinalPreview() {
        this.fetchSessionStatus()
            .then(data => {
                this.updateStatusDisplay(data.status);
                this.updateSelectionProgressMessage(data.status);
                if (data.status === 'ready') {
                    this.stopPolling();
                    this.displayFinalPreview(
                        data.final_url,
                        data.download_url,
                        data.width,
                        data.height,
                        data.pixabay_page_url,
                        data.pixabay_user
                    );
                    this.setState('preview');
                    this.hideSelectionProgress();
                    this.enableEditButtons();
                } else if (data.status === 'failed') {
                    this.stopPolling();
                    this.hideSelectionProgress();
                    // Re-enable candidates
                    this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
                        card.style.pointerEvents = 'auto';
                        card.style.opacity = '1';
                    });
                    this.moreButton.disabled = false;
                    this.showError(data.message || 'Hiba történt a végső kép renderelése közben');
                } else {
                    // Continue polling
                    this.pollingIntervalId = setTimeout(() => this.pollForFinalPreview(), this.pollInterval);
                }
            })
            .catch(error => {
                console.error('Error polling for final preview:', error);
                this.hideSelectionProgress();
                // Re-enable candidates
                this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
                    card.style.pointerEvents = 'auto';
                    card.style.opacity = '1';
                });
                this.moreButton.disabled = false;
                this.showError('Hálózati hiba történt. Kérjük, próbálja újra.');
            });
    }

    /**
     * Display final preview
     */
    displayFinalPreview(finalUrl, downloadUrl, width = null, height = null, pixabayPageUrl = null, pixabayUser = null) {
        // Force image reload by clearing src first, then setting new src
        this.finalPreviewImage.src = '';
        
        // Set aspect ratio if dimensions are provided
        if (width && height) {
            const aspectRatio = width / height;
            this.finalPreviewImage.style.aspectRatio = aspectRatio.toString();
        } else {
            // Fallback to 4:3 if dimensions not provided
            this.finalPreviewImage.style.aspectRatio = '4/3';
        }
        
        // Use a small delay to ensure the src is cleared before setting new one
        setTimeout(() => {
            this.finalPreviewImage.src = finalUrl;
        }, 0);
        this.downloadButton.href = downloadUrl;
        
        // Create or update Pixabay link
        this.updatePixabayLink(pixabayPageUrl, pixabayUser);
    }
    
    /**
     * Update Pixabay link in the final preview state
     */
    updatePixabayLink(pixabayPageUrl, pixabayUser) {
        // Find or create the Pixabay link container
        let pixabayLinkContainer = document.getElementById('pixabayLinkContainer');
        
        if (!pixabayLinkContainer) {
            // Create container if it doesn't exist
            pixabayLinkContainer = document.createElement('div');
            pixabayLinkContainer.id = 'pixabayLinkContainer';
            pixabayLinkContainer.className = 'text-center text-sm';
            
            // Insert after the final preview image container
            const finalPreviewContainer = this.finalPreviewImage.parentElement;
            finalPreviewContainer.parentNode.insertBefore(pixabayLinkContainer, finalPreviewContainer.nextSibling);
        }
        
        // Update the link content
        if (pixabayPageUrl) {
            const userText = pixabayUser ? ` (${pixabayUser})` : '';
            pixabayLinkContainer.innerHTML = `
                <a href="${pixabayPageUrl}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1 hover:underline">
                    <i class="bi bi-link-45deg"></i>
                    Pixabay oldal${userText}
                </a>
            `;
        } else {
            pixabayLinkContainer.innerHTML = '';
        }
    }

    /**
     * Handle save edits button click
     */
    handleSaveEdits() {
        const verseRef = this.verseRefInput.value.trim();
        const verseText = this.verseTextInput.value.trim();

        if (!verseRef || !verseText) {
            this.showError('Kérjük, töltse ki mindkét mezőt');
            return;
        }

        // Disable buttons during save
        this.saveEditsButton.disabled = true;
        this.cancelEditsButton.disabled = true;

        this.fetchUpdateAndRender(verseRef, verseText)
            .then(data => {
                if (data.status === 'processing' || data.status === 'rendering') {
                    // Start polling for final preview
                    this.setState('loading');
                    this.pollForFinalPreview();
                } else {
                    this.saveEditsButton.disabled = false;
                    this.cancelEditsButton.disabled = false;
                    this.showError('Hiba történt a frissítés közben');
                }
            })
            .catch(error => {
                console.error('Error saving edits:', error);
                this.saveEditsButton.disabled = false;
                this.cancelEditsButton.disabled = false;
                this.showError('Hálózati hiba történt. Kérjük, próbálja újra.');
            });
    }

    /**
     * Handle cancel edits button click
     */
    handleCancelEdits() {
        // Clear the input fields
        this.verseRefInput.value = '';
        this.verseTextInput.value = '';
    }

    /**
     * Handle new card button click
     */
    handleNewCardClick() {
        // Navigate back to theme selection or close
        window.history.back();
    }

    /**
     * Handle retry button click
     */
    handleRetryClick() {
        this.setState('loading');
        this.startInitialPolling();
    }

    /**
     * Fetch session status
     */
    fetchSessionStatus() {
        return fetch(`/verse-card/status/${this.sessionId}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
            },
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
    }

    /**
     * Fetch more candidates
     */
    fetchMoreCandidates() {
        return fetch(`/verse-card/more/${this.sessionId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
    }

    /**
     * Fetch select candidate
     */
    fetchSelectCandidate(candidateId) {
        return fetch(`/verse-card/select/${this.sessionId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ candidate_id: candidateId }),
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
    }

    /**
     * Fetch update session and trigger re-render
     */
    fetchUpdateAndRender(verseRef, verseText) {
        return fetch(`/verse-card/update/${this.sessionId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                verse_ref: verseRef,
                verse_text: verseText,
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            });
    }

    /**
     * End session (best-effort)
     */
    endSession() {
        // Fire and forget - don't wait for response
        fetch(`/verse-card/end/${this.sessionId}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
            },
            keepalive: true, // Allow request to complete even if page unloads
        }).catch(() => {
            // Silently ignore errors
        });
    }

    /**
     * Set UI state
     */
    setState(state) {
        this.currentState = state;

        // Hide all states
        this.loadingState.style.display = 'none';
        this.candidateChooserState.style.display = 'none';
        this.finalPreviewState.style.display = 'none';
        this.errorState.style.display = 'none';

        // Show current state
        switch (state) {
            case 'loading':
                this.loadingState.style.display = 'block';
                break;
            case 'candidates':
                this.candidateChooserState.style.display = 'block';
                break;
            case 'preview':
                this.finalPreviewState.style.display = 'block';
                break;
            case 'error':
                this.errorState.style.display = 'block';
                break;
        }
    }

    /**
     * Get status label for a given status
     */
    getStatusLabel(status) {
        const statusLabels = {
            'initializing': 'Képek keresése...',
            'downloading': 'Letöltés...',
            'choosing': 'Kiválasztás...',
            'rendering': 'Renderelés...',
            'ready': 'Kész!',
            'failed': 'Hiba történt',
            'expired': 'Munkamenet lejárt',
            'ended': 'Munkamenet befejeződött',
        };

        return statusLabels[status] || status;
    }

    /**
     * Update status display during polling
     */
    updateStatusDisplay(status) {
        if (!this.statusMessage) return;

        const statusLabels = {
            'initializing': 'Képek keresése...',
            'downloading': 'Képek letöltése...',
            'choosing': 'Képek keresése...',
            'rendering': 'Végső kép renderelése...',
            'ready': 'Kész!',
            'failed': 'Hiba történt',
            'expired': 'Munkamenet lejárt',
            'ended': 'Munkamenet befejeződött',
        };

        this.statusMessage.textContent = statusLabels[status] || status;
    }

    /**
     * Update the "More" button text with current status
     */
    updateMoreButtonStatus(statusText) {
        if (this.moreButtonText) {
            this.moreButtonText.textContent = statusText;
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        this.errorMessage.textContent = message;
    }

    /**
     * Stop polling
     */
    stopPolling() {
        if (this.pollingIntervalId) {
            clearTimeout(this.pollingIntervalId);
            this.pollingIntervalId = null;
        }
    }

    /**
     * Enable edit buttons
     */
    enableEditButtons() {
        if (this.saveEditsButton) {
            this.saveEditsButton.disabled = false;
        }
        if (this.cancelEditsButton) {
            this.cancelEditsButton.disabled = false;
        }
    }

    /**
     * Disable candidate selection (during downloading state)
     */
    disableCandidateSelection() {
        this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
            card.style.pointerEvents = 'none';
            card.style.opacity = '0.5';
            card.style.cursor = 'not-allowed';
        });
        this.moreButton.disabled = true;
    }

    /**
     * Enable candidate selection (when choosing state is ready)
     */
    enableCandidateSelection() {
        this.candidatesGrid.querySelectorAll('.candidate-card').forEach(card => {
            card.style.pointerEvents = 'auto';
            card.style.opacity = '1';
            card.style.cursor = 'pointer';
        });
        this.moreButton.disabled = false;
    }

    /**
     * Show selection progress indicator
     */
    showSelectionProgress() {
        if (this.selectionProgressState) {
            this.selectionProgressState.style.display = 'block';
        }
    }

    /**
     * Hide selection progress indicator
     */
    hideSelectionProgress() {
        if (this.selectionProgressState) {
            this.selectionProgressState.style.display = 'none';
        }
    }

    /**
     * Update selection progress message based on status
     */
    updateSelectionProgressMessage(status) {
        if (!this.selectionProgressMessage) return;

        const statusLabels = {
            'initializing': 'Képek keresése...',
            'downloading': 'Képek letöltése...',
            'choosing': 'Képek keresése...',
            'rendering': 'Végső kép renderelése...',
            'processing': 'Feldolgozás...',
            'ready': 'Kész!',
            'failed': 'Hiba történt',
            'expired': 'Munkamenet lejárt',
            'ended': 'Munkamenet befejeződött',
        };

        this.selectionProgressMessage.textContent = statusLabels[status] || status;
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Get SVG icon for image orientation
     */
    getOrientationIcon(orientation) {
        const icons = {
            'horizontal': '<svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="6" width="20" height="12" rx="1" stroke="currentColor" stroke-width="1" fill="none"/></svg>',
            'vertical': '<svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 24 24"><rect x="6" y="2" width="12" height="20" rx="1" stroke="currentColor" stroke-width="1" fill="none"/></svg>',
            'square': '<svg class="w-3 h-3 inline" fill="currentColor" viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="16" rx="1" stroke="currentColor" stroke-width="1" fill="none"/></svg>',
            'unknown': '',
        };
        return icons[orientation] || icons['unknown'];
    }

    /**
     * Get label for image orientation
     */
    getOrientationLabel(orientation) {
        const labels = {
            'horizontal': 'Vízszintes',
            'vertical': 'Függőleges',
            'square': 'Négyzet'
        };
        return labels[orientation]??'';
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new VerseCardCreator();
});
