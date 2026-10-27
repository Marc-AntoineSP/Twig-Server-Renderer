import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['rows', 'containerFields', 'textFields', 'referenceFields', 'tag', 'classes', 'text', 'reference', 'props', 'title', 'saveStatus', 'status', 'frame', 'generated', 'disableJs'];
    static values = { tree: Object, choices: Array, revision: Number, item: String, url: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String };
    connect() {
        this.model = structuredClone(this.treeValue); this.path = []; this.epoch = 0;
        this.render();
        this.unload = event => { if (this.dirty) { event.preventDefault(); event.returnValue = ''; } };
        this.message = event => {
            const v = event.data;
            if (event.source !== this.frameTarget.contentWindow || !v || v.channel !== this.channel || Object.keys(v).sort().join(',') !== 'channel,height,type' || v.type !== 'resize' || !Number.isInteger(v.height) || v.height < 100 || v.height > 4000) return;
            this.frameTarget.style.height = `${v.height}px`;
        };
        window.addEventListener('beforeunload', this.unload); window.addEventListener('message', this.message);
        this.timer = setTimeout(() => this.preview(), 0);
    }
    disconnect() { clearTimeout(this.timer); this.epoch++; this.cancel(this.job); window.removeEventListener('beforeunload', this.unload); window.removeEventListener('message', this.message); }
    node(path = this.path) { return path.reduce((node, index) => node.children[index], this.model.root); }
    siblings() { return this.node(this.path.slice(0, -1)).children; }
    render(focus = false) {
        this.rowsTarget.replaceChildren(); this.visible = [];
        const walk = (node, path) => {
            const button = document.createElement('button'); button.type = 'button'; button.role = 'treeitem';
            button.className = 'block w-full rounded px-3 py-2 text-left hover:bg-base-200'; button.style.paddingLeft = `${path.length * 20 + 12}px`;
            const selected = JSON.stringify(path) === JSON.stringify(this.path);
            button.setAttribute('aria-selected', String(selected)); button.setAttribute('aria-level', String(path.length + 1)); button.tabIndex = selected ? 0 : -1;
            button.classList.toggle('bg-base-200', selected);
            if (node.type === 'container') button.setAttribute('aria-expanded', 'true');
            button.textContent = node.type === 'reference' ? `${node.name}@${node.version}` : node.type === 'text' ? `Text: ${node.value.slice(0, 80)}` : `<${node.tag}> ${node.classes.join(' ')}`;
            button.addEventListener('click', () => { this.path = path; this.render(true); });
            button.addEventListener('keydown', event => this.key(event, path));
            this.rowsTarget.append(button); this.visible.push({ path, button });
            if (selected && focus) button.focus();
            for (const [i, child] of (node.children || []).entries()) walk(child, [...path, i]);
        };
        walk(this.model.root, []);
        const node = this.node();
        this.containerFieldsTarget.hidden = node.type !== 'container'; this.textFieldsTarget.hidden = node.type !== 'text'; this.referenceFieldsTarget.hidden = node.type !== 'reference';
        if (node.type === 'container') { this.tagTarget.value = node.tag; this.classesTarget.value = node.classes.join(' '); }
        if (node.type === 'text') this.textTarget.value = node.value;
        if (node.type === 'reference') { this.referenceTarget.value = this.choicesValue.findIndex(c => c.name === node.name && c.version === node.version); this.propsTarget.value = JSON.stringify(node.props, null, 2); }
    }
    key(event, path) {
        const i = this.visible.findIndex(row => JSON.stringify(row.path) === JSON.stringify(path));
        let target;
        if (event.key === 'ArrowUp') target = this.visible[i - 1]?.path;
        if (event.key === 'ArrowDown') target = this.visible[i + 1]?.path;
        if (event.key === 'ArrowLeft' && path.length) target = path.slice(0, -1);
        if (event.key === 'ArrowRight' && this.node(path).children?.length) target = [...path, 0];
        if (target) { event.preventDefault(); this.path = target; this.render(true); }
    }
    metadata() { this.dirty = true; this.saveStatusTarget.textContent = 'Unsaved changes'; }
    changed() { this.invalid = false; this.metadata(); this.render(); this.schedule(); }
    fields() {
        const node = this.node();
        try {
            if (node.type === 'container') { node.tag = this.tagTarget.value; node.classes = this.classesTarget.value.trim().split(/\s+/).filter(Boolean); }
            if (node.type === 'text') node.value = this.textTarget.value;
            if (node.type === 'reference') { const props = JSON.parse(this.propsTarget.value); if (!props || Array.isArray(props) || typeof props !== 'object') throw Error('Props must be an object.'); node.props = props; }
            this.invalid = false; this.metadata(); this.schedule();
            // Preserve the current caret while updating row labels.
            this.visible.forEach(row => { if (JSON.stringify(row.path) === JSON.stringify(this.path)) row.button.textContent = node.type === 'text' ? `Text: ${node.value.slice(0,80)}` : node.type === 'container' ? `<${node.tag}> ${node.classes.join(' ')}` : `${node.name}@${node.version}`; });
        } catch (error) { this.invalid = true; this.metadata(); clearTimeout(this.timer); this.epoch++; this.statusTarget.textContent = `Stale preview — ${error.message}`; }
    }
    referenceChanged() { const c = this.choicesValue[Number(this.referenceTarget.value)]; if (!c) return; Object.assign(this.node(), { name: c.name, version: c.version, props: structuredClone(c.props) }); this.changed(); }
    add(node) {
        if (this.node().type === 'container') { const parent = this.node(); parent.children.push(node); this.path = [...this.path, parent.children.length - 1]; }
        else if (this.path.length) { const siblings = this.siblings(); const i = this.path.at(-1) + 1; siblings.splice(i, 0, node); this.path[this.path.length - 1] = i; }
        else return;
        this.changed();
    }
    addContainer() { this.add({ type: 'container', tag: 'div', classes: ['grid', 'gap-4'], children: [] }); }
    addText() { this.add({ type: 'text', value: 'New text' }); }
    addReference() { const c = this.choicesValue[0]; if (!c) { this.statusTarget.textContent = 'Publish a component or block before adding a reference.'; return; } this.add({ type: 'reference', name: c.name, version: c.version, props: structuredClone(c.props) }); }
    up() { this.move(-1); } down() { this.move(1); }
    move(delta) { if (!this.path.length) return; const list = this.siblings(); const i = this.path.at(-1); const j = i + delta; if (j < 0 || j >= list.length) return; [list[i], list[j]] = [list[j], list[i]]; this.path[this.path.length - 1] = j; this.changed(); }
    indent() { if (!this.path.length) return; const list = this.siblings(); const i = this.path.at(-1); if (i < 1 || list[i - 1].type !== 'container') return; const [node] = list.splice(i,1); list[i - 1].children.push(node); this.path = [...this.path.slice(0, -1), i - 1, list[i - 1].children.length - 1]; this.changed(); }
    outdent() { if (this.path.length < 2) return; const node = this.node(); this.siblings().splice(this.path.at(-1),1); const parentIndex = this.path.at(-2); const grand = this.node(this.path.slice(0,-2)); grand.children.splice(parentIndex + 1,0,node); this.path = [...this.path.slice(0,-2),parentIndex + 1]; this.changed(); }
    duplicate() { if (!this.path.length) return; const copy = structuredClone(this.node()); const i = this.path.at(-1) + 1; this.siblings().splice(i,0,copy); this.path[this.path.length - 1] = i; this.changed(); }
    remove() { if (!this.path.length) return; this.siblings().splice(this.path.at(-1),1); this.path = this.path.slice(0,-1); this.changed(); }
    async json(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue } });
        const data = await response.json(); if (!response.ok) throw Error(data.message || 'Composition request failed.'); return data;
    }
    input() { if (this.invalid) throw Error('Correct the current JSON before saving or previewing.'); return { document: this.model, expectedRevision: this.revisionValue, title: this.titleTarget.value }; }
    async save() {
        try {
            const snapshot = JSON.stringify(this.input());
            const result = await this.json(this.urlValue, { method: 'PUT', body: snapshot });
            this.dirty = JSON.stringify(this.input()) !== snapshot;
            this.revisionValue = result.revision;
            this.saveStatusTarget.textContent = `Saved revision ${result.revision}${this.dirty ? '; newer changes remain unsaved' : ''}`;
        } catch (error) { this.saveStatusTarget.textContent = error.message; }
    }
    schedule() { clearTimeout(this.timer); this.epoch++; this.statusTarget.textContent = 'Stale preview — changes pending'; this.timer = setTimeout(() => this.preview(), 300); }
    async cancel(id) { if (!id) return; try { await this.json(this.statusUrlValue.replace('__JOB__',id), { method: 'DELETE', keepalive: true }); } catch {} }
    async preview() {
        clearTimeout(this.timer); const epoch = ++this.epoch;
        await this.cancel(this.job); if (epoch !== this.epoch) return;
        this.statusTarget.textContent = 'Validating composition…';
        try {
            const prepared = await this.json(this.urlValue, { method: 'POST', body: JSON.stringify(this.input()) });
            if (epoch !== this.epoch) return;
            this.generatedTarget.value = prepared.sourceMap[prepared.manifestData.entry];
            const channel = [...crypto.getRandomValues(new Uint8Array(16))].map(v=>v.toString(16).padStart(2,'0')).join('');
            const created = await this.json(this.createUrlValue, { method: 'POST', body: JSON.stringify({ itemId: this.itemValue, sequence: epoch, channel, manifestData: prepared.manifestData, sourceMap: prepared.sourceMap, preset: { scripts: !this.disableJsTarget.checked } }) });
            if (epoch !== this.epoch) { this.cancel(created.id); return; } this.job = created.id;
            let state;
            do { state = await this.json(this.statusUrlValue.replace('__JOB__',created.id)); if (epoch !== this.epoch) return; if (['failed','cancelled','expired'].includes(state.status)) throw Error(state.error?.message || state.status); if (state.status !== 'succeeded') await new Promise(r=>setTimeout(r,100)); } while (state.status !== 'succeeded');
            const document = await this.json(this.documentUrlValue.replace('__JOB__',created.id)); if (epoch !== this.epoch || document.sequence !== epoch) return;
            this.channel = channel; this.frameTarget.srcdoc = document.html; this.statusTarget.textContent = 'Composition preview current';
        } catch (error) { if (epoch === this.epoch) this.statusTarget.textContent = `Stale preview — ${error.message}`; }
    }
    width(event) { this.frameTarget.style.width = `${event.currentTarget.dataset.width}px`; }
    reset() { clearTimeout(this.timer); this.epoch++; this.cancel(this.job); this.job = null; this.channel = null; this.frameTarget.srcdoc = ''; this.statusTarget.textContent = 'Preview reset. Build preview to resume.'; }
}
