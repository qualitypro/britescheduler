(()=>{
'use strict';
const list=document.getElementById('briteModuleList'),status=document.getElementById('briteModuleStatus');
if(!list||!status)return;
const base=window.BRITE_BASE||'';
const roleLabels={owner:'Owner',admin:'Admin',scheduler:'Scheduler',accounting:'Accounting',contractor:'Contractor',client:'Client'};
const node=(tag,text)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=String(text);return e;};
async function request(data){
 const response=await fetch(base+'/api/tenant_modules.php',{
  method:'POST',credentials:'same-origin',
  headers:{'X-CSRF-Token':window.BRITE_CSRF,Accept:'application/json'},body:data
 });
 const result=await response.json();
 if(!response.ok)throw Error(result.error||'Unable to save module settings');
 return result;
}
async function load(){
 status.textContent='Loading installed modules…';
 try{
  const response=await fetch(base+'/api/tenant_modules.php',{credentials:'same-origin',headers:{Accept:'application/json'}});
  const result=await response.json();
  if(!response.ok)throw Error(result.error||'Unable to load modules');
  list.replaceChildren();
  if(!result.modules.length){status.textContent='No modules installed. Place a trusted module in the modules directory.';return;}
  for(const mod of result.modules){
   const card=node('section');card.className='card';card.style.cssText='display:grid;gap:14px';
   const header=node('div');header.style.cssText='display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px';
   const details=node('div');details.append(node('h3',mod.name+' · v'+mod.version),node('p',mod.description||'No description'));
   const label=node('label');label.style.cssText='display:flex;align-items:center;gap:9px;font-weight:650';
   const checkbox=node('input');checkbox.type='checkbox';checkbox.checked=!!mod.enabled;
   checkbox.setAttribute('aria-label','Activate '+mod.name);checkbox.style.cssText='width:20px;height:20px';
   label.append(checkbox,node('span','Active'));
   header.append(details,label);
   const heading=node('strong','Role access');
   const help=node('p','Choose which tenant roles may open this module. Access is enforced on the server.');
   help.className='muted';
   const roleGrid=node('div');roleGrid.style.cssText='display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:10px';
   const inputs=[];
   for(const role of Object.keys(roleLabels)){
    const item=node('label');item.style.cssText='display:flex;align-items:center;gap:9px;padding:10px;border:1px solid #8994a455;border-radius:8px';
    const input=node('input');input.type='checkbox';input.value=role;
    input.checked=(mod.allowed_roles||[]).includes(role);
    input.disabled=!mod.enabled||!(mod.supported_roles||[]).includes(role);
    if(!(mod.supported_roles||[]).includes(role))item.title='Not supported by this module';
    item.append(input,node('span',roleLabels[role]));roleGrid.append(item);inputs.push(input);
   }
   const actions=node('div');actions.style.cssText='display:flex;align-items:center;gap:12px;flex-wrap:wrap';
   const feedback=node('span');feedback.setAttribute('role','status');feedback.setAttribute('aria-live','polite');
   actions.append(feedback);
   const syncControls=busy=>{
    checkbox.disabled=busy;
    for(const input of inputs)input.disabled=busy||!mod.enabled||!(mod.supported_roles||[]).includes(input.value);
   };
   for(const input of inputs)input.addEventListener('change',async()=>{
    // Lock the module's controls until the full role set is persisted.
    // This prevents overlapping requests from overwriting a newer selection.
    const previous=[...(mod.allowed_roles||[])];
    const selected=inputs.filter(item=>item.checked).map(item=>item.value);
    syncControls(true);
    feedback.textContent='Saving role permissions…';
    const data=new FormData();data.set('action','permissions');data.set('module_key',mod.key);
    for(const role of selected)data.append('roles[]',role);
    try{
     const saved=await request(data);
     mod.allowed_roles=saved.allowed_roles;
     feedback.textContent='Permissions saved automatically. Reload to update navigation.';
    }catch(error){
     for(const item of inputs)item.checked=previous.includes(item.value);
     feedback.textContent='Unable to save permissions: '+error.message;
    }finally{syncControls(false);}
   });
   checkbox.addEventListener('change',async()=>{
    checkbox.disabled=true;feedback.textContent='Updating activation…';
    const data=new FormData();data.set('action','activation');
    data.set('module_key',mod.key);data.set('enabled',checkbox.checked?'1':'0');
    try{
     const saved=await request(data);
     mod.enabled=saved.enabled;
     feedback.textContent=mod.name+(saved.enabled?' activated.':' deactivated.')+' Reload to update navigation.';
     // Refresh persisted role grants (including default owner on first activation).
     await load();
    }catch(error){
     checkbox.checked=!checkbox.checked;feedback.textContent='Update failed: '+error.message;
    }finally{checkbox.disabled=false;}
   });
   card.append(header,heading,help,roleGrid,actions);list.append(card);
  }
  status.textContent=result.modules.length+' installed module'+(result.modules.length===1?'':'s')+'.';
 }catch(error){status.textContent='Module settings unavailable: '+error.message;}
}
load();
})();