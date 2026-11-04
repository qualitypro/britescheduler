(()=>{
'use strict';
const list=document.getElementById('briteModuleList'),status=document.getElementById('briteModuleStatus');
if(!list||!status)return;
const base=window.BRITE_BASE||'';
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);return e;};
async function load(){
 status.textContent='Loading installed modules…';
 try{
  const response=await fetch(base+'/api/tenant_modules.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
  const result=await response.json();if(!response.ok)throw Error(result.error||'Unable to load modules');
  list.replaceChildren();
  if(!result.modules.length){status.textContent='No modules installed. Place a trusted module in the modules directory.';return;}
  for(const mod of result.modules){
   const card=node('div');card.className='card';card.style.cssText='display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px';
   const details=node('div');details.append(node('strong',mod.name+' · v'+mod.version),node('p',mod.description||'No description'));
   const label=node('label');label.style.cssText='display:flex;align-items:center;gap:9px;font-weight:650';
   const checkbox=node('input');checkbox.type='checkbox';checkbox.checked=!!mod.enabled;checkbox.setAttribute('aria-label','Activate '+mod.name);checkbox.style.cssText='width:20px;height:20px';
   label.append(checkbox,node('span','Active'));
   checkbox.addEventListener('change',async()=>{
    checkbox.disabled=true;status.textContent='Updating '+mod.name+'…';
    const data=new FormData();data.set('module_key',mod.key);data.set('enabled',checkbox.checked?'1':'0');
    try{
     const response=await fetch(base+'/api/tenant_modules.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.BRITE_CSRF,Accept:'application/json'},body:data});
     const result=await response.json();if(!response.ok)throw Error(result.error||'Unable to update module');
     status.textContent=mod.name+(result.enabled?' activated.':' deactivated.')+' Reload the page to update the main menu.';
    }catch(error){checkbox.checked=!checkbox.checked;status.textContent='Unable to update '+mod.name+': '+error.message;}
    finally{checkbox.disabled=false;}
   });
   card.append(details,label);list.append(card);
  }
  status.textContent=result.modules.length+' installed module'+(result.modules.length===1?'':'s')+'.';
 }catch(error){status.textContent='Module settings unavailable: '+error.message;}
}
load();
})();