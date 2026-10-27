import { Controller } from '@hotwired/stimulus';
export default class extends Controller { static targets=['status']; connect(){this.count=0;} press(){this.count++;this.statusTarget.textContent=`Button pressed ${this.count} time${this.count===1?'':'s'}.`;} }
