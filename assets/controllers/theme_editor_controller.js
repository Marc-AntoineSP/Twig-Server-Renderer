import { Controller } from '@hotwired/stimulus';
import {canonical, EditingGuard} from '../editing_guard.js';

export default class extends Controller {
    static targets = ['frame', 'status', 'compare', 'component', 'publishStatus', 'disableJs', 'fontDetails', 'fontSearch', 'fontCategory', 'form', 'variant', 'title', 'saveError', 'saveStatus', 'tryButton', 'retryApply', 'exports'];
    static values = { document: Object, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, themeUrl: String, publishUrl: String, revision: Number, fonts: Array, family: Object, themeId: String, creating: Boolean, saveUrl: String, newUrl: String, editUrl: String, detailUrl: String };

    connect() {
        this.model = structuredClone(this.documentValue);
        this.epoch = 0;
        this.jobs = [];
        this.channels = [];
        this.lastDocuments = [];
        this.variantEdits = new Map();
        this.element.querySelectorAll('[data-theme-path]').forEach(input => this.readField(input));
        this.updateFonts();
        this.message = event => this.receive(event);
        window.addEventListener('message', this.message);
        this.savedModel = structuredClone(this.model);
        this.savedFamilyLabel = this.familyLabel();
        this.savedVariants = this.startingVariants();
        this.guard = new EditingGuard(() => this.hasUnsaved(), () => this.saving, this.element);
        this.element.dataset.editingState = 'clean';
        this.trialEnded = () => {this.tryEnabled=false;clearTimeout(this.trialTimer);this.tryButtonTarget.setAttribute('aria-pressed','false');};
        this.saveTrial = () => this.saveAndApply();
        window.addEventListener('appearance:trial-ended',this.trialEnded);window.addEventListener('appearance:save-trial',this.saveTrial);
        this.timer = setTimeout(() => this.run(), 0);
        queueMicrotask(() => this.notifyDraft());
    }

    disconnect() {
        this.epoch++;
        clearTimeout(this.timer);
        this.jobs.forEach(id => this.cancel(id));
        window.removeEventListener('message', this.message);
        clearTimeout(this.trialTimer);this.guard.disconnect();
        window.removeEventListener('appearance:trial-ended',this.trialEnded);window.removeEventListener('appearance:save-trial',this.saveTrial);
    }

    readField(input) {
        const keys = input.dataset.themePath.split('.');
        const key = keys.pop();
        const parent = keys.reduce((value, name) => value[name], this.model);
        parent[key] = input.dataset.themeType === 'boolean' ? input.checked : input.dataset.themeType === 'number' ? Number(input.value) : input.value;
    }

    changed(event) {
        if (event.target.dataset.themePath) this.readField(event.target);
        this.updateFonts();
        this.refreshDirty();
        this.notifyDraft();
        this.schedule();
        if(this.tryEnabled)this.scheduleTrial();
    }

    notifyDraft() {
        this.dispatch('draft', {detail: {model: structuredClone(this.model), saved: structuredClone(this.savedModel), creating: this.creatingValue}});
    }

    acceptPairing(typography, fonts) {
        this.model.fonts = structuredClone(fonts);
        for (const role of ['body', 'heading', 'code']) for (const suffix of ['FontId', 'Weight', 'Style']) {
            const key = role + suffix;
            this.model.typography[key] = typography[key];
            this.element.querySelector(`[data-theme-path="typography.${key}"]`).value = typography[key];
        }
        this.updateFonts(); this.refreshDirty(); this.notifyDraft(); this.schedule();
        if (this.tryEnabled) this.scheduleTrial();
    }

    filterFonts() {
        const query=this.fontSearchTarget.value.toLowerCase().trim(), category=this.fontCategoryTarget.value;
        const definitions=new Map(this.fontsValue.map(font=>[font.id,font]));
        for(const select of this.element.querySelectorAll('select[data-theme-path$="FontId"]'))for(const option of select.options){const font=definitions.get(option.value);option.hidden=option.value!==select.value&&(!option.textContent.toLowerCase().includes(query)||!!category&&font?.category!==category);}
    }

