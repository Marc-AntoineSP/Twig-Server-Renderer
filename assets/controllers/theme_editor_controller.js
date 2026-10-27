import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'status', 'compare', 'component', 'publishStatus', 'disableJs'];
    static values = { document: Object, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, themeUrl: String, publishUrl: String, revision: Number };

    connect() {
        this.model = structuredClone(this.documentValue);
        this.epoch = 0;
        this.jobs = [];
        this.channels = [];
        this.lastDocuments = [];
        this.element.querySelectorAll('[data-theme-path]').forEach(input => this.readField(input));
        this.message = event => this.receive(event);
        window.addEventListener('message', this.message);
        this.timer = setTimeout(() => this.run(), 0);
    }

    disconnect() {
        this.epoch++;
        clearTimeout(this.timer);
        this.jobs.forEach(id => this.cancel(id));
        window.removeEventListener('message', this.message);
    }

    readField(input) {
        const keys = input.dataset.themePath.split('.');
        const key = keys.pop();
        const parent = keys.reduce((value, name) => value[name], this.model);
        parent[key] = input.dataset.themeType === 'boolean' ? input.checked : input.dataset.themeType === 'number' ? Number(input.value) : input.value;
    }

    changed(event) {
        if (event.target.dataset.themePath) this.readField(event.target);
        this.dirty = true;
        this.schedule();
    }

    pick(event) {
        const input = document.getElementById(event.target.dataset.colorInput);
        input.value = event.target.value.toUpperCase();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    schedule() {
        clearTimeout(this.timer);
        this.epoch++;
        this.statusTarget.textContent = 'Changes pending — existing previews are stale.';
        this.timer = setTimeout(() => this.run(), 300);
    }

    async json(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue } });
        const data = await response.json();
        if (!response.ok) {
            const fields = Object.entries(data.fieldErrors || {}).map(([key, errors]) => `${key}: ${errors.join(', ')}`).join('; ');
            throw new Error(`${data.message || 'Theme request failed.'}${fields ? ' '+fields.slice(0, 1000) : ''}`);
        }
        return data;
    }

    async cancel(id) {
        if (!id) return;
        try { await this.json(this.statusUrlValue.replace('__JOB__', id), { method: 'DELETE', keepalive: true }); } catch {}
    }

    async run() {
        clearTimeout(this.timer);
        const epoch = ++this.epoch;
        await Promise.all(this.jobs.map(id => this.cancel(id)));
        this.jobs = [];
        if (epoch !== this.epoch) return;
        this.statusTarget.textContent = 'Rendering themes…';
        try {
            const comparison = this.compareTarget.value ? (await this.json(this.themeUrlValue.replace('__THEME__', this.compareTarget.value))).document : this.documentValue;
            if (epoch !== this.epoch) return;
            const documents = [structuredClone(this.model), comparison];
            // Queue in order, under the same per-account pending quota as source previews.
            for (let index = 0; index < documents.length; index++) {
                const theme = documents[index];
                const channel = [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
                const preset = { width: 768, zoom: 100, rootSize: theme.typography.rootFontSizePx, bodyFont: 'theme', headingFont: 'theme', textClass: theme.typography.textClass, scripts: !this.disableJsTarget.checked };
                const created = await this.json(this.createUrlValue, { method: 'POST', body: JSON.stringify({ itemId: this.componentTarget.value, sequence: epoch, channel, themeData: theme, preset }) });
                if (epoch !== this.epoch) { this.cancel(created.id); return; }
                this.jobs[index] = created.id;
                let job;
                do {
                    job = await this.json(this.statusUrlValue.replace('__JOB__', created.id));
                    if (epoch !== this.epoch) return;
                    if (['failed', 'cancelled', 'expired'].includes(job.status)) throw new Error(job.error?.message || `Preview ${job.status}.`);
                    if (job.status !== 'succeeded') await new Promise(resolve => setTimeout(resolve, 100));
                } while (job.status !== 'succeeded');
                const result = await this.json(this.documentUrlValue.replace('__JOB__', created.id));
                if (epoch !== this.epoch || result.sequence !== epoch) return;
                this.channels[index] = channel;
                this.lastDocuments[index] = result.html;
                this.frameTargets[index].setAttribute('sandbox',this.disableJsTarget.checked?'':'allow-scripts');
                this.frameTargets[index].srcdoc = result.html;
            }
            this.statusTarget.textContent = 'Theme previews current';
        } catch (error) {
            if (epoch === this.epoch) this.statusTarget.textContent = `${this.lastDocuments.length ? 'Stale preview — ' : ''}${error.message}`;
        }
    }

    receive(event) {
        const index = this.frameTargets.findIndex(frame => frame.contentWindow === event.source);
        const value = event.data;
        if (index < 0 || !value || typeof value !== 'object' || value.channel !== this.channels[index] || Object.keys(value).sort().join(',') !== 'channel,height,type' || value.type !== 'resize' || !Number.isInteger(value.height) || value.height < 100 || value.height > 4000) return;
        this.frameTargets[index].style.height = `${value.height}px`;
    }

    reset() {
        clearTimeout(this.timer);this.epoch++;this.jobs.forEach(id=>this.cancel(id));this.jobs=[];this.channels=[];this.lastDocuments=[];
        this.frameTargets.forEach(frame=>{frame.srcdoc='<!doctype html><html><body></body></html>';});this.statusTarget.textContent='Theme previews reset';
    }
    toggleScripts() {this.frameTargets.forEach((frame,index)=>{frame.setAttribute('sandbox',this.disableJsTarget.checked?'':'allow-scripts');if(this.lastDocuments[index])frame.srcdoc=this.lastDocuments[index];});}

    async publish() {
        if (this.dirty) { this.publishStatusTarget.textContent = 'Save the theme before publishing this revision.'; return; }
        this.publishStatusTarget.textContent = 'Validating and compiling release…';
        try {
            const release = await this.json(this.publishUrlValue, { method: 'POST', body: JSON.stringify({ expectedRevision: this.revisionValue }) });
            this.publishStatusTarget.textContent = `Theme ${release.version} published immutably.`;
        } catch (error) { this.publishStatusTarget.textContent = error.message; }
    }
}
