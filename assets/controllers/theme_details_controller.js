import {Controller} from '@hotwired/stimulus';

// Native disclosure stays usable without JavaScript; wide layouts open it initially.
export default class extends Controller {
    static targets = ['disclosure', 'summary'];
    connect() {
        this.media = matchMedia('(min-width: 1024px)');
        this.resize = () => {
            const restore = this.disclosureTarget.contains(document.activeElement);
            this.disclosureTarget.open = this.media.matches;
            if (!this.media.matches && restore) this.summaryTarget.focus();
        };
        this.media.addEventListener('change', this.resize);
        this.resize();
    }
    disconnect() {this.media.removeEventListener('change', this.resize);}
}
