/**
 * Opens a slide-in panel (Bootstrap offcanvas) showing the meaning and explanation
 * of a Greek word when it is clicked in the Greek text or in the Greek search results.
 *
 * The panel content is the same as the inline explanation used in the verse popover,
 * served by the `/ai-greek/{usx}/{chapter}/{verse}/{i}` endpoint.
 */
export function initGreekWordPanel() {
    const offcanvasEl = document.getElementById('greekWordOffcanvas');
    if (!offcanvasEl) {
        return;
    }

    const offcanvas = new bootstrap.Offcanvas(offcanvasEl);
    const contentEl = offcanvasEl.querySelector('.greek-word-content');

    function showGreekWord(word) {
        document.querySelectorAll('.greekWord.clickable-greek.mark').forEach(el => el.classList.remove('mark'));
        word.classList.add('mark');

        contentEl.innerHTML = '<div class="text-center p-3"><span class="spinner-border spinner-border-sm"></span></div>';
        offcanvas.show();

        fetch(`/ai-greek/${word.dataset.usx}/${word.dataset.chapter}/${word.dataset.verse}/${word.dataset.i}`)
            .then(response => response.json())
            .then(data => {
                contentEl.innerHTML = data;
                const tooltipTriggerList = contentEl.querySelectorAll("[data-bs-toggle='tooltip']");
                [...tooltipTriggerList].map(el => new bootstrap.Tooltip(el));
                contentEl.querySelectorAll('a.find-all').forEach(link => {
                    link.addEventListener('click', () => {
                        $('#interstitial').show();
                    });
                });
            })
            .catch(() => {
                contentEl.innerHTML = '<div class="p-3 text-danger">Hiba a betöltés során.</div>';
            });
    }

    document.addEventListener('click', (event) => {
        const word = event.target.closest('.greekWord.clickable-greek');
        if (!word) {
            return;
        }
        event.preventDefault();
        showGreekWord(word);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }
        const word = event.target.closest && event.target.closest('.greekWord.clickable-greek');
        if (!word) {
            return;
        }
        event.preventDefault();
        showGreekWord(word);
    });

    offcanvasEl.addEventListener('hidden.bs.offcanvas', () => {
        document.querySelectorAll('.greekWord.clickable-greek.mark').forEach(el => el.classList.remove('mark'));
    });
}
