import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'status'];
    static values = {url: String, title: String};
    disconnect() { this.request?.abort(); this.observer?.disconnect(); }
    async load() {
        if (this.request) return;
        this.request = new AbortController();
        this.buttonTarget.disabled = true;
        this.statusTarget.textContent = 'Loading published preview…';
        try {
            const response = await fetch(this.urlValue, {signal: this.request.signal, credentials: 'same-origin'});
            if (!response.ok) throw Error('Published preview unavailable. Open the item to retry.');
            const document = await response.json();
            const frame = document.html && window.document.createElement('iframe');
            if (!frame) throw Error('Published preview unavailable.');
            frame.title = `Published preview ${this.titleValue}`;
            frame.tabIndex = -1;
            frame.setAttribute('sandbox', '');
            frame.setAttribute('aria-hidden', 'true');
            frame.style.cssText = 'position:absolute;left:0;top:0;width:768px;height:432px;border:0;transform-origin:top left;pointer-events:none';
            frame.srcdoc = document.html;
            this.element.append(frame);
            this.observer = new ResizeObserver(() => { frame.style.transform = `scale(${this.element.clientWidth / 768})`; });
            this.observer.observe(this.element);
            this.buttonTarget.hidden = true;
            this.statusTarget.textContent = `Published ${document.version}`;
        } catch (error) {
            if (error.name !== 'AbortError') { this.statusTarget.textContent = error.message; this.buttonTarget.disabled = false; }
            this.request = null;
        }
    }
}
