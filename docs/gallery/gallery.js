(() => {
  'use strict';
  const byId = id => document.getElementById(id);
  const gallery = byId('gallery'), role = byId('role'), viewport = byId('viewport');
  const box = byId('lightbox'), large = byId('large');
  let all = [], visible = [], active = -1, opener = null;
  const roles = new Set(['owner','contractor','client']);
  const screens = new Set(['desktop','tablet','mobile']);
  function valid(item) {
    return item && typeof item.src === 'string' &&
      /^images\/[a-zA-Z0-9/_-]+\.png$/.test(item.src) &&
      typeof item.title === 'string' && item.title.length <= 120 &&
      roles.has(item.role) && screens.has(item.viewport);
  }
  function render() {
    visible = all.filter(x => (role.value === 'all' || x.role === role.value) &&
      (viewport.value === 'all' || x.viewport === viewport.value));
    gallery.replaceChildren();
    visible.forEach((item,i) => {
      const tile = document.createElement('button');
      tile.className = 'tile'; tile.type = 'button';
      const img = document.createElement('img');
      img.src = item.src; img.alt = item.title; img.loading = 'lazy';
      const details = document.createElement('span'); details.className = 'details';
      const title = document.createElement('strong'); title.textContent = item.title;
      const meta = document.createElement('small');
      meta.textContent = item.role + ' · ' + item.viewport;
      details.append(title,meta);tile.append(img,details);
      tile.addEventListener('click',() => open(i,tile));
      gallery.append(tile);
    });
    byId('count').textContent = visible.length + ' screenshots';
    byId('empty').hidden = visible.length > 0;
  }
  function show() {
    if (active < 0 || !visible.length) return;
    const item = visible[active];
    large.src = item.src; large.alt = item.title;
    byId('lightbox-title').textContent = item.title;
    byId('lightbox-counter').textContent = (active+1) + ' / ' + visible.length;
    byId('prev').disabled = visible.length < 2;
    byId('next').disabled = visible.length < 2;
  }
  function open(i,el) {
    opener = el; active = i; show();box.hidden = false;
    document.body.style.overflow = 'hidden';byId('close').focus();
  }
  function close() {
    box.hidden = true;large.removeAttribute('src');
    document.body.style.overflow = '';active = -1;opener?.focus();
  }
  function move(step) {
    if (active < 0 || visible.length < 2) return;
    active = (active + step + visible.length) % visible.length;show();
  }
  role.addEventListener('change',render);viewport.addEventListener('change',render);
  byId('close').addEventListener('click',close);
  byId('prev').addEventListener('click',()=>move(-1));
  byId('next').addEventListener('click',()=>move(1));
  box.addEventListener('click',e=>{if(e.target === box)close();});
  document.addEventListener('keydown',e=>{
    if(box.hidden)return;
    if(e.key === 'Escape'){e.preventDefault();close();}
    if(e.key === 'ArrowLeft'){e.preventDefault();move(-1);}
    if(e.key === 'ArrowRight'){e.preventDefault();move(1);}
    if(e.key === 'Tab'){
      const controls = [byId('close'),byId('prev'),byId('next')].filter(x=>!x.disabled);
      const i = controls.indexOf(document.activeElement);
      if(e.shiftKey && i===0){e.preventDefault();controls[controls.length-1].focus();}
      else if(!e.shiftKey && i===controls.length-1){e.preventDefault();controls[0].focus();}
    }
  });
  fetch('./manifest.json',{cache:'no-store'})
    .then(r=>{if(!r.ok)throw new Error('Manifest unavailable');return r.json();})
    .then(data=>{all = Array.isArray(data.screenshots)?data.screenshots.filter(valid):[];render();})
    .catch(()=>{byId('empty').hidden=false;byId('empty').textContent='Gallery manifest could not be loaded.';});
})();
