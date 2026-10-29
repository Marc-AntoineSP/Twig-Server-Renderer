import { Controller } from '@hotwired/stimulus';
import {canonical, EditingGuard} from '../editing_guard.js';

export default class extends Controller {
    static targets = ['tab', 'panel', 'twig', 'javascript', 'schema', 'defaults', 'examples', 'twigFile', 'jsFile', 'unsaved', 'notice'];
    static values = { saved: Object, url: String, csrf: String, revision: Number };

    connect() {
        this.disconnected = false;
        this.rawSources = document.getElementById('draft_sourceMapJson');
        this.rawManifest = document.getElementById('draft_manifestJson');
        this.dirty = false;
        this.loadSources();
        this.selectTab('twig');
        this.updateDirty();
        this.guard = new EditingGuard(() => this.dirty, () => this.saving, this.element);
        this.dispatch('ready');
    }

    disconnect() {
        this.guard.disconnect(); this.disconnected = true;
    }

    loadSources() {
        try {
            const sources = JSON.parse(this.rawSources.value);
            const manifest = JSON.parse(this.rawManifest.value);
            if (!sources || Array.isArray(sources) || typeof sources !== 'object' || !manifest || Array.isArray(manifest) || typeof manifest !== 'object') throw new Error('Manifest and source map must be objects.');
            if (typeof manifest.name !== 'string' || typeof manifest.entry !== 'string' || typeof manifest.propsSchema !== 'string' || !Array.isArray(manifest.controllers)) throw new Error('Manifest needs name, entry, propsSchema and controllers for source tabs.');
            this.sources = sources;
            this.manifest = manifest;
            [this.twigTarget, this.javascriptTarget, this.schemaTarget, this.defaultsTarget, this.examplesTarget].forEach(field => { field.disabled = false; });
            this.fillSelect(this.twigFileTarget, Object.keys(this.sources).filter(path => path.endsWith('.html.twig')));
            this.fillSelect(this.jsFileTarget, Object.keys(this.sources).filter(path => path.endsWith('.js')));
            this.twigTarget.value = this.sources[this.twigFileTarget.value] || '';
            this.javascriptTarget.value = this.sources[this.jsFileTarget.value] || '';
            this.javascriptTarget.disabled = !this.jsFileTarget.value;
            this.schemaTarget.value = this.sources[this.manifest.propsSchema] || '{}';
            this.defaultsTarget.value = JSON.stringify(this.manifest.defaults || {}, null, 2);
            this.examplesTarget.value = this.sources['meta/examples.json'] || '[]';
            this.noticeTarget.textContent = '';
            this.rawError = false;
            this.defaultsError = false;
        } catch (error) {
            this.rawError = true;
            [this.twigTarget, this.javascriptTarget, this.schemaTarget, this.defaultsTarget, this.examplesTarget].forEach(field => { field.disabled = true; });
            this.noticeTarget.textContent = `Advanced JSON needs repair: ${error.message}`;
            this.dispatch('invalid', { detail: { message: this.noticeTarget.textContent } });
        }
    }

    fillSelect(select, paths) {
        const selected = select.value;
        select.replaceChildren(...paths.map(path => new Option(path, path)));
        if (paths.includes(selected)) select.value = selected;
    }

    fileChanged(event) {
        if (event.target === this.twigFileTarget) this.twigTarget.value = this.sources[event.target.value] || '';
        else this.javascriptTarget.value = this.sources[event.target.value] || '';
    }

    sourceChanged(event) {
        try {
            if (event.target === this.twigTarget) this.sources[this.twigFileTarget.value] = event.target.value;
            else if (event.target === this.javascriptTarget) this.sources[this.jsFileTarget.value] = event.target.value;
            else if (event.target === this.schemaTarget) this.sources[this.manifest.propsSchema] = event.target.value;
            else if (event.target === this.examplesTarget) this.sources['meta/examples.json'] = event.target.value;
            else if (event.target === this.defaultsTarget) { this.manifest.defaults = JSON.parse(event.target.value); this.defaultsError = false; }
            this.rawSources.value = JSON.stringify(this.sources, null, 2);
            this.rawManifest.value = JSON.stringify(this.manifest, null, 2);
            if (!this.defaultsError) this.noticeTarget.textContent = '';
            this.changed();
        } catch (error) {
            this.defaultsError = true;
            this.noticeTarget.textContent = `JSON needs repair: ${error.message}`;
            this.dirty = true;
            this.unsavedTarget.textContent = 'Unsaved changes';
            this.dispatch('invalid', { detail: { message: this.noticeTarget.textContent } });
        }
    }

    rawChanged() {
        this.loadSources();
        this.changed();
    }

    changed() {
        this.updateDirty();
        if (this.rawError || this.defaultsError) this.dispatch('invalid', { detail: { message: this.noticeTarget.textContent } });
        else this.dispatch('changed');
    }

