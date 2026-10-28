import {Controller} from '@hotwired/stimulus';

// Ordinary native disclosures, without menu-widget roles or a modal focus trap.
export default class extends Controller {
    static targets = ['recoveryTemplate'];

    connect() {
        this.disclosures = [...this.element.querySelectorAll('[data-shell-disclosure]')];
        this.dialogOpeners = new WeakMap();
        this.onToggle = event => {
            if (!this.disclosures.includes(event.target)) return;
            if (event.target.open) this.disclosures.filter(d => d !== event.target).forEach(d => this.close(d));
            this.expanded();
        };
        this.onClick = event => {
            this.lastInvoker = event.target.closest('button, a, summary');
            this.disclosures.filter(d => !d.contains(event.target)).forEach(d => this.close(d));
        };
        this.onFocus = event => this.disclosures.filter(d => !d.contains(event.target)).forEach(d => this.close(d));
        this.onEscape = event => {
            const modal = document.querySelector('dialog:modal');
            if (modal) {
                if (event.key === 'Tab') {
                    const controls = [...modal.querySelectorAll('button, a[href], input, select, textarea, [tabindex="0"]')].filter(e => !e.disabled && e.checkVisibility({checkVisibilityCSS: true, checkOpacity: true}));
                    const first = controls[0], last = controls.at(-1);
                    if (first && event.shiftKey && document.activeElement === first) {event.preventDefault(); last.focus();}
                    else if (last && !event.shiftKey && document.activeElement === last) {event.preventDefault(); first.focus();}
                }
                return;
            }
            if (event.key !== 'Escape') return;
            const open = this.disclosures.find(d => d.open && d.contains(document.activeElement));
            if (open) { event.preventDefault(); this.close(open, true); }
        };
        this.beforeCache = () => this.disclosures.forEach(d => this.close(d));
        this.onDialogBeforeToggle = event => {
            if (event.target instanceof HTMLDialogElement && event.newState === 'open') {
                const active = document.activeElement;
                this.dialogOpeners.set(event.target, active?.closest('[data-shell-disclosure]')?.querySelector('summary') || active);
            }
        };
        this.onDialogClose = event => {
            if (!(event.target instanceof HTMLDialogElement)) return;
            const opener = this.dialogOpeners.get(event.target);
            this.dialogOpeners.delete(event.target);
            requestAnimationFrame(() => {const remaining = document.querySelector('dialog:modal');if (opener?.isConnected && (!remaining || remaining.contains(opener))) opener.focus();});
        };
        this.wide = matchMedia('(min-width: 1024px)');
        this.onResize = () => {
            const mobile = this.element.querySelector('.shell-mobile-navigation');
            if (this.wide.matches && mobile?.open) {
                const hadFocus = mobile.contains(document.activeElement);
                this.close(mobile);
                if (hadFocus) this.element.querySelector('.shell-brand').focus();
            }
        };
        document.addEventListener('toggle', this.onToggle, true);
        document.addEventListener('click', this.onClick, true);
        document.addEventListener('focusin', this.onFocus);
        document.addEventListener('keydown', this.onEscape);
        document.addEventListener('close', this.onDialogClose, true);
        document.addEventListener('beforetoggle', this.onDialogBeforeToggle, true);
        document.addEventListener('turbo:before-cache', this.beforeCache);
        this.wide.addEventListener('change', this.onResize);
        this.dialogObserver = new MutationObserver(records => {
            if (records.some(r => r.attributeName === 'open' || [...r.addedNodes].some(n => n instanceof HTMLDialogElement))) this.recoverDialogs();
        });
        this.dialogObserver.observe(this.element, {subtree: true, childList: true, attributes: true, attributeFilter: ['open']});
        this.expanded(); this.recoverDialogs();
    }

    disconnect() {
        document.removeEventListener('toggle', this.onToggle, true);
        document.removeEventListener('click', this.onClick, true);
        document.removeEventListener('focusin', this.onFocus);
        document.removeEventListener('keydown', this.onEscape);
        document.removeEventListener('close', this.onDialogClose, true);
        document.removeEventListener('beforetoggle', this.onDialogBeforeToggle, true);
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.wide.removeEventListener('change', this.onResize);
        this.dialogObserver.disconnect();
    }

    expanded() { this.disclosures.forEach(d => d.querySelector('summary').setAttribute('aria-expanded', String(d.open))); }
    close(disclosure, restore = false) {
        if (!disclosure.open) return;
        disclosure.open = false; this.expanded();
        if (restore) disclosure.querySelector('summary').focus();
    }

    recoverDialogs() {
        // Native modal dialogs make the rest of the document inert. Put a small,
        // independently legible recovery form INSIDE their scrollable content.
        for (const dialog of this.element.querySelectorAll('dialog:modal')) {
            if (!this.dialogOpeners.has(dialog)) {
                const invoker = this.lastInvoker;
                this.dialogOpeners.set(dialog, invoker?.closest('[data-shell-disclosure]')?.querySelector('summary') || invoker);
            }
            this.disclosures.forEach(d => this.close(d));
            if (!dialog.querySelector('[data-modal-recovery]')) {
                const content = dialog.querySelector('.modal-box, :scope > aside') || dialog;
                content.append(this.recoveryTemplateTarget.content.cloneNode(true));
                content.querySelector('[data-appearance-recovery-message]').setAttribute('role', 'status');
            }
        }
        window.applicationAppearance?.updateRecovery();
    }
}
