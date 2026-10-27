import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['content', 'format', 'revision', 'status', 'link'];
    static values = { url: String, csrf: String, editUrl: String };

    async file(event) {
        const file = event.target.files[0];
        if (!file) return;
        if (file.size > 262144 || !/\.(json|ya?ml|css)$/i.test(file.name)) { this.statusTarget.textContent = 'Choose JSON, YAML or generated CSS within 256 KiB.'; return; }
        this.contentTarget.value = await file.text();
        this.formatTarget.value = /\.css$/i.test(file.name) ? 'css' : /\.ya?ml$/i.test(file.name) ? 'yaml' : 'json';
    }

    async submit(event) {
        const mode = event.currentTarget.dataset.mode;
        this.statusTarget.textContent = mode === 'validate' ? 'Validating tokens…' : 'Applying theme…';
        this.linkTarget.hidden = true;
        try {
            const response = await fetch(this.urlValue, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue }, body: JSON.stringify({ format: this.formatTarget.value, content: this.contentTarget.value, mode, expectedRevision: this.revisionTarget.value ? Number(this.revisionTarget.value) : null }) });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Import failed.');
            this.statusTarget.textContent = mode === 'validate' ? 'Theme tokens validate. No changes applied.' : `Theme imported at revision ${data.revision}.`;
            if (data.id) { this.linkTarget.href = this.editUrlValue.replace('__THEME__', data.id); this.linkTarget.hidden = false; }
        } catch (error) { this.statusTarget.textContent = error.message; }
    }
}
