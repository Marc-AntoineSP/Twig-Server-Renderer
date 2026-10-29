// A control description for the server's bounded schema subset, not a validator.
const own = (value, key) => Object.hasOwn(value, key);
const text = value => JSON.stringify(value)?.slice(0, 240) ?? '';
export function propsObject(raw) {
    const value = JSON.parse(raw);
    if (!value || Array.isArray(value) || typeof value !== 'object') throw Error('Properties must be a JSON object, for example {"message":"Hello"}.');
    const finite = item => {if (typeof item === 'number' && !Number.isFinite(item)) throw Error('Use finite JSON numbers.');if (item && typeof item === 'object') Object.values(item).forEach(finite);};
    finite(value);return value;
}

export function describeFields(schema, defaults = {}) {
    const resolve = (rule, seen = []) => {
        if (!rule || typeof rule !== 'object' || Array.isArray(rule)) return {};
        if (!rule.$ref) return rule;
        const match = /^#\/\$defs\/([A-Za-z0-9_-]+)$/.exec(rule.$ref);
        // Ref siblings can add validation constraints. Do not pretend that a merge
        // implements their intersection: leave that case to JSON and the server.
        if (!match || seen.includes(rule.$ref) || seen.length >= 20 || Object.keys(rule).some(k => !['$ref','title','description','default'].includes(k))) return {...rule, complex: true};
        const target = schema.$defs?.[match[1]];
        return {...resolve(target, [...seen, rule.$ref]), ...Object.fromEntries(Object.entries(rule).filter(([k]) => k !== '$ref'))};
    };
    return Object.entries(schema.properties || {}).slice(0, 100).map(([name, original]) => {
        const rule = resolve(original), types = Array.isArray(rule.type) ? rule.type : [rule.type];
        const choices = Array.isArray(rule.enum) ? rule.enum : own(rule, 'const') ? [rule.const] : null;
        const scalar = types.filter(t => t !== 'null');
        const type = !rule.complex && scalar.length === 1 && ['string','boolean','integer','number'].includes(scalar[0]) ? scalar[0] : 'json';
        const hasDefault = own(defaults, name) || own(rule, 'default');
        return {name, rule, label: typeof rule.title === 'string' ? rule.title : name,
            description: typeof rule.description === 'string' ? rule.description : '',
            type: choices && choices.every(v => v === null || typeof v !== 'object') ? 'enum' : type, choices, required: (schema.required || []).includes(name),
            nullable: types.includes('null'), hasDefault, defaultValue: own(defaults, name) ? defaults[name] : rule.default};
    });
}

