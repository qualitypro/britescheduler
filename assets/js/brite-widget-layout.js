/* Brite dashboard widget layout v1: scoped, local-only persistence.
   No external drag dependency; handles avoid intercepting metric interactions. */
(function(){
 'use strict';
 window.BriteWidgetLayout={
  mount:function(grid,options){
   if(!grid)return;
   const key='brite.widgets.v1.'+options.scope;
   const cards=()=>Array.from(grid.querySelectorAll('[data-widget-id]'));
   const known=new Set(cards().map(c=>c.dataset.widgetId));
   let saved={order:[],hidden:[],wide:[]};
   try{
    const parsed=JSON.parse(localStorage.getItem(key)||'{}');
    if(parsed&&typeof parsed==='object'){
     for(const field of ['order','hidden','wide']){
      if(Array.isArray(parsed[field]))saved[field]=parsed[field].filter(id=>typeof id==='string'&&known.has(id)).slice(0,50);
     }
    }
   }catch(_e){}
   const persist=()=>{try{localStorage.setItem(key,JSON.stringify(saved))}catch(_e){}};
   const status=document.createElement('span');
   status.className='brite-widget-status';
   status.setAttribute('role','status');
   status.setAttribute('aria-live','polite');
   const toolbar=document.createElement('div');
   toolbar.className='brite-widget-toolbar';
   const customize=document.createElement('button');
   customize.type='button';customize.textContent='Customize widgets';
   customize.setAttribute('aria-pressed','false');
   const reset=document.createElement('button');
   reset.type='button';reset.textContent='Reset layout';
   reset.hidden=true;
   toolbar.append(customize,reset,status);
   grid.before(toolbar);
   let editing=false,dragging=null;
   const apply=()=>{
    const byId=new Map(cards().map(c=>[c.dataset.widgetId,c]));
    saved.order.forEach(id=>{if(byId.has(id))grid.append(byId.get(id))});
    cards().forEach(card=>{
     const id=card.dataset.widgetId;
     card.hidden=saved.hidden.includes(id);
     card.classList.toggle('brite-widget-wide',saved.wide.includes(id));
     card.draggable=editing;
     card.classList.toggle('brite-widget-editing',editing);
     const controls=card.querySelector('.brite-widget-controls');
     if(controls)controls.hidden=!editing;
    });
   };
   const move=(id,delta)=>{
    const visible=cards().filter(c=>!c.hidden);
    const index=visible.findIndex(c=>c.dataset.widgetId===id);
    const target=visible[index+delta];
    if(index<0||!target)return;
    const item=visible[index];
    if(delta<0)grid.insertBefore(item,target);
    else grid.insertBefore(item,target.nextSibling);
    saved.order=cards().map(c=>c.dataset.widgetId);
    persist();
    item.querySelector('.brite-widget-handle')?.focus();
    status.textContent='Widget position saved.';
   };
   cards().forEach(card=>{
    const id=card.dataset.widgetId;
    const controls=document.createElement('div');
    controls.className='brite-widget-controls';
    controls.hidden=true;
    const handle=document.createElement('button');
    handle.type='button';handle.className='brite-widget-handle';
    handle.textContent='↕ Move';
    handle.setAttribute('aria-label','Move '+(card.dataset.widgetLabel||id)+'; use Alt+Arrow keys');
    handle.addEventListener('keydown',e=>{
     if(!editing||!e.altKey||!['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(e.key))return;
     e.preventDefault();move(id,['ArrowLeft','ArrowUp'].includes(e.key)?-1:1);
    });
    const width=document.createElement('button');
    width.type='button';width.textContent='Resize';
    width.setAttribute('aria-label','Toggle width for '+(card.dataset.widgetLabel||id));
    width.addEventListener('click',()=>{
     saved.wide=saved.wide.includes(id)?saved.wide.filter(x=>x!==id):saved.wide.concat(id);
     persist();apply();
    });
    const toggle=document.createElement('button');
    toggle.type='button';toggle.textContent='Hide';
    toggle.setAttribute('aria-label','Hide '+(card.dataset.widgetLabel||id));
    toggle.addEventListener('click',()=>{
     saved.hidden=saved.hidden.includes(id)?saved.hidden.filter(x=>x!==id):saved.hidden.concat(id);
     persist();apply();status.textContent='Widget hidden. Use Reset layout to restore.';
    });
    controls.append(handle,width,toggle);
    card.prepend(controls);
    card.addEventListener('dragstart',e=>{
     if(!editing||!e.target.closest('.brite-widget-handle')){e.preventDefault();return}
     dragging=card;
     e.dataTransfer.effectAllowed='move';
     e.dataTransfer.setData('text/plain',id);
    });
    handle.addEventListener('dragstart',e=>{dragging=card;e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',id)});
    handle.draggable=true;
    card.addEventListener('dragover',e=>{if(!editing||!dragging||dragging===card)return;e.preventDefault();e.dataTransfer.dropEffect='move'});
    card.addEventListener('drop',e=>{
     if(!editing||!dragging||dragging===card)return;
     e.preventDefault();
     const before=e.clientX<card.getBoundingClientRect().left+card.offsetWidth/2;
     grid.insertBefore(dragging,before?card:card.nextSibling);
     saved.order=cards().map(c=>c.dataset.widgetId);
     persist();status.textContent='Widget position saved.';
     dragging=null;
    });
    card.addEventListener('dragend',()=>{dragging=null});
   });
   customize.addEventListener('click',()=>{
    editing=!editing;customize.setAttribute('aria-pressed',String(editing));
    customize.textContent=editing?'Done customizing':'Customize widgets';
    reset.hidden=!editing;apply();
   });
   reset.addEventListener('click',()=>{
    saved={order:[],hidden:[],wide:[]};
    try{localStorage.removeItem(key)}catch(_e){}
    const original=new Map(options.defaultOrder.map((id,i)=>[id,i]));
    cards().sort((a,b)=>(original.get(a.dataset.widgetId)??99)-(original.get(b.dataset.widgetId)??99)).forEach(c=>grid.append(c));
    apply();status.textContent='Default widget layout restored.';
   });
   apply();
  }
 };
})();
