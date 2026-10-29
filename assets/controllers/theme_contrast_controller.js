import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['status', 'row', 'frame'];
    static values = {url: String};
    connect() {this.sequence = 0;}
    disconnect() {this.sequence++; clearTimeout(this.timer); this.abort?.abort();}
    changed(event) {
        const colors = event.detail.model.colors;
        const key = JSON.stringify([colors, event.detail.creating, event.detail.saved.colors]);
        if (key === this.lastKey) return; this.lastKey = key;
        this.saved = event.detail.creating ? null : event.detail.saved.colors;
        this.colors = structuredClone(colors);
        clearTimeout(this.timer); this.abort?.abort(); const sequence = ++this.sequence;
        this.statusTarget.textContent = 'Draft changed — contrast results pending.';
        this.rowTargets.forEach(row => {row.querySelector('[data-draft-result]').textContent = 'Pending…';});
        this.frameTarget.removeAttribute('srcdoc');
        this.timer = setTimeout(() => this.evaluate(sequence), 300);
    }
    editor() {return this.application.getControllerForElementAndIdentifier(this.element, 'theme-editor');}
    async request(colors, signal) {
        const response = await fetch(this.urlValue, {method: 'POST', credentials: 'same-origin', signal, headers: {'Content-Type': 'application/json', 'X-CSRF-Token': this.editor().csrfValue}, body: JSON.stringify({colors})});
        const data = await response.json();
        if (!response.ok) throw Error(data.message || 'Contrast unavailable. Check the colour fields and try again.');
        return data;
    }
    async evaluate(sequence) {
        this.abort = new AbortController();
        try {
            const baseline = this.saved ? JSON.stringify(this.saved) : null;
            const draft = await this.request(this.colors, this.abort.signal);
            if (sequence !== this.sequence) return;
            if (baseline && baseline !== this.baselineKey) {
                const saved = await this.request(this.saved, this.abort.signal);
                if (sequence !== this.sequence) return;
                this.baselineKey = baseline; this.savedResults = saved.results;
            }
            for (const row of this.rowTargets) {
                const result = draft.results[row.dataset.background];
                row.querySelector('[data-draft-result]').textContent = this.describe(result);
                row.querySelector('[data-saved-result]').textContent = baseline ? `${this.savedResults[row.dataset.background].ratio}:1` : 'Not saved yet';
            }
            for (const field of this.element.querySelectorAll('[data-theme-path^="colors."]')) {
                const key = field.dataset.themePath.slice(7), invalid = Object.values(draft.results).some(result => result.invalid.includes(key));
                field.setAttribute('aria-invalid', String(invalid));
                const error = document.getElementById(field.id + '-contrast-error');
                if (error) error.hidden = !invalid;
            }
            this.frameTarget.srcdoc = draft.html;
            this.statusTarget.textContent = 'Current draft contrast updated. Theme and application appearance are unchanged.';
        } catch (error) {if (error.name !== 'AbortError' && sequence === this.sequence) this.statusTarget.textContent = `Cannot evaluate current draft: ${error.message}`;}
    }
    describe(result) {
        if (result.ratio === null) return `Cannot evaluate: ${result.invalid.join(', ')} needs opaque #RRGGBB. Alpha/background compositing and CSS expressions are unsupported.`;
        return `${result.ratio.toFixed(2)}:1 · Ordinary text: ${result.ordinary ? 'meets 4.5' : 'below 4.5'} · Large text / content-colour icon on this surface: ${result.large ? 'meets 3' : 'below 3'}.`;
    }
    focusToken(event) {
        event.preventDefault();
        const field = this.element.querySelector(`[data-theme-path="colors.${event.currentTarget.dataset.token}"]`);
        field?.focus(); field?.scrollIntoView({block: 'center'});
    }
    showExample(event) {
        this.frameTarget.scrollIntoView({block: 'center'}); this.frameTarget.focus();
        this.frameTarget.contentWindow.postMessage({type: 'contrast-example', id: event.currentTarget.dataset.example}, '*');
    }
}
