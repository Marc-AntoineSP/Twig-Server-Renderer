import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'status', 'props', 'disableJs'];
    static values = { itemId: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String };

    connect() {
        this.sequence = 0;
        this.channel = this.randomChannel();
        this.messageListener = event => this.receive(event);
        window.addEventListener('message', this.messageListener);
    }

    disconnect() {
        this.sequence++;
        this.request?.abort();
        window.removeEventListener('message', this.messageListener);
    }

    randomChannel() {
        return [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
    }

    async run() {
        const sequence = ++this.sequence;
        this.request?.abort();
        this.request = new AbortController();
        const signal = this.request.signal;
        const channel = this.randomChannel();
        this.setStatus('pending', this.lastDocument ? 'Rendering and building… Previous preview is stale.' : 'Rendering and building…');
        try {
            const body = {
                itemId: this.itemIdValue, sequence, channel,
                manifestData: JSON.parse(document.getElementById('draft_manifestJson').value),
                sourceMap: JSON.parse(document.getElementById('draft_sourceMapJson').value),
                props: JSON.parse(this.propsTarget.value),
            };
            const created = await this.json(this.createUrlValue, { method: 'POST', body: JSON.stringify(body), signal });
            if (sequence !== this.sequence) return;
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

    async json(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue }, credentials: 'same-origin' });
        const value = await response.json();
        if (!response.ok) throw new Error(value.message || 'Preview request failed.');
        return value;
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
    }

    async reset() {
        this.sequence++;
        this.request?.abort();
        if (this.jobId) {
            try { await this.json(this.statusUrlValue.replace('__JOB__', this.jobId), { method: 'DELETE' }); } catch {}
        }
        this.channel = this.randomChannel();
        this.lastDocument = null;
        this.frameTarget.srcdoc = '<!doctype html><html lang="en"><body></body></html>';
        this.setStatus('idle', 'Preview reset');
    }

    toggleScripts() {
        this.frameTarget.setAttribute('sandbox', this.disableJsTarget.checked ? '' : 'allow-scripts');
        if (this.lastDocument) this.frameTarget.srcdoc = this.lastDocument;
    }
}
