/* Brite Scheduler: accessible directory accordion and inline record editor.
   The server remains the authority for tenant and role permissions. */
(() => {
'use strict';
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const schemas = {
  clients: {
    endpoint:'/api/clients.php', key:'clients',
    fields:[['first_name','First name','text',true],['last_name','Last name','text',true],
      ['company_name','Company'],['email','Email','email'],['phone','Phone','tel'],
      ['address1','Address'],['address2','Address line 2'],['city','City'],
      ['state','State'],['postal_code','ZIP / postal code'],['notes','Notes','textarea'],
      ['status','Status','status']]
  },
  contractors: {
    endpoint:'/api/contractors.php', key:'contractors',
    fields:[['first_name','First name','text',true],['last_name','Last name','text',true],
      ['business_name','Business'],['email','Email','email'],['phone','Phone','tel'],
      ['hourly_rate','Hourly rate','number'],['skills','Skills','textarea'],
      ['color','Color'],['status','Status','status']]
  }
};
const statusOptions=['active','inactive'];
function fieldMarkup(f,record) {
  const [name,label,type='text',required=false]=f;
  const value=esc(record[name]);
  const attr=`name="${name}" id="directory-${esc(record.id)}-${name}" ${required?'required':''}`;
  let control;
  if(type==='textarea') control=`<textarea ${attr} rows="3">${value}</textarea>`;
  else if(type==='status') control=`<select ${attr}>${statusOptions.map(s=>`<option value="${s}" ${record[name]===s?'selected':''}>${s}</option>`).join('')}</select>`;
  else control=`<input ${attr} type="${type}" value="${value}" ${type==='number'?'min="0" step="0.01"':''}>`;
  return `<div class="brite-directory-field"><label for="directory-${esc(record.id)}-${name}">${label}</label>${control}</div>`;
}
function renderRecord(record,schema,type) {
  const details=document.createElement('details');
  details.className='brite-directory-record';
  details.dataset.recordId=String(record.id);
  const name=[record.first_name,record.last_name].filter(Boolean).join(' ')||'Unnamed record';
  const organization=type==='clients'?record.company_name:record.business_name;
  const summary=document.createElement('summary');
  summary.className='brite-directory-summary';
  const label=document.createElement('span');
  label.className='brite-directory-summary-main';
  const strong=document.createElement('strong');strong.textContent=name;label.append(strong);
  const sub=document.createElement('small');
  sub.textContent=[organization,record.email,record.phone].filter(Boolean).join(' · ');
  label.append(sub);summary.append(label);
  const state=document.createElement('span');state.className='brite-directory-state';state.textContent=record.status||'Unknown';
  summary.append(state);
  details.append(summary);
  const form=document.createElement('form');
  form.className='brite-directory-form';
  form.innerHTML=`<div class="brite-directory-fields">${schema.fields.map(f=>fieldMarkup(f,record)).join('')}</div>
  <div class="brite-directory-actions"><button type="submit">Save changes</button><button type="reset">Reset</button><span class="brite-directory-feedback" role="status" aria-live="polite"></span></div>`;
  const feedback=form.querySelector('.brite-directory-feedback');
  form.addEventListener('submit',async event=>{
    event.preventDefault();
    const button=form.querySelector('[type="submit"]');
    button.disabled=true;feedback.textContent='Saving…';
    try {
      const payload=Object.fromEntries(new FormData(form).entries());
      payload.id=Number(record.id);
      const response=await api(schema.endpoint,{method:'PUT',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      if(response?.error) throw Error(response.error);
      Object.assign(record,payload);
      strong.textContent=[record.first_name,record.last_name].filter(Boolean).join(' ');
      sub.textContent=[type==='clients'?record.company_name:record.business_name,record.email,record.phone].filter(Boolean).join(' · ');
      state.textContent=record.status||'Unknown';
      feedback.textContent='Changes saved';
    } catch(error) {feedback.textContent='Save failed: '+error.message;}
    finally {button.disabled=false;}
  });
  details.append(form);
  return details;
}
async function mount(type) {
  const root=document.querySelector('[data-directory="'+type+'"]');
  if(!root) return;
  const schema=schemas[type];
  const list=root.querySelector('[data-directory-list]');
  const status=root.querySelector('[data-directory-status]');
  const reload=async()=>{
    status.textContent='Loading records…';
    try {
      const data=await api(schema.endpoint);
      const records=Array.isArray(data[schema.key])?data[schema.key]:[];
      list.replaceChildren(...records.map(record=>renderRecord(record,schema,type)));
      if(!records.length){const empty=document.createElement('p');empty.textContent='No records found.';list.append(empty);}
      status.textContent=records.length+' record(s)';
    }catch(error){status.textContent='Unable to load records: '+error.message;}
  };
  root.querySelector('[data-directory-refresh]')?.addEventListener('click',reload);
  root.querySelector('[data-directory-create]')?.addEventListener('submit',async event=>{
    event.preventDefault();
    const form=event.currentTarget,button=form.querySelector('[type="submit"]');
    button.disabled=true;status.textContent='Creating record…';
    try {
      await api(schema.endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.fromEntries(new FormData(form)))});
      form.reset();await reload();
    }catch(error){status.textContent='Create failed: '+error.message;}
    finally{button.disabled=false;}
  });
  await reload();
}
document.addEventListener('DOMContentLoaded',()=>{mount('clients');mount('contractors');});
})();
