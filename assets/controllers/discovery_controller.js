import {Controller} from '@hotwired/stimulus';
import {requestFavorite, favoriteEvent} from '../favorites.js';

/** Progressive GET filters. Turbo owns history and rendering; the server owns the list. */
export default class extends Controller {
    static targets = ['form', 'frame', 'summary', 'status', 'retry', 'undoNotice', 'undoMessage', 'undo'];
    static values = {csrf: String};

    connect() {
        this.sequence = 0; this.instance = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2);
        this.defaults = {q: '', tags: '', kind: '', mode: '', category: '', sort: 'updated', favorites: '0'};
        this.element.dataset.discoveryState = 'ready';
    }
    disconnect() {this.leaving(); clearTimeout(this.undoTimer); this.undoAbort?.abort();}
    leaving() {clearTimeout(this.timer); this.abort?.abort(); this.requestId = null;}
    invalidate() {clearTimeout(this.timer); this.abort?.abort(); this.requestId = null;}
    input(event) {
        if (!this.formTarget.contains(event.target) || !event.target.matches('input[type="search"], input[name="tags"]')) return;
        this.invalidate();
        if (this.composing || event.isComposing) return;
        this.timer = setTimeout(() => {this.nextAction = 'replace'; this.formTarget.requestSubmit();}, 350);
    }
    compositionStart(event) {if (this.formTarget.contains(event.target)) {this.composing = true; this.invalidate();}}
    compositionEnd(event) {if (this.formTarget.contains(event.target)) {this.composing = false; this.input(event);}}
    change(event) {
        if (!this.formTarget.contains(event.target) || !event.target.matches('select, input[type="checkbox"]')) return;
        this.invalidate(); this.nextAction = 'advance'; this.formTarget.requestSubmit();
    }
    submit(event) {
        if (event.target !== this.formTarget) return;
        if (this.composing) {event.preventDefault(); return;}
        clearTimeout(this.timer);
        this.formTarget.dataset.turboAction = event.submitter ? 'advance' : this.nextAction || 'advance';
        this.formTarget.elements.page.value = this.refreshPage || 1;
        this.refreshPage = null; this.nextAction = null;
    }
    follow(event) {
        const link = event.target.closest('a[data-discovery-link]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        this.invalidate(); this.syncForm(new URL(link.href).searchParams);
        if (this.frameTarget.contains(link)) this.focusAfter = {source: link};
    }
    syncForm(values) {
        for (const [name, fallback] of Object.entries(this.defaults)) {
            const field = this.formTarget.elements.namedItem(name); if (!field) continue;
            const value = values instanceof URLSearchParams ? values.get(name) ?? fallback : values[name] ?? fallback;
            if (field.type === 'checkbox') field.checked = String(value) === '1';
            else if (field.value !== String(value)) field.value = value;
        }
    }
    request(event) {
        if (![this.formTarget, this.frameTarget].includes(event.target)) return;
        this.abort?.abort(); this.abort = new AbortController();
        this.requestId = `${this.instance}-${++this.sequence}`;
        event.detail.fetchOptions.headers['X-Discovery-Request'] = this.requestId;
        event.detail.fetchOptions.signal = AbortSignal.any([event.detail.fetchOptions.signal, this.abort.signal]);
        this.retryUrl = event.detail.url.href; this.retryTarget.hidden = true;
        this.element.dataset.discoveryState = 'loading'; this.element.setAttribute('aria-busy', 'true');
        this.statusTarget.textContent = 'Loading results…';
    }
    response(event) {
        if (![this.formTarget, this.frameTarget].includes(event.target)) return;
        const response = event.detail.fetchResponse;
        const receipt = response.response.headers.get('X-Discovery-Request');
        if (receipt !== this.requestId) {
            event.preventDefault();
            // Framework/authentication errors can occur before the listing adds its receipt.
            if (!receipt && this.requestId && (response.location.href === this.retryUrl || response.redirected)) this.failed();
            return;
        }
        if (!response.succeeded) {event.preventDefault(); this.failed();}
    }
    render(event) {
        if (event.target !== this.frameTarget) return;
        const id = event.detail.newFrame.dataset.discoveryResponse, render = event.detail.render;
        event.detail.render = (current, next) => {
            // Also reject responses queued for rendering before a newer input/disconnect.
            if (id !== this.requestId || !this.element.isConnected) return;
            render(current, next); this.renderedId = id;
        };
    }
    loaded(event) {
        if (event.target !== this.frameTarget || this.renderedId !== this.requestId || !this.requestId) return;
        this.element.dataset.discoveryState = 'ready'; this.element.removeAttribute('aria-busy');
        this.statusTarget.textContent = this.summaryTarget.textContent.trim(); this.retryTarget.hidden = true;
        this.syncForm(JSON.parse(this.summaryTarget.dataset.discoveryFilters));
        if (this.focusAfter && document.activeElement === document.body) {
            const target = this.focusAfter.selector ? this.frameTarget.querySelector(this.focusAfter.selector) : null;
            (target || this.summaryTarget).focus({preventScroll: true});
        }
        this.focusAfter = null;
    }
    error(event) {
        if (![this.formTarget, this.frameTarget].includes(event.target)) return;
        event.preventDefault();
        if (event.detail.request.headers['X-Discovery-Request'] === this.requestId) this.failed();
    }
    missing(event) {if (event.target === this.frameTarget) {event.preventDefault(); this.failed();}}
    failed() {
        this.element.dataset.discoveryState = 'error'; this.element.removeAttribute('aria-busy');
        this.statusTarget.textContent = 'Results could not be refreshed. The previous list is kept and may be out of date. Retry results or reload.';
        this.retryTarget.hidden = false;
    }
    retry() {
        const url = new URL(this.retryUrl || location.href); this.syncForm(url.searchParams);
        this.refreshPage = url.searchParams.get('page') || 1; this.nextAction = 'replace'; this.formTarget.requestSubmit();
    }
    refresh() {
        const filters = JSON.parse(this.summaryTarget.dataset.discoveryFilters);
        const unchanged = Object.entries(filters).every(([name, value]) => {
            const field = this.formTarget.elements.namedItem(name);
            return field && String(field.type === 'checkbox' ? (field.checked ? 1 : 0) : field.value) === String(value);
        });
        this.refreshPage = unchanged ? this.summaryTarget.dataset.discoveryPage : 1;
        this.nextAction = 'replace'; this.formTarget.requestSubmit();
    }
    favorite({detail}) {
        if (detail.favorite || detail.previousFavorite !== true) return;
        const source = [...this.frameTarget.querySelectorAll('[data-controller~="favorite"]')].find(el => el.dataset.favoriteKindValue === detail.kind && el.dataset.favoriteIdValue === detail.id);
        if (!source) return;
        if (detail.hadFocus) {
            const cards = [...this.frameTarget.querySelectorAll('article')], index = cards.indexOf(source.closest('article'));
            const next = (cards[index + 1] || cards[index - 1])?.querySelector('[data-controller~="favorite"]');
            this.focusAfter = {source, selector: next ? `[data-favorite-kind-value="${CSS.escape(next.dataset.favoriteKindValue)}"][data-favorite-id-value="${CSS.escape(next.dataset.favoriteIdValue)}"] button` : null};
        }
        this.undoEntry = {kind: detail.kind, id: detail.id, url: detail.url, label: detail.label};
        this.undoNoticeTarget.hidden = false; this.undoTarget.hidden = false; this.undoTarget.disabled = false;
        this.undoTarget.setAttribute('aria-label', `Undo removal of ${detail.label} from favorites`);
        this.undoMessageTarget.textContent = `Removed ${detail.label} from favorites.`;
        clearTimeout(this.undoTimer); this.expireUndo();
        if (this.formTarget.elements.favorites?.checked) this.refresh();
    }
    expireUndo() {
        this.undoTimer = setTimeout(() => {
            if (this.undoNoticeTarget.contains(document.activeElement) || this.undoPending) this.expireUndo();
            else this.dismissUndo();
        }, 15000);
    }
    dismissUndo() {
        clearTimeout(this.undoTimer); this.undoEntry = null;
        const focused = this.undoNoticeTarget.contains(document.activeElement); this.undoNoticeTarget.hidden = true;
        if (focused) this.summaryTarget.focus({preventScroll: true});
    }
    async undo() {
        const entry = this.undoEntry; if (!entry || this.undoPending) return;
        const hadFocus = document.activeElement === this.undoTarget;
        clearTimeout(this.undoTimer); this.undoPending = true; this.undoTarget.disabled = true;
        this.undoAbort = new AbortController(); favoriteEvent('pending', {...entry, pending: true});
        let confirmed = null;
        try {confirmed = await requestFavorite(entry.url, this.csrfValue, 'PUT', this.undoAbort.signal);}
        catch (error) {
            if (!this.element.isConnected) return;
            try {confirmed = await requestFavorite(entry.url, this.csrfValue, 'GET', this.undoAbort.signal);} catch {}
            if (!confirmed?.favorite) this.undoMessageTarget.textContent = `Favorite could not be restored. ${error.message} Retry Undo or reload to check its current availability.`;
        } finally {
            this.undoPending = false;
            if (this.element.isConnected) {favoriteEvent('pending', {...entry, pending: false}); this.undoTarget.disabled = false; this.expireUndo();}
        }
        if (!confirmed || !this.element.isConnected) return;
        favoriteEvent('changed', {...confirmed, operation: 'undo'});
        if (confirmed.favorite) {
            if (this.undoEntry === entry) {this.undoMessageTarget.textContent = `Restored ${entry.label} to favorites.`; this.undoTarget.hidden = true; this.undoEntry = null;}
            if (this.formTarget.elements.favorites?.checked) {
                if (hadFocus) this.focusAfter = {selector: `[data-favorite-kind-value="${CSS.escape(entry.kind)}"][data-favorite-id-value="${CSS.escape(entry.id)}"] button`};
                this.refresh();
            } else if (hadFocus && document.activeElement === document.body) this.summaryTarget.focus({preventScroll: true});
        }
    }
}
