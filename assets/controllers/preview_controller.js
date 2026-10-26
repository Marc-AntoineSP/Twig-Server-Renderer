import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'stage', 'status', 'props', 'disableJs', 'width', 'zoom', 'rootSize', 'bodyFont', 'headingFont', 'textClass', 'presetStatus', 'example'];
    static values = { itemId: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, presetUrl: String, settings: Object, defaultProps: Object };

    connect() {
        this.sequence = 0;
        this.channel = this.randomChannel();
        this.applySettings(this.settingsValue);
        this.messageListener = event => this.receive(event);
        window.addEventListener('message', this.messageListener);
        this.refreshExamples();
        this.initialTimer = setTimeout(() => this.run(), 0);
    }

    disconnect() {
        this.sequence++;
        clearTimeout(this.timer);
        clearTimeout(this.initialTimer);
        this.request?.abort();
        this.cancelJob(this.jobId);
        window.removeEventListener('message', this.messageListener);
    }

    randomChannel() {
        return [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
    }

    schedule() {
        clearTimeout(this.timer);
        this.sequence++;
        this.request?.abort();
        this.cancelJob(this.jobId);
        this.jobId = null;
        this.refreshExamples();
        this.setStatus('pending', this.lastDocument ? 'Changes pending — previous preview is stale.' : 'Changes pending…');
        this.timer = setTimeout(() => this.run(), 300);
    }

    invalid(event) {
        clearTimeout(this.timer);
        this.sequence++;
        this.request?.abort();
        this.cancelJob(this.jobId);
        this.setStatus('error', `${this.lastDocument ? 'Stale preview — ' : ''}${event.detail.message}`);
    }

    async run() {
        clearTimeout(this.timer);
        clearTimeout(this.initialTimer);
        const sequence = ++this.sequence;
        this.request?.abort();
        this.cancelJob(this.jobId);
        this.jobId = null;
        this.request = new AbortController();
        const signal = this.request.signal;
        const channel = this.randomChannel();
        this.setStatus('pending', this.lastDocument ? 'Rendering and building… Previous preview is stale.' : 'Rendering and building…');
        try {
            const preset = this.settings();
            const body = {
                itemId: this.itemIdValue, sequence, channel, preset,
                manifestData: JSON.parse(document.getElementById('draft_manifestJson').value),
                sourceMap: JSON.parse(document.getElementById('draft_sourceMapJson').value),
                props: JSON.parse(this.propsTarget.value),
            };
            // Always collect a created ID so an obsolete creation can be cancelled server-side.
            const created = await this.json(this.createUrlValue, { method: 'POST', body: JSON.stringify(body) });
            if (sequence !== this.sequence) { this.cancelJob(created.id); return; }
            this.jobId = created.id;
            for (;;) {
                const job = await this.json(this.statusUrlValue.replace('__JOB__', created.id), { signal });
                if (sequence !== this.sequence) return;
                if (job.status === 'succeeded') {
                    const document = await this.json(this.documentUrlValue.replace('__JOB__', created.id), { signal });
                    if (sequence !== this.sequence || document.sequence !== sequence) return;
                    this.channel = channel;
                    this.lastDocument = document.html;
                    this.frameTarget.setAttribute('sandbox', this.disableJsTarget.checked ? '' : 'allow-scripts');
                    this.frameTarget.srcdoc = document.html;
                    this.setStatus('current', 'Preview current');
                    return;
                }
                if (['failed', 'cancelled', 'expired'].includes(job.status)) {
                    const error = job.error;
                    throw new Error(error ? `${error.template || ''}${error.line > 0 ? ':'+error.line : ''} ${error.message}`.trim() : `Preview ${job.status}.`);
                }
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        } catch (error) {
            if (sequence !== this.sequence || error.name === 'AbortError') return;
            this.setStatus('error', `${this.lastDocument ? 'Stale preview — ' : ''}${error.message}`);
        }
    }

    async cancelJob(id) {
        if (!id) return;
        try { await this.json(this.statusUrlValue.replace('__JOB__', id), { method: 'DELETE', keepalive: true }); } catch {}
    }

    async json(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue }, credentials: 'same-origin' });
        const value = await response.json();
        if (!response.ok) throw new Error(value.message || 'Preview request failed.');
        return value;
    }

    settings() {
        return { width: Number(this.widthTarget.value), zoom: Number(this.zoomTarget.value), rootSize: Number(this.rootSizeTarget.value), bodyFont: this.bodyFontTarget.value, headingFont: this.headingFontTarget.value, textClass: this.textClassTarget.value, scripts: !this.disableJsTarget.checked, props: JSON.parse(this.propsTarget.value) };
    }

    applySettings(settings) {
        for (const key of ['width', 'zoom', 'rootSize', 'bodyFont', 'headingFont', 'textClass']) this[`${key}Target`].value = settings[key];
        this.disableJsTarget.checked = settings.scripts === false;
        this.propsTarget.value = JSON.stringify(settings.props || this.defaultPropsValue, null, 2);
        this.applyViewport();
    }

    applyViewport() {
        const width = Math.min(1920, Math.max(320, Number(this.widthTarget.value) || 768));
        const scale = Number(this.zoomTarget.value) / 100;
        this.frameTarget.style.width = `${width}px`;
        this.frameTarget.style.transform = `scale(${scale})`;
        this.frameTarget.style.transformOrigin = 'top left';
        this.stageTarget.style.width = `${width * scale}px`;
        this.stageTarget.style.height = `${(parseInt(this.frameTarget.style.height) || 250) * scale}px`;
    }

    widthPreset(event) {
        this.widthTarget.value = event.currentTarget.dataset.width;
        this.applyViewport();
    }

    viewportChanged() { this.applyViewport(); }
    typographyChanged() { this.applyViewport(); this.schedule(); }

    async savePreset() {
        this.presetStatusTarget.textContent = 'Saving renderer settings…';
        try {
            await this.json(this.presetUrlValue, { method: 'PUT', body: JSON.stringify(this.settings()) });
            this.presetStatusTarget.textContent = 'Renderer settings saved';
        } catch (error) { this.presetStatusTarget.textContent = error.message; }
    }

    refreshExamples() {
        if (!this.hasExampleTarget) return;
        try {
            const sources = JSON.parse(document.getElementById('draft_sourceMapJson').value);
            this.examples = JSON.parse(sources['meta/examples.json'] || '[]');
            const selected = this.exampleTarget.value;
            this.exampleTarget.replaceChildren(new Option('Choose example', ''), ...this.examples.map((example, index) => new Option(example.name, String(index))));
            this.exampleTarget.value = selected;
        } catch { this.examples = []; }
    }

    chooseExample() {
        const example = this.examples[Number(this.exampleTarget.value)];
        if (this.exampleTarget.value !== '' && example) { this.propsTarget.value = JSON.stringify(example.props, null, 2); this.schedule(); }
    }

    resetSettings() {
        this.applySettings({ width: 768, zoom: 100, rootSize: 16, bodyFont: 'system-sans', headingFont: 'system-sans', textClass: 'text-base', scripts: true, props: this.defaultPropsValue });
        this.schedule();
    }

    setStatus(state, text) {
        this.element.dataset.previewState = state;
        this.statusTarget.textContent = text;
        this.frameTarget.setAttribute('aria-busy', String(state === 'pending'));
    }

    receive(event) {
        const data = event.data;
        if (event.source !== this.frameTarget.contentWindow || !data || typeof data !== 'object' || Array.isArray(data) || data.channel !== this.channel) return;
        if (Object.keys(data).sort().join(',') !== 'channel,height,type' || data.type !== 'resize' || !Number.isInteger(data.height) || data.height < 100 || data.height > 4000) return;
        this.frameTarget.style.height = `${data.height}px`;
        this.applyViewport();
    }

    async reset() {
        clearTimeout(this.timer);
        this.sequence++;
        this.request?.abort();
        this.cancelJob(this.jobId);
        this.jobId = null;
        this.channel = this.randomChannel();
        this.lastDocument = null;
        this.frameTarget.srcdoc = '<!doctype html><html lang="en"><body></body></html>';
        this.setStatus('idle', 'Preview reset');
    }

    resetProps() {
        try {
            const manifest = JSON.parse(document.getElementById('draft_manifestJson').value);
            const example = this.hasExampleTarget && this.exampleTarget.value !== '' ? this.examples[Number(this.exampleTarget.value)] : null;
            this.propsTarget.value = JSON.stringify(example?.props || manifest.defaults, null, 2);
            this.schedule();
        } catch (error) { this.setStatus('error', error.message); }
    }

    toggleScripts() {
        this.frameTarget.setAttribute('sandbox', this.disableJsTarget.checked ? '' : 'allow-scripts');
        if (this.lastDocument) this.frameTarget.srcdoc = this.lastDocument;
    }
}
