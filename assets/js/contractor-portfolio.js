(() => {
 'use strict';
 const input=document.getElementById('portfolioUpload');
 const gallery=document.getElementById('portfolioGallery');
 const status=document.getElementById('portfolioStatus');
 if (!input || !gallery) return;
 const url=(window.BRITE_BASE||'')+'/api/contractor_portfolio.php';
 const say=s=>{status.textContent=s;};
 const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function load() {
  const r=await fetch(url,{credentials:'same-origin'});
  const d=await r.json();
  if (!r.ok) throw Error(d.error||'Unable to load portfolio');
  gallery.replaceChildren();
  d.photos.forEach((p,i)=>{
   const card=document.createElement('div');
   card.style.cssText='border:1px solid #cbd5e1;border-radius:8px;padding:8px;min-width:0';
   const img=document.createElement('img');
   img.src=p.url;img.alt='Portfolio photo '+(i+1);img.loading='lazy';
   img.style.cssText='width:100%;height:140px;object-fit:cover;border-radius:6px';
   const btn=document.createElement('button');btn.type='button';btn.textContent='Remove photo';btn.dataset.britePills='off';
   btn.addEventListener('click',async()=>{
    if (!confirm('Remove this portfolio photo?')) return;
    btn.disabled=true;
    try {
     const r=await fetch(url,{method:'DELETE',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':window.BRITE_CSRF},body:JSON.stringify({id:p.id})});
     const d=await r.json();if (!r.ok) throw Error(d.error||'Delete failed');
     await load();
    } catch(e){say(e.message);btn.disabled=false;}
   });
   card.append(img,btn);gallery.append(card);
  });
  say(d.photos.length+' of 20 portfolio photos uploaded.');
  input.disabled=d.photos.length>=20;
  return d.photos.length;
 }
 input.addEventListener('change',async()=>{
  const files=Array.from(input.files||[]);
  if (!files.length) return;
  input.disabled=true;
  try {
   const count=await load();
   if (count+files.length>20) throw Error('You can upload only '+(20-count)+' more photos.');
   for (const file of files) {
    if (!/\.(jpe?g|png)$/i.test(file.name) || !['image/jpeg','image/png'].includes(file.type) || file.size>5*1024*1024) throw Error('Use JPG/PNG images up to 5 MB each.');
    say('Uploading '+esc(file.name)+'...');
    const body=new FormData();body.append('photo',file);
    const r=await fetch(url,{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':window.BRITE_CSRF},body});
    const d=await r.json();if (!r.ok) throw Error(d.error||'Upload failed');
   }
   await load();
  } catch(e){say(e.message);}
  finally {input.value='';await load().catch(e=>{input.disabled=false;say(e.message);});}
 });
 load().catch(e=>say(e.message));
})();
