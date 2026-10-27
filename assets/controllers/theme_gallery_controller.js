import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets=['search','mode','card'];
    filter(){const query=this.searchTarget.value.toLowerCase().trim(),mode=this.modeTarget.value;for(const card of this.cardTargets)card.hidden=!card.dataset.name.includes(query)||!!mode&&card.dataset.mode!==mode;}
}
