/* Accessible Coming Soon interaction for the developing Parts Finder app. */
(function(){
 'use strict';
 function init(){
  document.querySelectorAll('.litrd-carousel').forEach(function(carousel){
   const track=carousel.querySelector('.litrd-carousel-track');
   const previous=carousel.querySelector('.litrd-carousel-prev');
   const next=carousel.querySelector('.litrd-carousel-next');
   const status=carousel.querySelector('.litrd-carousel-status');
   const cards=Array.from(track.querySelectorAll('.litrd-collection'));
   if(!cards.length)return;
   let current=0;
   let timer=null;
   let paused=window.matchMedia('(prefers-reduced-motion: reduce)').matches;
   let hovering=false;
   let focused=false;
   const controls=carousel.querySelector('.litrd-carousel-controls');
   const pause=document.createElement('button');pause.type='button';pause.className='litrd-carousel-pause';
   controls.appendChild(pause);
   const dots=document.createElement('div');dots.className='litrd-carousel-dots';dots.setAttribute('aria-label','Choose a category slide');
   carousel.appendChild(dots);
   const buttons=cards.map(function(card,index){
    card.setAttribute('aria-roledescription','slide');const name=card.querySelector('h2');card.setAttribute('aria-label',(name?name.textContent.replace('↗','').trim():'Category')+' — '+(index+1)+' of '+cards.length);
    const button=document.createElement('button');button.type='button';
    const heading=card.querySelector('h2');button.setAttribute('aria-label','Show '+(heading?heading.textContent.replace('↗','').trim():'slide '+(index+1)));
    button.addEventListener('click',function(){show(index);});dots.appendChild(button);return button;
   });
   function updateTimer(){
    clearInterval(timer);timer=null;
    pause.textContent=paused?'Play':'Pause';pause.setAttribute('aria-label',paused?'Start automatic slide rotation':'Pause automatic slide rotation');
    status.setAttribute('aria-live',paused?'polite':'off');
    if(!paused && !hovering && !focused && !document.hidden)timer=setInterval(function(){show(current+1);},5000);
   }
   function show(index){
    current=(index+cards.length)%cards.length;
    cards.forEach(function(card,i){card.hidden=false;card.setAttribute('aria-hidden',i===current?'false':'true');card.tabIndex=i===current?0:-1;card.inert=i!==current;});
    track.style.transform='translate3d(-'+(current*100)+'%,0,0)';
    buttons.forEach(function(button,i){button.setAttribute('aria-pressed',i===current?'true':'false');});
    status.textContent=(current+1)+' / '+cards.length;
   }
   previous.addEventListener('click',function(){show(current-1);});
   next.addEventListener('click',function(){show(current+1);});
   pause.addEventListener('click',function(){paused=!paused;updateTimer();});
   track.addEventListener('keydown',function(event){if(event.key==='ArrowRight'){event.preventDefault();show(current+1);}else if(event.key==='ArrowLeft'){event.preventDefault();show(current-1);}});
   let touchStart=null;
   track.addEventListener('touchstart',function(event){touchStart=event.changedTouches[0].clientX;},{passive:true});
   track.addEventListener('touchend',function(event){if(touchStart===null)return;const delta=event.changedTouches[0].clientX-touchStart;if(Math.abs(delta)>50)show(current+(delta<0?1:-1));touchStart=null;},{passive:true});
   carousel.addEventListener('mouseenter',function(){hovering=true;updateTimer();});
   carousel.addEventListener('mouseleave',function(){hovering=false;updateTimer();});
   carousel.addEventListener('focusin',function(){focused=true;updateTimer();});
   carousel.addEventListener('focusout',function(){setTimeout(function(){focused=carousel.contains(document.activeElement);updateTimer();},0);});
   document.addEventListener('visibilitychange',updateTimer);
   carousel.classList.add('is-ready','is-slideshow');show(0);updateTimer();
  });
  const logo=document.querySelector('.litrd-blue-header-logo');
  if(logo && !logo.closest('.litrd-blue-brand-row')){
   const parent=logo.parentElement;
   const header=logo.closest('header') || parent;
   const title=parent.querySelector('.site-title') || logo.nextElementSibling;
   if(title && title!==logo && !title.contains(logo)){
    const description=parent.querySelector('.site-description') || header.querySelector('.site-description');
    const next=title.nextElementSibling;
    const fallbackDescription=next && /^(H2|P)$/.test(next.tagName) && /litdecals|online store/i.test(next.textContent)?next:null;
    const row=document.createElement('div');row.className='litrd-blue-brand-row';
    const text=document.createElement('div');text.className='litrd-blue-brand-text';
    logo.before(row);row.appendChild(logo);row.appendChild(text);text.appendChild(title);
    if(description && !description.contains(row))text.appendChild(description);
    else if(fallbackDescription)text.appendChild(fallbackDescription);
   }
  }

  const dialog=document.getElementById('litrd-parts-finder-dialog');
  document.querySelectorAll('.litrd-parts-finder-trigger').forEach(function(button){
   button.addEventListener('click',function(){
    if(dialog && typeof dialog.showModal==='function'){
     if(!dialog.open)dialog.showModal();
    }else{window.alert('Coming Soon');}
   });
  });
  if(dialog){dialog.addEventListener('click',function(event){if(event.target===dialog){const bounds=dialog.getBoundingClientRect();if(event.clientX<bounds.left || event.clientX>bounds.right || event.clientY<bounds.top || event.clientY>bounds.bottom)dialog.close();}});}
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
