/**
 * Verse Card Dialog - Theme selection and session creation
 */

export class VerseCardDialog {
    constructor() {
        this.modal = document.getElementById('verseCardModal');
        if (!this.modal) return;

        this.initializeElements();
        this.attachEventListeners();
    }

    initializeElements() {
        // Steps
        this.verseSelectionStep = document.getElementById('verseSelectionStep');
        this.themeSelectionStep = document.getElementById('themeSelectionStep');
        this.loadingState = document.getElementById('loadingState');
        this.errorState = document.getElementById('errorState');

        // Verse selection
        this.checkboxes = document.querySelectorAll('.verse-checkbox');
        this.selectAllBtn = document.getElementById('selectAll');
        this.deselectAllBtn = document.getElementById('deselectAll');
        this.findThemesBtn = document.getElementById('findThemesBtn');

        // Theme selection
        this.themesList = document.getElementById('themesList');
        this.backToVersesBtn = document.getElementById('backToVersesBtn');

        // Error handling
        this.errorMessage = document.getElementById('errorMessage');
        this.errorRetryBtn = document.getElementById('errorRetryBtn');
        this.loadingMessage = document.getElementById('loadingMessage');

        // Modal body for data
        this.modalBody = this.modal.querySelector('.modal-body');
        this.csrfToken = this.modalBody.getAttribute('data-csrf-token');
        this.translationAbbrev = this.modalBody.getAttribute('data-translation-abbrev');
    }

    attachEventListeners() {
        // Verse selection
        this.checkboxes.forEach(cb => cb.addEventListener('change', () => this.updateFindButton()));
        this.selectAllBtn.addEventListener('click', () => this.selectAllVerses());
        this.deselectAllBtn.addEventListener('click', () => this.deselectAllVerses());
        this.findThemesBtn.addEventListener('click', () => this.handleFindThemes());

        // Theme selection
        this.backToVersesBtn.addEventListener('click', () => this.backToVerseSelection());

        // Error handling
        this.errorRetryBtn.addEventListener('click', () => this.handleErrorRetry());
    }

    /**
     * Update find button state based on checkbox selection
     */
    updateFindButton() {
        const hasSelected = Array.from(this.checkboxes).some(cb => cb.checked);
        this.findThemesBtn.disabled = !hasSelected;
    }

    /**
     * Select all verses
     */
    selectAllVerses() {
        this.checkboxes.forEach(cb => cb.checked = true);
        this.updateFindButton();
    }

    /**
     * Deselect all verses
     */
    deselectAllVerses() {
        this.checkboxes.forEach(cb => cb.checked = false);
        this.updateFindButton();
    }

    /**
     * Handle find themes button click
     */
    handleFindThemes() {
        const selectedVerses = Array.from(this.checkboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value)
            .join(';');

        if (!selectedVerses) {
            this.showError('Kérjük, válassz ki legalább egy verset!');
            return;
        }

        this.showLoading('Témák keresése...');

        fetch('/verse-card/find-themes', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                selectedVerses: selectedVerses,
                translationAbbrev: this.translationAbbrev,
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success && data.themes && data.themes.length > 0) {
                    this.displayThemes(data.themes);
                    this.showThemeSelection();
                } else {
                    this.showError('Nem találtunk témákat a kiválasztott versekhez.');
                }
            })
            .catch(error => {
                console.error('Error finding themes:', error);
                this.showError('Hiba történt a témák keresése közben. Kérjük, próbálja újra.');
            });
    }

    /**
     * Display themes in the list
     */
    displayThemes(themes) {
        this.themesList.innerHTML = '';

        themes.forEach(theme => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action text-start';
            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${this.escapeHtml(theme.hungarian_keyword)}</strong>
                        ${theme.photo_keywords ? `<div class="text-muted small">${this.escapeHtml(theme.photo_keywords)}</div>` : ''}
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </div>
            `;
            item.addEventListener('click', () => this.handleThemeSelect(theme));
            this.themesList.appendChild(item);
        });
    }

    /**
     * Handle theme selection
     */
    handleThemeSelect(theme) {
        const selectedVerses = Array.from(this.checkboxes)
            .filter(cb => cb.checked)
            .map(cb => ({
                reference: cb.value,
                text: cb.getAttribute('data-text'),
            }));

        if (selectedVerses.length === 0) {
            this.showError('Nincs kiválasztott vers.');
            return;
        }

        this.showLoading('Igés kártya munkamenet létrehozása...');

        fetch('/verse-card/create', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                verse_refs: selectedVerses.map(v => v.reference),
                verse_texts: selectedVerses.map(v => v.text),
                theme_id: theme.id,
                keywords: theme.photo_keywords ? theme.photo_keywords.split(',').map(k => k.trim()) : [],
            }),
        })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.session_id) {
                    // Close modal and navigate to creator page
                    const modal = bootstrap.Modal.getInstance(this.modal);
                    modal.hide();
                    window.location.href = `/verse-card/creator/${data.session_id}`;
                } else {
                    this.showError('Hiba történt a munkamenet létrehozása közben.');
                }
            })
            .catch(error => {
                console.error('Error creating session:', error);
                this.showError('Hálózati hiba történt. Kérjük, próbálja újra.');
            });
    }

    /**
     * Show verse selection step
     */
    backToVerseSelection() {
        this.verseSelectionStep.style.display = 'block';
        this.themeSelectionStep.style.display = 'none';
        this.loadingState.style.display = 'none';
        this.errorState.style.display = 'none';
    }

    /**
     * Show theme selection step
     */
    showThemeSelection() {
        this.verseSelectionStep.style.display = 'none';
        this.themeSelectionStep.style.display = 'block';
        this.loadingState.style.display = 'none';
        this.errorState.style.display = 'none';
    }

    /**
     * Show loading state
     */
    showLoading(message = 'Feldolgozás...') {
        this.loadingMessage.textContent = message;
        this.verseSelectionStep.style.display = 'none';
        this.themeSelectionStep.style.display = 'none';
        this.loadingState.style.display = 'block';
        this.errorState.style.display = 'none';
    }

    /**
     * Show error state
     */
    showError(message) {
        this.errorMessage.textContent = message;
        this.verseSelectionStep.style.display = 'none';
        this.themeSelectionStep.style.display = 'none';
        this.loadingState.style.display = 'none';
        this.errorState.style.display = 'block';
    }

    /**
     * Handle error retry
     */
    handleErrorRetry() {
        this.backToVerseSelection();
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
}

