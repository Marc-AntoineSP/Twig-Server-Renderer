import {Controller} from '@hotwired/stimulus';
import {PropertyFields, propsObject} from '../property_fields.js';

// Invalid text belongs to a stable node ID. The only valid working value is node.props.
export default class extends Controller {
    static targets = ['fields','json','jsonPanel','formPanel','formButton','jsonButton','status','errors','discard','defaults','invalidNotice'];
    get composer() {return this.application.getControllerForElementAndIdentifier(this.element, 'composition');}
    connect() {this.states = new Map();this.select({detail:{node:this.composer.node()}});}
    disconnect() {this.states.clear();this.view = null;}
    state(node = this.composer.node()) {
        if (node.type !== 'reference') return null;
        const identity = `${node.name}@${node.version}`;
        if (this.states.get(node.id)?.identity !== identity) this.states.set(node.id, {identity, raw:JSON.stringify(node.props,null,2), drafts:new Map(), errors:{}, mode:'form'});
        return this.states.get(node.id);
    }
    select({detail:{node}}) {
        this.nodeId = node.id;this.view = null;
        const ids = new Set(this.composer.visible.map(row => this.composer.node(row.path).id));
        for (const id of this.states.keys()) if (!ids.has(id)) this.states.delete(id);
        if (node.type !== 'reference') {this.validity();return;}
        const state = this.state(node);
        if (!state.jsonError) state.raw = JSON.stringify(node.props,null,2);
        this.jsonTarget.value = state.raw;this.fieldsTarget.replaceChildren();
        this.statusTarget.textContent = state.reference ? '' : 'Loading the exact reference schema. Advanced JSON keeps all current properties available.';
        this.build();this.mode(state.mode);this.showErrors();
    }
    reference({detail}) {
        if (detail.nodeId !== this.nodeId) return;
        const node = this.composer.node(), state = this.state(node);
        if (!state || state.identity !== detail.identity) return;
        if (!detail.reference) {this.statusTarget.textContent = 'Schema unavailable. Local properties are kept; use Advanced JSON or recheck the reference.';return;}
        state.reference = detail.reference;this.statusTarget.textContent = 'Only included properties are saved. Defaults are guidance; edits affect this instance.';
        this.build();
    }
    build() {
        const node = this.composer.node(), state = this.state(node);
        if (!state?.reference) {this.defaultsTarget.disabled = true;return;}
        this.defaultsTarget.disabled = false;
        const id = node.id, identity = state.identity;
        this.view = new PropertyFields(this.fieldsTarget, {schema:state.reference.propsSchema, defaults:state.reference.props,
            prefix:`instance-${id}`, value:node.props, drafts:state.drafts,
            onChange:(props) => {
                if (id !== this.nodeId || this.state()?.identity !== identity) return false;
                if (state.jsonError) {this.statusTarget.textContent = 'Correct or explicitly discard invalid JSON before editing form fields.';return false;}
                this.commit(props);return true;
            },
            onInvalid:() => {if (id === this.nodeId) {this.validity();this.composer.invalidProperties('Correct the highlighted property value. Your last valid object is kept.');this.showErrors();}},
        });
        this.showErrors();
    }
    commit(props) {
        const node = this.composer.node(), state = this.state(node);
        node.props = props;state.raw = JSON.stringify(props,null,2);state.jsonError = null;state.errors = {};
        this.jsonTarget.value = state.raw;this.validity();this.composer.metadata();this.composer.schedule();this.showErrors();
    }
    jsonChanged() {
        const state = this.state();if (!state) return;
        state.raw = this.jsonTarget.value;
        try {
            const props = propsObject(state.raw);state.jsonError = null;state.drafts.clear();state.errors = {};
            // Preserve the user's whitespace/caret while updating the canonical object.
            this.composer.node().props = props;this.view?.update(props);
            this.validity();this.composer.metadata();this.composer.schedule();
        } catch (error) {state.jsonError = error.message;this.validity();this.composer.invalidProperties('Invalid instance JSON. '+error.message);}
        this.showErrors();
    }
    form() {this.switchMode('form');}
    advanced() {this.switchMode('json');}
    switchMode(mode) {
        const state = this.state();if (!state) return;
        if (state.jsonError || state.drafts.size) {this.statusTarget.textContent = 'Correct the invalid text or use Discard invalid property text before switching views.';this.showErrors();return;}
        state.mode = mode;this.mode(mode);
        if (mode === 'json') this.jsonTarget.focus();else this.fieldsTarget.querySelector('input:not(:disabled),select:not(:disabled),textarea:not(:disabled)')?.focus();
    }
    mode(mode) {
        this.jsonPanelTarget.hidden = mode !== 'json';this.formPanelTarget.hidden = mode === 'json';
        this.jsonButtonTarget.setAttribute('aria-pressed',String(mode === 'json'));this.formButtonTarget.setAttribute('aria-pressed',String(mode !== 'json'));
    }
    discard() {
        const state = this.state();if (!state) return;
        state.jsonError = null;state.drafts.clear();state.raw = JSON.stringify(this.composer.node().props,null,2);this.jsonTarget.value = state.raw;
        this.view?.update(this.composer.node().props);this.validity();this.composer.metadata();this.composer.schedule();this.showErrors();
    }
    defaults() {
        const state = this.state();if (!state?.reference) return;
        if (state.jsonError || state.drafts.size) {this.statusTarget.textContent = 'Resolve or explicitly discard invalid property text before applying defaults.';return;}
        this.commit(structuredClone(state.reference.props));this.view?.update(this.composer.node().props);
    }
    invalidText() {return [...this.states.values()].some(s => s.jsonError || s.drafts.size);}
    serverErrors() {return [...this.states.values()].some(s => Object.keys(s.errors).length);}
    validity() {
        this.composer.invalid = this.invalidText();
        this.composer.saveButtonTarget.disabled = this.invalidText() || this.serverErrors();
        this.invalidNoticeTarget.hidden = !this.invalidText() && !this.serverErrors();
        const state = this.state();this.discardTarget.hidden = !state || (!state.jsonError && !state.drafts.size);
    }
    firstInvalid() {
        const entry = [...this.states].find(([,s]) => s.jsonError || s.drafts.size || Object.keys(s.errors).length);
        if (entry) {this.composer.selectNode(entry[0]);this.composer.navigateTo('composition-properties');this.errorsTarget.focus();}
    }
    validation({detail:{fieldErrors = {}}}) {
        for (const state of this.states.values()) state.errors = {};
        for (const [path, messages] of Object.entries(fieldErrors)) {
            const match = /^nodes\.(n_[a-f0-9]{32})\.props(.*)$/.exec(path);if (!match) continue;
            const node = this.composer.findNode(match[1]);if (!node) continue;
            this.state(node).errors[match[2]] = messages;
        }
        this.validity();this.showErrors();
    }
    showErrors() {
        const state = this.state();this.errorsTarget.replaceChildren();if (!state) return;
        const byField = Object.create(null);
        const errors = [...Object.entries(state.errors), ...[...state.drafts].map(([k,v])=>['/'+k,[v.message]]), ...(state.jsonError ? [['Advanced JSON',[state.jsonError]]] : [])];
        for (const [path, messages] of errors) {
            let name = path.startsWith('/') ? path.slice(1).split('/')[0].replaceAll('~1','/').replaceAll('~0','~') : path.startsWith('.') ? path.slice(1) : null;
            if (name && this.view?.fields.has(name)) (byField[name] ||= []).push(...messages);
            const line = document.createElement('p');line.className = 'break-words';line.textContent = `${path || 'Properties'}: ${messages.join(' ')}`;this.errorsTarget.append(line);
        }
        this.errorsTarget.hidden = !errors.length;
        this.jsonTarget.setAttribute('aria-invalid',String(Boolean(state.jsonError)));this.view?.showErrors(byField);
        this.validity();
    }
    reset() {this.states.clear();}
}
