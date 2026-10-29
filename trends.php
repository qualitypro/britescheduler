<?php
require_once __DIR__.'/app/bootstrap.php';
Auth::requireUser();
Auth::requireRole('owner','admin','scheduler','accounting');
$pageTitle='Trends';
require __DIR__.'/partials/app_header.php';
?>
<style>
.trends-controls{display:flex;flex-wrap:wrap;gap:12px;align-items:end;margin:16px 0}
.trends-controls label{display:grid;gap:5px;font-weight:600}
.trends-controls select,.trends-controls input{min-width:140px}
.trends-legend{display:flex;flex-wrap:wrap;gap:12px;margin:10px 0}
.trends-legend label{display:flex;align-items:center;gap:6px}
.trends-legend input{min-width:0}
.trends-chart{width:100%;height:360px;display:block}
.trends-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:12px;margin:14px 0}
.trends-stats strong{display:block;font-size:1.6rem}
.trends-plot{overflow-x:auto}
.trends-plot svg{min-width:600px}
</style>
<h1>Booking & Scheduling Trends</h1>
<p class="muted">A rolling three-month window: previous month, selected month, and following month. Dates reflect scheduled service dates in the tenant calendar.</p>
<section class="card" aria-label="Trend filters">
 <div class="trends-controls">
 <label>Center month <input type="month" id="trendMonth" min="2020-01" max="2099-12"></label>
 <label>Appointment status <select id="trendStatus"><option value="all">All statuses</option><option>tentative</option><option>scheduled</option><option>confirmed</option><option>in_progress</option><option>completed</option><option>cancelled</option><option>no_show</option></select></label>
 <label>Time grouping <select id="trendGroup"><option value="day">Daily</option><option value="week">Weekly</option><option value="month">Monthly</option></select></label>
 <label>Chart style <select id="trendStyle"><option value="line">Lines</option><option value="bar">Bars</option></select></label>
 <button type="button" id="trendPrev">Previous month</button><button type="button" id="trendNext">Next month</button>
 </div>
 <div class="trends-legend" id="trendLegend"></div>
 <p id="trendStatusText" role="status" aria-live="polite">Loading trends…</p>
 <div class="trends-stats" id="trendStats"></div>
 <div class="trends-plot"><svg class="trends-chart" id="trendChart" viewBox="0 0 980 360" role="img" aria-label="Booking and scheduling trends chart"></svg></div>
 <p class="muted">X: service date · Y: number of records · Series: appointments, booking requests, assigned/unassigned appointments, and accepted/declined contractor assignments. Accepted and declined are current contractor assignment states, counted by appointment service date (not response date). A single appointment may have multiple contractor decisions; series should not be added together. Gross booked value uses non-void invoice totals when linked to appointments, otherwise service list prices as estimates; it excludes cancelled and no-show appointments. It is not cash collected. Gold series uses its own dollar scale.</p>
