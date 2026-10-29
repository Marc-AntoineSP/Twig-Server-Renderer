import PreviewController from './preview_controller.js';

// Share polling, cancellation, property fields and strict frame-message validation.
export default class extends PreviewController {
    static targets = ['identity', 'height', 'viewport', 'selectionForm'];
    static values = {selection: Object, examples: Array, schema: Object, label: String, exampleIndex: Number};

    connect() {
        this.sequence = 0;
        this.channel = this.randomChannel();
        this.applySettings(this.settingsValue);
        this.exampleTarget.value = String(this.exampleIndexValue);
        this.refreshExamples();
        this.messageListener = event => this.receive(event);
        window.addEventListener('message', this.messageListener);
        this.resizeObserver = new ResizeObserver(() => { if (this.fitting) this.fit(); });
        this.resizeObserver.observe(this.viewportTarget);
        this.fit();
        this.updateURL();
        this.setStatus('idle', 'Ready to load. Demo interactions stay inside the isolated frame.');
    }

    disconnect() { this.resizeObserver?.disconnect(); super.disconnect(); }

    previewBody(sequence, channel, preset) {
        return {sequence, channel, preset, releaseId: this.selectionValue.releaseId, draftRevision: this.selectionValue.draftRevision, example: Number(this.exampleTarget.value), props: JSON.parse(this.propsTarget.value)};
    }

    acceptDocument(document, body) {
        const selection = document.selection;
        if (!selection || selection.releaseId !== body.releaseId || selection.draftRevision !== body.draftRevision || selection.example !== body.example) {
            this.setStatus('error', 'Preview identity did not match. Load preview to retry.');
            return false;
        }
        return true;
    }

    documentLoaded() { this.identityTarget.textContent = this.labelValue; }
    run() { this.loaded = true; return super.run(); }
    schedule() {
        this.refreshExamples();
        if (this.loaded) super.schedule();
    }

    refreshExamples() {
        // Only schema and example data cross into trusted controls, never user markup.
        this.refreshPropsForm({'schema.json': JSON.stringify(this.schemaValue)}, {propsSchema: 'schema.json'});
    }

    chooseExample() {
        const index = Number(this.exampleTarget.value);
        this.propsTarget.value = JSON.stringify(index < 0 ? this.defaultPropsValue : this.examplesValue[index].props, null, 2);
        this.updateURL();
        this.schedule();
    }

    themeChanged() { this.updateURL(); this.schedule(); }
    releaseChanged() {
        // Examples are scoped to their immutable source; a new source starts at defaults.
        this.exampleTarget.value = '-1';
        this.selectionFormTarget.requestSubmit();
    }

    updateURL() {
        const url = new URL(location.href);
        url.searchParams.set('release', this.selectionValue.releaseId || 'draft');
        url.searchParams.set('example', this.exampleTarget.value);
        if (this.themeTarget.value) url.searchParams.set('theme', this.themeTarget.value);
        else url.searchParams.delete('theme');
        // Retain Turbo's restoration state. This is selection state, not a saved preset.
        history.replaceState(history.state, '', url);
    }

    fit() {
        this.fitting = true;
        const width = Math.max(320, Math.min(1920, Math.floor(this.viewportTarget.clientWidth)));
        this.widthTarget.value = String(width);
        this.zoomTarget.value = '100';
        this.applyViewport();
    }
    widthPreset(event) { this.fitting = false; super.widthPreset(event); }
    viewportChanged() { this.fitting = false; super.viewportChanged(); }
    heightChanged() { this.viewportTarget.style.height = `${Number(this.heightTarget.value)}px`; }

    setStatus(state, text) {
        super.setStatus(state, state === 'error' ? `${text} Load preview to retry.` : text);
    }

    reset() { this.loaded = false; return super.reset(); }
}
