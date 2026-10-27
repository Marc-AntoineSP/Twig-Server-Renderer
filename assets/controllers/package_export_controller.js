import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['format','theme','props','status','link'];
    static values={url:String,csrf:String,release:String};
    async build(){
        this.statusTarget.textContent='Building export from the immutable release…';this.linkTarget.hidden=true;
        try{
            const payload={releaseId:this.releaseValue,format:this.formatTarget.value,themeReleaseId:this.hasThemeTarget?this.themeTarget.value||null:null};
            if(this.hasPropsTarget&&this.propsTarget.value.trim())payload.props=JSON.parse(this.propsTarget.value);
            const response=await fetch(this.urlValue,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':this.csrfValue},body:JSON.stringify(payload)});const data=await response.json();
            if(!response.ok)throw Error(data.message||'Export failed.');
            this.linkTarget.href=data.downloadUrl;this.linkTarget.hidden=false;this.statusTarget.textContent=`Export ready. SHA-256 ${data.sha256}. Private download expires in 30 minutes.`;
        }catch(error){this.statusTarget.textContent=error.message;}
    }
}
