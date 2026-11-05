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
   let saved=empty(),revision=0,queue=Promise.resolve(),editing=false,pointer=null;
   try{saved=clean(JSON.parse(localStorage.getItem(storageKey)||'null'))}catch(_e){}
   const cards=()=>Array.from(grid.querySelectorAll(':scope > [data-widget-id]'));
   const toolbar=document.createElement('div');
   toolbar.className='brite-widget-toolbar';
   const customize=document.createElement('button');
   customize.type='button';customize.textContent='Customize widgets';customize.setAttribute('aria-pressed','false');
   const reset=document.createElement('button');
   reset.type='button';reset.textContent='Reset layout';reset.hidden=true;
   const restore=document.createElement('div');
   restore.className='brite-widget-restore';restore.hidden=true;
   const status=document.createElement('span');
   status.className='brite-widget-status';status.setAttribute('role','status');status.setAttribute('aria-live','polite');
   toolbar.append(customize,reset,restore,status);
   grid.before(toolbar);
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
     card.classList.toggle('brite-widget-editing',editing);
     card.draggable=false;
     const controls=card.querySelector(':scope > .brite-widget-controls');
     if(controls)controls.hidden=!editing;
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
    restore.hidden=!editing||saved.hidden.length===0;
    notify();
   };
   const reorder=(source,target,before)=>{
    if(!source||!target||source===target||source.hidden||target.hidden)return;
    const ordered=cards().map(card=>card.dataset.widgetId).filter(id=>id!==source.dataset.widgetId);
    const index=ordered.indexOf(target.dataset.widgetId);
    ordered.splice(index+(before?0:1),0,source.dataset.widgetId);
    saved.order=ordered;
    render();persist();announce('Widget order saved.');
    source.querySelector('.brite-widget-handle')?.focus({preventScroll:true});
   };
   const move=(card,direction)=>{
    const visible=cards().filter(item=>!item.hidden);
    const index=visible.indexOf(card);
    const target=visible[index+direction];
    if(target)reorder(card,target,direction<0);
   };
   const stopPointer=()=>{
    if(!pointer)return;
    pointer.handle.classList.remove('brite-widget-dragging');
    pointer=null;
   };
   for(const card of defaultCards){
    const id=card.dataset.widgetId;
    const name=card.dataset.widgetLabel||id;
    const controls=document.createElement('div');
    controls.className='brite-widget-controls';controls.hidden=true;
    const handle=document.createElement('button');
    handle.type='button';handle.className='brite-widget-handle';
    handle.textContent='↕ Move';
    handle.setAttribute('aria-label','Move '+name+'; Alt and arrow keys to reorder');
    handle.addEventListener('keydown',event=>{
     if(!editing||!event.altKey)return;
     if(['ArrowLeft','ArrowUp','ArrowRight','ArrowDown'].includes(event.key)){
      event.preventDefault();move(card,['ArrowLeft','ArrowUp'].includes(event.key)?-1:1);
     }
    });
    // Pointer Events support mouse, pen, and touch without dragging the card's content.
    handle.addEventListener('pointerdown',event=>{
     if(!editing||event.button!==0)return;
     pointer={handle,card,id:event.pointerId,startX:event.clientX,startY:event.clientY,moved:false};
     handle.setPointerCapture(event.pointerId);
    });
    handle.addEventListener('pointermove',event=>{
     if(!pointer||pointer.id!==event.pointerId)return;
     if(Math.hypot(event.clientX-pointer.startX,event.clientY-pointer.startY)<8&&!pointer.moved)return;
     pointer.moved=true;handle.classList.add('brite-widget-dragging');
    });
    handle.addEventListener('pointerup',event=>{
     if(!pointer||pointer.id!==event.pointerId)return;
     const wasMoved=pointer.moved;
     stopPointer();
     if(!wasMoved)return;
     const hovered=document.elementFromPoint(event.clientX,event.clientY)?.closest('[data-widget-id]');
     if(!hovered||hovered.parentElement!==grid||hovered===card||hovered.hidden)return;
     const bounds=hovered.getBoundingClientRect();
     const before=event.clientY<bounds.top+bounds.height/2;
     reorder(card,hovered,before);
    });
    handle.addEventListener('pointercancel',stopPointer);
    handle.addEventListener('lostpointercapture',stopPointer);
    const width=document.createElement('button');
    width.type='button';width.textContent='Resize';
    width.setAttribute('aria-label','Toggle width of '+name);
    width.addEventListener('click',()=>{
     saved.wide=saved.wide.includes(id)?saved.wide.filter(value=>value!==id):[...saved.wide,id];
     render();persist();announce('Widget width saved.');
    });
    const hide=document.createElement('button');
    hide.type='button';hide.textContent='Hide';
    hide.setAttribute('aria-label','Hide '+name);
    hide.addEventListener('click',()=>{
     saved.hidden=[...saved.hidden,id];render();persist();announce('Widget hidden; use Show to restore.');
    });
    controls.append(handle,width,hide);card.prepend(controls);
   }
   customize.addEventListener('click',()=>{
    editing=!editing;
    customize.textContent=editing?'Done customizing':'Customize widgets';
    customize.setAttribute('aria-pressed',String(editing));
    reset.hidden=!editing;
    render();
   });
   reset.addEventListener('click',()=>{
    saved=empty();render();persist();announce('Default widget layout restored.');
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
