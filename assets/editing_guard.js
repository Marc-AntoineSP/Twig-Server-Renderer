import {visit, session} from '@hotwired/turbo';

export const canonical = value => JSON.stringify(value, (key, item) => item && typeof item === 'object' && !Array.isArray(item) ? Object.fromEntries(Object.keys(item).sort().map(name => [name, item[name]])) : item);

let activeGuard;
let confirmation;
export function confirmEditing({message, label = 'Discard changes and leave'} = {}) {
    if (confirmation) return confirmation;
    const dialog = document.getElementById('editing-confirmation-template').content.firstElementChild.cloneNode(true);
    if (message) dialog.querySelector('[data-confirm-message]').textContent = message;
    dialog.querySelector('[data-confirm-discard]').textContent = label;
    const opener = document.activeElement;
    const selection = typeof opener?.selectionStart === 'number' ? [opener.selectionStart, opener.selectionEnd] : null;
    confirmation = new Promise(resolve => {
        const finish = discard => { dialog.returnValue = discard ? 'discard' : ''; dialog.close(); };
        dialog.querySelector('[data-confirm-keep]').addEventListener('click', () => finish(false));
        dialog.querySelector('[data-confirm-discard]').addEventListener('click', () => finish(true));
        dialog.addEventListener('close', () => {
            const discard = dialog.returnValue === 'discard'; dialog.remove(); confirmation = null;
            requestAnimationFrame(() => {if (opener?.isConnected) {opener.focus({preventScroll: true}); if (selection) opener.setSelectionRange(...selection);}});
            resolve(discard);
        }, {once: true});
        document.body.append(dialog); dialog.showModal();
    });
    return confirmation;
}

/** One lifecycle-scoped guard for the active editor. No browser storage or history entries. */
export class EditingGuard {
    constructor(dirty, pending = () => false, root = document.body) {
        activeGuard?.disconnect(); activeGuard = this;
        this.dirty = dirty; this.pending = pending; this.root = root; this.location = location.href;
        this.index = history.state?.turbo?.restorationIndex;
        this.unload = event => {if (this.protected()) {const restore = this.preserveFields(); event.preventDefault(); event.returnValue = ''; setTimeout(restore, 0);}};
        this.beforeVisit = event => {
            const target = new URL(event.detail.url);
            if (target.pathname === location.pathname && target.search === location.search && target.hash !== location.hash) return;
            if (!this.protected()) return;
            event.preventDefault();
            this.leave(() => visit(event.detail.url));
        };
        this.submit = event => {
            const form = event.target;
            if (!this.protected() || form.matches('[data-editing-save], [data-editing-stay]')) return;
            event.preventDefault(); event.stopImmediatePropagation();
            const submitter = event.submitter;
            this.leave(() => form.requestSubmit(submitter));
        };
        this.pop = event => {
            const next = event.state?.turbo?.restorationIndex;
            if (this.returning) {
                event.stopImmediatePropagation();
                if (next === this.index) {const action = this.returning; this.returning = null; action();}
                return;
            }
            if (!this.protected() || !Number.isInteger(next) || !Number.isInteger(this.index) || next === this.index) return;
            // Turbo 8.0.23's capture-free popstate listener must not see the canceled traversal.
            event.stopImmediatePropagation();
            const delta = next - this.index;
            const restore = this.preserveFields();
            this.returning = () => setTimeout(() => {restore(); this.leave(() => history.go(delta));}, 0);
            history.go(-delta);
        };
        this.loaded = () => {this.location = location.href; this.index = history.state?.turbo?.restorationIndex;};
        window.addEventListener('beforeunload', this.unload);
        // Window is popstate's target: capture alone does not precede earlier target listeners.
        // Reorder the pinned Turbo 8.0.23 listener without replacing it or changing its state.
        const historyListener = session.history?.onPopState;
        if (session.history?.started) window.removeEventListener('popstate', historyListener, false);
        window.addEventListener('popstate', this.pop, true);
        if (session.history?.started) window.addEventListener('popstate', historyListener, false);
        document.addEventListener('turbo:before-visit', this.beforeVisit);
        document.addEventListener('submit', this.submit, true);
        document.addEventListener('turbo:load', this.loaded);
    }
    protected() {return !this.allowed && (this.dirty() || this.pending());}
    preserveFields() {
        // Browsers can restore an entry's old form values AFTER popstate. Keep the current editor's values.
        const stamp = () => [...this.root.attributes].filter(a => /-(revision|theme-id)-value$/.test(a.name)).map(a => a.value).join(':');
        const revision = stamp(), selected = this.root.querySelector('[role="treeitem"][aria-selected="true"]');
        const scroll = [scrollX, scrollY];
        const fields = [...this.root.querySelectorAll('textarea, select, input:not([type="hidden"]):not([type="password"]):not([type="file"])')].map(el => ({el, value: el.value, checked: el.checked, selection: typeof el.selectionStart === 'number' ? [el.selectionStart, el.selectionEnd] : null, scroll: el.scrollTop}));
        return () => {
            if (!this.root.isConnected || stamp() !== revision || selected && !selected.isConnected) return;
            for (const {el, value, checked, selection, scroll} of fields) if (el.isConnected) {el.value = value; if (checked !== undefined) el.checked = checked; if (selection) el.setSelectionRange(...selection); el.scrollTop = scroll;}
            window.scrollTo(...scroll);
        };
    }
    async leave(navigate) {
        if (this.asking || activeGuard !== this || !this.root.isConnected) return;
        this.asking = true;
        try {
            const accepted = await confirmEditing(this.pending() ? {message: 'A save is in progress. Leaving does not cancel that request. Any newer unsaved changes will be discarded.'} : {});
            if (accepted && activeGuard === this && this.root.isConnected) {this.allowed = true; navigate();}
        } finally {this.asking = false;}
    }
    disconnect() {
        window.removeEventListener('beforeunload', this.unload); window.removeEventListener('popstate', this.pop, true);
        document.removeEventListener('turbo:before-visit', this.beforeVisit); document.removeEventListener('submit', this.submit, true); document.removeEventListener('turbo:load', this.loaded);
        if (activeGuard === this) {activeGuard = null; document.querySelector('[data-editing-confirmation]')?.close();}
    }
}
