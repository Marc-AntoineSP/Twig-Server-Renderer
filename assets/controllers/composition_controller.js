import { Controller } from '@hotwired/stimulus';
import {canonical, EditingGuard, confirmEditing} from '../editing_guard.js';
export default class extends Controller {
    static targets = ['referenceIdentity', 'referenceStatus', 'referencePreview', 'rows', 'containerFields', 'textFields', 'referenceFields', 'tag', 'classes', 'text', 'props', 'title', 'saveStatus', 'status', 'frame', 'generated', 'disableJs', 'bodyFont', 'headingFont', 'codeFont', 'theme', 'presetStatus', 'externalNotice', 'externalMessage', 'historyDialog', 'historyList', 'historyStatus', 'historyDetail', 'historyPrevious', 'historyNext', 'historyRestore', 'historyLoad'];
    static values = { tree: Object, referenceReadUrl: String, revision: Number, item: String, url: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, settings: Object, themes: Object, presetUrl: String, commandsUrl: String, readUrl: String, historyUrl: String, restoreUrl: String };
    connect() {
        this.model = structuredClone(this.treeValue); this.path = []; this.epoch = 0;
        for (const key of ['bodyFont', 'headingFont', 'codeFont']) this[`${key}Target`].value = this.settingsValue[key];
        this.themeTarget.value = this.settingsValue.themeReleaseId || '';
        this.disableJsTarget.checked = this.settingsValue.scripts === false;
        this.frameTarget.style.width = `${this.settingsValue.width || 768}px`;
        this.render();
        this.savedSnapshot = canonical(this.snapshot()); this.dirty = false; this.element.dataset.editingState = 'clean';
        this.guard = new EditingGuard(() => this.dirty, () => this.saving || this.commandPending, this.element);
        this.message = event => {
            const v = event.data;
            if (event.source !== this.frameTarget.contentWindow || !v || v.channel !== this.channel || Object.keys(v).sort().join(',') !== 'channel,height,type' || v.type !== 'resize' || !Number.isInteger(v.height) || v.height < 100 || v.height > 4000) return;
            this.frameTarget.style.height = `${v.height}px`;
        };
        window.addEventListener('message', this.message);
        this.pollTimer=setInterval(()=>this.pollRevision(),5000);
        this.timer = setTimeout(() => this.preview(), 0);
    }
    disconnect() { this.referenceRequest?.abort();this.referenceEpoch=(this.referenceEpoch||0)+1;clearInterval(this.pollTimer);this.guard.disconnect();this.historyEpoch=(this.historyEpoch||0)+1;clearTimeout(this.timer); this.epoch++; this.cancel(this.job); window.removeEventListener('message', this.message); }
    node(path = this.path) { return path.reduce((node, index) => node.children[index], this.model.root); }
    siblings() { return this.node(this.path.slice(0, -1)).children; }
    render(focus = false) {
        this.rowsTarget.replaceChildren(); this.visible = [];
        const walk = (node, path) => {
            const button = document.createElement('button'); button.type = 'button'; button.role = 'treeitem';
            button.className = 'block w-full break-all rounded px-3 py-2 text-left hover:bg-base-200'; button.style.paddingLeft = `${path.length * 20 + 12}px`;
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
        this.inspectReference(node);
        this.containerFieldsTarget.hidden = node.type !== 'container'; this.textFieldsTarget.hidden = node.type !== 'text'; this.referenceFieldsTarget.hidden = node.type !== 'reference';
        if (node.type === 'container') { this.tagTarget.value = node.tag; this.classesTarget.value = node.classes.join(' '); }
        if (node.type === 'text') this.textTarget.value = node.value;
        if (node.type === 'reference') this.propsTarget.value = JSON.stringify(node.props, null, 2);
    }
    async inspectReference(node, force = false) {
        const key = node.type === 'reference' ? `${node.id}:${node.name}@${node.version}` : null;
        if (!force && key === this.referenceKey) return;
        this.referenceKey = key;
        const epoch = this.referenceEpoch = (this.referenceEpoch || 0) + 1;
        this.referenceRequest?.abort();this.referencePreviewTarget.hidden = true;
        if (!key) return;
        this.referenceIdentityTarget.textContent = `${node.name}@${node.version}`;
        this.referenceStatusTarget.textContent = 'Checking pinned immutable reference…';
        this.referenceRequest = new AbortController();
        try {
            const url = new URL(this.referenceReadUrlValue, location.href);url.searchParams.set('name', node.name);url.searchParams.set('version', node.version);
            const detail = await this.json(url, {signal: this.referenceRequest.signal});
            if (epoch !== this.referenceEpoch || !this.element.isConnected) return;
            this.referenceStatusTarget.textContent = `${detail.kind} · Published immutable source accessible. Instance props are kept.`;
            this.referencePreviewTarget.href = detail.previewUrl;this.referencePreviewTarget.hidden = false;
        } catch (error) {
            if (epoch !== this.referenceEpoch || error.name === 'AbortError') return;
            this.referenceStatusTarget.textContent = 'Reference unavailable or access changed. Your instance props are kept. Recheck or choose a replacement.';
        }
    }
    recheckReference() {return this.inspectReference(this.node(), true);}
    pickerContext() {return {document: structuredClone(this.model), expectedRevision: this.revisionValue, selectedNodeId: this.node().id};}
    browseReference(event, mode) {
        if (this.invalid || this.saving || this.commandPending) {this.statusTarget.textContent = 'Finish the pending edit and correct props JSON before choosing a reference.';return;}
        this.dispatch('browse', {detail: {mode, opener: event.currentTarget}});
    }
    addReference(event) {this.browseReference(event, 'insert');}
    replaceReference(event) {this.browseReference(event, 'replace');}
    async chooseReference(reference, mode, useDefaults) {
        if (mode === 'insert') return this.command({type:'add_reference', selectedId:this.node().id, name:reference.name, version:reference.version});
        const changes = {name:reference.name, version:reference.version};
        if (useDefaults) changes.props = structuredClone(reference.props);
        return this.command({type:'update_node', nodeId:this.node().id, changes});
    }
    focusSelected() {this.visible.find(row => JSON.stringify(row.path) === JSON.stringify(this.path))?.button.focus();}
    key(event, path) {
        const i = this.visible.findIndex(row => JSON.stringify(row.path) === JSON.stringify(path));
        let target;
        if (event.key === 'ArrowUp') target = this.visible[i - 1]?.path;
        if (event.key === 'ArrowDown') target = this.visible[i + 1]?.path;
        if (event.key === 'ArrowLeft' && path.length) target = path.slice(0, -1);
        if (event.key === 'ArrowRight' && this.node(path).children?.length) target = [...path, 0];
        if (target) { event.preventDefault(); this.path = target; this.render(true); }
    }
    snapshot() {return {document: this.model, title: this.titleTarget.value};}
    metadata() {
        this.dirty = Boolean(this.invalid) || canonical(this.snapshot()) !== this.savedSnapshot;
        this.element.dataset.editingState = this.saving ? 'saving' : this.dirty ? 'dirty' : 'clean';
        this.saveStatusTarget.textContent = this.saving ? 'Saving…; newer changes remain unsaved' : this.dirty ? 'Unsaved changes' : `Saved revision ${this.revisionValue}`;
    }
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
    addContainer() {return this.command({type:'add_container',selectedId:this.node().id});}
    addText() {return this.command({type:'add_text',selectedId:this.node().id});}
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
            this.model=result.document;this.path=this.pathForId(result.selectedNodeId);this.changed();this.render(true);return true;
        }catch(error){this.failure(error);return false;}
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
        if(this.dirty&&!await confirmEditing({message:'Discard your unsaved tree and title and load the latest saved revision?',label:'Discard local edits and load saved revision'}))return;
        this.saving=true;const release=this.disableControls();let loaded=false;
        try {const latest=await this.json(this.readUrlValue);if(!this.element.isConnected)return;this.acceptSaved(latest);loaded=true;
            this.saveStatusTarget.textContent=`Saved revision ${latest.revision}`;
            if(this.historyDialogTarget.open){this.historyExpected=latest.revision;await this.loadHistory(0);}
        }catch(error){this.failure(error);}
        finally{this.saving=false;release();if(loaded)this.schedule();}
    }
    disableControls() {
        const fields=[...this.element.querySelectorAll('input,select,textarea,button')].filter(el=>!el.closest('[data-appearance-recovery]')).map(el=>[el,el.disabled]);
        fields.forEach(([el])=>el.disabled=true);return ()=>fields.forEach(([el,disabled])=>{if(el.isConnected)el.disabled=disabled;});
    }
    acceptSaved(data) {
        this.epoch++;this.cancel(this.job);this.job=null;this.channel=null;this.frameTarget.srcdoc='';
        this.model=data.document;this.revisionValue=data.revision;this.titleTarget.value=data.title;this.path=[];this.invalid=false;
        this.savedSnapshot=canonical(this.snapshot());this.dirty=false;this.element.dataset.editingState='saved';this.externalNoticeTarget.hidden=true;
        if(data.generatedTwig)this.generatedTarget.value=data.generatedTwig;
        this.render(true);
    }
    failure(error) {
        this.saveStatusTarget.textContent=error.message;this.element.dataset.editingState=error.status===412?'conflict':'failed';
        if(error.status===412){this.externalNoticeTarget.hidden=false;this.externalMessageTarget.textContent='Revision conflict. Your local work is kept. Copy or merge it before deliberately loading the saved revision.';}
    }
    async openHistory() {
        this.historyExpected=this.revisionValue;this.selectedHistory=null;this.historyRestoreTarget.disabled=true;this.historyLoadTarget.hidden=true;
        this.historyDetailTarget.textContent='Select a saved revision to compare with the editor’s saved head. Unsaved local edits are not part of this comparison.';
        this.historyDialogTarget.showModal();await this.loadHistory(0);
    }
    closeHistory() {this.historyEpoch=(this.historyEpoch||0)+1;this.historyDialogTarget.close();}
    historyBack() {return this.loadHistory(Math.max(0,this.historyOffset-20));}
    historyForward() {if(this.historyNextOffset!==null)return this.loadHistory(this.historyNextOffset);}
    countsText(counts) {return Object.entries(counts).map(([key,count])=>`${count} ${key}`).join(' · ');}
    async loadHistory(offset) {
        const epoch=this.historyEpoch=(this.historyEpoch||0)+1;this.historyStatusTarget.textContent='Loading saved history…';
        try {
            const page=await this.json(this.historyUrlValue+'?offset='+offset+'&limit=20');if(epoch!==this.historyEpoch||!this.element.isConnected)return;
            this.historyOffset=offset;this.historyNextOffset=page.nextOffset;this.historyPreviousTarget.disabled=offset===0;this.historyNextTarget.disabled=page.nextOffset===null;
            this.historyListTarget.replaceChildren();
            for(const entry of page.history){
                const row=document.createElement('li');row.className='rounded border border-base-300 p-3';
                const button=document.createElement('button');button.type='button';button.className='btn btn-outline btn-sm';button.textContent=`Revision ${entry.revision}`;button.addEventListener('click',()=>this.inspectHistory(entry.revision));
                const summary=document.createElement('p');summary.className='my-2 text-sm';summary.textContent=`${entry.summary} · ${new Date(entry.createdAt).toLocaleString()}`;
                const actor=document.createElement('p');actor.className='break-words text-xs';actor.textContent=entry.actorId?`Snapshot actor: ${entry.actorId}`:'Snapshot actor unavailable';
                const changes=document.createElement('p');changes.className='text-sm';changes.textContent=entry.changes?`${this.countsText(entry.changes)} (vs recoverable revision ${entry.comparedWith})`:'First recoverable snapshot; no earlier comparison available.';
                row.append(button,summary,actor,changes);this.historyListTarget.append(row);
            }
            this.historyStatusTarget.textContent=`${page.history.length?'Saved history page '+(offset/20+1)+'.':'No saved history yet.'} ${page.warnings.join(' ')}${page.revision!==this.revisionValue?' A newer saved head exists; your editor is unchanged.':''}`;
        }catch(error){if(epoch===this.historyEpoch)this.historyStatusTarget.textContent=error.message;}
    }
    async inspectHistory(revision) {
        const epoch=this.historyEpoch=(this.historyEpoch||0)+1;this.historyRestoreTarget.disabled=true;this.selectedHistory=null;
        try {
            const detail=await this.json(this.historyUrlValue+'/'+revision+'?expectedRevision='+this.revisionValue);
            if(epoch!==this.historyEpoch||!this.element.isConnected)return;
            this.selectedHistory=detail;this.historyExpected=detail.expectedRevision;this.historyDetailTarget.replaceChildren();
            const heading=document.createElement('h3');heading.className='font-semibold';heading.textContent=`Restore revision ${revision} as a new revision`;heading.tabIndex=-1;
            const summary=document.createElement('p');summary.className='my-3 break-words';summary.textContent=`Title: ${detail.title}. ${this.countsText(detail.counts)}. This compares saved revision ${detail.expectedRevision} with revision ${revision}; restoring also replaces the saved title and description.`;
            const list=document.createElement('ul');list.className='list-disc space-y-2 pl-5';
            for(const diff of [...detail.differences,...detail.metadata.map(field=>({...field,kind:'metadata'}))]){const line=document.createElement('li');line.className='break-words text-sm';line.textContent=`${diff.kind}: ${diff.label||diff.nodeId||diff.field}${diff.field?' · '+diff.field:''}${diff.before!==undefined?' · '+diff.before+' → '+diff.after:''}`;list.append(line);}
            if(detail.truncated){const notice=document.createElement('p');notice.textContent='First 100 differences shown; the counts above include all changes.';this.historyDetailTarget.append(notice);}
            this.historyDetailTarget.append(heading,summary,list);this.historyRestoreTarget.disabled=false;heading.focus();
        }catch(error){if(epoch!==this.historyEpoch)return;this.historyStatusTarget.textContent=error.message;this.historyLoadTarget.hidden=error.status!==412;}
    }
    async restoreHistory() {
        if(this.saving||this.commandPending||!this.selectedHistory)return;
        const detail=this.selectedHistory;
        if(this.revisionValue!==detail.expectedRevision){this.historyStatusTarget.textContent='The saved head changed. Select this revision again before restoring.';return;}
        if(this.dirty&&!await confirmEditing({message:`Restore saved revision ${detail.revision} as a new head? This replaces your unsaved tree and title. ${this.countsText(detail.counts)}.`,label:'Discard local edits and restore'}))return;
        this.saving=true;this.element.dataset.editingState='saving';const release=this.disableControls();let restored=false;
        try {
            const result=await this.json(this.restoreUrlValue,{method:'POST',body:JSON.stringify({expectedRevision:detail.expectedRevision,restoreRevision:detail.revision})});
            if(!this.element.isConnected)return;this.acceptSaved(result);restored=true;
            this.saveStatusTarget.textContent=`Restored revision ${result.restoredFrom} as new revision ${result.revision}`;this.historyDialogTarget.close();
        }catch(error){this.failure(error);this.historyStatusTarget.textContent=error.message;this.historyLoadTarget.hidden=error.status!==412;}
        finally{this.saving=false;release();if(restored)this.schedule();}
    }
    async json(url, options = {}) {
        const response = await fetch(url, { ...options, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrfValue } });
        const data = await response.json(); if (!response.ok) throw Object.assign(Error(data.message || 'Composition request failed.'), {status: response.status}); return data;
    }
    input() { if (this.invalid) throw Error('Correct the current JSON before saving or previewing.'); return { document: this.model, expectedRevision: this.revisionValue, title: this.titleTarget.value }; }
    async save() {
        if(this.saving||this.commandPending)return;
        let saved=false;
        try {
            const captured = structuredClone(this.input()); const snapshot = JSON.stringify(captured);
            this.saving=true;this.element.dataset.editingState='saving';this.saveStatusTarget.textContent='Saving…';clearTimeout(this.timer);this.epoch++;this.cancel(this.job);this.job=null;this.statusTarget.textContent='Stale preview — saving composition';
            const result = await this.json(this.urlValue, { method: 'PUT', body: snapshot });
            if(!this.element.isConnected)return;
            this.savedSnapshot=canonical({document:result.document,title:captured.title});
            this.revisionValue=result.revision;this.dirty=Boolean(this.invalid)||canonical(this.snapshot())!==this.savedSnapshot;this.element.dataset.editingState=this.dirty?'dirty':'saved';saved=true;
            this.saveStatusTarget.textContent = `Saved revision ${result.revision}${this.dirty ? '; newer changes remain unsaved' : ''}`;
        } catch (error) { this.failure(error); }
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
