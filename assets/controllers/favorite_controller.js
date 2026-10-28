import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'icon', 'status'];
    static values = {url: String, kind: String, id: String, saved: Boolean, csrf: String};

    matches(detail) { return detail.kind === this.kindValue && detail.id === this.idValue; }
    emit(name, detail = {}) { document.dispatchEvent(new CustomEvent(`favorite:${name}`, {detail: {kind: this.kindValue, id: this.idValue, ...detail}})); }
    pending({detail}) {
        if (!this.matches(detail)) return;
        this.busy = detail.pending;
        this.buttonTarget.disabled = detail.pending;
        this.buttonTarget.setAttribute('aria-busy', String(detail.pending));
    }
    synchronize({detail}) {
        if (!this.matches(detail)) return;
        this.savedValue = detail.favorite;
        const label = detail.favorite ? 'Remove from favorites' : 'Add to favorites';
        this.buttonTarget.setAttribute('aria-pressed', String(detail.favorite));
        this.buttonTarget.title = this.buttonTarget.title.replace(/^(Add to|Remove from) favorites/, label);
        this.buttonTarget.setAttribute('aria-label', label);
        this.iconTarget.setAttribute('fill', detail.favorite ? 'currentColor' : 'none');
        this.statusTarget.textContent = '';
    }
    async request(method) {
        const response = await fetch(this.urlValue, {method, credentials: 'same-origin', redirect: 'error', signal: AbortSignal.timeout(12000), headers: {'Accept': 'application/json', 'X-CSRF-Token': this.csrfValue}});
        const data = await response.json();
        if (!response.ok) throw Error(data.message || 'Favorite request failed.');
        if (typeof data.favorite !== 'boolean') throw Error('Favorite status could not be confirmed.');
        return data;
    }
    async save(event) {
        event.preventDefault(); event.stopPropagation();
        if (this.busy) return;
        this.emit('pending', {pending: true});
        this.statusTarget.textContent = 'Saving…';
        try { this.emit('changed', await this.request(this.savedValue ? 'DELETE' : 'PUT')); }
        catch (error) {
            // A lost response may follow a committed write. Reconcile before displaying a result.
            try { this.emit('changed', await this.request('GET')); this.statusTarget.textContent = `${error.message} Status refreshed; you can retry.`; }
            catch { this.statusTarget.textContent = 'Could not confirm favorite status. Refresh or retry. Your last confirmed state is shown.'; }
        }
        finally { this.emit('pending', {pending: false}); }
    }
}
