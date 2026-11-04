'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {parseHTML}=require(process.env.LITDECALS_DOM_MODULE);
const {window}=parseHTML(`<!doctype html><html><body>
<header id="masthead"><div class="site-branding"><div class="litrd-blue-brand-row litrd-banner-brand-row"><a class="litrd-blue-header-logo" href="/"><img alt="Lit Decals"></a><a class="litrd-header-ad" href="https://findrc.parts/"><img class="litrd-header-ad-image" src="/?litrd_header_banner=basher-v1"></a></div></div></header>
<nav class="litrd-page-bar"><ul><li><a href="/">Home</a></li><li><a href="/decals/">Decals</a></li><li><a class="litrd-nav-cart-link" href="/cart/">Cart</a></li><li class="lit-language-menu-item"><details><summary>English</summary></details></li></ul></nav>
<a class="litrd-product-button" href="/product/">Select options<span>↗</span></a>
</body></html>`);
const {document}=window;
Object.defineProperty(document,'readyState',{value:'complete'});
const listeners=new Set();
const mobile={matches:true,addEventListener:(name,fn)=>listeners.add(fn)};
const reduced={matches:false};
window.matchMedia=query=>query.includes('750px')?mobile:reduced;
window.scrollY=0;
window.requestAnimationFrame=callback=>callback();
let availableWidth=361;
const brandRow=document.querySelector('.litrd-banner-brand-row');
Object.defineProperty(brandRow,'clientWidth',{get:()=>availableWidth});
let scrollOptions;
window.scrollTo=options=>{scrollOptions=options;window.scrollY=options.top;window.dispatchEvent(new window.Event('scroll'));};
let headerFocused=false;
document.getElementById('masthead').focus=()=>{headerFocused=true;};
vm.runInNewContext(fs.readFileSync(__dirname+'/../storefront/storefront.js','utf8'),{window,document,console,setTimeout,clearInterval,setInterval});
const nav=document.querySelector('.litrd-page-bar');
const toggle=document.querySelector('.litrd-mobile-menu-toggle');
const top=document.querySelector('.litrd-back-to-top');
assert.equal(toggle.getAttribute('aria-expanded'),'false');
assert.ok(nav.classList.contains('litrd-mobile-enhanced'));
assert.ok(document.querySelector('.litrd-nav-cart-link').closest('li').classList.contains('litrd-mobile-cart'));
assert.ok(document.querySelector('.lit-language-menu-item summary'));
assert.equal(document.querySelector('.site-title'),null);
assert.equal(document.querySelector('.litrd-mobile-tagline'),null);
assert.equal(document.querySelector('.site-description'),null);
toggle.click();
assert.equal(toggle.getAttribute('aria-expanded'),'true');
assert.ok(nav.classList.contains('is-mobile-menu-open'));
const escape=new window.Event('keydown',{bubbles:true});escape.key='Escape';toggle.dispatchEvent(escape);
assert.equal(toggle.getAttribute('aria-expanded'),'false');
toggle.click();document.querySelector('a[href="/decals/"]').click();
assert.equal(toggle.getAttribute('aria-expanded'),'false');
assert.equal(top.hidden,true);
window.scrollY=500;window.dispatchEvent(new window.Event('scroll'));
assert.equal(top.hidden,false);
top.click();
assert.equal(scrollOptions.top,0);assert.equal(scrollOptions.behavior,'smooth');assert.equal(headerFocused,true);
assert.equal(top.hidden,true);
reduced.matches=true;window.scrollY=500;window.dispatchEvent(new window.Event('scroll'));top.click();
assert.equal(scrollOptions.behavior,'auto');
mobile.matches=false;window.scrollY=500;listeners.forEach(fn=>fn());
assert.equal(top.hidden,true);
assert.equal(toggle.getAttribute('aria-expanded'),'false');
assert.equal(document.querySelector('.litrd-product-button span').textContent,'↗');
console.log('PASS: mobile menu, cart/language retention, header structure, scroll visibility, reduced motion, focus return, and desktop reset.');

for(const canvasWidth of [288,361,720,1152]){
 availableWidth=canvasWidth;mobile.matches=canvasWidth<=720;
 window.dispatchEvent(new window.Event('resize'));
 const height=parseFloat(brandRow.style.getPropertyValue('--litrd-brand-height'));
 const logoWidth=parseFloat(brandRow.style.getPropertyValue('--litrd-brand-logo-width'));
 const adWidth=parseFloat(brandRow.style.getPropertyValue('--litrd-brand-ad-width'));
 assert.ok(height>0);
 assert.ok(Math.abs(adWidth/3-height)<0.01);
 assert.ok(Math.abs(logoWidth/(220/120)-height)<0.01);
 assert.ok(adWidth<=canvasWidth*.8+0.01);
 assert.ok(logoWidth+adWidth+(mobile.matches?12:24)<=canvasWidth+0.01);
}
console.log('PASS: Equal logo/banner heights, complete artwork, and maximum 80% banner width across mobile and desktop header sizes.');
