import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['search','category','card','status','more'];
    connect(){this.limit=12;this.filter();}
    filter(){const query=this.searchTarget.value.toLowerCase().trim(),category=this.categoryTarget.value;let count=0;for(const card of this.cardTargets){const matches=card.dataset.family.includes(query)&&(!category||card.dataset.category===category);card.hidden=!matches||++count>this.limit;}this.statusTarget.textContent=`${count} matching families. Showing up to ${this.limit}.`;this.moreTarget.hidden=count<=this.limit;}
    more(){this.limit+=12;this.filter();}
}
