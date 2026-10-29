import {Controller} from '@hotwired/stimulus';
import {requestFavorite, favoriteEvent} from '../favorites.js';

export default class extends Controller {
    static targets = ['button', 'icon', 'status'];
    static values = {url: String, kind: String, id: String, saved: Boolean, csrf: String, label: String};

    connect() {this.disconnected = false;}
    disconnect() {this.disconnected = true; this.abort?.abort();}

    matches(detail) { return detail.kind === this.kindValue && detail.id === this.idValue; }
    emit(name, detail = {}) { if (!this.disconnected) favoriteEvent(name, {kind: this.kindValue, id: this.idValue, ...detail}); }
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
        return requestFavorite(this.urlValue, this.csrfValue, method, this.abort?.signal);
    }
    async save(event) {
        event.preventDefault(); event.stopPropagation();
        if (this.busy) return;
        this.abort = new AbortController();
        const change = {previousFavorite: this.savedValue, operation: this.savedValue ? 'remove' : 'add', hadFocus: document.activeElement === this.buttonTarget, label: this.labelValue, url: this.urlValue};
        this.emit('pending', {pending: true});
        this.statusTarget.textContent = 'Saving…';
        try { this.emit('changed', {...await this.request(this.savedValue ? 'DELETE' : 'PUT'), ...change}); }
        catch (error) {
            if (this.disconnected) return;
            // A lost response may follow a committed write. Reconcile before displaying a result.
            try { this.emit('changed', {...await this.request('GET'), ...change}); this.statusTarget.textContent = `${error.message} Status refreshed; you can retry.`; }
            catch { this.statusTarget.textContent = 'Could not confirm favorite status. Refresh or retry. Your last confirmed state is shown.'; }
        }
        finally { this.emit('pending', {pending: false}); }
    }
}