    updateDirty() {
        try {
            const state = this.snapshot();
            this.dirty = this.rawError || this.defaultsError || canonical(state) !== canonical(this.savedValue);
        } catch { this.dirty = true; }
        this.state = this.saving ? 'saving' : this.dirty ? 'dirty' : 'saved';
        this.element.dataset.editingState = this.state;
        this.unsavedTarget.textContent = this.saving ? 'Saving…' : this.dirty ? 'Unsaved changes' : 'Saved';
        this.unsavedTarget.classList.toggle('badge-warning', this.dirty);
    }
    snapshot() {
        return {manifestData: JSON.parse(this.rawManifest.value), sourceMap: JSON.parse(this.rawSources.value), title: document.getElementById('draft_title').value, description: document.getElementById('draft_description').value, tags: [...new Set(document.getElementById('draft_tagsText').value.split(',').map(x => x.trim().toLowerCase()).filter(Boolean))]};
    }
    async submitting(event) {
        event.preventDefault(); if (this.saving) return;
        // Invalid editor JSON cannot silently submit older hidden source.
        if (this.rawError || this.defaultsError) { this.noticeTarget.focus(); return; }
        const snapshot = this.snapshot(); this.saving = true; this.updateDirty();
        try {
            const response = await fetch(this.urlValue, {method: 'PUT', credentials: 'same-origin', headers: {'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue, 'If-Match': `"${this.revisionValue}"`}, body: JSON.stringify(snapshot)});
            const saved = await response.json();
            if (!response.ok) throw Object.assign(Error(saved.message || 'Draft was not saved.'), {status: response.status});
            if (this.disconnected) return;
            let current = null;
            try {current = this.snapshot();} catch {} // Later invalid JSON cannot undo a successful earlier save.
            this.savedValue = {manifestData: saved.manifestData, sourceMap: saved.sourceMap, title: saved.title, description: saved.description, tags: saved.tags};
            this.revisionValue = saved.revision;
            document.querySelector('[data-saved-revision]').textContent = saved.revision;
            document.getElementById('draft_revision').value = saved.revision;
            if (current) {
                const field = document.activeElement; const selection = typeof field?.selectionStart === 'number' ? [field.selectionStart, field.selectionEnd] : null; const scroll = field?.scrollTop;
                // Merge server normalization only into fields unchanged since this request.
                if (canonical(current.manifestData) === canonical(snapshot.manifestData)) this.rawManifest.value = JSON.stringify(saved.manifestData, null, 2);
                if (canonical(current.sourceMap) === canonical(snapshot.sourceMap)) this.rawSources.value = JSON.stringify(saved.sourceMap, null, 2);
                for (const [key, id] of [['title', 'draft_title'], ['description', 'draft_description'], ['tags', 'draft_tagsText']]) {
                    if (canonical(current[key]) === canonical(snapshot[key])) document.getElementById(id).value = key === 'tags' ? saved.tags.join(', ') : saved[key];
                }
                if (!this.rawError && !this.defaultsError) this.loadSources();
                else if (!this.rawError) {this.manifest = JSON.parse(this.rawManifest.value); this.sources = JSON.parse(this.rawSources.value);}
                if (selection) {field.setSelectionRange(...selection); field.scrollTop = scroll;}
            }
            this.saving = false; this.updateDirty();
            this.noticeTarget.textContent = `Draft saved as revision ${saved.revision}.${this.dirty ? ' Newer changes remain unsaved.' : ''}${this.rawError || this.defaultsError ? ' Correct the entered JSON before saving again.' : ''}`;
            const flash = document.querySelector('[data-flash]') || document.createElement('div');
            flash.dataset.flash = ''; flash.className = 'alert alert-success mb-6'; flash.setAttribute('role', 'status'); flash.textContent = 'Draft saved.';
            if (!flash.isConnected) this.element.prepend(flash);
        } catch (error) {
            if (this.disconnected) return;
            this.saving = false; this.updateDirty();
            this.element.dataset.editingState = error.status === 412 ? 'conflict' : 'failed';
            this.noticeTarget.textContent = error.message;
            this.unsavedTarget.textContent = error.status === 412 ? 'Conflict — unsaved changes kept' : 'Save failed — unsaved changes kept';
        } finally {this.saving = false;}
    }

    tabClick(event) {
        this.selectTab(event.currentTarget.dataset.tab);
    }

    tabKey(event) {
        const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
        if (!keys.includes(event.key)) return;
        event.preventDefault();
        const index = this.tabTargets.indexOf(event.currentTarget);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? this.tabTargets.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + this.tabTargets.length) % this.tabTargets.length;
        this.selectTab(this.tabTargets[next].dataset.tab);
        this.tabTargets[next].focus();
    }

    selectTab(name) {
        this.tabTargets.forEach(tab => {
            const selected = tab.dataset.tab === name;
            tab.setAttribute('aria-selected', String(selected));
            tab.tabIndex = selected ? 0 : -1;
            tab.classList.toggle('tab-active', selected);
        });
        this.panelTargets.forEach(panel => { panel.hidden = panel.dataset.panel !== name; });
    }

    addController() {
        if (this.rawError) return;
        const path = `assets/controllers/catalogue/${this.manifest.name}_controller.js`;
        const identifier = `catalogue--${this.manifest.name.replace('/', '--')}`;
        if (!this.sources[path]) {
            this.sources[path] = "import { Controller } from '@hotwired/stimulus';\n\nexport default class extends Controller {\n    connect() {\n    }\n}\n";
            this.manifest.controllers.push({ identifier, path });
            this.rawSources.value = JSON.stringify(this.sources, null, 2);
            this.rawManifest.value = JSON.stringify(this.manifest, null, 2);
            this.loadSources();
            this.changed();
        }
        this.selectTab('javascript');
        this.noticeTarget.textContent = `Controller ${identifier} is registered. Add its data-controller attribute in your Twig.`;
    }
}
