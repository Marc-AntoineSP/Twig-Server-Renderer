import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['frame', 'stage', 'status', 'props', 'disableJs', 'width', 'zoom', 'rootSize', 'bodyFont', 'headingFont', 'textClass', 'presetStatus', 'example', 'theme', 'colorScheme', 'surface', 'deviceFrame', 'propForm'];
    static values = { itemId: String, csrf: String, createUrl: String, statusUrl: String, documentUrl: String, presetUrl: String, settings: Object, defaultProps: Object, themes: Object };

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
        return { width: Number(this.widthTarget.value), zoom: Number(this.zoomTarget.value), rootSize: Number(this.rootSizeTarget.value), bodyFont: this.bodyFontTarget.value, headingFont: this.headingFontTarget.value, textClass: this.textClassTarget.value, scripts: !this.disableJsTarget.checked, props: JSON.parse(this.propsTarget.value), colorScheme:this.colorSchemeTarget.value,surface:this.surfaceTarget.value,deviceFrame:this.deviceFrameTarget.checked,themeReleaseId: this.hasThemeTarget ? this.themeTarget.value || null : null };
    }

    applySettings(settings) {
        for (const key of ['width', 'zoom', 'rootSize', 'bodyFont', 'headingFont', 'textClass']) this[`${key}Target`].value = settings[key];
        this.colorSchemeTarget.value=settings.colorScheme||'theme';this.surfaceTarget.value=settings.surface||'base-100';this.deviceFrameTarget.checked=settings.deviceFrame===true;
        this.disableJsTarget.checked = settings.scripts === false;
        if (this.hasThemeTarget) this.themeTarget.value = settings.themeReleaseId || '';
        this.propsTarget.value = JSON.stringify(settings.props || this.defaultPropsValue, null, 2);
        this.applyViewport();
    }

    applyViewport() {
        const width = Math.min(1920, Math.max(320, Number(this.widthTarget.value) || 768));
        const scale = Number(this.zoomTarget.value) / 100;
        this.frameTarget.style.width = `${width}px`;
        this.frameTarget.style.transform = `scale(${scale})`;
        this.frameTarget.style.transformOrigin = 'top left';
        this.stageTarget.style.boxShadow=this.deviceFrameTarget.checked?'0 0 0 10px #1f2937':'none';this.stageTarget.style.borderRadius=this.deviceFrameTarget.checked?'1.5rem':'0';this.frameTarget.style.borderRadius=this.deviceFrameTarget.checked?'1.5rem':'0';
        this.stageTarget.style.width = `${width * scale}px`;
        this.stageTarget.style.height = `${(parseInt(this.frameTarget.style.height) || 250) * scale}px`;
    }

    widthPreset(event) {
        this.widthTarget.value = event.currentTarget.dataset.width;
        this.applyViewport();
    }

    viewportChanged() { this.applyViewport(); }
    typographyChanged() { this.applyViewport(); this.schedule(); }

    themeChanged() {
        const theme = this.themesValue[this.themeTarget.value]?.document;
        if (theme) {
            this.bodyFontTarget.value = 'theme';
            this.headingFontTarget.value = 'theme';
            this.rootSizeTarget.value = theme.typography.rootFontSizePx;
            this.textClassTarget.value = theme.typography.textClass;
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

    refreshExamples() {
        if (!this.hasExampleTarget) return;
        try {
            const sources = JSON.parse(document.getElementById('draft_sourceMapJson').value);
            this.refreshPropsForm(sources,JSON.parse(document.getElementById('draft_manifestJson').value));
            this.examples = JSON.parse(sources['meta/examples.json'] || '[]');
            const selected = this.exampleTarget.value;
            this.exampleTarget.replaceChildren(new Option('Choose example', ''), ...this.examples.map((example, index) => new Option(example.name, String(index))));
            this.exampleTarget.value = selected;
        } catch { this.examples = []; }
    }

    refreshPropsForm(sources,manifest) {
        if(!this.hasPropFormTarget)return;
        try{
            const schema=JSON.parse(sources[manifest.propsSchema]);const key=JSON.stringify(schema);
            if(key!==this.propSchemaKey){
                this.propSchemaKey=key;this.propFormTarget.replaceChildren();this.propFields=[];
                for(const [name,rule] of Object.entries(schema.properties||{}).slice(0,30)){
                    const id=`preview-property-${this.propFields.length}`;const wrap=document.createElement('div');const label=document.createElement('label');label.htmlFor=id;label.textContent=rule.title||name;
                    let input;if(Array.isArray(rule.enum)&&rule.enum.length<=30){input=document.createElement('select');for(const option of rule.enum)input.add(new Option(String(option),JSON.stringify(option)));}
                    else{input=document.createElement(['array','object'].includes(rule.type)?'textarea':'input');if(input.tagName==='INPUT')input.type=rule.type==='boolean'?'checkbox':['integer','number'].includes(rule.type)?'number':'text';if(rule.type==='integer')input.step='1';if(typeof rule.minimum==='number')input.min=rule.minimum;if(typeof rule.maximum==='number')input.max=rule.maximum;if(typeof rule.maxLength==='number')input.maxLength=rule.maxLength;}
                    input.id=id;input.dataset.propName=name;input.dataset.propType=rule.type;input.dataset.propEnum=String(Array.isArray(rule.enum));input.dataset.action='input->preview#propsFieldChanged';input.className=rule.type==='boolean'?'checkbox':'input input-bordered w-full';wrap.append(label,input);this.propFormTarget.append(wrap);this.propFields.push(input);
                }
            }
            const props=JSON.parse(this.propsTarget.value);
            for(const input of this.propFields){if(document.activeElement===input)continue;const value=props[input.dataset.propName];if(input.type==='checkbox')input.checked=value===true;else input.value=input.dataset.propEnum==='true'||['array','object'].includes(input.dataset.propType)?JSON.stringify(value??null):value??'';}
        }catch{}
    }

    propsFieldChanged(event) {
        try{
            const input=event.target;const props=JSON.parse(this.propsTarget.value);let value=input.type==='checkbox'?input.checked:input.value;
            if(input.dataset.propEnum==='true'||['array','object'].includes(input.dataset.propType))value=JSON.parse(value);
            else if(['integer','number'].includes(input.dataset.propType)){value=Number(value);if(!Number.isFinite(value)||input.value==='')throw Error('Enter a finite property number.');}
            Object.defineProperty(props,input.dataset.propName,{value,enumerable:true,writable:true,configurable:true});this.propsTarget.value=JSON.stringify(props,null,2);this.schedule();
        }catch(error){this.invalid({detail:{message:error.message}});}
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
