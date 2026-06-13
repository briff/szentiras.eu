class ReferenceInput {
    constructor() {
        this.input = document.getElementById('referenceInput');
        this.button = document.getElementById('goToReferenceButton');
        this.currentLink = null;
        this.debounceTimer = null;
        this.validationToken = 0;
        this.pendingNavigation = false;
        this.init();
    }

    init() {
        if (!this.input || !this.button) {
            return;
        }

        this.setState('idle');

        this.input.addEventListener('input', () => {
            this.handleInput();
        });

        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.handleEnter();
            }
        });

        this.button.addEventListener('click', (e) => {
            e.preventDefault();
            this.navigate();
        });

        if (this.input.value.trim()) {
            this.validateReference(this.input.value.trim());
        }
    }

    handleInput() {
        clearTimeout(this.debounceTimer);
        this.currentLink = null;
        const value = this.input.value.trim();
        if (value.length < 2) {
            this.setState('idle');
            return;
        }
        this.setState('checking');
        this.debounceTimer = setTimeout(() => {
            this.validateReference(value);
        }, 300);
    }

    handleEnter() {
        if (this.currentLink) {
            this.navigate();
            return;
        }
        const term = this.input.value.trim();
        if (term.length < 2) {
            return;
        }
        clearTimeout(this.debounceTimer);
        this.pendingNavigation = true;
        this.setState('checking');
        this.validateReference(term);
    }

    async validateReference(term) {
        const token = ++this.validationToken;
        try {
            const response = await fetch(`/kereses/suggest?term=${encodeURIComponent(term)}`);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const suggestions = await response.json();
            if (token !== this.validationToken) {
                return;
            }
            const refSuggestion = suggestions.find(item => item.cat === 'ref');
            if (refSuggestion) {
                this.currentLink = refSuggestion.link;
                this.setState('valid');
                if (this.pendingNavigation) {
                    this.navigate();
                }
            } else {
                this.currentLink = null;
                this.setState('invalid');
            }
        } catch (error) {
            if (token !== this.validationToken) {
                return;
            }
            console.error('Reference validation failed:', error);
            this.currentLink = null;
            this.setState('invalid');
        } finally {
            if (token === this.validationToken) {
                this.pendingNavigation = false;
            }
        }
    }

    navigate() {
        if (this.currentLink) {
            window.location.href = this.currentLink;
        }
    }

    setState(state) {
        this.input.classList.remove('is-valid', 'is-invalid');

        switch (state) {
            case 'checking':
                this.button.disabled = true;
                this.button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Ellenőrzés';
                this.button.style.color = '#8b8fa8';
                this.button.style.borderColor = '#d4cfc9';
                this.button.style.backgroundColor = '#ede9e4';
                break;
            case 'valid':
                this.button.disabled = false;
                this.button.innerHTML = '<i class="bi-arrow-right"></i> Ugrás';
                this.button.style.color = '#fff';
                this.button.style.borderColor = '#c4a96a';
                this.button.style.backgroundColor = '#c4a96a';
                this.input.classList.add('is-valid');
                break;
            case 'invalid':
                this.button.disabled = true;
                this.button.innerHTML = '<i class="bi-arrow-right"></i> Ugrás';
                this.button.style.color = '#8b8fa8';
                this.button.style.borderColor = '#d4cfc9';
                this.button.style.backgroundColor = '#ede9e4';
                this.input.classList.add('is-invalid');
                break;
            default:
                this.button.disabled = true;
                this.button.innerHTML = '<i class="bi-arrow-right"></i> Ugrás';
                this.button.style.color = '#8b8fa8';
                this.button.style.borderColor = '#d4cfc9';
                this.button.style.backgroundColor = '#ede9e4';
                break;
        }
    }
}

export default function initReferenceInput() {
    const refInput = new ReferenceInput();
}
