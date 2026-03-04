/**
 * Commentary Status Polling
 * Polls for commentary status every 5 seconds and updates the UI.
 *
 * Handles two cases:
 * 1. No commentaries exist yet — the `#status-{index}` container is present.
 * 2. Commentaries exist but some are pending/processing — `.commentary-pending-section`
 *    elements are rendered inside the details block.
 */

class CommentaryStatusPoller {
    constructor() {
        this.pollingIntervals = new Map();
        this.pollInterval = 5000; // 5 seconds
        this.generatingContainers = new Set(); // tracks containers where generation was triggered
        this.init();
        this.setupGenerationButtons();
    }

    init() {
        // Case 1: containers with no commentaries at all (status-{index} div present)
        const containers = document.querySelectorAll('.commentary-container');
        containers.forEach((container) => {
            const reference = container.dataset.reference;
            const translation = container.dataset.translation;
            const containerIndex = container.dataset.containerIndex;

            const statusContainer = document.getElementById(`status-${containerIndex}`);
            if (statusContainer) {
                // Do an initial status check; only start continuous polling if
                // there is already a pending/processing commentary in the database.
                this.checkAndStartPollingIfActive(reference, translation, containerIndex);
            }
        });

        // Case 3: placeholders for pending commentaries
        const pendingPlaceholders = document.querySelectorAll('.commentary-pending-placeholder');
        pendingPlaceholders.forEach((placeholder) => {
            const reference = placeholder.dataset.reference;
            const translation = placeholder.dataset.translation;
            const containerIndex = placeholder.dataset.containerIndex;

            if (!this.pollingIntervals.has(containerIndex)) {
                this.generatingContainers.add(containerIndex);
                this.startPollingForPendingPlaceholder(reference, translation, containerIndex);
            }
        });
    }

