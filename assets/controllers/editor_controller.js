import { Controller } from '@hotwired/stimulus';

const canonical = value => JSON.stringify(value, (key, item) => item && typeof item === 'object' && !Array.isArray(item) ? Object.fromEntries(Object.keys(item).sort().map(name => [name, item[name]])) : item);

export default class extends Controller {
    static targets = ['tab', 'panel', 'twig', 'javascript', 'schema', 'defaults', 'examples', 'twigFile', 'jsFile', 'unsaved', 'notice'];
    static values = { saved: Object };

    connect() {
        this.rawSources = document.getElementById('draft_sourceMapJson');
        this.rawManifest = document.getElementById('draft_manifestJson');
        this.dirty = false;
        this.loadSources();
        this.selectTab('twig');
        this.updateDirty();
        this.unload = event => {
            if (this.dirty) { event.preventDefault(); event.returnValue = ''; }
        };
        window.addEventListener('beforeunload', this.unload);
        this.dispatch('ready');
    }

    disconnect() {
        window.removeEventListener('beforeunload', this.unload);
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
            const state = { manifestData: JSON.parse(this.rawManifest.value), sourceMap: JSON.parse(this.rawSources.value), title: document.getElementById('draft_title').value, description: document.getElementById('draft_description').value, tags: document.getElementById('draft_tagsText').value.split(',').map(x => x.trim().toLowerCase()).filter(Boolean) };
            this.dirty = this.rawError || this.defaultsError || canonical(state) !== canonical(this.savedValue);
        } catch { this.dirty = true; }
        this.unsavedTarget.textContent = this.dirty ? 'Unsaved changes' : 'Saved';
        this.unsavedTarget.classList.toggle('badge-warning', this.dirty);
    }

    submitting(event) {
        // Invalid editor JSON cannot silently submit older hidden source.
        if (this.rawError || this.defaultsError) { event.preventDefault(); this.noticeTarget.focus(); return; }
        this.dirty = false;
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