    updateFonts() {
        const definitions = new Map([...(this.fontsValue || []), ...this.documentValue.fonts, ...this.model.fonts].map(font => [font.id,font]));
        for(const id of ['system-sans','system-serif','system-mono'])if(!definitions.has(id))definitions.set(id,{id,kind:'system',family:id});
        const selected = [...new Set(['body','heading','code'].map(role=>this.model.typography[role+'FontId']).filter(Boolean))];
        this.model.fonts = selected.map(id=>definitions.get(id)).filter(Boolean);
        const details=[];
        for(const role of ['body','heading','code']){
            const font=definitions.get(this.model.typography[role+'FontId']);if(!font)continue;
            if(font.kind==='local'){
                let style=this.model.typography[role+'Style']||'normal';
                if(!font.faces.some(f=>f.style===style))style='normal';
                const supported=font.faces.filter(f=>f.style===style);
                const weight=this.model.typography[role+'Weight']||400;
                const candidates=supported.map(f=>Math.max(f.weightMin,Math.min(f.weightMax,weight)));
                const adjusted=candidates.reduce((best,value)=>Math.abs(value-weight)<Math.abs(best-weight)?value:best,candidates[0]);
                const styles=this.element.querySelector(`[data-theme-path="typography.${role}Style"]`);
                if(styles)for(const option of styles.options)option.disabled=!font.faces.some(face=>face.style===option.value);
                for(const[key,value]of [[role+'Style',style],[role+'Weight',adjusted]]){
                    this.model.typography[key]=value;
                    const field=this.element.querySelector(`[data-theme-path="typography.${key}"]`);if(field)field.value=value;
                }
                const ranges=[...new Set(font.faces.map(f=>`${f.weightMin}${f.weightMax!==f.weightMin?'–'+f.weightMax:''} ${f.style}`))].join(', ');
                details.push(`${role}: ${font.family} · ${ranges} · ${font.license} · ${font.sourceVersion} · ${font.source}`);
            }else {const styles=this.element.querySelector(`[data-theme-path="typography.${role}Style"]`);if(styles)for(const option of styles.options)option.disabled=false;details.push(`${role}: ${font.family} · system fallback`);}
        }
        if(this.hasFontDetailsTarget)this.fontDetailsTarget.textContent=details.join(' | ');
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
            throw Object.assign(new Error(`${data.message || 'Theme request failed.'}${fields ? ' '+fields.slice(0, 1000) : ''}`), {status: response.status});
        }
        return data;
    }

    async cancel(id) {
        if (!id) return;
        try { await this.json(this.statusUrlValue.replace('__JOB__', id), { method: 'DELETE', keepalive: true }); } catch {}
    }

    async run() {
        clearTimeout(this.timer);
        if(this.creatingValue&&(!this.model.name||!this.model.cssName)){this.statusTarget.textContent='Enter a theme identifier and CSS name to preview.';return;}
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
            const release = await this.json(this.publishUrlValue.replace('__THEME__',this.themeIdValue), { method: 'POST', body: JSON.stringify({ expectedRevision: this.revisionValue }) });
            this.publishStatusTarget.textContent = `Theme ${release.version} published immutably.`;
        } catch (error) { this.publishStatusTarget.textContent = error.message; }
    }

    familyLabel() {return this.formTarget.querySelector('[name$="[familyLabel]"]').value.trim();}
    startingVariants() {return this.formTarget.querySelector('[name$="[startingVariants]"]')?.value || 'single';}
    refreshDirty() {
        this.dirty = canonical(this.model) !== canonical(this.savedModel) || this.familyLabel() !== this.savedFamilyLabel || this.startingVariants() !== this.savedVariants;
        this.element.dataset.editingState = this.saving ? 'saving' : this.hasUnsaved() ? 'dirty' : 'clean';
    }
    hasUnsaved() {return this.dirty || [...this.variantEdits.entries()].some(([id,entry])=>id!==this.themeIdValue && canonical(entry.model)!==canonical(entry.savedModel));}
    stashVariant() {if(this.themeIdValue)this.variantEdits.set(this.themeIdValue,{model:structuredClone(this.model),original:structuredClone(this.documentValue),savedModel:structuredClone(this.savedModel),revision:this.revisionValue,dirty:this.dirty});}
    async switchVariant() {
        if(this.saving){this.variantTarget.value=this.themeIdValue;return;}
        this.stashVariant();const selected=this.variantTarget.value;
        const epoch=this.variantEpoch=(this.variantEpoch||0)+1;this.switching=true;
        try {
            let entry=this.variantEdits.get(selected);const fresh=!entry;
            if(!entry){const data=await this.json(this.themeUrlValue.replace('__THEME__',selected));entry={model:data.document,original:structuredClone(data.document),savedModel:structuredClone(data.document),revision:data.revision,dirty:false};}
            if(epoch!==this.variantEpoch||!this.element.isConnected)return;
            this.themeIdValue=selected;this.model=structuredClone(entry.model);this.documentValue=entry.original;this.savedModel=entry.savedModel;this.revisionValue=entry.revision;
            this.model.typography={codeFontId:'system-mono',bodyWeight:400,headingWeight:700,codeWeight:400,bodyStyle:'normal',headingStyle:'normal',codeStyle:'normal',...this.model.typography};
            for(const select of this.element.querySelectorAll('select[data-theme-path$="FontId"]'))for(const font of this.model.fonts)if(![...select.options].some(option=>option.value===font.id))select.add(new Option(font.family,font.id));
            if(this.hasExportsTarget)this.exportsTarget.href=this.detailUrlValue.replace('__THEME__',selected);
            for(const input of this.element.querySelectorAll('[data-theme-path]')){
                const value=input.dataset.themePath.split('.').reduce((parent,key)=>parent?.[key],this.model);
                if(input.dataset.themeType==='boolean')input.checked=Boolean(value);else input.value=value??'';
            }
            this.updateFonts();if(fresh)this.savedModel=structuredClone(this.model);this.refreshDirty();this.titleTarget.textContent=`Edit ${this.familyValue.label} · ${this.model.colorScheme}`;
            this.formTarget.querySelector('[name$="[revision]"]').value=this.revisionValue;
            this.notifyDraft();this.schedule();if(this.tryEnabled)this.scheduleTrial();
        }catch(error){if(epoch===this.variantEpoch){this.saveErrorTarget.hidden=false;this.saveErrorTarget.textContent=error.message;this.variantTarget.value=this.themeIdValue;}}
        finally{if(epoch===this.variantEpoch)this.switching=false;}
    }

    async save(event, apply = false) {
        event?.preventDefault();if(this.saving)return;
        if(this.switching){this.saveStatusTarget.textContent='Wait for the selected variant to finish loading.';return;}
        if(!this.formTarget.reportValidity())return;
        this.saving=true;this.element.dataset.editingState='saving';if(this.hasVariantTarget)this.variantTarget.disabled=true;this.saveErrorTarget.hidden=true;this.saveStatusTarget.textContent='Saving theme…';
        const captured=JSON.stringify(this.model),creating=this.creatingValue;
        const familyLabel=this.formTarget.querySelector('[name$="[familyLabel]"]').value.trim()||null;
        const variants=this.startingVariants();
        try {
            const result=await this.json(creating?this.newUrlValue:this.saveUrlValue.replace('__THEME__',this.themeIdValue),{method:creating?'POST':'PUT',body:JSON.stringify({document:JSON.parse(captured),expectedRevision:creating?null:this.revisionValue,familyLabel,variants,expectedFamilyRevision:this.familyValue.revision||null})});
            if(!this.element.isConnected)return;
            this.themeIdValue=result.id;this.revisionValue=result.revision;this.familyValue=result.family;this.documentValue=result.document;this.creatingValue=false;
            if(JSON.stringify(this.model)===captured)this.model=structuredClone(result.document);
            this.savedModel=structuredClone(result.document);this.savedVariants=variants;
            if(this.familyLabel()===(familyLabel||''))this.formTarget.querySelector('[name$="[familyLabel]"]').value=result.family.label;
            this.savedFamilyLabel=result.family.label;this.refreshDirty();this.notifyDraft();
            this.stashVariant();this.formTarget.querySelector('[name$="[revision]"]').value=result.revision;
            this.tryEnabled=false;clearTimeout(this.trialTimer);this.tryButtonTarget.setAttribute('aria-pressed','false');window.applicationAppearance.revertTrial(true);
            this.flash(creating?'Theme created.':'Theme saved.');this.saveStatusTarget.textContent='Theme saved. Your applied snapshot is unchanged.';
            let applyFailed=false;
            if(apply){
                const success=await window.applicationAppearance.select({kind:'family',themeId:result.id,themeRevision:result.revision});
                if(!success){applyFailed=true;this.retryApplyTarget.hidden=false;this.saveErrorTarget.hidden=false;this.saveErrorTarget.textContent='Theme saved, but application appearance was not updated. Your confirmed appearance is kept. Retry Apply saved theme.';}
                else{this.retryApplyTarget.hidden=true;this.saveStatusTarget.textContent='Theme saved and applied to your application.';}
            }
            if(this.dirty)this.saveStatusTarget.textContent+=' Newer edits remain unsaved.';
            if(creating&&!this.dirty){this.variantEdits.clear();this.dirty=false;location.assign(this.editUrlValue.replace('__THEME__',result.id)+(applyFailed?'?appearanceApplyFailed=1':''));}
            else if(creating){this.formTarget.querySelector('button[type="submit"]').textContent='Save theme';this.titleTarget.textContent='Edit '+result.family.label;for(const key of ['name','colorScheme'])this.formTarget.querySelector(`[data-theme-path="${key}"]`).disabled=true;}
        }catch(error){if(!this.element.isConnected)return;this.saveErrorTarget.hidden=false;this.saveErrorTarget.textContent=error.message;this.saveStatusTarget.textContent='Theme was not saved. Your entered values are kept.';this.element.dataset.editingState=error.status===412?'conflict':'failed';}
        finally{this.saving=false;if(this.hasVariantTarget)this.variantTarget.disabled=false;if(this.element.dataset.editingState==='saving')this.element.dataset.editingState=this.hasUnsaved()?'dirty':'saved';}
    }
    saveAndApply() {return this.save(null,true);}
    async retryApplication() {
        if(await window.applicationAppearance.select({kind:'family',themeId:this.themeIdValue,themeRevision:this.revisionValue})){this.retryApplyTarget.hidden=true;this.saveErrorTarget.hidden=true;this.saveStatusTarget.textContent='Saved theme applied.'+(this.dirty?' Newer edits remain unsaved.':'');}
    }
    flash(message) {
        const existing=document.querySelector('[data-flash]');const alert=existing||document.createElement('div');alert.dataset.flash='';alert.className='alert alert-success mb-6';alert.setAttribute('role','status');alert.textContent=message;if(!existing)this.element.prepend(alert);
        for(const other of document.querySelectorAll('[data-flash]'))if(other!==alert)other.remove();
    }
    tryApplication() {this.tryEnabled=true;this.tryButtonTarget.setAttribute('aria-pressed','true');this.scheduleTrial(0);}
    scheduleTrial(delay = 300) {
        clearTimeout(this.trialTimer);
        this.trialTimer=setTimeout(()=>{if(this.tryEnabled)window.applicationAppearance.tryDocument(structuredClone(this.model));},delay);
    }
    revertApplication() {this.tryEnabled=false;clearTimeout(this.trialTimer);this.tryButtonTarget.setAttribute('aria-pressed','false');window.applicationAppearance.revertTrial();}
}