</section>
<script>
(()=>{
const keys={appointments:['Appointments','#2563eb'],requests:['Booking requests','#d97706'],assigned:['Assigned','#059669'],unassigned:['Unassigned','#9333ea'],accepted:['Accepted assignments','#0d9488'],declined:['Declined assignments','#dc2626'],gross_revenue:['Gross booked value (USD)','#ca8a04']};
const visible=new Set(Object.keys(keys));
const $=id=>document.getElementById(id);
const month=$('trendMonth'), status=$('trendStatus'), group=$('trendGroup'), style=$('trendStyle');
month.value=new Date().toLocaleDateString('en-CA',{year:'numeric',month:'2-digit'}).slice(0,7);
let days={};
for(const [key,[label,color]] of Object.entries(keys)){
 const wrapper=document.createElement('label'),input=document.createElement('input');
 input.type='checkbox';input.checked=true;input.addEventListener('change',()=>{input.checked?visible.add(key):visible.delete(key);render()});
 wrapper.append(input,document.createTextNode(label));wrapper.style.color=color;$('trendLegend').append(wrapper);
}
const svgNS='http://www.w3.org/2000/svg';
function node(tag,attrs={},text){const n=document.createElementNS(svgNS,tag);for(const [k,v] of Object.entries(attrs))n.setAttribute(k,String(v));if(text!==undefined)n.textContent=String(text);return n;}
function buckets(){
 const result=new Map();
 for(const [day,values] of Object.entries(days)){
  const date=new Date(day+'T12:00:00Z');
  let key=day;
  if(group.value==='month')key=day.slice(0,7);
  if(group.value==='week'){const weekday=date.getUTCDay();date.setUTCDate(date.getUTCDate()-((weekday+6)%7));key=date.toISOString().slice(0,10);}
  if(!result.has(key))result.set(key,Object.fromEntries(Object.keys(keys).map(k=>[k,0])));
  for(const k of Object.keys(keys))result.get(key)[k]+=Number(values[k]||0);
 }
 return [...result].sort((a,b)=>a[0].localeCompare(b[0]));
}
function render(){
 const data=buckets(),svg=$('trendChart');svg.replaceChildren();
 const width=980,height=360,left=56,right=20,top=20,bottom=54,w=width-left-right,h=height-top-bottom;
 const max=Math.max(1,...data.flatMap(([,v])=>[...visible].filter(k=>k!=='gross_revenue').map(k=>v[k])));
 const revenueMax=Math.max(1,...data.map(([,v])=>v.gross_revenue));
 const y=v=>top+h-(v/max)*h;
 const yr=v=>top+h-(v/revenueMax)*h;
 for(let i=0;i<=4;i++){
  const val=max*i/4,py=y(val);
  svg.append(node('line',{x1:left,y1:py,x2:width-right,y2:py,stroke:'currentColor','stroke-opacity':'.17'}));
  svg.append(node('text',{x:left-8,y:py+4,'text-anchor':'end',fill:'currentColor','font-size':12},Number(val.toFixed(1))));
 }
 if(visible.has('gross_revenue'))for(let i=0;i<=4;i++){const val=revenueMax*i/4;svg.append(node('text',{x:width-right+3,y:yr(val)+4,'text-anchor':'end',fill:'#ca8a04','font-size':11},'
 const tick=Math.max(1,Math.ceil(data.length/9));
 data.forEach(([label],i)=>{if(i%tick===0||i===data.length-1)svg.append(node('text',{x:left+i*step,y:height-23,'text-anchor':'middle',fill:'currentColor','font-size':11},label.slice(5)))});
 for(const key of visible){
  const [name,color]=keys[key];
  const axis=key==='gross_revenue'?yr:y;
  if(style.value==='line'){
   const pts=data.map(([,v],i)=>(left+i*step)+','+axis(v[key])).join(' ');
   svg.append(node('polyline',{points:pts,fill:'none',stroke:color,'stroke-width':2.5,'stroke-linejoin':'round'}));
   data.forEach(([label,v],i)=>{const dot=node('circle',{cx:left+i*step,cy:y(v[key]),r:data.length>35?2:4,fill:color});dot.append(node('title',{},label+' · '+name+': '+(key==='gross_revenue'?'));svg.append(dot)});
  } else {
   const selected=[...visible],idx=selected.indexOf(key),bw=Math.min(24,w/Math.max(1,data.length)/Math.max(1,selected.length));
   data.forEach(([label,v],i)=>{const x=left+(i+.5)*w/data.length+(idx-selected.length/2)*bw;
    const bar=node('rect',{x,y:y(v[key]),width:Math.max(1,bw-1),height:Math.max(0,top+h-y(v[key])),fill:color});
    bar.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(bar);
   });
  }
 }
 $('trendStats').replaceChildren();
 for(const [key,[label,color]] of Object.entries(keys)){
  const div=document.createElement('div');div.className='card';
  const title=document.createElement('span');title.textContent=label;
  const number=document.createElement('strong');number.style.color=color;const total=data.reduce((n,[,v])=>n+v[key],0);
  number.textContent=key==='gross_revenue'?total.toLocaleString('en-US',{style:'currency',currency:'USD'}):total.toLocaleString();
  div.append(title,number);$('trendStats').append(div);
 }
}
async function load(){
 $('trendStatusText').textContent='Loading…';
 try{
  const d=await api('/api/trends.php?month='+encodeURIComponent(month.value)+'&status='+encodeURIComponent(status.value));
  days=d.days;$('trendStatusText').textContent='Showing '+d.start+' through '+Object.keys(days).at(-1)+' · '+d.timezone;
  render();
 }catch(e){$('trendStatusText').textContent='Unable to load trends: '+e.message;$('trendChart').replaceChildren();}
}
function shift(n){const [y,m]=month.value.split('-').map(Number);const d=new Date(Date.UTC(y,m-1+n,1));month.value=d.toISOString().slice(0,7);load();}
$('trendPrev').addEventListener('click',()=>shift(-1));$('trendNext').addEventListener('click',()=>shift(1));
month.addEventListener('change',load);status.addEventListener('change',load);group.addEventListener('change',render);style.addEventListener('change',render);
load();
})();
</script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
+Math.round(val).toLocaleString()));}
 const step=w/Math.max(1,data.length-1);
 const tick=Math.max(1,Math.ceil(data.length/9));
 data.forEach(([label],i)=>{if(i%tick===0||i===data.length-1)svg.append(node('text',{x:left+i*step,y:height-23,'text-anchor':'middle',fill:'currentColor','font-size':11},label.slice(5)))});
 for(const key of visible){
  const [name,color]=keys[key];
  if(style.value==='line'){
   const pts=data.map(([,v],i)=>(left+i*step)+','+y(v[key])).join(' ');
   svg.append(node('polyline',{points:pts,fill:'none',stroke:color,'stroke-width':2.5,'stroke-linejoin':'round'}));
   data.forEach(([label,v],i)=>{const dot=node('circle',{cx:left+i*step,cy:y(v[key]),r:data.length>35?2:4,fill:color});dot.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(dot)});
  } else {
   const selected=[...visible],idx=selected.indexOf(key),bw=Math.min(24,w/Math.max(1,data.length)/Math.max(1,selected.length));
   data.forEach(([label,v],i)=>{const x=left+(i+.5)*w/data.length+(idx-selected.length/2)*bw;
    const bar=node('rect',{x,y:y(v[key]),width:Math.max(1,bw-1),height:Math.max(0,top+h-y(v[key])),fill:color});
    bar.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(bar);
   });
  }
 }
 $('trendStats').replaceChildren();
 for(const [key,[label,color]] of Object.entries(keys)){
  const div=document.createElement('div');div.className='card';
  const title=document.createElement('span');title.textContent=label;
  const number=document.createElement('strong');number.style.color=color;number.textContent=data.reduce((n,[,v])=>n+v[key],0).toLocaleString();
  div.append(title,number);$('trendStats').append(div);
 }
}
async function load(){
 $('trendStatusText').textContent='Loading…';
 try{
  const d=await api('/api/trends.php?month='+encodeURIComponent(month.value)+'&status='+encodeURIComponent(status.value));
  days=d.days;$('trendStatusText').textContent='Showing '+d.start+' through '+Object.keys(days).at(-1)+' · '+d.timezone;
  render();
 }catch(e){$('trendStatusText').textContent='Unable to load trends: '+e.message;$('trendChart').replaceChildren();}
}
function shift(n){const [y,m]=month.value.split('-').map(Number);const d=new Date(Date.UTC(y,m-1+n,1));month.value=d.toISOString().slice(0,7);load();}
$('trendPrev').addEventListener('click',()=>shift(-1));$('trendNext').addEventListener('click',()=>shift(1));
month.addEventListener('change',load);status.addEventListener('change',load);group.addEventListener('change',render);style.addEventListener('change',render);
load();
})();
</script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
+Number(v[key]).toFixed(2):v[key])));svg.append(dot)});
  } else {
   const selected=[...visible],idx=selected.indexOf(key),bw=Math.min(24,w/Math.max(1,data.length)/Math.max(1,selected.length));
   data.forEach(([label,v],i)=>{const x=left+(i+.5)*w/data.length+(idx-selected.length/2)*bw;
    const bar=node('rect',{x,y:y(v[key]),width:Math.max(1,bw-1),height:Math.max(0,top+h-y(v[key])),fill:color});
    bar.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(bar);
   });
  }
 }
 $('trendStats').replaceChildren();
 for(const [key,[label,color]] of Object.entries(keys)){
  const div=document.createElement('div');div.className='card';
  const title=document.createElement('span');title.textContent=label;
  const number=document.createElement('strong');number.style.color=color;number.textContent=data.reduce((n,[,v])=>n+v[key],0).toLocaleString();
  div.append(title,number);$('trendStats').append(div);
 }
}
async function load(){
 $('trendStatusText').textContent='Loading…';
 try{
  const d=await api('/api/trends.php?month='+encodeURIComponent(month.value)+'&status='+encodeURIComponent(status.value));
  days=d.days;$('trendStatusText').textContent='Showing '+d.start+' through '+Object.keys(days).at(-1)+' · '+d.timezone;
  render();
 }catch(e){$('trendStatusText').textContent='Unable to load trends: '+e.message;$('trendChart').replaceChildren();}
}
function shift(n){const [y,m]=month.value.split('-').map(Number);const d=new Date(Date.UTC(y,m-1+n,1));month.value=d.toISOString().slice(0,7);load();}
$('trendPrev').addEventListener('click',()=>shift(-1));$('trendNext').addEventListener('click',()=>shift(1));
month.addEventListener('change',load);status.addEventListener('change',load);group.addEventListener('change',render);style.addEventListener('change',render);
load();
})();
</script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
+Math.round(val).toLocaleString()));}
 const step=w/Math.max(1,data.length-1);
 const tick=Math.max(1,Math.ceil(data.length/9));
 data.forEach(([label],i)=>{if(i%tick===0||i===data.length-1)svg.append(node('text',{x:left+i*step,y:height-23,'text-anchor':'middle',fill:'currentColor','font-size':11},label.slice(5)))});
 for(const key of visible){
  const [name,color]=keys[key];
  if(style.value==='line'){
   const pts=data.map(([,v],i)=>(left+i*step)+','+y(v[key])).join(' ');
   svg.append(node('polyline',{points:pts,fill:'none',stroke:color,'stroke-width':2.5,'stroke-linejoin':'round'}));
   data.forEach(([label,v],i)=>{const dot=node('circle',{cx:left+i*step,cy:y(v[key]),r:data.length>35?2:4,fill:color});dot.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(dot)});
  } else {
   const selected=[...visible],idx=selected.indexOf(key),bw=Math.min(24,w/Math.max(1,data.length)/Math.max(1,selected.length));
   data.forEach(([label,v],i)=>{const x=left+(i+.5)*w/data.length+(idx-selected.length/2)*bw;
    const bar=node('rect',{x,y:y(v[key]),width:Math.max(1,bw-1),height:Math.max(0,top+h-y(v[key])),fill:color});
    bar.append(node('title',{},label+' · '+name+': '+v[key]));svg.append(bar);
   });
  }
 }
 $('trendStats').replaceChildren();
 for(const [key,[label,color]] of Object.entries(keys)){
  const div=document.createElement('div');div.className='card';
  const title=document.createElement('span');title.textContent=label;
  const number=document.createElement('strong');number.style.color=color;number.textContent=data.reduce((n,[,v])=>n+v[key],0).toLocaleString();
  div.append(title,number);$('trendStats').append(div);
 }
}
async function load(){
 $('trendStatusText').textContent='Loading…';
 try{
  const d=await api('/api/trends.php?month='+encodeURIComponent(month.value)+'&status='+encodeURIComponent(status.value));
  days=d.days;$('trendStatusText').textContent='Showing '+d.start+' through '+Object.keys(days).at(-1)+' · '+d.timezone;
  render();
 }catch(e){$('trendStatusText').textContent='Unable to load trends: '+e.message;$('trendChart').replaceChildren();}
}
function shift(n){const [y,m]=month.value.split('-').map(Number);const d=new Date(Date.UTC(y,m-1+n,1));month.value=d.toISOString().slice(0,7);load();}
$('trendPrev').addEventListener('click',()=>shift(-1));$('trendNext').addEventListener('click',()=>shift(1));
month.addEventListener('change',load);status.addEventListener('change',load);group.addEventListener('change',render);style.addEventListener('change',render);
load();
})();
</script>
<?php require __DIR__.'/partials/app_footer.php'; ?>
