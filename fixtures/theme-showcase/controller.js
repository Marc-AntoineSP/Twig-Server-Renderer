import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['category', 'search', 'section', 'count', 'empty', 'status', 'toast', 'drawerPanel', 'countdown'];

    connect() {
        this.filter();
        this.timer = null;
        this.drawerOpener = null;
    }

    disconnect() {
        clearTimeout(this.toastTimer);clearInterval(this.timer);
        this.element.querySelectorAll('dialog[open]').forEach(dialog => dialog.close());
    }

    filter() {
        const category = this.categoryTarget.value, query = this.searchTarget.value.toLowerCase().trim();let count = 0;
        for (const section of this.sectionTargets) {
            let visible = 0;
            for (const card of section.querySelectorAll('[data-showcase-card]')) {
                card.hidden = (category !== 'all' && category !== section.dataset.category) || !card.dataset.search.includes(query);
                if (!card.hidden) visible++;
            }
            section.hidden = visible === 0;count += visible;
        }
        this.emptyTarget.hidden = count !== 0;
        this.countTarget.textContent = `${count} component examples. Nothing here saves, sends or uploads data.`;
        this.closeDrawer();this.closeToast();
    }

    announce(event) {this.message(`Preview action: ${event.currentTarget.dataset.demoAction || event.currentTarget.textContent.trim()}. No data was changed.`);}
    message(text) {this.statusTarget.textContent = text;this.statusTarget.hidden = false;}
    preventSubmit(event) {event.preventDefault();this.message('This form is a local preview. Nothing was sent.');}

    select(event) {
        const group = event.currentTarget.closest('[data-demo-selection]');
        for (const button of group.querySelectorAll('button')) {const active = button === event.currentTarget;button.classList.toggle('btn-active', active);button.setAttribute('aria-pressed', String(active));}
        const output = group.querySelector('output');if (output && this.element.contains(output)) output.textContent = `${event.currentTarget.textContent.trim()} selected in this example.`;
    }
    dock(event) {
        for (const button of event.currentTarget.closest('[data-demo-dock]').querySelectorAll('button')) {const active = button === event.currentTarget;button.classList.toggle('dock-active', active);button.setAttribute('aria-pressed', String(active));}
    }
    navigate(event) {
        const anchor = event.target.closest('a[href^="#"]');if (!anchor) return;
        const target = document.getElementById(anchor.getAttribute('href').slice(1));if (!target || !this.element.contains(target)) return;
        event.preventDefault();
        if (target.closest('[data-showcase-target="section"]')?.hidden) {this.categoryTarget.value = 'all';this.searchTarget.value = '';this.filter();}
        // Turbo must not replace the page for a demo fragment: keep local control state.
        history.replaceState(history.state, '', anchor.getAttribute('href'));
        target.setAttribute('tabindex', '-1');target.focus({preventScroll:true});target.scrollIntoView({block:'start'});
    }
    range(event) {const output = document.getElementById(event.currentTarget.getAttribute('aria-describedby'));if (output && this.element.contains(output)) output.textContent = `${event.currentTarget.value}%`;}
    file(event) {const file = event.currentTarget.files[0];this.message(file ? `Selected ${file.name} locally. No file was uploaded.` : 'No file selected.');}
    toast(event) {clearTimeout(this.toastTimer);this.toastOpener = event.currentTarget;this.toastTarget.hidden = false;this.toastTimer = setTimeout(() => this.closeToast(), 4500);}
    closeToast() {clearTimeout(this.toastTimer);if (this.hasToastTarget) {const restore=this.toastTarget.contains(document.activeElement);this.toastTarget.hidden = true;if(restore)this.toastOpener?.focus();}}
    openDrawer(event) {this.drawerOpener=event.currentTarget;this.drawerPanelTarget.showModal();}
    closeDrawer() {if (this.hasDrawerPanelTarget && this.drawerPanelTarget.open) this.drawerPanelTarget.close();}
    trapDrawer(event) {
        if (event.key !== 'Tab' || !this.hasDrawerPanelTarget || !this.drawerPanelTarget.open) return;
        const buttons = [...this.drawerPanelTarget.querySelectorAll('button,a[href],input,select,[tabindex="0"]')].filter(el => !el.disabled && !el.hidden);
        const first = buttons[0], last = buttons.at(-1);
        if (event.shiftKey && event.target === first) {event.preventDefault();last.focus();}
        if (!event.shiftKey && event.target === last) {event.preventDefault();first.focus();}
    }
    drawerClosed() {if (this.drawerOpener?.isConnected) this.drawerOpener.focus();}
    drawerBackdrop(event) {if (event.target === this.drawerPanelTarget) this.closeDrawer();}
    escape() {this.closeDrawer();this.closeToast();this.element.querySelectorAll('details.dropdown[open]').forEach(details => {const restore=details.contains(document.activeElement);details.open = false;if(restore)details.querySelector('summary').focus();});}
    carousel(event) {const card = event.currentTarget.closest('[data-showcase-card]');card.querySelector(`[data-slide="${event.currentTarget.dataset.slideTo}"]`).scrollIntoView({block:'nearest',inline:'start',behavior:'smooth'});}
    countdown() {
        clearInterval(this.timer);let seconds = 10;const output = this.countdownTarget;
        const render = () => {output.style.setProperty('--value', seconds);output.setAttribute('aria-label', `${seconds} seconds`);output.textContent = String(seconds);};
        render();this.timer = setInterval(() => {seconds--;render();if (seconds === 0) clearInterval(this.timer);}, 1000);
    }
    otp(event) {event.currentTarget.value = event.currentTarget.value.replace(/\D/g, '').slice(0, 6);}
}
