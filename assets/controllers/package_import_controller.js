import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['file','format','content','apply','status','plan','links'];
    static values={url:String,csrf:String,itemUrl:String};
    changed(){this.planHash=null;this.applyTarget.disabled=true;this.statusTarget.textContent='Input changed. Validate the current file before applying.';}
    async run(event){
        const mode=event.currentTarget.dataset.mode;this.statusTarget.textContent=mode==='apply'?'Applying reviewed plan…':'Validating sources, dependency graph and real render/build…';
        const form=new FormData();form.set('mode',mode);
        if(this.fileTarget.files[0])form.set('file',this.fileTarget.files[0]);else{form.set('content',this.contentTarget.value);form.set('format',this.formatTarget.value);}
        if(mode==='apply')form.set('expectedPlan',this.planHash||'');
        const snapshot=[this.fileTarget.files[0],this.contentTarget.value,this.formatTarget.value];
        try{
            const response=await fetch(this.urlValue,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':this.csrfValue},body:form});const data=await response.json();
            if(!response.ok)throw Error(data.message||'Import failed.');
            if(snapshot[0]!==this.fileTarget.files[0]||snapshot[1]!==this.contentTarget.value||snapshot[2]!==this.formatTarget.value)return;
            this.planTarget.textContent=JSON.stringify(data.plan,null,2);this.linksTarget.replaceChildren();
            if(data.result){this.statusTarget.textContent=`Imported ${data.result.applied} release(s); ${data.result.skipped} identical release(s) preserved. Existing drafts were kept.`;this.planHash=null;this.applyTarget.disabled=true;
                for(const [name,id] of Object.entries(data.result.items)){const link=document.createElement('a');link.className='link';link.textContent=`Open ${name}`;link.href=this.itemUrlValue.replace('__ITEM__',id);this.linksTarget.append(link);}
            }else{this.planHash=data.planHash;this.applyTarget.disabled=false;this.statusTarget.textContent='Validation passed. Review package origins, files, licences and exact dependencies below; nothing has been applied.';}
        }catch(error){this.planHash=null;this.applyTarget.disabled=true;this.statusTarget.textContent=error.message;}
    }
}