    checkAndStartPollingIfActive(reference, translation, containerIndex) {
        fetch(`/api/commentaries/status?reference=${encodeURIComponent(reference)}&translation=${encodeURIComponent(translation)}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                if (data.status === 'pending' || data.status === 'processing') {
                    // A commentary is already being generated — show spinner and start polling
                    this.generatingContainers.add(containerIndex);
                    const statusContainer = document.getElementById(`status-${containerIndex}`);
                    if (statusContainer) {
                        statusContainer.innerHTML = this.buildSpinnerHtml();
                    }
                    this.startPolling(reference, translation, containerIndex);
                } else if (data.status === 'completed') {
                    location.reload();
                }
                // For 'not_found' or 'failed': do nothing — leave the generate button visible
            })
            .catch((error) => {
                console.error('Error checking commentary status on init:', error);
            });
    }

    setupGenerationButtons() {
        // Find all commentary generator forms
        const forms = document.querySelectorAll('.commentary-generator form');
        forms.forEach((form) => {
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                this.handleGenerateCommentary(form);
            });
        });
    }

    handleGenerateCommentary(form) {
        const reference = form.querySelector('input[name="reference"]').value;
        const translation = form.querySelector('input[name="translation"]').value;
        const csrfToken = form.querySelector('input[name="_token"]').value;

        // Find the parent commentary container to get the container index
        const commentaryContainer = form.closest('.commentary-container');
        if (!commentaryContainer) {
            console.error('Could not find parent commentary container for form');
            return;
        }
        
        const containerIndex = commentaryContainer.dataset.containerIndex;
        if (!containerIndex) {
            console.error('Commentary container missing data-container-index attribute');
            return;
        }

        // Immediately hide the form and show a pending status indicator
        form.style.display = 'none';
        const statusContainer = document.getElementById(`status-${containerIndex}`);
        if (statusContainer) {
            statusContainer.innerHTML = this.buildSpinnerHtml();
        }

        // Send the generation request
        fetch('/editor/commentaries/generate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                reference: reference,
                translation: translation,
            }),
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                // Mark this container as actively generating before polling starts
                this.generatingContainers.add(containerIndex);
                // Start polling for this container
                this.startPolling(reference, translation, containerIndex);
            })
            .catch((error) => {
                console.error('Error generating commentary:', error);
                // Restore the form on error
                form.style.display = '';
                if (statusContainer) {
                    statusContainer.innerHTML = '';
                }
                alert('Hiba történt a kommentár generálása során. Kérjük, próbálja újra.');
            });
    }

    /**
     * Start polling for a container that has no commentaries yet (uses #status-{index}).
     */
    startPolling(reference, translation, containerIndex) {
        // Initial fetch
        this.fetchStatus(reference, translation, containerIndex);

        // Set up interval
        const intervalId = setInterval(() => {
            this.fetchStatus(reference, translation, containerIndex);
        }, this.pollInterval);

        this.pollingIntervals.set(containerIndex, intervalId);
    }

    /**
     * Start polling for pending/processing commentaries (both sections and placeholders).
     */
    startPollingForPending(reference, translation, containerIndex, selector) {
        // Initial fetch
        this.fetchStatusForPending(reference, translation, containerIndex, selector);

        const intervalId = setInterval(() => {
            this.fetchStatusForPending(reference, translation, containerIndex, selector);
        }, this.pollInterval);

        this.pollingIntervals.set(containerIndex, intervalId);
    }

    /**
     * Start polling for a container that already has some completed commentaries
     * but also has pending/processing ones rendered as `.commentary-pending-section`.
     */
    startPollingForPendingSection(reference, translation, containerIndex) {
        this.startPollingForPending(reference, translation, containerIndex, '.commentary-pending-section');
    }

    /**
     * Start polling for a placeholder within the exact commentaries section.
     */
    startPollingForPendingPlaceholder(reference, translation, containerIndex) {
        this.startPollingForPending(reference, translation, containerIndex, '.commentary-pending-placeholder');
    }

    fetchStatus(reference, translation, containerIndex) {
        const statusContainer = document.getElementById(`status-${containerIndex}`);
        if (!statusContainer) {
            // Container no longer exists, stop polling
            this.stopPolling(containerIndex);
            return;
        }

        fetch(`/api/commentaries/status?reference=${encodeURIComponent(reference)}&translation=${encodeURIComponent(translation)}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                this.updateStatusDisplay(data, statusContainer, reference, translation, containerIndex);
            })
            .catch((error) => {
                console.error('Error fetching commentary status:', error);
            });
    }

    fetchStatusForPending(reference, translation, containerIndex, selector) {
        fetch(`/api/commentaries/status?reference=${encodeURIComponent(reference)}&translation=${encodeURIComponent(translation)}`)
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                if (data.status === 'completed') {
                    // Reload to show the newly completed commentary in the proper section
                    this.stopPolling(containerIndex);
                    location.reload();
                } else if (data.status === 'failed' || data.status === 'not_found') {
                    // Remove the pending element and stop polling
                    this.stopPolling(containerIndex);
                    const element = document.querySelector(
                        `${selector}[data-container-index="${containerIndex}"]`
                    );
                    if (element) {
                        element.remove();
                    }
                } else if (data.status === 'pending' || data.status === 'processing') {
                    // Update the element to show current status
                    const element = document.querySelector(
                        `${selector}[data-container-index="${containerIndex}"]`
                    );
                    if (element) {
                        const statusText = data.status === 'processing' ? 'készül...' : 'készítése sorba állítva...';
                        element.innerHTML = this.buildSpinnerHtml(statusText);
                    }
                }
            })
            .catch((error) => {
                console.error('Error fetching commentary status:', error);
            });
    }

    fetchStatusForPendingSection(reference, translation, containerIndex) {
        this.fetchStatusForPending(reference, translation, containerIndex, '.commentary-pending-section');
    }

    fetchStatusForPendingPlaceholder(reference, translation, containerIndex) {
        this.fetchStatusForPending(reference, translation, containerIndex, '.commentary-pending-placeholder');
    }

    updateStatusDisplay(data, statusContainer, reference, translation, containerIndex) {
        // Filter out failed commentaries - they don't exist
        if (data.status === 'failed') {
            statusContainer.innerHTML = '';
            this.stopPolling(containerIndex);
            return;
        }

        if (data.status === 'completed') {
            // Commentary is complete - reload the page to show the completed commentary
            location.reload();
            return;
        }

        if (data.status === 'pending' || data.status === 'processing') {
            // Show loading state with rotating icon
            statusContainer.innerHTML = this.buildSpinnerHtml(data.status === 'processing' ? 'készül...' : 'készítése sorba állítva...');
            return;
        }

        // For 'not_found' status: if generation was triggered in this session, keep the
        // spinner visible (job queued but not yet picked up by the worker).
        // Otherwise (page load with no active generation), stop polling and leave the button.
        if (this.generatingContainers.has(containerIndex)) {
            statusContainer.innerHTML = this.buildSpinnerHtml();
        } else {
            // No active generation — stop polling, leave the generate button visible
            this.stopPolling(containerIndex);
        }
    }

    buildSpinnerHtml(statusText = 'létrehozása folyamatban...') {
        return `
            <div class="commentaries mt-4 mb-4 parsedVerses">
                <div class="border border-yellow-200 rounded-lg bg-yellow-50 overflow-hidden">
                    <div class="flex items-center px-4 py-3 bg-yellow-100">
                        <span class="loading mr-2">⏳</span>
                        <span class="text-sm text-gray-700">Kommentár ${statusText}</span>
                    </div>
                </div>
            </div>
        `;
    }

    stopPolling(containerIndex) {
        const intervalId = this.pollingIntervals.get(containerIndex);
        if (intervalId) {
            clearInterval(intervalId);
            this.pollingIntervals.delete(containerIndex);
        }
    }

    stopAllPolling() {
        this.pollingIntervals.forEach((intervalId) => {
            clearInterval(intervalId);
        });
        this.pollingIntervals.clear();
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        new CommentaryStatusPoller();
    });
} else {
    new CommentaryStatusPoller();
}
