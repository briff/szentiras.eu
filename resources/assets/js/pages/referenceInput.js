class ReferenceInput {
    constructor() {
        this.input = document.getElementById('referenceInput');
        this.button = document.getElementById('goToReferenceButton');
        this.currentLink = null;
        this.debounceTimer = null;
        this.init();
    }

    init() {
        if (!this.input || !this.button) {
            return;
        }

        // Disable button initially
        this.button.disabled = true;

        // Attach input event with debounce
        this.input.addEventListener('input', () => {
            this.handleInput();
        });

        // Attach button click
        this.button.addEventListener('click', (e) => {
            this.handleButtonClick(e);
        });

        // Also validate on page load if there's already a value
        if (this.input.value.trim()) {
            this.validateReference(this.input.value.trim());
        }
    }

    handleInput() {
        clearTimeout(this.debounceTimer);
        const value = this.input.value.trim();
        if (value.length < 2) {
            // Too short, disable button
            this.button.disabled = true;
            this.currentLink = null;
            return;
        }
        // Show loading? maybe not needed
        this.button.disabled = true;
        this.debounceTimer = setTimeout(() => {
            this.validateReference(value);
        }, 300);
    }

    async validateReference(term) {
        try {
            const response = await fetch(`/kereses/suggest?term=${encodeURIComponent(term)}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const suggestions = await response.json();
            const refSuggestion = suggestions.find(item => item.cat === 'ref');
            if (refSuggestion) {
                // Valid reference found
                this.currentLink = refSuggestion.link;
                this.button.disabled = false;
            } else {
                this.currentLink = null;
                this.button.disabled = true;
            }
        } catch (error) {
            console.error('Reference validation failed:', error);
            this.currentLink = null;
            this.button.disabled = true;
        }
    }

    handleButtonClick(event) {
        event.preventDefault();
        if (this.currentLink) {
            window.location.href = this.currentLink;
        } else {
            // If no stored link, try to validate again (maybe the input changed)
            const term = this.input.value.trim();
            if (term.length >= 2) {
                this.validateReference(term);
                // After validation, if valid, navigate (but async)
                // We'll just wait a bit and retry? For simplicity, we can just let the validation enable button.
                // For now, we'll just not navigate.
            }
        }
    }
}

export default function initReferenceInput() {
    const refInput = new ReferenceInput();
}