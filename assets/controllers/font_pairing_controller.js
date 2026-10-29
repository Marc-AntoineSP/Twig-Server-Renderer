import {Controller} from '@hotwired/stimulus';

const roles = ['body', 'heading', 'code'];
export default class extends Controller {
    static targets = ['field', 'details', 'status', 'frame', 'transfer', 'search', 'text', 'scheme'];
    static values = {url: String};

    connect() {
        const families = this.fieldTargets.find(field => field.dataset.pairingKey === 'bodyFontId');
        this.installed = new Set([...families.options].map(option => option.value));
        this.sequence = 0; queueMicrotask(() => this.fromEditor());
    }
    disconnect() {this.sequence++; this.abort?.abort();}
    editor() {return this.application.getControllerForElementAndIdentifier(this.element.closest('[data-controller~="theme-editor"]'), 'theme-editor');}
    proposal() {return Object.fromEntries(this.fieldTargets.map(field => [field.dataset.pairingKey, field.type === 'number' ? Number(field.value) : field.value]));}
    fromEditor() {
        const model = this.editor()?.model; if (!model) return;
        for (const field of this.fieldTargets) {
            const value = model.typography[field.dataset.pairingKey] ?? (field.type === 'number' ? 400 : field.dataset.pairingKey.endsWith('FontId') ? 'system-mono' : 'normal');
            if (field.dataset.pairingKey.endsWith('FontId') && ![...field.options].some(option => option.value === value)) field.add(new Option('Unavailable locally: ' + value, value));
            field.value = value;
        }
        this.schemeTarget.value = model.colorScheme; this.changed();
    }
    changed() {
        this.sequence++; this.abort?.abort(); this.validated = null; this.transferTarget.disabled = true;
        this.frameTarget.removeAttribute('srcdoc'); this.statusTarget.textContent = 'Proposal changed. Preview to load only these faces; editor fields are unchanged.';
        const definitions = new Map();
        for (const font of this.editor().fontsValue) if (!definitions.has(font.id)) definitions.set(font.id, font);
        for (const role of roles) {
            const id = this.proposal()[role + 'FontId'], font = definitions.get(id);
            const output = this.detailsTargets.find(el => el.dataset.role === role);
            if (!this.installed.has(id)) {output.textContent = `${id}: unavailable in the local library. Choose an installed family before preview or transfer.`; continue;}
            if (!font || font.kind === 'system') {output.textContent = `${id}: device system fallback; real faces and glyph coverage depend on your device.`; continue;}
            const available = [...new Set(font.faces.map(face => `${face.weightMin}${face.weightMax !== face.weightMin ? '–' + face.weightMax : ''} ${face.style} (${face.subset})`))].join(', ');
            output.textContent = `${font.family}: available ${available}. ${font.license} · ${font.sourceVersion}. English/French catalogue coverage verified; other characters are checked against declared ranges.`;
        }
    }
    filter() {
        const query = this.searchTarget.value.trim().toLocaleLowerCase();
        for (const field of this.fieldTargets.filter(el => el.dataset.pairingKey.endsWith('FontId'))) for (const option of field.options) option.hidden = option.value !== field.value && !option.textContent.toLocaleLowerCase().includes(query);
    }
    async preview() {
        if (this.fieldTargets.some(field => !field.reportValidity())) return;
        const sequence = ++this.sequence, typography = this.proposal();
        this.validated = null; this.transferTarget.disabled = true; this.abort?.abort(); this.abort = new AbortController();
        this.statusTarget.textContent = 'Loading requested local faces…'; this.frameTarget.removeAttribute('srcdoc');
        try {
            const response = await fetch(this.urlValue, {method: 'POST', signal: this.abort.signal, credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': this.editor().csrfValue}, body: JSON.stringify({typography, text: this.textTarget.value, scheme: this.schemeTarget.value})});
            const data = await response.json(); if (sequence !== this.sequence) return;
            if (!response.ok) throw Error(data.message || 'Pairing unavailable. Choose installed families and real faces.');
            this.frameTarget.srcdoc = data.html; this.validated = typography; this.definitions = data.fonts; this.transferTarget.disabled = false;
            const messages = roles.map(role => {
                const result = data.roles[role];
                return `${role}: ${result.family} ${result.weight} ${result.style} — ${result.kind === 'system' ? 'device system fallback' : result.missing.length ? 'fallback for characters outside declared coverage: ' + result.missing.join(', ') : 'requested local face; decoding status shown inside sample'}.`;
            });
            this.statusTarget.textContent = messages.join(' ');
        } catch (error) {if (error.name !== 'AbortError' && sequence === this.sequence) this.statusTarget.textContent = error.message;}
    }
    transfer() {
        if (!this.validated || JSON.stringify(this.validated) !== JSON.stringify(this.proposal())) return;
        this.editor().acceptPairing(this.validated, this.definitions);
        this.statusTarget.textContent = 'Pairing transferred to the theme font fields. Other draft edits are kept. Save theme to persist; Save & Apply also updates application appearance.';
    }
}
