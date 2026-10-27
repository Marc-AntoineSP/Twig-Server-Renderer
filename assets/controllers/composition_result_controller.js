import {Controller} from '@hotwired/stimulus';
export default class extends Controller {
    static targets=['status','frame'];static values={statusUrl:String,documentUrl:String};
    connect(){this.stopped=false;this.poll();}
    disconnect(){this.stopped=true;clearTimeout(this.timer);}
    async poll(){
        try{const response=await fetch(this.statusUrlValue,{credentials:'same-origin',headers:{Accept:'application/json'}});if(!response.ok)throw Error('Preview access expired.');const result=await response.json();if(this.stopped)return;this.statusTarget.textContent=result.status;
            if(result.status==='succeeded'){if(!this.frameTarget.srcdoc){const document=await(await fetch(this.documentUrlValue,{credentials:'same-origin',headers:{Accept:'application/json'}})).json();if(!this.stopped)this.frameTarget.srcdoc=document.html;}return;}
            if(['failed','cancelled','expired'].includes(result.status)){this.statusTarget.textContent=result.error?.message||result.status;return;}
            this.timer=setTimeout(()=>this.poll(),500);
        }catch(error){if(!this.stopped)this.statusTarget.textContent=error.message;}
    }
}
