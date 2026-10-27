import { Controller } from '@hotwired/stimulus';
let sequence=0;
export default class extends Controller {static targets=['dialog','title'];connect(){this.titleTarget.id=`portable-dialog-title-${++sequence}`;this.dialogTarget.setAttribute('aria-labelledby',this.titleTarget.id);}open(event){this.opener=event.currentTarget;this.dialogTarget.showModal();}close(){this.dialogTarget.close();this.opener?.focus();}disconnect(){if(this.dialogTarget.open)this.dialogTarget.close();}}

