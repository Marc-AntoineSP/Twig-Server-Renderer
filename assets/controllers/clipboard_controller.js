import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['command','status'];
 async copy(){try{if(!navigator.clipboard)throw Error();await navigator.clipboard.writeText(this.commandTarget.value);this.statusTarget.textContent='Installation command copied.';}catch{this.commandTarget.focus();this.commandTarget.select();this.statusTarget.textContent='Command selected. Use your browser copy shortcut.';}}
}
