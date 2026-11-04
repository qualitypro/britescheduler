(()=>{
'use strict';
let dialog=null,photo=null,caption=null,counter=null,previous=null,next=null,items=[],index=0,returnFocus=null;
function setup(){
 if(dialog)return;
 dialog=document.createElement('dialog');dialog.className='brite-photo-lightbox';dialog.setAttribute('aria-label','Photo inspection');
 const panel=document.createElement('div');panel.className='brite-photo-lightbox-panel';
 const bar=document.createElement('div');bar.className='brite-photo-lightbox-toolbar';
 caption=document.createElement('strong');caption.id='britePhotoCaption';
 counter=document.createElement('span');counter.setAttribute('aria-live','polite');
 const close=document.createElement('button');close.type='button';close.textContent='Close ✕';close.setAttribute('aria-label','Close photo inspection');
 close.addEventListener('click',()=>dialog.close());
 bar.append(caption,counter,close);
 photo=document.createElement('img');photo.alt='';photo.className='brite-photo-lightbox-image';
 const controls=document.createElement('div');controls.className='brite-photo-lightbox-controls';
 previous=document.createElement('button');previous.type='button';previous.textContent='← Previous photo';
 next=document.createElement('button');next.type='button';next.textContent='Next photo →';
 previous.addEventListener('click',()=>show(index-1));next.addEventListener('click',()=>show(index+1));
 controls.append(previous,next);panel.append(bar,photo,controls);dialog.append(panel);document.body.append(dialog);
 dialog.addEventListener('click',e=>{if(e.target===dialog)dialog.close();});
 dialog.addEventListener('close',()=>{photo.removeAttribute('src');items=[];if(returnFocus?.isConnected)returnFocus.focus();returnFocus=null;});
 dialog.addEventListener('keydown',e=>{if(e.key==='ArrowLeft'&&index>0){e.preventDefault();show(index-1);}else if(e.key==='ArrowRight'&&index<items.length-1){e.preventDefault();show(index+1);}});
}
function show(i){
 if(i<0||i>=items.length)return;
 index=i;const item=items[i];photo.src=item.url;photo.alt=item.alt||'Photo '+(i+1);
 caption.textContent=item.label||'Photo inspection';counter.textContent=(i+1)+' of '+items.length;
 previous.disabled=i===0;next.disabled=i===items.length-1;
}
function isPhotoLink(a){
 if(!a)return false;
 try{const u=new URL(a.href,location.href);return u.origin===location.origin&&/\/api\/(?:quote_request_photo|quote_message_photo)\.php$/.test(u.pathname);}catch(_){return false;}
}
document.addEventListener('click',event=>{
 const anchor=event.target.closest('a[href]');
 const preview=event.target.closest('img[data-brite-quote-preview]');
 if(!isPhotoLink(anchor)&&!preview)return;
 if(event.button!==0||event.ctrlKey||event.metaKey||event.shiftKey||event.altKey)return;
 const source=anchor||preview;
 const group=source.closest('[data-brite-photo-gallery]')||source.parentElement;
 const candidates=Array.from(group.querySelectorAll('a[href],img[data-brite-quote-preview]')).filter(n=>isPhotoLink(n)||n.matches('img[data-brite-quote-preview]'));
 const selected=candidates.length?candidates:[source];
 items=selected.map((n,i)=>({url:n.tagName==='A'?n.href:n.src,alt:(n.querySelector('img')||n).alt||'Photo '+(i+1),label:n.dataset.photoLabel||'Photo inspection'}));
 const position=selected.indexOf(source);
 if(position<0)return;
 event.preventDefault();setup();returnFocus=source;show(position);
 if(!dialog.open)dialog.showModal();
});
window.BritePhotoLightbox={open:(url,label)=>{setup();items=[{url,label}];returnFocus=document.activeElement;show(0);if(!dialog.open)dialog.showModal();}};
})();
