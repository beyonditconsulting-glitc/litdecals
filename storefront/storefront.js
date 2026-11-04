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
  // Four products at a time, crossfading in place without lateral sliding.
  document.querySelectorAll('.litrd-products[data-cycle-size]').forEach(function(section){
   const grid=section.querySelector('.litrd-product-grid');
   const cards=Array.from(grid.querySelectorAll('.litrd-product'));
   const size=4,total=Math.ceil(cards.length/size);
   const controls=section.querySelector('.litrd-products-cycle-controls');
   if(total<2 || !controls)return;
   const previous=controls.querySelector('.litrd-products-prev');
   const next=controls.querySelector('.litrd-products-next');
   const pause=controls.querySelector('.litrd-products-pause');
   const status=controls.querySelector('.litrd-products-cycle-status');
   const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
   let current=0,paused=motion.matches,timer=null,fade=null,hovering=false,focused=false;
   let inView=!('IntersectionObserver' in window);
   controls.hidden=false;
   function updateTimer(){
    clearInterval(timer);timer=null;
    pause.textContent=paused?'Play':'Pause';
    pause.setAttribute('aria-label',paused?'Start cycling new products':'Pause cycling new products');
    status.setAttribute('aria-live',paused?'polite':'off');
    if(!paused && !hovering && !focused && inView && !document.hidden)timer=setInterval(function(){show(current+1,false);},8000);
   }
   function paint(index){
    current=(index+total)%total;
    cards.forEach(function(card,i){card.hidden=Math.floor(i/size)!==current;});
    status.textContent=(current*size+1)+'–'+Math.min((current+1)*size,cards.length)+' of '+cards.length;
    const height=grid.getBoundingClientRect().height;
    if(height>0)grid.style.minHeight=Math.max(parseFloat(grid.style.minHeight)||0,height)+'px';
   }
   function show(index,manual){
    clearTimeout(fade);fade=null;
    if(manual)paused=true;
    if(motion.matches){paint(index);grid.classList.remove('is-changing');updateTimer();return;}
    grid.classList.add('is-changing');
    fade=setTimeout(function(){
     fade=null;
     if(!manual && (hovering || focused || document.hidden || !inView)){grid.classList.remove('is-changing');updateTimer();return;}
     paint(index);grid.classList.remove('is-changing');updateTimer();
    },180);
   }
   previous.addEventListener('click',function(){show(current-1,true);});
   next.addEventListener('click',function(){show(current+1,true);});
   pause.addEventListener('click',function(){paused=!paused;updateTimer();});
   grid.addEventListener('mouseenter',function(){hovering=true;updateTimer();});
   grid.addEventListener('mouseleave',function(){hovering=false;updateTimer();});
   grid.addEventListener('focusin',function(){focused=true;clearTimeout(fade);fade=null;grid.classList.remove('is-changing');updateTimer();});
   grid.addEventListener('focusout',function(){setTimeout(function(){focused=grid.contains(document.activeElement);updateTimer();},0);});
   document.addEventListener('visibilitychange',updateTimer);
   window.addEventListener('resize',function(){grid.style.minHeight='';paint(current);},{passive:true});
   if(motion.addEventListener)motion.addEventListener('change',function(){if(motion.matches)paused=true;updateTimer();});
   if('IntersectionObserver' in window){
    const observer=new window.IntersectionObserver(function(entries){inView=entries.some(function(entry){return entry.isIntersecting;});updateTimer();},{threshold:.15});
    observer.observe(section);
   }
   paint(0);updateTimer();
  });

  // Fit the approved banner and original logo at exactly the same displayed height.
  function fitHeaderAds(){
   document.querySelectorAll('.litrd-banner-brand-row').forEach(function(row){
    const logo=row.querySelector('.litrd-blue-header-logo img');
    const ad=row.querySelector('.litrd-header-ad-image');
    const width=row.clientWidth;
    if(!logo || !ad || !width)return;
    const logoRatio=logo.naturalWidth && logo.naturalHeight?logo.naturalWidth/logo.naturalHeight:220/120;
    const adRatio=2172/724;
    const small=window.matchMedia('(max-width: 750px)').matches;
    const gap=small?12:24;
    const preferredLogoWidth=small?120:220;
    const height=Math.max(1,Math.min(preferredLogoWidth/logoRatio,130,(width-gap)/(logoRatio+adRatio),width*.8/adRatio));
    row.style.setProperty('--litrd-brand-height',height+'px');
    row.style.setProperty('--litrd-brand-logo-width',(height*logoRatio)+'px');
    row.style.setProperty('--litrd-brand-ad-width',(height*adRatio)+'px');
   });
  }
  document.querySelectorAll('.litrd-banner-brand-row img').forEach(function(image){image.addEventListener('load',fitHeaderAds);});
  window.addEventListener('resize',fitHeaderAds,{passive:true});
  fitHeaderAds();

  // Progressive mobile navigation: cart and language remain available when closed.
  const mobile=window.matchMedia('(max-width: 750px)');
  const nav=document.querySelector('.litrd-page-bar');
  if(nav && !nav.querySelector('.litrd-mobile-menu-toggle')){
   const list=nav.querySelector('ul');
   const cart=nav.querySelector('.litrd-nav-cart-link');
   if(list && cart){
    cart.closest('li').classList.add('litrd-mobile-cart');
    if(!list.id)list.id='litrd-mobile-navigation-links';
    const item=document.createElement('li');item.className='litrd-mobile-toggle-item';
    const toggle=document.createElement('button');toggle.type='button';toggle.className='litrd-mobile-menu-toggle';
    toggle.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg><span>Menu</span>';
    toggle.setAttribute('aria-controls',list.id);toggle.setAttribute('aria-expanded','false');
    item.appendChild(toggle);list.insertBefore(item,list.firstChild);nav.classList.add('litrd-mobile-enhanced');
    function closeMenu(){nav.classList.remove('is-mobile-menu-open');toggle.setAttribute('aria-expanded','false');}
    toggle.addEventListener('click',function(){
     const open=toggle.getAttribute('aria-expanded')!=='true';
     nav.classList.toggle('is-mobile-menu-open',open);toggle.setAttribute('aria-expanded',String(open));
    });
    nav.addEventListener('keydown',function(event){if(event.key==='Escape' && toggle.getAttribute('aria-expanded')==='true'){closeMenu();toggle.focus();}});
    nav.addEventListener('click',function(event){if(event.target.closest('a'))closeMenu();});
    if(mobile.addEventListener)mobile.addEventListener('change',closeMenu);else mobile.addListener(closeMenu);
   }
  }
  // Use text-presentation arrows on iPhone rather than blue emoji tiles.
  const arrowSpans=Array.from(document.querySelectorAll('.litrd-product-button>span,.litrd-collection h2>span,.litrd-section-title>a>span,.litrd-browse-links a>span,.litrd-search button>span,.litrd-skins a>span'));
  const arrowText=arrowSpans.map(function(span){return span.textContent;});
  function updateArrows(){arrowSpans.forEach(function(span,index){if(arrowText[index].trim()==='↗')span.textContent=mobile.matches?'↗\uFE0E':arrowText[index];});}
  updateArrows();
  if(mobile.addEventListener)mobile.addEventListener('change',updateArrows);else mobile.addListener(updateArrows);
  const topButton=document.createElement('button');topButton.type='button';topButton.className='litrd-back-to-top';topButton.hidden=true;
  topButton.setAttribute('aria-label','Back to Top');
  topButton.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 7-7 7 7M12 5v15"/></svg><span>Top</span>';
  document.body.appendChild(topButton);
  let scrollQueued=false;
  function updateTopButton(){topButton.hidden=!mobile.matches || window.scrollY<400;scrollQueued=false;}
  window.addEventListener('scroll',function(){if(!scrollQueued){scrollQueued=true;window.requestAnimationFrame(updateTopButton);}},{passive:true});
  if(mobile.addEventListener)mobile.addEventListener('change',updateTopButton);else mobile.addListener(updateTopButton);
  topButton.addEventListener('click',function(){
   const heading=document.getElementById('masthead');
   if(heading){if(!heading.hasAttribute('tabindex'))heading.setAttribute('tabindex','-1');heading.focus({preventScroll:true});}
   window.scrollTo({top:0,behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
  });
  updateTopButton();

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
