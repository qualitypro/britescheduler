/* Brite widget layouts v3: pointer, keyboard, and account-synced customization. */
(function(){
 'use strict';
 window.BriteWidgetLayout={
  mount(grid,options){
   if(!grid||grid.dataset.briteWidgetsMounted==='1')return;
   grid.dataset.briteWidgetsMounted='1';
   const defaultCards=Array.from(grid.querySelectorAll(':scope > [data-widget-id]'));
   const defaults=options.defaultOrder.slice();
   const known=new Set(defaults);
   const storageKey='brite.widgets.v1.'+options.scope;
   const endpoint=options.endpoint||'';
   const csrf=options.csrf||'';
   const empty=()=>({order:[],hidden:[],wide:[]});
   const clean=value=>{
    const result=empty();
    if(!value||typeof value!=='object')return result;
    for(const field of ['order','hidden','wide']){
     const list=Array.isArray(value[field])?value[field]:[];
     result[field]=Array.from(new Set(list.filter(id=>typeof id==='string'&&known.has(id)))).slice(0,known.size);
    }
    return result;
   };
   let saved=empty(),revision=0,queue=Promise.resolve(),pointer=null;
   try{saved=clean(JSON.parse(localStorage.getItem(storageKey)||'null'))}catch(_e){}
   const cards=()=>Array.from(grid.querySelectorAll(':scope > [data-widget-id]'));
   const toolbar=document.createElement('div');
   toolbar.className='brite-widget-toolbar brite-widget-utility';
   const restore=document.createElement('div');
   restore.className='brite-widget-restore';restore.hidden=true;
   const restoreToggle=document.createElement('button');
   restoreToggle.type='button';restoreToggle.className='brite-widget-utility-toggle';
   restoreToggle.textContent='⊞';
   restoreToggle.title='Restore hidden widgets or reset layout';
   restoreToggle.setAttribute('aria-label','Restore hidden widgets or reset dashboard layout');
   restoreToggle.setAttribute('aria-expanded','false');
   const menu=document.createElement('div');
   menu.className='brite-widget-utility-menu';menu.hidden=true;
   const reset=document.createElement('button');
   reset.type='button';reset.textContent='Reset layout';
   const status=document.createElement('span');
   status.className='brite-widget-status';
   status.setAttribute('role','status');status.setAttribute('aria-live','polite');
   menu.append(restore,reset);
   toolbar.append(restoreToggle,menu,status);
   grid.before(toolbar);
   restoreToggle.addEventListener('click',()=>{
    menu.hidden=!menu.hidden;
    restoreToggle.setAttribute('aria-expanded',String(!menu.hidden));
   });
   document.addEventListener('pointerdown',event=>{
    if(!toolbar.contains(event.target)){
     menu.hidden=true;restoreToggle.setAttribute('aria-expanded','false');
    }
   });
   const announce=message=>{status.textContent=message};
   const notify=()=>{grid.dispatchEvent(new CustomEvent('brite:widgets-changed',{bubbles:true}));window.dispatchEvent(new Event('resize'))};
   const cache=()=>{try{localStorage.setItem(storageKey,JSON.stringify(saved))}catch(_e){}};
   const persist=()=>{
    cache();revision++;
    if(!endpoint)return;
    const snapshot=JSON.stringify(saved);
    queue=queue.catch(()=>{}).then(async()=>{
     const response=await fetch(endpoint,{method:'PUT',credentials:'same-origin',headers:{
      'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':csrf
     },body:snapshot});
     if(!response.ok)throw Error('Account sync unavailable');
     announce('Layout saved to your account.');
    }).catch(()=>announce('Layout saved in this browser; account sync unavailable.'));
   };
   const render=()=>{
    const byId=new Map(defaultCards.map(card=>[card.dataset.widgetId,card]));
    const order=[...saved.order,...defaults.filter(id=>!saved.order.includes(id))];
    order.forEach(id=>{if(byId.has(id))grid.append(byId.get(id))});
    for(const card of defaultCards){
     const id=card.dataset.widgetId;
     card.hidden=saved.hidden.includes(id);
     card.classList.toggle('brite-widget-wide',saved.wide.includes(id));
     card.classList.toggle('brite-widget-editing',true);
     card.draggable=false;
     const controls=card.querySelector(':scope > .brite-widget-chrome > .brite-widget-controls');
     if(controls)controls.hidden=false;
    }
    restore.replaceChildren();
    for(const id of saved.hidden){
     const button=document.createElement('button');
     button.type='button';
     button.textContent='Show '+(byId.get(id)?.dataset.widgetLabel||id);
     button.addEventListener('click',()=>{
      saved.hidden=saved.hidden.filter(value=>value!==id);
      render();persist();announce('Widget restored.');notify();
     });
     restore.append(button);
    }
    restore.hidden=saved.hidden.length===0;
    notify();
   };
   const animateLayout=change=>{
    const visible=cards().filter(card=>!card.hidden);
    const positions=new Map(visible.map(card=>[card,card.getBoundingClientRect()]));
    change();
    if(window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    for(const card of visible){
     const a=positions.get(card),b=card.getBoundingClientRect();
     const dx=a.left-b.left,dy=a.top-b.top;
     if(Math.abs(dx)<1&&Math.abs(dy)<1)continue;
     card.animate([{transform:'translate('+dx+'px,'+dy+'px)'},{transform:'translate(0,0)'}],
      {duration:260,easing:'cubic-bezier(.2,.8,.2,1)'});
    }
   };
   const reorder=(source,target,before=true)=>{
    if(!source||!target||source===target||source.hidden||target.hidden)return;
    // Insert at the indicated grid slot, shifting intervening widgets.
    // Swapping arbitrary slots makes the released widget appear elsewhere.
    const ordered=cards().map(card=>card.dataset.widgetId);
    const from=ordered.indexOf(source.dataset.widgetId);
    if(from<0)return;
    ordered.splice(from,1);
    const to=ordered.indexOf(target.dataset.widgetId);
    if(to<0)return;
    ordered.splice(to+(before?0:1),0,source.dataset.widgetId);
    saved.order=ordered;
    animateLayout(()=>render());persist();announce('Widget moved to selected position.');
    source.querySelector('.brite-widget-handle')?.focus({preventScroll:true});
   };
   const move=(card,direction)=>{
    const visible=cards().filter(item=>!item.hidden);
    const index=visible.indexOf(card);
    const target=visible[index+direction];
    if(target)reorder(card,target,direction<0);
   };
   const clearDropTarget=()=>defaultCards.forEach(card=>{
    card.classList.remove('brite-widget-drop-target');
    card.removeAttribute('data-widget-drop-label');
    card.removeAttribute('data-widget-drop-side');
   });
   const dropAt=(x,y,source)=>{
    const candidates=cards().filter(card=>card!==source&&!card.hidden);
    if(!candidates.length)return null;
    const bounds=grid.getBoundingClientRect();
    // A valid drop includes the gaps between cards, not just their content.
    if(x<bounds.left-24||x>bounds.right+24||y<bounds.top-24||y>bounds.bottom+24)return null;
    // Use geometry rather than elementFromPoint: captured pointers, overlays,
    // and the translated source card can otherwise obscure the destination.
    const ranked=candidates.map(card=>{
     const rect=card.getBoundingClientRect();
     const nearestX=Math.max(rect.left,Math.min(x,rect.right));
     const nearestY=Math.max(rect.top,Math.min(y,rect.bottom));
     const distance=Math.hypot(x-nearestX,y-nearestY);
     return {card,rect,distance};
    }).sort((a,b)=>a.distance-b.distance);
    const closest=ranked[0];
    if(!closest)return null;
    const {card,rect}=closest;
    // For side-by-side cards use horizontal midpoint; for stacked cards,
    // use vertical midpoint to determine the insertion edge.
    const ordered=candidates;
    const index=ordered.indexOf(card);
    const prev=ordered[index-1]?.getBoundingClientRect();
    const next=ordered[index+1]?.getBoundingClientRect();
    const horizontal=(prev&&Math.abs(prev.top-rect.top)<rect.height*.45)||
      (next&&Math.abs(next.top-rect.top)<rect.height*.45);
    const before=horizontal?x<rect.left+rect.width/2:y<rect.top+rect.height/2;
    return {card,before};
   };
   const stopPointer=()=>{
    if(!pointer)return;
    pointer.card.style.transform='';
    pointer.card.classList.remove('brite-widget-drag-active');
    pointer.handle.classList.remove('brite-widget-dragging');
    clearDropTarget();
    pointer=null;
   };
   for(const card of defaultCards){
    const id=card.dataset.widgetId;
    const name=card.dataset.widgetLabel||id;
    // Anchor the floating launcher to this card, regardless of dashboard CSS.
    if(getComputedStyle(card).position==='static')card.style.position='relative';
    const controls=document.createElement('div');
    controls.className='brite-widget-controls';
    const chrome=document.createElement('div');
    chrome.className='brite-widget-chrome';
    chrome.setAttribute('role','group');
    chrome.setAttribute('aria-label','Widget actions for '+name);
    chrome.tabIndex=0;
    chrome.addEventListener('click',event=>{
     if(event.target===chrome)chrome.classList.toggle('brite-widget-chrome-open');
    });
    chrome.addEventListener('focusout',event=>{
     if(!chrome.contains(event.relatedTarget))chrome.classList.remove('brite-widget-chrome-open');
    });
    chrome.addEventListener('keydown',event=>{
     if(event.key==='Escape'){chrome.classList.remove('brite-widget-chrome-open');chrome.focus();}
     if((event.key==='Enter'||event.key===' ')&&event.target===chrome){
      event.preventDefault();chrome.classList.toggle('brite-widget-chrome-open');
     }
    });
    const handle=document.createElement('button');
    handle.type='button';handle.className='brite-widget-handle';
    handle.textContent='⠿';
    handle.title='Drag to move · Alt + arrow keys';
    handle.setAttribute('aria-label','Move '+name+'; Alt and arrow keys to reorder');
    handle.addEventListener('keydown',event=>{
     if(!event.altKey)return;
     if(['ArrowLeft','ArrowUp','ArrowRight','ArrowDown'].includes(event.key)){
      event.preventDefault();move(card,['ArrowLeft','ArrowUp'].includes(event.key)?-1:1);
     }
    });
    // Pointer Events support mouse, pen, and touch without dragging the card's content.
    handle.addEventListener('pointerdown',event=>{
     if(event.button!==0||pointer)return;
     pointer={handle,card,id:event.pointerId,startX:event.clientX,startY:event.clientY,
      moved:false,target:null,before:false};
     handle.setPointerCapture(event.pointerId);
    });
    handle.addEventListener('pointermove',event=>{
     const drag=pointer;
     if(!drag||drag.id!==event.pointerId)return;
     const dx=event.clientX-drag.startX,dy=event.clientY-drag.startY;
     if(!drag.moved&&Math.hypot(dx,dy)<7)return;
     if(!drag.moved){
      drag.moved=true;
      handle.classList.add('brite-widget-dragging');
      card.classList.add('brite-widget-drag-active');
     }
     event.preventDefault();
     card.style.transform='translate3d('+dx+'px,'+dy+'px,0)';
     clearDropTarget();
     const destination=dropAt(event.clientX,event.clientY,card);
     drag.target=destination?.card||null;
     drag.before=destination?.before??true;
     if(drag.target){
      drag.target.classList.add('brite-widget-drop-target');
      drag.target.setAttribute('data-widget-drop-label',drag.before?'Insert before '+name:'Insert after '+name);
      drag.target.dataset.widgetDropSide=drag.before?'before':'after';
     }
    });
    handle.addEventListener('pointerup',event=>{
     const drag=pointer;
     if(!drag||drag.id!==event.pointerId)return;
     const moved=drag.moved;
     // Re-evaluate on release so a fast final pointer movement cannot use a stale target.
     const destination=dropAt(event.clientX,event.clientY,card)||
      (drag.target?{card:drag.target,before:drag.before}:null);
     const dx=event.clientX-drag.startX,dy=event.clientY-drag.startY;
     stopPointer();
     if(moved&&destination){reorder(card,destination.card,destination.before);return;}
     if(moved){
      if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches){
       card.animate([{transform:'translate3d('+dx+'px,'+dy+'px,0)',opacity:.9},{transform:'translate3d(0,0,0)',opacity:1}],
        {duration:220,easing:'cubic-bezier(.2,.8,.2,1)'});
      }
      announce('Widget returned to its original position.');
     }
    });
    handle.addEventListener('pointercancel',stopPointer);
    handle.addEventListener('keydown',event=>{
     if(event.key==='Escape'&&pointer){event.preventDefault();stopPointer();announce('Widget move cancelled.');}
    });
    handle.addEventListener('lostpointercapture',stopPointer);
    const width=document.createElement('button');
    width.type='button';width.textContent='□';width.title='Toggle widget width';
    width.setAttribute('aria-label','Toggle width of '+name);
    width.addEventListener('click',()=>{
     saved.wide=saved.wide.includes(id)?saved.wide.filter(value=>value!==id):[...saved.wide,id];
     render();persist();announce('Widget width saved.');
    });
    const hide=document.createElement('button');
    hide.type='button';hide.textContent='×';hide.title='Hide widget';
    hide.setAttribute('aria-label','Hide '+name);
    hide.addEventListener('click',()=>{
     saved.hidden=[...saved.hidden,id];render();persist();announce('Widget hidden; use Show to restore.');
    });
    controls.append(handle,width,hide);
    chrome.append(controls);
    card.prepend(chrome);
   }
   reset.addEventListener('click',()=>{
    saved=empty();render();persist();announce('Default widget layout restored.');
    menu.hidden=true;restoreToggle.setAttribute('aria-expanded','false');
   });
   render();
   if(endpoint){
    fetch(endpoint,{credentials:'same-origin',headers:{Accept:'application/json'}})
     .then(async response=>{
      if(!response.ok)throw Error('Unavailable');
      return response.json();
     }).then(result=>{
      if(revision)return;
      if(result.layout&&typeof result.layout==='object'){
       saved=clean(result.layout);cache();render();
      }else if(saved.order.length||saved.hidden.length||saved.wide.length){
       persist();
      }
     }).catch(()=>announce('Using browser layout; account sync unavailable.'));
   }
  }
 };
})();
