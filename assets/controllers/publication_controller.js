import {Controller} from '@hotwired/stimulus';

// A native synchronous POST: this reports the request, never a background job/stage.
export default class extends Controller {
    static targets = ['button', 'status', 'version', 'revision'];
    connect() {
        this.restore = () => {
            this.pending = false;
            this.buttonTarget.disabled = false;
            this.buttonTarget.textContent = 'Publish release';
            this.statusTarget.textContent = '';
            this.statusTarget.hidden = true;
            this.element.dataset.publicationState = 'ready';
        };
        window.addEventListener('pageshow', this.restore);
        this.restore();
    }
    disconnect() {window.removeEventListener('pageshow', this.restore);}
    submit(event) {
        if (this.pending) {event.preventDefault(); return;}
        this.pending = true;
        this.element.dataset.publicationState = 'submitting';
        this.buttonTarget.disabled = true;
        this.buttonTarget.textContent = 'Publishing…';
        this.statusTarget.hidden = false;
        this.statusTarget.textContent = `Publishing saved revision ${this.revisionTarget.value} as ${this.versionTarget.value}. This request validates defaults and examples before committing the release. If interrupted, reload this page and check Releases before retrying.`;
    }
}
