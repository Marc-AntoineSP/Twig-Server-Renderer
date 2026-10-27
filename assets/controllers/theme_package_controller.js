import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['status','link'];static values={url:String,csrf:String,sourceUrl:String};
    async publish(){this.statusTarget.textContent='Validating package and installing immutable archive…';try{
        const response=await fetch(this.urlValue,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrfValue},body:'{}'});const data=await response.json();if(!response.ok)throw Error(data.message||'Package publication failed.');
        this.linkTarget.href=this.sourceUrlValue.replace('__RELEASE__',data.id);this.linkTarget.hidden=false;this.statusTarget.textContent='Theme package is available in the private registry. Distribution licence: UNLICENSED.';
    }catch(error){this.statusTarget.textContent=error.message;}}
}