export class PropertyFields {
    constructor(element, {schema, defaults = {}, prefix, value, drafts = new Map(), onChange, onInvalid}) {
        Object.assign(this, {element, value, drafts, onChange, onInvalid});this.fields = new Map();
        element.replaceChildren();
        for (const [index, field] of describeFields(schema, defaults).entries()) this.build(field, `${prefix}-${index}`);
        this.update(value);
    }
    build(field, id) {
        const make = (tag, cls, content) => {const el = document.createElement(tag);el.className = cls;if (content !== undefined) el.textContent = content;return el;};
        const row = make('div', 'min-w-0 space-y-2 rounded border border-base-300 p-3');
        const label = make('label', 'block font-medium', field.label);label.htmlFor = id;
        const includeLabel = make('label', 'flex items-center gap-2 text-xs');
        const include = make('input', 'checkbox checkbox-xs');include.type = 'checkbox';include.setAttribute('aria-label', `Include ${field.label}`);includeLabel.append(include, document.createTextNode(field.required ? 'Include property · required by schema' : 'Include property'));
        let input;
        if (field.type === 'enum') {input = make('select','select w-full');field.choices.forEach((value, index) => input.add(new Option(text(value), String(index))));}
        else {input = make(['json','string'].includes(field.type) ? 'textarea' : 'input', field.type === 'boolean' ? 'checkbox' : field.type === 'json' ? 'textarea w-full font-mono text-sm' : field.type === 'string' ? 'textarea w-full' : 'input w-full');
            if (['json','string'].includes(field.type)) input.rows = field.type === 'json' ? 4 : 2;
            else input.type = field.type === 'boolean' ? 'checkbox' : ['integer','number'].includes(field.type) ? 'number' : 'text';
            if (['integer','number'].includes(field.type)) input.step = field.type === 'integer' ? '1' : 'any';
            for (const [key, attr] of [['minimum','min'],['maximum','max'],['minLength','minLength'],['maxLength','maxLength']]) if (typeof field.rule[key] === 'number') input[attr] = field.rule[key];
        }
        input.id = id;input.setAttribute('aria-required', String(field.required));
        const hint = make('p','break-words text-xs');hint.id = id+'-hint';
        hint.textContent = [field.description, field.hasDefault ? `Default guidance: ${text(field.defaultValue)}. Inclusion is an explicit edit.` : 'No default is supplied.', field.type === 'json' ? 'Structured or unsupported schema: edit this value as JSON, or use Advanced JSON for the complete object.' : ''].filter(Boolean).join(' ');
        const state = make('p','text-xs');
        const error = make('p','break-words text-sm text-error');error.id = id+'-error';error.hidden = true;
        input.setAttribute('aria-describedby', `${hint.id} ${error.id}`);
        const actions = make('div','flex flex-wrap gap-2');
        const button = (label, action) => {const b = make('button','btn btn-ghost btn-xs h-auto whitespace-normal py-1',label);b.type = 'button';b.addEventListener('click', action);actions.append(b);return b;};
        const initial = () => field.hasDefault ? structuredClone(field.defaultValue) : field.choices ? structuredClone(field.choices[0]) : field.type === 'boolean' ? false : ['integer','number'].includes(field.type) ? 0 : field.type === 'json' ? null : '';
        include.addEventListener('change', () => this.commit(field.name, include.checked ? initial() : undefined, !include.checked));
        if (field.hasDefault) button(`Use default for ${field.label}`, () => this.commit(field.name, structuredClone(field.defaultValue)));
        let nullButton;
        if (field.nullable && field.type !== 'enum') nullButton = button(`Set ${field.label} to null`, () => this.commit(field.name, own(this.value,field.name) && this.value[field.name] === null ? (field.type === 'string' ? '' : field.type === 'boolean' ? false : field.type === 'json' ? {} : 0) : null));
        input.addEventListener(field.type === 'enum' || field.type === 'boolean' ? 'change' : 'input', () => {
            try {
                let value = input.value;
                if (field.type === 'boolean') value = input.checked;
                else if (field.type === 'enum') {if (value === '') throw Error('Choose an allowed value.');value = structuredClone(field.choices[Number(value)]);}
                else if (field.type === 'json') {value = JSON.parse(value);propsObject('{"value":'+input.value+'}');}
                else if (['integer','number'].includes(field.type)) {value = Number(value);if (input.value === '' || !Number.isFinite(value) || (field.type === 'integer' && !Number.isInteger(value))) throw Error('Enter a finite '+field.type+'; use Include property to omit it.');}
                this.commit(field.name, value);
            } catch (e) {this.drafts.set(field.name, {raw: input.value, message: e.message});this.onInvalid(field.name, e.message);this.showErrors(this.errors);}
        });
        row.append(label, includeLabel, input, hint, state, error, actions);this.element.append(row);
        this.fields.set(field.name, {...field, row, input, include, state, error, nullButton});
    }
    commit(name, value, remove = false) {
        const next = structuredClone(this.value);
        if (remove) delete next[name];else Object.defineProperty(next, name, {value, enumerable:true, configurable:true, writable:true});
        const previous = this.drafts.get(name);this.drafts.delete(name);
        if (this.onChange(next, name) === false) {if (previous) this.drafts.set(name,previous);return;}this.update(next);
    }
    update(value) {
        this.value = value;
        for (const f of this.fields.values()) {
            const present = own(value, f.name), current = value[f.name], draft = this.drafts.get(f.name);
            const incompatible = present && current !== null && (f.type === 'enum' ? !f.choices.some(v=>JSON.stringify(v)===JSON.stringify(current)) : f.type === 'json' ? false : f.type === 'integer' ? !Number.isInteger(current) : typeof current !== ({number:'number',boolean:'boolean',string:'string'}[f.type]));
            f.include.checked = present;f.input.disabled = incompatible || !present || (current === null && f.nullable && f.type !== 'enum' && f.type !== 'json');
            f.state.textContent = !present ? 'Omitted: no override will be saved.' : incompatible ? `Incompatible value: ${text(current)}. Use Advanced JSON, remove it, or deliberately apply a default.` : current === null ? 'Explicit null.' : `Explicit value: ${text(current)}.`;
            if (f.nullButton) {f.nullButton.disabled = !present;f.nullButton.textContent = current === null ? `Use a value for ${f.label}` : `Set ${f.label} to null`;}
            if (draft) {f.input.value = draft.raw;continue;}
            if (f.input === document.activeElement) continue;
            if (f.type === 'boolean') f.input.checked = current === true;
            else if (f.type === 'enum') {const i = f.choices.findIndex(v => JSON.stringify(v) === JSON.stringify(current));f.input.value = present && i >= 0 ? String(i) : '';}
            else f.input.value = !present ? '' : f.type === 'json' ? JSON.stringify(current, null, 2) : current === null ? '' : String(current);
        }
        this.showErrors(this.errors);
    }
    showErrors(errors = {}) {
        this.errors = errors;
        for (const f of this.fields.values()) {
            const messages = [...new Set([...(Object.hasOwn(errors,f.name) ? errors[f.name] : []), ...(this.drafts.has(f.name) ? [this.drafts.get(f.name).message] : [])])];
            f.error.textContent = messages.join(' ');f.error.hidden = !messages.length;f.input.setAttribute('aria-invalid', String(Boolean(messages.length)));
        }
    }
}
