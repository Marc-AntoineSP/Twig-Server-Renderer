import { Controller } from '@hotwired/stimulus';
let sequence=0;
export default class extends Controller {
 static targets=['tab','panel'];connect(){const prefix=`portable-tabs-${++sequence}`;this.tabTargets.forEach((tab,i)=>{tab.disabled=false;tab.id=`${prefix}-tab-${i}`;tab.setAttribute('aria-controls',`${prefix}-panel-${i}`);this.panelTargets[i].id=`${prefix}-panel-${i}`;this.panelTargets[i].setAttribute('role','tabpanel');this.panelTargets[i].setAttribute('aria-labelledby',tab.id);});this.activate(0);}
 activate(index){this.tabTargets.forEach((tab,i)=>{tab.setAttribute('aria-selected',String(i===index));tab.tabIndex=i===index?0:-1;tab.classList.toggle('tab-active',i===index);this.panelTargets[i].hidden=i!==index;});}
 select(event){this.activate(this.tabTargets.indexOf(event.currentTarget));}
 key(event){const keys=['ArrowLeft','ArrowRight','Home','End'];if(!keys.includes(event.key))return;event.preventDefault();const i=this.tabTargets.indexOf(event.currentTarget);const next=event.key==='Home'?0:event.key==='End'?this.tabTargets.length-1:(i+(event.key==='ArrowRight'?1:-1)+this.tabTargets.length)%this.tabTargets.length;this.activate(next);this.tabTargets[next].focus();}
 disconnect(){this.tabTargets.forEach(tab=>{tab.disabled=true;});this.panelTargets.forEach(panel=>{panel.hidden=false;});}
}
