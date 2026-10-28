import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['toggle', 'moon', 'sun', 'name', 'mode', 'status', 'family', 'menu', 'unavailable', 'trialBanner', 'applied', 'details', 'variantLink'];
    static values = {state: Object, csrf: String, prepareUrl: String, commitUrl: String, readUrl: String, trialUrl: String, familiesUrl: String, themeEditUrl: String};

    connect() {
        this.confirmed = structuredClone(this.stateValue); this.current = this.confirmed; this.serverState = this.confirmed;
        this.clientId = [...crypto.getRandomValues(new Uint8Array(16))].map(v=>v.toString(16).padStart(2,'0')).join('');
        this.sequence = 0; this.commitTail = Promise.resolve();
        this.system = matchMedia('(prefers-color-scheme: dark)');
        this.osChanged = () => {if (this.current.mode === 'system') this.render(this.current);};
        this.beforeCache = () => this.revertTrial(true);
        this.fontError = () => this.fontStatus();
        this.onPageShow = event => {if (event.persisted) this.reconcile();};
        this.system.addEventListener('change', this.osChanged);
        document.addEventListener('turbo:before-cache', this.beforeCache);
        document.fonts.addEventListener('loadingerror', this.fontError);
        window.addEventListener('pageshow', this.onPageShow);
        window.applicationAppearance = this;
        this.initialStylesMissing=Object.values(this.confirmed.variants).some(variant=>![...document.querySelectorAll('link[data-appearance-sheet]')].some(link=>link.href===new URL(variant.url,location.href).href&&this.sheetReady(link)));
        this.render(this.confirmed); this.prune();
        if (this.confirmed.notice) this.message(this.confirmed.notice, true);
        if(this.initialStylesMissing)this.message('A saved appearance stylesheet could not load. Readable built-in styling and Restore default appearance remain available.',true);
    }

    disconnect() {
        this.sequence++; this.pending?.abort();
        if (this.trialActive && this.element.isConnected) window.StyleguideAppearance.render(this.confirmed);
        this.system.removeEventListener('change', this.osChanged);
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        document.fonts.removeEventListener('loadingerror', this.fontError);
        window.removeEventListener('pageshow', this.onPageShow);
        if (window.applicationAppearance === this) delete window.applicationAppearance;
    }

    render(state) {
        this.current = state;
        const mode = window.StyleguideAppearance.render(state), opposite = mode === 'dark' ? 'light' : 'dark';
        const action = `Switch to ${opposite} mode`, available = Boolean(state.variants[opposite]);
        this.toggleTarget.setAttribute('aria-label', action); this.toggleTarget.title = action;
        this.toggleTarget.disabled = !available;
        this.moonTarget.toggleAttribute('hidden', mode !== 'light'); this.sunTarget.toggleAttribute('hidden', mode !== 'dark');
        this.nameTarget.textContent = state.familyName;
        this.nameTarget.title = state.familyName;
        this.modeTarget.textContent = `${mode} · ${state.mode === 'system' ? 'system' : 'explicit preference'}`;
        this.familyTarget.value = state.familyId;
        this.unavailableTarget.hidden = available;
        this.unavailableTarget.textContent = available ? '' : `Only ${mode} is available in this ${state.trial ? 'trial' : 'applied snapshot'}. Your ${state.mode} preference is kept.`;
        const variant = state.variants[mode];
        this.variantLinkTarget.hidden = available || !variant.themeId;
        if (!this.variantLinkTarget.hidden) this.variantLinkTarget.href = this.themeEditUrlValue.replace('__THEME__', variant.themeId);
        this.trialBannerTarget.hidden = !state.trial;
        this.updateRecovery();
        for (const badge of this.appliedTargets) {
            const active = !state.trial && !this.initialStylesMissing && badge.dataset.familyId === state.familyId && badge.dataset.variantMode === mode;
            badge.hidden = !active;
            if (active) badge.textContent = `Applied · ${mode} snapshot`;
        }
        this.element.dataset.appearanceStateValue = JSON.stringify(this.confirmed);
        this.detailsTarget.textContent = `${state.familyName}. Requested: ${state.mode}; effective: ${mode}. Saved preference revision ${this.confirmed.revision}. ${Object.entries(state.variants).map(([key,v])=>`${key}: theme revision ${v.revision ?? 'unsaved'}`).join('; ')}.`;
        this.dispatch('changed', {detail:{state, mode}});
        document.fonts.ready.then(()=>{if (this.current === state) this.fontStatus();});
    }

    message(text, failed = false) {this.statusTarget.textContent = text; this.statusTarget.dataset.failed = String(failed); this.statusTarget.hidden = !text;if(text)this.statusTarget.setAttribute('role','status');else this.statusTarget.removeAttribute('role');this.updateRecovery();}
    updateRecovery() {
        const trial = Boolean(this.current?.trial), failed = this.statusTarget.dataset.failed === 'true';
        for (const region of this.element.querySelectorAll('[data-appearance-recovery]')) region.dataset.recoveryState = failed ? 'error' : (trial ? 'trial' : 'normal');
        for (const action of this.element.querySelectorAll('[data-appearance-trial-only]')) action.hidden = !trial;
        for (const message of this.element.querySelectorAll('[data-appearance-recovery-message]')) {message.textContent = this.statusTarget.textContent; message.hidden = this.statusTarget.hidden;}
        const explanation = this.element.querySelector('[data-appearance-recovery-explanation]');
        explanation.hidden = !trial && !failed;
        explanation.textContent = trial
            ? 'Revert trial restores your saved appearance without saving editor changes. Restore default also replaces your saved appearance with Studio default and system mode.'
            : 'Restore default saves Studio default and system mode. Unsaved editor changes are kept.';
    }
    async json(url, options = {}) {
        let response;
        const signal=options.signal?AbortSignal.any([options.signal,AbortSignal.timeout(15000)]):AbortSignal.timeout(15000);
        try {response=await fetch(url, {...options,signal,credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrfValue}});}
        catch(error){if(error.name==='TimeoutError')throw Error('The appearance request timed out. Your confirmed appearance is kept.');throw error;}
        let data; try {data = await response.json();} catch {throw Error('The appearance service returned an invalid response.');}
        if (!response.ok) {const error=Error(data.message || 'Appearance request failed.');error.status=response.status;error.code=data.code;throw error;}
        return data;
    }
    fresh() {this.pending?.abort();this.pending=new AbortController();return {sequence:++this.sequence, signal:this.pending.signal};}
    active(sequence) {if (sequence !== this.sequence) throw new DOMException('Obsolete appearance request','AbortError');}

    async sheet(url, signal) {
        const address = new URL(url, location.href);
        if (address.origin !== location.origin || !/^\/appearance\/assets\/[a-f0-9]{64}-[a-f0-9]{64}\.css$/.test(address.pathname)) throw Error('The theme stylesheet URL is invalid.');
        let link=[...document.querySelectorAll('link[data-appearance-sheet]')].find(el=>el.href===address.href);
        if (this.sheetReady(link)) return;
        let created = false;
        if (!link) {link=document.createElement('link');link.rel='stylesheet';link.href=address.href;link.integrity='sha256-'+btoa(String.fromCharCode(...address.pathname.match(/-([a-f0-9]{64})\.css$/)[1].match(/../g).map(hex=>parseInt(hex,16))));link.dataset.appearanceSheet='';link.dataset.turboTrack='dynamic';created=true;}
        await new Promise((resolve,reject)=>{
            let timer;
            const clear=()=>{clearTimeout(timer);link.removeEventListener('load',loaded);link.removeEventListener('error',failed);signal.removeEventListener('abort',aborted);};
            const loaded=()=>{if(!this.sheetReady(link)){failed();return;}clear();resolve();};
            const failed=()=>{clear();if(created)link.remove();reject(Error('The theme stylesheet could not load. Your confirmed appearance is kept.'));};
            const aborted=()=>{clear();if(created)link.remove();reject(new DOMException('Obsolete stylesheet','AbortError'));};
            link.addEventListener('load',loaded);link.addEventListener('error',failed);signal.addEventListener('abort',aborted,{once:true});
            timer=setTimeout(failed,15000);if(created)document.head.append(link);if(signal.aborted)aborted();
        });
    }
    sheetReady(link) {try{return Boolean(link?.sheet?.cssRules?.length);}catch{return false;}}
    async sheets(state, signal) {await Promise.all(Object.values(state.variants).map(variant=>this.sheet(variant.url,signal)));}
    prune() {
        const keep = new Set([...Object.values(this.confirmed.variants),...Object.values(this.current.variants)].map(v=>new URL(v.url,location.href).href));
        for (const link of document.querySelectorAll('link[data-appearance-sheet]')) if(!keep.has(link.href))link.remove();
    }

    async select(input) {
        if (this.trialActive || this.trialPending) this.revertTrial(true);
        const {sequence,signal}=this.fresh();this.message('Preparing application appearance…');
        try {
            const prepared=await this.json(this.prepareUrlValue,{method:'POST',signal,body:JSON.stringify({...input,clientId:this.clientId,sequence})});this.active(sequence);
            await this.sheets(prepared.selection,signal);this.active(sequence);
            const task=this.commitTail.catch(()=>{}).then(async()=>{
                this.active(sequence);
                for(let attempt=0;attempt<3;attempt++){
                    try {
                        this.preferenceUncertain=true;
                        const state=await this.json(this.commitUrlValue,{method:'POST',body:JSON.stringify({clientId:this.clientId,sequence,token:prepared.token,expectedRevision:this.serverState.revision})});
                        this.serverState=state;this.preferenceUncertain=false;return state;
                    }catch(error){
                        if(error.status!==412||attempt===2)throw error;
                        this.serverState=await this.json(this.readUrlValue);this.preferenceUncertain=false;this.active(sequence);
                    }
                }
            });
            this.commitTail=task;const state=await task;this.active(sequence);
            this.confirmed=state;this.initialStylesMissing=false;this.render(state);this.prune();this.message(`Applied ${state.familyName}.`);
            return true;
        } catch(error) {
            if(error.name==='AbortError'||sequence!==this.sequence)return false;
            this.render(this.confirmed);this.message(error.message,true);
            await this.restoreUnconfirmed(sequence).catch(()=>{if(sequence===this.sequence)this.message(`${error.message} Use Restore default appearance to retry recovery.`,true);});
            if(sequence===this.sequence)this.prune();
            return false;
        }
    }

    async restoreUnconfirmed(sequence) {
        await this.commitTail.catch(()=>{});this.active(sequence);
        if(this.preferenceUncertain){this.serverState=await this.json(this.readUrlValue);this.active(sequence);this.preferenceUncertain=false;}
        if(JSON.stringify(this.serverState)===JSON.stringify(this.confirmed))return;
        const restore={kind:'restore',familyId:this.confirmed.familyId,mode:this.confirmed.mode,snapshots:Object.fromEntries(Object.entries(this.confirmed.variants).map(([key,v])=>[key,v.id]))};
        if(this.confirmed.scope==='guest') {restore.kind='mode';delete restore.familyId;delete restore.snapshots;}
        const prepared=await this.json(this.prepareUrlValue,{method:'POST',body:JSON.stringify({...restore,clientId:this.clientId,sequence})});this.active(sequence);
        const state=await this.json(this.commitUrlValue,{method:'POST',body:JSON.stringify({clientId:this.clientId,sequence,token:prepared.token,expectedRevision:this.serverState.revision})});this.active(sequence);
        this.confirmed=state;this.serverState=state;this.render(state);
    }

    toggle() {const mode=window.StyleguideAppearance.effectiveMode(this.current);return this.select({kind:'mode',mode:mode==='dark'?'light':'dark'});}
    systemAppearance() {return this.select({kind:'mode',mode:'system'});}
    restoreDefault() {return this.select({kind:'default'});}
    reset(event) {event.preventDefault();return this.restoreDefault();}
    applyTheme(event) {return this.select({kind:'family',themeId:event.currentTarget.dataset.themeId,themeRevision:Number(event.currentTarget.dataset.themeRevision)});}
    applyFamily() {return this.select({kind:'family',familyId:this.familyTarget.value});}
    async refreshFamilies() {
        if(!this.menuTarget.open)return;
        try {const data=await this.json(this.familiesUrlValue);const selected=this.familyTarget.value;this.familyTarget.replaceChildren(...data.families.map(f=>new Option(`${f.label}${f.modes.length===1?' · '+f.modes[0]+' only':''}`,f.id)));this.familyTarget.value=selected;}catch(error){this.message(error.message,true);}
    }
    async reconcile() {
        const {sequence,signal}=this.fresh();
        try {const state=await this.json(this.readUrlValue,{signal});await this.sheets(state,signal);this.active(sequence);this.initialStylesMissing=false;this.confirmed=state;this.serverState=state;this.render(state);this.prune();}catch(error){if(error.name!=='AbortError')this.message(error.message,true);}
    }

    async tryDocument(document) {
        const {sequence,signal}=this.fresh();this.trialPending=true;
        try {
            await this.restoreUnconfirmed(sequence);this.active(sequence);
            const state=await this.json(this.trialUrlValue,{method:'POST',signal,body:JSON.stringify({document})});this.active(sequence);
            await this.sheets(state,signal);this.active(sequence);
            this.trialActive=true;this.render(state);this.prune();this.message('Trying unsaved changes. Your saved preference is unchanged.');
            window.dispatchEvent(new CustomEvent('appearance:trial-started'));return true;
        }catch(error){if(error.name!=='AbortError'&&sequence===this.sequence)this.message(`Trial not updated: ${error.message} The last valid appearance is kept.`,true);return false;}
        finally{if(sequence===this.sequence)this.trialPending=false;}
    }
    revertTrial(silent = false) {
        if(!this.trialActive&&!this.trialPending)return;
        this.sequence++;this.pending?.abort();this.trialActive=false;this.trialPending=false;this.render(this.confirmed);this.prune();
        if(!silent)this.message('Saved appearance restored. Unsaved editor changes are kept.');
        window.dispatchEvent(new CustomEvent('appearance:trial-ended'));
    }
    revert() {this.revertTrial();}
    saveTrial() {window.dispatchEvent(new CustomEvent('appearance:save-trial'));}
    showDetails() {document.getElementById('appearance-details-dialog').showModal();}
    closeDetails() {document.getElementById('appearance-details-dialog').close();}
    fontStatus() {
        const style=getComputedStyle(document.documentElement), families=['body','heading','code'].map(role=>style.getPropertyValue('--sg-font-'+role).trim().split(',')[0].replaceAll('"',''));
        if([...document.fonts].some(face=>face.status==='error'&&families.includes(face.family.replaceAll('"',''))))this.message('A selected application font could not load. Readable system fallback fonts remain active.',true);
    }
}
