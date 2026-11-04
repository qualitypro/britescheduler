(() => {
 'use strict';
 const form=document.getElementById('directoryFilters');
 if(!form)return;
 const results=document.getElementById('directoryResults');
 const status=document.getElementById('directoryStatus');
 const dialog=document.getElementById('contractorDetails');
 const detail=document.getElementById('directoryDetailBody');
 const base=(window.BRITE_BASE||'')+'/api/client_contractor_directory.php';
 let contractors=[];
 let requestVersion=0;
 let profileTrigger=null;
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);if(cls)n.className=cls;return n;};
 const title=c=>c.business_name||[c.first_name,c.last_name].filter(Boolean).join(' ')||'Contractor';
 const money=n=>n===null||n===undefined||n===''||!Number.isFinite(Number(n))?'Contact for pricing':new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(n));
 const quoteUrl=c=>(window.BRITE_BASE||'')+'/client-quote-request.php?contractor_id='+encodeURIComponent(c.contractor_id);
 const rate=s=>s.rate!==null&&s.rate!==undefined?money(s.rate)+(s.pricing_model==='hourly'?'/hour':' flat rate'):'Price on request';
 function photoGrid(c,limit){
  const grid=el('div');grid.style.cssText='display:grid;grid-template-columns:repeat(auto-fill,minmax(125px,1fr));gap:10px;margin:12px 0';
  (c.photos||[]).slice(0,limit).forEach(p=>{
   const img=document.createElement('img');img.src=p.url;img.alt='Portfolio example for '+title(c);
   img.loading='lazy';img.style.cssText='width:100%;height:120px;object-fit:cover;border-radius:9px';
   grid.append(img);
  });
  return grid;
 }
 function show(c){
  profileTrigger=document.activeElement;
  detail.replaceChildren();
  detail.append(el('h2',title(c)),el('p',c.headline||c.specialties||'Network contractor','muted'));
  if(c.biography)detail.append(el('p',c.biography));
  if(c.years_experience!==null&&c.years_experience!==undefined)detail.append(el('p',c.years_experience+' years of experience'));
  if(c.skills)detail.append(el('p','Skills: '+c.skills));
  detail.append(el('h3','Services and pricing'));
  if(!(c.services||[]).length)detail.append(el('p','No services listed yet.','muted'));
  (c.services||[]).forEach(s=>detail.append(el('p',s.name+' — '+rate(s))));
  detail.append(el('h3','Service areas'));
  if(!(c.service_areas||[]).length)detail.append(el('p','Service areas not listed.','muted'));
  (c.service_areas||[]).forEach(a=>detail.append(el('p',a.label+(a.area_type==='zip'&&a.postal_code?' ('+a.postal_code+')':''))));
  detail.append(el('h3','Work portfolio'));
  if((c.photos||[]).length)detail.append(photoGrid(c,20));else detail.append(el('p','No portfolio photos yet.','muted'));
  if(c.portfolio_url){
   try{const u=new URL(c.portfolio_url);if(u.protocol==='https:'){const link=el('a','Visit portfolio website');link.href=u.href;link.target='_blank';link.rel='noopener noreferrer';detail.append(link);}}catch(_){}
  }
  const quote=el('a','Request a quote from this contractor');quote.href=quoteUrl(c);quote.style.cssText='display:inline-block;padding:10px 14px;margin-top:12px;background:#175cd3;color:white;border-radius:8px;font-weight:700;text-decoration:none';detail.append(quote);
  if(typeof dialog.showModal==='function'&&!dialog.open)dialog.showModal();else dialog.setAttribute('open','');
  document.getElementById('directoryClose')?.focus();
 }
 function render(){
  results.replaceChildren();
  if(!contractors.length){
   const empty=el('div',undefined,'card');empty.style.gridColumn='1 / -1';
   empty.append(el('h2','No matching contractors'),el('p','Try a different service, search term, or ZIP code.','muted'));
   const clear=el('button','Clear filters');clear.type='button';clear.addEventListener('click',()=>{form.reset();load();});empty.append(clear);results.append(empty);return;
  }
  contractors.forEach(c=>{
   const card=el('article',undefined,'card');card.style.cssText='min-width:0;display:flex;flex-direction:column;gap:10px';
   const heading=el('h2',title(c));heading.style.margin='0';
   const tagline=el('p',c.headline||c.specialties||'Available network contractor','muted');tagline.style.margin='0';
   card.append(heading,tagline);
   if((c.photos||[]).length)card.append(photoGrid(c,1));
   const services=(c.services||[]).slice(0,3);
   if(services.length){
    const list=el('div');list.style.cssText='display:grid;gap:5px';
    services.forEach(s=>list.append(el('div',s.name+' · '+rate(s))));
    card.append(list);
    if(c.services.length>3)card.append(el('p','+'+(c.services.length-3)+' more services','muted'));
   }else card.append(el('p','Services and rates available on request.','muted'));
   if((c.service_areas||[]).length)card.append(el('p','Service areas: '+c.service_areas.slice(0,2).map(a=>a.label||a.postal_code).filter(Boolean).join(', '),'muted'));
   const actions=el('div');actions.style.cssText='display:flex;flex-wrap:wrap;gap:10px;margin-top:auto;padding-top:10px';
   const profile=el('button','View profile');profile.type='button';profile.addEventListener('click',()=>show(c));
   const quote=el('a','Request quote');quote.href=quoteUrl(c);quote.style.cssText='display:inline-flex;align-items:center;padding:9px 14px;border-radius:8px;background:#175cd3;color:white;text-decoration:none;font-weight:700';
   actions.append(profile,quote);card.append(actions);results.append(card);
  });
 }
 async function load(){
  const version=++requestVersion;
  const data=new FormData(form),query=new URLSearchParams();
  for(const [k,v] of data)if(String(v).trim())query.set(k,String(v).trim());
  status.textContent='Loading contractors...';
  try{
   const r=await fetch(base+'?'+query.toString(),{credentials:'same-origin',headers:{Accept:'application/json'}});
   const d=await r.json();if(version!==requestVersion)return;if(!r.ok)throw Error(d.error||'Unable to load directory');
   contractors=d.contractors||[];
   const select=form.elements.namedItem('service_id'),previous=select.value;
   select.replaceChildren(new Option('All services',''));
   (d.services||[]).forEach(s=>select.add(new Option(s.name,s.id)));
   select.value=previous;
   status.textContent=contractors.length+' contractor'+(contractors.length===1?'':'s')+' found'+(contractors.length===100?' (first 100 results)':'')+'.';
   render();
  }catch(e){if(version!==requestVersion)return;status.textContent='Could not load contractors: '+e.message;results.replaceChildren();const retry=el('button','Try again');retry.type='button';retry.addEventListener('click',load);results.append(retry);}
 }
 form.addEventListener('submit',e=>{e.preventDefault();load();});
 form.addEventListener('reset',()=>{setTimeout(load,0);});
 form.elements.namedItem('postal_code')?.addEventListener('input',event=>{event.target.setCustomValidity('');});
 document.getElementById('directoryClose').addEventListener('click',()=>{if(dialog.close)dialog.close();else {dialog.removeAttribute('open');profileTrigger?.focus();}});
 dialog.addEventListener('close',()=>{if(profileTrigger?.isConnected)profileTrigger.focus();});
 dialog.addEventListener('click',event=>{if(event.target===dialog&&dialog.close)dialog.close();});
 load();
})();
