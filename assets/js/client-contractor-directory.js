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
 const el=(tag,text,cls)=>{const n=document.createElement(tag);if(text!==undefined)n.textContent=String(text);if(cls)n.className=cls;return n;};
 const title=c=>c.business_name||[c.first_name,c.last_name].filter(Boolean).join(' ')||'Contractor';
 const money=n=>n===null||n===undefined||n===''?'Contact for pricing':new Intl.NumberFormat('en-US',{style:'currency',currency:'USD'}).format(Number(n));
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
  detail.replaceChildren();
  detail.append(el('h2',title(c)),el('p',c.headline||c.specialties||'Network contractor','muted'));
  if(c.biography)detail.append(el('p',c.biography));
  if(c.years_experience!==null&&c.years_experience!==undefined)detail.append(el('p',c.years_experience+' years of experience'));
  if(c.skills)detail.append(el('p','Skills: '+c.skills));
  detail.append(el('h3','Services and pricing'));
  if(!c.services.length)detail.append(el('p','No services listed yet.','muted'));
  c.services.forEach(s=>detail.append(el('p',s.name+' — '+(s.rate!==null&&s.rate!==undefined?money(s.rate)+(s.pricing_model==='hourly'?'/hour':' flat rate'):money(null)))));
  detail.append(el('h3','Service areas'));
  if(!c.service_areas.length)detail.append(el('p','Service areas not listed.','muted'));
  c.service_areas.forEach(a=>detail.append(el('p',a.label+(a.area_type==='zip'&&a.postal_code?' ('+a.postal_code+')':''))));
  detail.append(el('h3','Work portfolio'));
  if(c.photos.length)detail.append(photoGrid(c,20));else detail.append(el('p','No portfolio photos yet.','muted'));
  if(c.portfolio_url){
   try{const u=new URL(c.portfolio_url);if(u.protocol==='https:'){const link=el('a','Visit portfolio website');link.href=u.href;link.target='_blank';link.rel='noopener noreferrer';detail.append(link);}}catch(_){}
  }
  detail.append(el('p','Quote requests will be available in Cycle 71D.','muted'));
  if(typeof dialog.showModal==='function')dialog.showModal();else dialog.setAttribute('open','');
 }
 function render(){
  results.replaceChildren();
  if(!contractors.length){results.append(el('p','No matching contractors found. Try clearing the filters.'));return;}
  contractors.forEach(c=>{
   const card=el('article',undefined,'card');card.style.minWidth='0';
   card.append(el('h2',title(c)),el('p',c.headline||c.specialties||'Network contractor','muted'));
   if(c.photos.length)card.append(photoGrid(c,1));
   const services=c.services.map(s=>s.name).slice(0,4).join(', ');
   if(services)card.append(el('p','Services: '+services));
   const btn=el('button','View profile and photos');btn.type='button';btn.addEventListener('click',()=>show(c));card.append(btn);
   results.append(card);
  });
 }
 async function load(){
  const data=new FormData(form),query=new URLSearchParams();
  for(const [k,v] of data)if(String(v).trim())query.set(k,String(v).trim());
  status.textContent='Loading contractors...';
  try{
   const r=await fetch(base+'?'+query.toString(),{credentials:'same-origin',headers:{Accept:'application/json'}});
   const d=await r.json();if(!r.ok)throw Error(d.error||'Unable to load directory');
   contractors=d.contractors||[];
   const select=form.elements.namedItem('service_id'),previous=select.value;
   select.replaceChildren(new Option('All services',''));
   (d.services||[]).forEach(s=>select.add(new Option(s.name,s.id)));
   select.value=previous;
   status.textContent=contractors.length+' contractor'+(contractors.length===1?'':'s')+' found'+(contractors.length===100?' (first 100 results)':'')+'.';
   render();
  }catch(e){status.textContent=e.message;results.replaceChildren();}
 }
 form.addEventListener('submit',e=>{e.preventDefault();load();});
 form.addEventListener('reset',()=>{setTimeout(load,0);});
 document.getElementById('directoryClose').addEventListener('click',()=>{if(dialog.close)dialog.close();else dialog.removeAttribute('open');});
 load();
})();
