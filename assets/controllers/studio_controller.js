import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['help'];

    toggle() {
        this.helpTarget.hidden = !this.helpTarget.hidden;
        this.element.querySelector('button').setAttribute('aria-expanded', String(!this.helpTarget.hidden));
    }
}
