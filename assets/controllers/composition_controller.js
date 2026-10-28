import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets = ['favoritesOnly', 'insertReference', 'pickerStatus', 'rows', 'containerFields', 'textFields', 'referenceFields', 'tag', 'classes', 'text', 'reference', 'props', 'title', 'saveStatus', 'status', 'frame', 'generated', 'disableJs', 'bodyFont', 'headingFont', 'codeFont', 'theme', 'presetStatus', 'externalNotice', 'externalMessage'];
    static values = { tree: Object, choices: Array, revision: Number, item: String, url: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, settings: Object, themes: Object, presetUrl: String, commandsUrl: String, readUrl: String };
    connect() {
        this.model = structuredClone(this.treeValue); this.path = []; this.epoch = 0;
        for (const key of ['bodyFont', 'headingFont', 'codeFont']) this[`${key}Target`].value = this.settingsValue[key];
        this.themeTarget.value = this.settingsValue.themeReleaseId || '';
        this.disableJsTarget.checked = this.settingsValue.scripts === false;
        this.frameTarget.style.width = `${this.settingsValue.width || 768}px`;
        this.favoritesOnlyTarget.checked = new URL(window.location.href).searchParams.get('favorites') === '1';
        this.render();
        this.unload = event => { if (this.dirty) { event.preventDefault(); event.returnValue = ''; } };
        this.message = event => {
            const v = event.data;
            if (event.source !== this.frameTarget.contentWindow || !v || v.channel !== this.channel || Object.keys(v).sort().join(',') !== 'channel,height,type' || v.type !== 'resize' || !Number.isInteger(v.height) || v.height < 100 || v.height > 4000) return;
            this.frameTarget.style.height = `${v.height}px`;
        };
        window.addEventListener('beforeunload', this.unload); window.addEventListener('message', this.message);
        this.beforeVisit = event => {if(this.dirty&&!window.confirm('Leave this composition and discard unsaved changes?'))event.preventDefault();};
        document.addEventListener('turbo:before-visit',this.beforeVisit);
        this.pollTimer=setInterval(()=>this.pollRevision(),5000);
        this.timer = setTimeout(() => this.preview(), 0);
    }
    disconnect() { clearInterval(this.pollTimer);document.removeEventListener('turbo:before-visit',this.beforeVisit);clearTimeout(this.timer); this.epoch++; this.cancel(this.job); window.removeEventListener('beforeunload', this.unload); window.removeEventListener('message', this.message); }
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
        this.renderChoices();
        this.containerFieldsTarget.hidden = node.type !== 'container'; this.textFieldsTarget.hidden = node.type !== 'text'; this.referenceFieldsTarget.hidden = node.type !== 'reference';
        if (node.type === 'container') { this.tagTarget.value = node.tag; this.classesTarget.value = node.classes.join(' '); }
        if (node.type === 'text') this.textTarget.value = node.value;
        if (node.type === 'reference') this.propsTarget.value = JSON.stringify(node.props, null, 2);
    }
    filterChoices() {
        const url = new URL(window.location.href);
        if (this.favoritesOnlyTarget.checked) url.searchParams.set('favorites', '1');
        else url.searchParams.delete('favorites');
        history.replaceState(history.state, '', url);
        this.renderChoices();
    }
    favoriteChanged({detail}) {
        if (detail.kind !== 'catalogue') return;
        this.choicesValue = this.choicesValue.map(choice => choice.itemId === detail.id ? {...choice, favorite: detail.favorite} : choice);
        this.renderChoices();
    }
    renderChoices() {
        const only = this.favoritesOnlyTarget.checked;
        const current = this.node();
        const selected = this.insertReferenceTarget.value;
        const choices = this.choicesValue.map((choice, index) => ({choice, index})).filter(({choice}) => !only || choice.favorite);
        const option = ({choice, index}, suffix = '', detailed = false) => new Option(detailed ? `${choice.favorite ? '★ ' : ''}${choice.label} · ${choice.kind}${suffix}` : choice.label + suffix, String(index));
        this.insertReferenceTarget.replaceChildren(...choices.map(c => option(c, '', true)));
        if (!choices.length) this.insertReferenceTarget.add(new Option('No eligible references', ''));
        else if (choices.some(c => String(c.index) === selected)) this.insertReferenceTarget.value = selected;
        this.insertReferenceTarget.disabled = !choices.length;
        this.pickerStatusTarget.textContent = !choices.length ? (only ? 'No favorite published references match. Clear Favorites only to browse all eligible items.' : 'Publish a component, block or layout before inserting a reference.') : `${choices.length} eligible released references${only ? ' from favorites' : ''}`;
        this.referenceTarget.replaceChildren(...choices.map(c => option(c)));
        if (current.type === 'reference') {
            const index = this.choicesValue.findIndex(c => c.name === current.name && c.version === current.version);
            if (index >= 0 && !choices.some(c => c.index === index)) this.referenceTarget.add(option({choice: this.choicesValue[index], index}, ' (current, outside favorites)'));
            if (index < 0) this.referenceTarget.add(new Option(`${current.name}@${current.version} (current)`, ''));
            this.referenceTarget.value = index >= 0 ? String(index) : '';
        }
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
    referenceChanged() {if(this.referenceTarget.value==='')return;const c=this.choicesValue[Number(this.referenceTarget.value)];if(c)this.command({type:'update_node',nodeId:this.node().id,changes:{name:c.name,version:c.version,props:structuredClone(c.props)}});}
    addContainer() {return this.command({type:'add_container',selectedId:this.node().id});}
    addText() {return this.command({type:'add_text',selectedId:this.node().id});}
    addReference() {const c=this.insertReferenceTarget.value===''?null:this.choicesValue[Number(this.insertReferenceTarget.value)];if(!c){this.pickerStatusTarget.textContent='No eligible references. Clear Favorites only or publish a component, block or layout first.';return;}return this.command({type:'add_reference',selectedId:this.node().id,name:c.name,version:c.version,props:structuredClone(c.props)});}
    up() {if(this.path.length&&this.path.at(-1)>0)return this.command({type:'move_up',nodeId:this.node().id});}
    down() {if(this.path.length&&this.path.at(-1)<this.siblings().length-1)return this.command({type:'move_down',nodeId:this.node().id});}
    indent() {if(this.path.length&&this.path.at(-1)>0&&this.siblings()[this.path.at(-1)-1].type==='container')return this.command({type:'indent_node',nodeId:this.node().id});}
    outdent() {if(this.path.length>=2)return this.command({type:'outdent_node',nodeId:this.node().id});}
    duplicate() {if(this.path.length)return this.command({type:'duplicate_node',nodeId:this.node().id});}
    remove() {if(this.path.length)return this.command({type:'remove_node',nodeId:this.node().id});}
    pathForId(id) {let found=[];const walk=(node,path)=>{if(node.id===id)found=path;for(const[i,child]of(node.children||[]).entries())walk(child,[...path,i]);};walk(this.model.root,[]);return found;}
    async command(command) {
        if(this.commandPending||this.saving)return;
        if(this.invalid){this.statusTarget.textContent='Correct the props JSON before changing the tree.';return;}
        this.commandPending=true;
        const controls=[...this.element.querySelectorAll('button,input,select,textarea')].map(el=>[el,el.disabled]);controls.forEach(([el])=>el.disabled=true);
        try {const result=await this.json(this.commandsUrlValue,{method:'POST',body:JSON.stringify({document:this.model,expectedRevision:this.revisionValue,commands:[command]})});
            this.model=result.document;this.path=this.pathForId(result.selectedNodeId);this.changed();this.render(true);
        }catch(error){this.saveStatusTarget.textContent=error.message;}
        finally{this.commandPending=false;controls.forEach(([el,disabled])=>{if(el.isConnected)el.disabled=disabled;});}
    }
    async pollRevision() {
        if(document.hidden||this.saving||this.commandPending||this.polling)return;this.polling=true;
        try{const latest=await this.json(this.readUrlValue);if(latest.revision!==this.revisionValue){
            this.externalNoticeTarget.hidden=false;this.externalMessageTarget.textContent=`Saved revision ${latest.revision} is available. ${this.dirty?'Your unsaved tree is preserved; load the saved revision only when ready to discard or merge it.':'Load it to view the latest saved edits.'}`;
        }}catch{}finally{this.polling=false;}
    }
    async loadSaved() {
        if(this.commandPending||this.saving)return;
        if(this.dirty&&!window.confirm('Discard your unsaved tree and load the latest saved revision?'))return;
        try{const latest=await this.json(this.readUrlValue);this.model=latest.document;this.revisionValue=latest.revision;this.titleTarget.value=latest.title;this.path=[];this.dirty=false;this.invalid=false;this.externalNoticeTarget.hidden=true;this.saveStatusTarget.textContent=`Saved revision ${latest.revision}`;this.render(true);this.schedule();}catch(error){this.saveStatusTarget.textContent=error.message;}
    }
    async json(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue } });
        const data = await response.json(); if (!response.ok) throw Error(data.message || 'Composition request failed.'); return data;
    }
    input() { if (this.invalid) throw Error('Correct the current JSON before saving or previewing.'); return { document: this.model, expectedRevision: this.revisionValue, title: this.titleTarget.value }; }
    async save() {
        if(this.saving||this.commandPending)return;
        let saved=false;
        try {
            const snapshot = JSON.stringify(this.input());
            this.saving=true;clearTimeout(this.timer);this.epoch++;this.cancel(this.job);this.job=null;this.statusTarget.textContent='Stale preview — saving composition';
            const result = await this.json(this.urlValue, { method: 'PUT', body: snapshot });
            this.dirty = JSON.stringify(this.input()) !== snapshot;
            this.revisionValue = result.revision;saved=true;
            this.saveStatusTarget.textContent = `Saved revision ${result.revision}${this.dirty ? '; newer changes remain unsaved' : ''}`;
        } catch (error) { this.saveStatusTarget.textContent = error.message; }
        finally{this.saving=false;if(saved)this.schedule();}
    }
    schedule() { clearTimeout(this.timer); this.epoch++; this.statusTarget.textContent = 'Stale preview — changes pending'; this.timer = setTimeout(() => this.preview(), 300); }
    settings() {
        return { ...this.settingsValue, bodyFont: this.bodyFontTarget.value, headingFont: this.headingFontTarget.value, codeFont: this.codeFontTarget.value, themeReleaseId: this.themeTarget.value || null, scripts: !this.disableJsTarget.checked };
    }
    themeChanged() {
        const theme = this.themesValue[this.themeTarget.value]?.document;
        if (theme) {
            for (const key of ['bodyFont', 'headingFont', 'codeFont']) this[`${key}Target`].value = 'theme';
            this.settingsValue = { ...this.settingsValue, rootSize: theme.typography.rootFontSizePx, textClass: theme.typography.textClass };
        }
        this.schedule();
    }
    async savePreset() {
        this.presetStatusTarget.textContent = 'Saving renderer settings…';
        try {
            await this.json(this.presetUrlValue, { method: 'PUT', body: JSON.stringify(this.settings()) });
            this.presetStatusTarget.textContent = 'Renderer settings saved';
        } catch (error) { this.presetStatusTarget.textContent = error.message; }
    }
    async cancel(id) { if (!id) return; try { await this.json(this.statusUrlValue.replace('__JOB__',id), { method: 'DELETE', keepalive: true }); } catch {} }
    async preview() {
        if(this.saving)return;
        clearTimeout(this.timer); const epoch = ++this.epoch;
        await this.cancel(this.job); if (epoch !== this.epoch) return;
        this.statusTarget.textContent = 'Validating composition…';
        try {
            const prepared = await this.json(this.urlValue, { method: 'POST', body: JSON.stringify(this.input()) });
            if (epoch !== this.epoch) return;
            this.generatedTarget.value = prepared.sourceMap[prepared.manifestData.entry];
            const channel = [...crypto.getRandomValues(new Uint8Array(16))].map(v=>v.toString(16).padStart(2,'0')).join('');
            const created = await this.json(this.createUrlValue, { method: 'POST', body: JSON.stringify({ itemId: this.itemValue, sequence: epoch, channel, manifestData: prepared.manifestData, sourceMap: prepared.sourceMap, preset: this.settings() }) });
            if (epoch !== this.epoch) { this.cancel(created.id); return; } this.job = created.id;
            let state;
            do { state = await this.json(this.statusUrlValue.replace('__JOB__',created.id)); if (epoch !== this.epoch) return; if (['failed','cancelled','expired'].includes(state.status)) throw Error(state.error?.message || state.status); if (state.status !== 'succeeded') await new Promise(r=>setTimeout(r,100)); } while (state.status !== 'succeeded');
            const document = await this.json(this.documentUrlValue.replace('__JOB__',created.id)); if (epoch !== this.epoch || document.sequence !== epoch) return;
            this.channel = channel; this.frameTarget.setAttribute('sandbox', this.disableJsTarget.checked ? '' : 'allow-scripts'); this.frameTarget.srcdoc = document.html; this.statusTarget.textContent = 'Composition preview current';
        } catch (error) { if (epoch === this.epoch) this.statusTarget.textContent = `Stale preview — ${error.message}`; }
    }
    width(event) { const width = Number(event.currentTarget.dataset.width); this.settingsValue = { ...this.settingsValue, width }; this.frameTarget.style.width = `${width}px`; }
    reset() { clearTimeout(this.timer); this.epoch++; this.cancel(this.job); this.job = null; this.channel = null; this.frameTarget.srcdoc = ''; this.statusTarget.textContent = 'Preview reset. Build preview to resume.'; }
}
