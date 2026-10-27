import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets=['family','weight','style','text','frame','status'];
    static values={url:String};
    connect(){this.sequence=0;}
    disconnect(){this.sequence++;this.abort?.abort();}
    async preview(){
        const sequence=++this.sequence;this.abort?.abort();this.abort=new AbortController();this.statusTarget.textContent='Loading selected font specimen…';
        try{
            const params=new URLSearchParams({text:this.textTarget.value,weight:this.weightTarget.value,style:this.styleTarget.value});
            const response=await fetch(this.urlValue.replace('font-family-placeholder',this.familyTarget.value)+'?'+params,{signal:this.abort.signal,credentials:'same-origin'});
            const data=await response.json();if(!response.ok)throw Error(data.message||'Font unavailable; system fallback remains active.');if(sequence!==this.sequence)return;
            this.frameTarget.srcdoc=data.html;this.statusTarget.textContent=`${data.family} specimen ready. Browser font status is checked inside the frame.`;
        }catch(error){if(error.name!=='AbortError'&&sequence===this.sequence)this.statusTarget.textContent=error.message;}
    }
}
