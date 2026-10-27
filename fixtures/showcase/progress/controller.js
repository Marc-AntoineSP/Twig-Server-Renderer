import { Controller } from '@hotwired/stimulus';
export default class extends Controller {
 static targets=['bar','value','toggle'];static values={start:Number,autoStart:Boolean};
 connect(){this.motion=matchMedia('(prefers-reduced-motion: reduce)');this.motionChanged=()=>{if(this.motion.matches)this.stop();else if(this.autoStartValue)this.play();};this.motion.addEventListener('change',this.motionChanged);this.current=this.startValue;this.paint();if(this.autoStartValue&&!this.motion.matches)this.play();else this.stop();}
 disconnect(){this.stop();this.motion.removeEventListener('change',this.motionChanged);}
 paint(){this.barTarget.value=this.current;this.valueTarget.textContent=`${this.current}%`;}
 advance(){this.current=Math.min(100,this.current+5);this.paint();if(this.current===100)this.stop();}
 play(){if(this.timer||this.motion.matches||this.current>=100)return;this.timer=setInterval(()=>{if(!document.hidden)this.advance();},250);this.toggleTarget.textContent='Pause progress';}
 stop(){clearInterval(this.timer);this.timer=null;this.toggleTarget.textContent='Resume progress';}
 reset(){this.stop();this.current=this.startValue;this.paint();}
 toggle(){if(this.timer)this.stop();else this.play();}
}
