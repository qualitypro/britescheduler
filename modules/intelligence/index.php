<?php
declare(strict_types=1);
if (!defined('BRITE_MODULE_DISPATCH')) { http_response_code(403); exit; }
$biEndpoint=app_url('/modules/intelligence/metrics.php');
$biViewsEndpoint=app_url('/modules/intelligence/views.php');
$biFinancialEndpoint=app_url('/modules/intelligence/financial.php');
$biFinancialAccess=in_array((string)($tenant['role']??''),['owner','admin','accounting'],true);
?>
<style>
.bi-workspace{display:grid;gap:14px;min-width:0;color:var(--brite-text)}
.bi-workspace .bi-panel{background:var(--brite-surface);border:1px solid var(--brite-border);border-radius:14px;padding:16px;min-width:0}
.bi-workspace .bi-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.bi-workspace .bi-toolbar label{display:grid;gap:4px;font-size:.78rem;font-weight:650}
.bi-workspace .bi-layout{display:grid;grid-template-columns:minmax(215px,260px) minmax(0,1fr);gap:14px}
.bi-workspace .bi-metrics{display:grid;gap:8px;max-height:400px;overflow:auto}
.bi-workspace .bi-metrics label{display:flex;gap:9px;align-items:center;font-size:.85rem;font-weight:400;line-height:1.4;padding:4px 2px;background:transparent;border:0;border-radius:0;box-shadow:none;cursor:pointer}
.bi-workspace .bi-metrics h3{font-size:.9rem;margin:12px 0 2px;font-weight:700}
.bi-workspace .bi-metrics h3:first-child{margin-top:0}
.bi-workspace .bi-metrics input[type=checkbox]{appearance:auto!important;-webkit-appearance:checkbox!important;display:inline-block!important;flex:0 0 auto;width:16px!important;height:16px!important;min-width:16px!important;margin:0;accent-color:var(--brite-accent,#2563eb);border-radius:2px!important;box-shadow:none!important}
.bi-workspace .bi-metrics label:focus-within{outline:2px solid var(--brite-accent,#2563eb);outline-offset:2px}
.bi-workspace .bi-toolbar input[type=date],.bi-workspace .bi-toolbar select{appearance:auto!important;-webkit-appearance:auto!important;min-height:36px;border:1px solid var(--brite-border);border-radius:5px;padding:5px 8px;background:var(--brite-surface);color:var(--brite-text);font:inherit}
.bi-workspace .bi-toolbar button{border-radius:5px!important;min-height:36px;padding:6px 11px}
.bi-workspace .bi-toolbar label{background:transparent!important;border:0!important;border-radius:0!important;padding:0!important;box-shadow:none!important}
.bi-workspace .bi-metrics input{width:auto;min-width:0}
.bi-workspace canvas{display:block;width:100%;max-width:100%;height:auto}
.bi-workspace .bi-legend{display:flex;flex-wrap:wrap;gap:10px;font-size:.78rem}
.bi-workspace .bi-legend span{display:inline-flex;align-items:center;gap:5px}
.bi-workspace .bi-legend i{width:9px;height:9px;border-radius:50%;display:inline-block}
.bi-workspace .bi-note{font-size:.78rem;color:var(--brite-text-secondary)}

/* BI configuration controls: consistently rectangular, never pills or chips. */
section.bi-workspace .bi-toolbar,
section.bi-workspace .bi-metrics{border-radius:0}
section.bi-workspace .bi-toolbar label,
section.bi-workspace .bi-metrics label{
 border-radius:0!important;border:0!important;background:transparent!important;
 box-shadow:none!important;clip-path:none!important;transform:none!important;
 text-transform:none!important;
}
section.bi-workspace .bi-toolbar label{
 display:flex!important;flex-direction:column!important;align-items:flex-start!important;
 gap:5px!important;padding:0!important;margin:0!important;min-width:0!important;
 width:auto!important;height:auto!important;line-height:1.4!important;
}
section.bi-workspace .bi-toolbar input[type="date"],
section.bi-workspace .bi-toolbar select{
 display:block!important;box-sizing:border-box!important;position:static!important;
 appearance:auto!important;-webkit-appearance:auto!important;
 min-width:145px!important;max-width:230px!important;width:100%!important;
 height:38px!important;min-height:38px!important;max-height:38px!important;
 padding:6px 9px!important;margin:0!important;line-height:normal!important;
 border:1px solid var(--brite-border,#cbd5e1)!important;border-radius:0!important;
 background:var(--brite-surface,#fff)!important;background-image:none!important;
 color:var(--brite-text,#172033)!important;box-shadow:none!important;
 font-size:14px!important;font-weight:400!important;text-align:left!important;
 text-transform:none!important;clip-path:none!important;transform:none!important;
}
section.bi-workspace .bi-toolbar button,
#biExportDialog button{
 appearance:auto!important;-webkit-appearance:auto!important;
 border-radius:0!important;box-shadow:none!important;
 clip-path:none!important;transform:none!important;
 min-height:36px!important;white-space:normal!important;
}
section.bi-workspace .bi-metrics label{
 display:flex!important;flex-direction:row!important;align-items:center!important;
 justify-content:flex-start!important;gap:9px!important;
 width:100%!important;min-height:28px!important;padding:3px 0!important;
 font-weight:400!important;text-align:left!important;
}
section.bi-workspace .bi-metrics input[type="checkbox"]{
 appearance:checkbox!important;-webkit-appearance:checkbox!important;
 border-radius:0!important;width:16px!important;height:16px!important;
 flex:0 0 16px!important;box-shadow:none!important;
}
section.bi-workspace .bi-toolbar input:focus-visible,
section.bi-workspace .bi-toolbar select:focus-visible,
section.bi-workspace .bi-toolbar button:focus-visible{
 outline:2px solid #2563eb!important;outline-offset:2px!important;
}

/* Compact, accessible BI navigation and native financial configuration fields. */
.bi-tabs{display:flex;gap:4px;border-bottom:1px solid var(--brite-border);padding:0 2px;flex-wrap:wrap}
.bi-tabs button{border:1px solid var(--brite-border)!important;border-bottom:0!important;border-radius:4px 4px 0 0!important;padding:6px 12px!important;min-height:32px!important;background:var(--brite-surface)!important;color:var(--brite-text)!important;font-size:.82rem!important;display:inline-flex;align-items:center;gap:7px}
.bi-tabs button[aria-selected="true"]{font-weight:700!important;box-shadow:inset 0 3px var(--brite-accent,#2563eb)!important}
.bi-tabs button:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
.bi-tab-icon{width:15px;height:15px;display:inline-block;stroke:currentColor;fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.bi-workspace [hidden]{display:none!important}
.bi-fin-config{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
.bi-fin-config label{display:flex!important;flex-direction:column!important;align-items:flex-start!important;gap:5px!important;font-size:.8rem;font-weight:650;padding:0!important;margin:0!important;background:transparent!important;border:0!important;border-radius:0!important;box-shadow:none!important}
.bi-fin-config input[type=date],.bi-fin-config select{appearance:auto!important;-webkit-appearance:auto!important;box-sizing:border-box!important;border:1px solid var(--brite-border)!important;border-radius:0!important;box-shadow:none!important;background:var(--brite-surface)!important;color:var(--brite-text)!important;height:38px!important;padding:6px 9px!important;min-width:150px!important;font:inherit}
.bi-fin-config button{border-radius:0!important;min-height:38px}
.bi-fin-metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:8px}
.bi-fin-metrics label{display:flex!important;align-items:center!important;gap:8px!important;border:0!important;border-radius:0!important;background:transparent!important;padding:5px 0!important;font-size:.85rem}
.bi-fin-metrics input[type=checkbox]{appearance:checkbox!important;-webkit-appearance:checkbox!important;width:16px!important;height:16px!important;flex:0 0 16px!important;border-radius:0!important;box-shadow:none!important}
.bi-fin-totals{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:10px}
.bi-fin-total{border:1px solid var(--brite-border);padding:12px;min-width:0}
.bi-fin-total strong{display:block;font-size:1.2rem;overflow-wrap:anywhere}
.bi-fin-total small{display:block;color:var(--brite-text-secondary);margin-bottom:4px}
.bi-fin-plot{overflow:hidden;min-width:0}
.bi-fin-plot canvas{width:100%;height:auto;max-width:100%}
.bi-fin-table{overflow-x:auto}
.bi-fin-table th,.bi-fin-table td{text-align:right;padding:8px 10px;white-space:nowrap}
.bi-fin-table th:first-child,.bi-fin-table td:first-child{text-align:left}
@media(max-width:760px){.bi-workspace .bi-layout{grid-template-columns:1fr}}
</style>
<section class="bi-workspace" aria-labelledby="biTitle" data-brite-pills="off">
 <div><h1 id="biTitle">Intelligence</h1><p class="muted">Tenant-scoped, multi-series business analytics.</p></div>
 <div class="bi-tabs" role="tablist" aria-label="Intelligence workspaces">
  <button type="button" id="biTabPerformance" role="tab" aria-selected="true" aria-controls="biPanelPerformance"><svg class="bi-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 18h18M5 15l5-6 4 3 5-8"/></svg>Performance</button>
  <?php if($biFinancialAccess): ?><button type="button" id="biTabFinancial" role="tab" aria-selected="false" aria-controls="biPanelFinancial" tabindex="-1"><svg class="bi-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 10h18M16 15h2"/></svg>Financial</button><?php endif; ?>
 </div>
 <div id="biPanelPerformance" role="tabpanel" aria-labelledby="biTabPerformance" class="bi-workspace"> 
 <div class="bi-panel bi-toolbar">
  <label>From <input type="date" id="biFrom" value="<?=date('Y-m-01',strtotime('-5 months'))?>"></label>
  <label>To <input type="date" id="biTo" value="<?=date('Y-m-d')?>"></label>
  <label>Interval <select id="biInterval"><option value="day">Day</option><option value="week">Week</option><option value="month" selected>Month</option></select></label>
  <button type="button" id="biRun">Update chart</button>
  <label>Saved view <select id="biViewList"><option value="">Choose view…</option></select></label>
  <button type="button" id="biSave">Save view</button>
  <button type="button" id="biLoad">Load view</button>
  <button type="button" id="biDelete" disabled title="Select a saved view to delete">Delete view</button>
  <button type="button" id="biPng">PNG</button>
  <button type="button" id="biJpg">JPG</button>
  <button type="button" id="biPdf">PDF</button>
 </div>
 <div class="bi-layout">
  <aside class="bi-panel"><h2>Available series</h2><p class="bi-note">Select up to eight metrics using standard checkboxes. All queries are restricted to your tenant.</p><div id="biMetrics" class="bi-metrics" role="group" aria-label="Available metric series">Loading metrics…</div></aside>
  <div class="bi-panel">
   <h2>Performance analysis</h2><p id="biStatus" class="bi-note" role="status" aria-live="polite">Select metrics to begin.</p>
   <canvas id="biChart" width="1100" height="540" role="img" aria-label="Intelligence multi-series trend chart"></canvas>
   <div id="biLegend" class="bi-legend"></div>
   <p class="bi-note">Each series is independently normalized for visual comparison; exact values appear in the data table below.</p>
  </div>
 </div>
 <div class="bi-panel"><h2>Series data</h2><div style="overflow-x:auto"><table id="biTable"><thead></thead><tbody></tbody></table></div></div>
 </div>
 <?php if($biFinancialAccess): ?>
 <div id="biPanelFinancial" class="bi-workspace" role="tabpanel" aria-labelledby="biTabFinancial" hidden>
  <div class="bi-panel">
   <h2>Financial chart configuration</h2>
   <div class="bi-fin-config">
    <label>From <input type="date" id="biFinFrom" value="<?=date('Y-m-01',strtotime('-5 months'))?>"></label>
    <label>To <input type="date" id="biFinTo" value="<?=date('Y-m-d')?>"></label>
    <label>Interval <select id="biFinInterval"><option value="day">Day</option><option value="week">Week</option><option value="month" selected>Month</option></select></label>
    <button type="button" id="biFinRun">Update financial chart</button>
   </div>
   <p class="bi-note">Select up to six dollar-valued series. Open and lost opportunities are estimates based on service list prices, not booked revenue.</p>
   <div id="biFinMetrics" class="bi-fin-metrics" role="group" aria-label="Financial metric selection">Loading financial metrics…</div>
  </div>
  <div id="biFinTotals" class="bi-fin-totals" aria-label="Selected financial totals"></div>
  <div class="bi-panel bi-fin-plot">
   <h2>Financial performance · USD</h2>
   <p id="biFinStatus" class="bi-note" role="status" aria-live="polite">Loading financial series…</p>
   <canvas id="biFinChart" width="1100" height="520" role="img" aria-label="Financial dollar-value chart"></canvas>
   <div id="biFinLegend" class="bi-legend"></div>
   <p id="biFinNote" class="bi-note"></p>
  </div>
  <div class="bi-panel"><h2>Financial series · dollar values</h2><div class="bi-fin-table"><table id="biFinTable"><thead></thead><tbody></tbody></table></div></div>
 </div>
 <?php endif; ?>
</section>
<style>
#biExportDialog{width:min(1100px,calc(100vw - 28px));max-width:1100px;height:min(90dvh,900px);max-height:90dvh;padding:0;border:1px solid var(--brite-border);border-radius:16px;background:var(--brite-surface);color:var(--brite-text);box-shadow:0 24px 80px #0006;overflow:hidden}
#biExportDialog::backdrop{background:rgba(12,20,35,.75)}
#biExportDialog .bi-export-shell{display:flex;flex-direction:column;height:100%;min-height:0;min-width:0;overflow:hidden}
#biExportDialog .bi-export-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:space-between;padding:12px 16px;border-bottom:1px solid var(--brite-border)}
#biExportDialog .bi-export-actions{display:flex;align-items:center;gap:8px}
#biExportDialog .bi-export-body{flex:1 1 auto;min-width:0;min-height:0;display:flex;align-items:center;justify-content:center;overflow:hidden;padding:12px;background:#e8edf3;box-sizing:border-box}
#biExportDialog .bi-export-body img{display:block;flex:0 1 auto;width:auto!important;height:auto!important;max-width:100%!important;max-height:100%!important;min-width:0;min-height:0;object-fit:contain!important;object-position:center;box-sizing:border-box}
#biExportDialog .bi-export-body iframe{width:100%;height:100%;border:0;background:#fff}
@media(max-width:540px){#biExportDialog .bi-export-toolbar{padding:8px 10px}#biExportDialog .bi-export-body{padding:5px}}
</style>
<dialog id="biExportDialog" aria-labelledby="biExportTitle">
 <div class="bi-export-shell">
  <div class="bi-export-toolbar">
   <strong id="biExportTitle">Intelligence export preview</strong>
   <div class="bi-export-actions">
    <button type="button" id="biExportDownload">Download</button>
    <button type="button" id="biExportClose" aria-label="Close export preview">Close ×</button>
   </div>
  </div>
  <div class="bi-export-body" id="biExportBody"></div>
 </div>
</dialog>
<script>
(() => {
'use strict';
const endpoint=<?=json_encode($biEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const viewsEndpoint=<?=json_encode($biViewsEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const financialEndpoint=<?=json_encode($biFinancialEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const csrf=<?=json_encode(csrf_token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const $=id=>document.getElementById(id);
let savedViews=[];
function syncViewActions(){const selected=$('biViewList').value;const exists=!!selected&&savedViews.some(v=>v.name===selected);$('biDelete').disabled=!exists;$('biLoad').disabled=!exists;$('biDelete').title=exists?'Delete selected saved view':'Select a saved view to delete';}
$('biViewList').addEventListener('change',syncViewActions);
async function refreshViews(){const r=await fetch(viewsEndpoint,{credentials:'same-origin'});const d=await r.json();if(!r.ok)throw Error(d.error||'Unable to list views');savedViews=d.views||[];const sel=$('biViewList');sel.replaceChildren(new Option('Choose view…',''));savedViews.forEach(v=>sel.add(new Option(v.name,v.name)));syncViewActions();}
function applyView(c){if(!c)return;['from','to','interval'].forEach(k=>{const id={from:'biFrom',to:'biTo',interval:'biInterval'}[k];if(c[k])$(id).value=c[k]});document.querySelectorAll('.bi-metric').forEach(el=>el.checked=Array.isArray(c.metrics)&&c.metrics.includes(el.value));}
async function mutateView(method,name,config){const r=await fetch(viewsEndpoint,{method,credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:JSON.stringify({name,config})});const d=await r.json();if(!r.ok)throw Error(d.error||'Unable to save view');await refreshViews();$('biViewList').value=method==='DELETE'?'':name;syncViewActions();}
const palette=['#2563eb','#059669','#d97706','#9333ea','#db2777','#0891b2','#dc2626','#64748b'];
const chart=$('biChart'),ctx=chart.getContext('2d');
let current=null;
let activeRequest=null;
let refreshTimer=null;
let requestId=0;
const controls=['biFrom','biTo','biInterval'];
const safeSettings=()=>({from:$('biFrom').value,to:$('biTo').value,interval:$('biInterval').value,metrics:[...document.querySelectorAll('.bi-metric:checked')].map(el=>el.value).slice(0,8)});
function paint(){
 const W=chart.width,H=chart.height;
 ctx.fillStyle='#fff';ctx.fillRect(0,0,W,H);
 ctx.fillStyle='#172033';ctx.font='bold 25px system-ui';ctx.fillText('Intelligence · Performance analysis',35,44);
 if(!current?.series?.length)return;
 const buckets=[...new Set(current.series.flatMap(s=>s.points.map(p=>p.bucket)))].sort();
 const L=72,R=W-35,T=95,B=H-75;
 ctx.strokeStyle='#e2e8f0';ctx.lineWidth=1;ctx.font='14px system-ui';ctx.fillStyle='#64748b';
 for(let j=0;j<=4;j++){let y=T+(B-T)*j/4;ctx.beginPath();ctx.moveTo(L,y);ctx.lineTo(R,y);ctx.stroke();ctx.fillText(String(100-j*25)+'%',20,y+4)}
 buckets.forEach((b,i)=>{if(i%Math.max(1,Math.ceil(buckets.length/9))===0){let x=L+(R-L)*i/Math.max(1,buckets.length-1);ctx.fillText(b,x-30,B+27)}});
 current.series.forEach((s,k)=>{
  const vals=new Map(s.points.map(p=>[p.bucket,p.value]));
  const max=Math.max(1,...s.points.map(p=>p.value));
  ctx.strokeStyle=palette[k%palette.length];ctx.lineWidth=3;ctx.beginPath();
  buckets.forEach((b,i)=>{const x=L+(R-L)*i/Math.max(1,buckets.length-1),y=B-(B-T)*(vals.get(b)||0)/max;if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y)});
  ctx.stroke();
 });
 ctx.font='13px system-ui';ctx.fillStyle='#64748b';ctx.fillText('Normalized to each series maximum',L,H-15);
}
function showTable(){
 const series=current.series,buckets=[...new Set(series.flatMap(s=>s.points.map(p=>p.bucket)))].sort();
 const head=$('biTable').tHead||$('biTable').createTHead();head.replaceChildren();
 const tr=head.insertRow();['Period',...series.map(s=>s.name)].forEach(name=>{let th=document.createElement('th');th.textContent=name;tr.append(th)});
 const body=$('biTable').tBodies[0];body.replaceChildren();
 buckets.forEach(b=>{let row=body.insertRow();[b,...series.map(s=>s.points.find(p=>p.bucket===b)?.value??0)].forEach(v=>{let td=row.insertCell();td.textContent=typeof v==='number'?v.toLocaleString(undefined,{maximumFractionDigits:2}):v})});
 const legend=$('biLegend');legend.replaceChildren();
 series.forEach((s,i)=>{let span=document.createElement('span'),dot=document.createElement('i');dot.style.background=palette[i%palette.length];span.append(dot,document.createTextNode(s.name));legend.append(span)});
}
async function load(){
 const id=++requestId;
 if(activeRequest)activeRequest.abort();
 activeRequest=new AbortController();
 const opts=safeSettings();
 if(!opts.metrics.length){current=null;paint();$('biLegend').replaceChildren();$('biTable').tHead?.replaceChildren();$('biTable').tBodies[0]?.replaceChildren();$('biStatus').textContent='Select at least one series.';return}
 if(opts.metrics.length>8){$('biStatus').textContent='Maximum eight series.';return}
 $('biStatus').textContent='Loading tenant metrics…';
 try{
 const query=new URLSearchParams({...opts,metrics:opts.metrics.join(',')});
 const response=await fetch(endpoint+'?'+query,{credentials:'same-origin',headers:{Accept:'application/json'},signal:activeRequest.signal});
 const data=await response.json();if(!response.ok)throw Error(data.error||'Unable to load data');
 if(id!==requestId)return;
 current=data;paint();showTable();$('biStatus').textContent='Loaded '+data.series.length+' series · '+data.from+' through '+data.to;
 }catch(e){if(id===requestId&&e.name!=='AbortError')$('biStatus').textContent=e.message}
}
function scheduleRefresh(){clearTimeout(refreshTimer);refreshTimer=setTimeout(load,180)}
$('biMetrics').addEventListener('change',event=>{if(!event.target.matches('.bi-metric'))return;const selected=document.querySelectorAll('.bi-metric:checked');if(selected.length>8){event.target.checked=false;$('biStatus').textContent='Select a maximum of eight series.';return}scheduleRefresh()});
controls.forEach(id=>$(id).addEventListener('change',scheduleRefresh));
let exportUrl=null,exportName=null;
const exportDialog=$('biExportDialog'),exportBody=$('biExportBody');
function closeExport(){if(exportDialog.open)exportDialog.close()}
function releaseExport(){exportBody.replaceChildren();if(exportUrl){URL.revokeObjectURL(exportUrl);exportUrl=null}exportName=null}
exportDialog.addEventListener('close',releaseExport);
exportDialog.addEventListener('click',event=>{if(event.target===exportDialog)closeExport()});
$('biExportClose').addEventListener('click',closeExport);
$('biExportDownload').addEventListener('click',()=>{
 if(!exportUrl||!exportName)return;
 const a=document.createElement('a');a.href=exportUrl;a.download=exportName;document.body.append(a);a.click();a.remove();
});
function previewExport(blob,filename){
 if(!blob||!blob.size){$('biStatus').textContent='Unable to generate export.';return}
 closeExport();releaseExport();
 exportUrl=URL.createObjectURL(blob);exportName=filename;
 $('biExportTitle').textContent='Preview · '+filename;
 const preview=document.createElement(blob.type==='application/pdf'?'iframe':'img');
 preview.src=exportUrl;
 if(preview.tagName==='IFRAME'){preview.title='PDF export preview'}else{preview.alt='Intelligence chart export preview';preview.style.cssText='display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;object-position:center';}
 exportBody.append(preview);
 if(typeof exportDialog.showModal==='function')exportDialog.showModal();
 else $('biStatus').textContent='Preview dialog is not supported by this browser.';
}
function exportFilename(extension){
 const selection=safeSettings();
 const names=(current?.series||[]).map(series=>series.name);
 const slug=value=>String(value).normalize('NFKD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,48);
 const selected=names.length===1?slug(names[0]):names.length<=3?names.map(slug).join('-'):names.slice(0,2).map(slug).join('-')+'-plus-'+(names.length-2)+'-more';
 const period=selection.from+'-to-'+selection.to;
 return ['brite-intelligence',selected||'dashboard',period,slug(selection.interval)].join('_')+'.'+extension;
}
function imageExport(type){if(!current)return;const filename=exportFilename(type==='image/png'?'png':'jpg');chart.toBlob(b=>previewExport(b,filename),type,.94)}
function pdfExport(){
 if(!current)return;
 const data=chart.toDataURL('image/jpeg',.9).split(',')[1];
 const raw=atob(data),bytes=Uint8Array.from(raw,c=>c.charCodeAt(0));
 const encoder=new TextEncoder(),chunks=[];let offset=0;const positions=[0];
 const add=x=>{const b=typeof x==='string'?encoder.encode(x):x;chunks.push(b);offset+=b.length};
 add('%PDF-1.4\n');
 const obj=(id,content)=>{positions[id]=offset;add(id+' 0 obj\n'+content+'\nendobj\n')};
 obj(1,'<< /Type /Catalog /Pages 2 0 R >>');
 obj(2,'<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
 obj(3,'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
 positions[4]=offset;add('4 0 obj\n<< /Type /XObject /Subtype /Image /Width 1100 /Height 540 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '+bytes.length+' >>\nstream\n');add(bytes);add('\nendstream\nendobj\n');
 const command='q 740 0 0 363 26 130 cm /Im0 Do Q\n';
 obj(5,'<< /Length '+encoder.encode(command).length+' >>\nstream\n'+command+'endstream');
 const xref=offset;add('xref\n0 6\n0000000000 65535 f \n');for(let i=1;i<=5;i++)add(String(positions[i]).padStart(10,'0')+' 00000 n \n');
 add('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF');
 previewExport(new Blob(chunks,{type:'application/pdf'}),exportFilename('pdf'));
}
$('biRun').addEventListener('click',load);
$('biPng').addEventListener('click',()=>imageExport('image/png'));
$('biJpg').addEventListener('click',()=>imageExport('image/jpeg'));
$('biPdf').addEventListener('click',pdfExport);
$('biSave').addEventListener('click',async()=>{const name=prompt('Name this Intelligence view:',$('biViewList').value||'My dashboard');if(!name)return;try{await mutateView('POST',name,safeSettings());$('biStatus').textContent='Saved view: '+name}catch(e){$('biStatus').textContent=e.message}});
$('biLoad').addEventListener('click',async()=>{const v=savedViews.find(v=>v.name===$('biViewList').value);if(!v)return;applyView(v.config);await load()});
$('biDelete').addEventListener('click',async()=>{const name=$('biViewList').value;if(!savedViews.some(v=>v.name===name)||!confirm('Delete view '+name+'?'))return;try{await mutateView('DELETE',name,null);$('biStatus').textContent='View deleted.'}catch(e){$('biStatus').textContent=e.message}});
(async()=>{
 try{
 const response=await fetch(endpoint+'?action=catalog',{credentials:'same-origin'});
 const data=await response.json();if(!response.ok)throw Error(data.error||'Catalog unavailable');
 const holder=$('biMetrics');holder.replaceChildren();
 const groups={};data.metrics.forEach(m=>(groups[m.source]??=[]).push(m));
 Object.entries(groups).forEach(([source,metrics])=>{const h=document.createElement('h3');h.textContent=source;holder.append(h);metrics.forEach(m=>{const l=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.className='bi-metric';input.value=m.key;input.checked=['appointments','completed'].includes(m.key);l.append(input,document.createTextNode(m.name));holder.append(l)})});
 try{const saved=JSON.parse(localStorage.getItem('brite-intelligence-view')||'null');if(saved){controls.forEach(id=>{const key={biFrom:'from',biTo:'to',biInterval:'interval'}[id];if(saved[key])$(id).value=saved[key]});document.querySelectorAll('.bi-metric').forEach(el=>el.checked=Array.isArray(saved.metrics)&&saved.metrics.includes(el.value))}}catch(e){}
 try{await refreshViews()}catch(e){$('biStatus').textContent=e.message}
 await load();
 }catch(e){$('biStatus').textContent=e.message}
})();

const finTab=$('biTabFinancial');
if(finTab){
 const performanceTab=$('biTabPerformance');
 const tabs=[performanceTab,finTab];
 const activateTab=tab=>{
  tabs.forEach(t=>{const active=t===tab;t.setAttribute('aria-selected',String(active));t.tabIndex=active?0:-1;$(t.getAttribute('aria-controls')).hidden=!active});
  if(tab===finTab&&!finLoaded)loadFinancial();
 };
 tabs.forEach((tab,i)=>{
  tab.addEventListener('click',()=>activateTab(tab));
  tab.addEventListener('keydown',event=>{
   if(!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;
   event.preventDefault();
   const next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;
   tabs[next].focus();activateTab(tabs[next]);
  });
 });
 const finCanvas=$('biFinChart'),finCtx=finCanvas.getContext('2d');
 const usd=value=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:2}).format(value);
 const axisUsd=value=>Math.abs(value)>=1000000?'USD '+(value/1000000).toFixed(1)+'m':Math.abs(value)>=1000?'USD '+(value/1000).toFixed(1)+'k':usd(value);
 let finCurrent=null,finRequest=null,finTimer=null,finLoaded=false,finSeq=0;
 const finColors=['#2563eb','#059669','#d97706','#9333ea','#dc2626','#0891b2'];
 const finOptions=()=>({from:$('biFinFrom').value,to:$('biFinTo').value,interval:$('biFinInterval').value,metrics:[...document.querySelectorAll('.bi-fin-metric:checked')].map(el=>el.value)});
 function renderFinancial(){
  const w=finCanvas.width,h=finCanvas.height,ctx=finCtx;
  ctx.clearRect(0,0,w,h);ctx.fillStyle='#fff';ctx.fillRect(0,0,w,h);
  if(!finCurrent?.series?.length)return;
  const series=finCurrent.series,buckets=[...new Set(series.flatMap(s=>s.points.map(p=>p.bucket)))].sort();
  const left=105,right=w-30,top=45,bottom=h-85;
  const max=Math.max(1,...series.flatMap(s=>s.points.map(p=>p.value)));
  const ceiling=max*1.12;
  ctx.font='14px system-ui';ctx.textAlign='right';ctx.strokeStyle='#e2e8f0';ctx.fillStyle='#475569';
  for(let i=0;i<=5;i++){const y=bottom-(bottom-top)*i/5;ctx.beginPath();ctx.moveTo(left,y);ctx.lineTo(right,y);ctx.stroke();ctx.fillText(axisUsd(ceiling*i/5),left-12,y+5)}
  ctx.textAlign='center';
  buckets.forEach((bucket,i)=>{if(i%Math.max(1,Math.ceil(buckets.length/9))===0){const x=left+(right-left)*(i+.5)/Math.max(1,buckets.length);ctx.fillText(bucket,x,bottom+28)}});
  const slot=(right-left)/Math.max(1,buckets.length);
  const barWidth=Math.min(28,slot/Math.max(1,series.length+1));
  series.forEach((s,j)=>{
   const values=new Map(s.points.map(p=>[p.bucket,p.value]));
   ctx.fillStyle=finColors[j%finColors.length];
   buckets.forEach((bucket,i)=>{
    const val=values.get(bucket)||0;
    const height=(bottom-top)*Math.max(0,val)/ceiling;
    const x=left+slot*i+slot/2+(j-(series.length-1)/2)*barWidth;
    ctx.fillRect(x-barWidth*.45,bottom-height,barWidth*.9,height);
   });
  });
  ctx.textAlign='left';
 }
 function renderFinancialDetails(){
  const totals=$('biFinTotals'),legend=$('biFinLegend'),table=$('biFinTable');
  totals.replaceChildren();legend.replaceChildren();table.tHead?.replaceChildren();table.tBodies[0]?.replaceChildren();
  if(!finCurrent)return;
  const series=finCurrent.series,buckets=[...new Set(series.flatMap(s=>s.points.map(p=>p.bucket)))].sort();
  series.forEach((item,i)=>{
   const card=document.createElement('div');card.className='bi-fin-total';
   const name=document.createElement('small');name.textContent=item.name;
   const amount=document.createElement('strong');amount.textContent=usd(item.total);
   card.append(name,amount);totals.append(card);
   const entry=document.createElement('span'),dot=document.createElement('i');
   dot.style.background=finColors[i%finColors.length];entry.append(dot,document.createTextNode(item.name));legend.append(entry);
  });
  const head=table.tHead||table.createTHead(),row=head.insertRow();
  ['Period',...series.map(item=>item.name)].forEach(label=>{const cell=document.createElement('th');cell.textContent=label;row.append(cell)});
  const body=table.tBodies[0]||table.createTBody();
  buckets.forEach(bucket=>{
   const row=body.insertRow();row.insertCell().textContent=bucket;
   series.forEach(item=>{const value=item.points.find(p=>p.bucket===bucket)?.value??0;row.insertCell().textContent=usd(value)});
  });
  const totalRow=body.insertRow();totalRow.insertCell().textContent='Period total';
  series.forEach(item=>totalRow.insertCell().textContent=usd(item.total));
  $('biFinNote').textContent=finCurrent.valuation_note||'';
 }
 async function loadFinancial(){
  const seq=++finSeq;if(finRequest)finRequest.abort();finRequest=new AbortController();
  const options=finOptions();
  if(!options.metrics.length){finCurrent=null;renderFinancial();renderFinancialDetails();$('biFinStatus').textContent='Select a financial metric.';return}
  $('biFinStatus').textContent='Loading financial metrics…';
  try{
   const query=new URLSearchParams({...options,metrics:options.metrics.join(',')});
   const response=await fetch(financialEndpoint+'?'+query,{credentials:'same-origin',headers:{Accept:'application/json'},signal:finRequest.signal});
   const data=await response.json();
   if(!response.ok)throw Error(data.error||'Unable to load financial metrics');
   if(seq!==finSeq)return;
   finCurrent=data;finLoaded=true;renderFinancial();renderFinancialDetails();
   $('biFinStatus').textContent='Loaded '+data.series.length+' dollar-valued series · '+data.from+' through '+data.to;
  }catch(error){if(seq===finSeq&&error.name!=='AbortError')$('biFinStatus').textContent=error.message}
 }
 function scheduleFinancial(){clearTimeout(finTimer);finTimer=setTimeout(loadFinancial,180)}
 ['biFinFrom','biFinTo','biFinInterval'].forEach(id=>$(id).addEventListener('change',scheduleFinancial));
 $('biFinRun').addEventListener('click',loadFinancial);
 $('biFinMetrics').addEventListener('change',event=>{
  if(!event.target.matches('.bi-fin-metric'))return;
  if(document.querySelectorAll('.bi-fin-metric:checked').length>6){event.target.checked=false;return}
  scheduleFinancial();
 });
 (async()=>{
  try{
   const response=await fetch(financialEndpoint+'?action=catalog',{credentials:'same-origin',headers:{Accept:'application/json'}});
   const data=await response.json();if(!response.ok)throw Error(data.error||'Financial catalog unavailable');
   const holder=$('biFinMetrics');holder.replaceChildren();
   data.metrics.forEach(item=>{
    const label=document.createElement('label'),input=document.createElement('input');
    input.type='checkbox';input.className='bi-fin-metric';input.value=item.key;
    input.checked=['revenue_received','outstanding','opportunity_open','opportunity_lost'].includes(item.key);
    label.title=item.description;label.append(input,document.createTextNode(item.name));holder.append(label);
   });
   if(finTab.getAttribute('aria-selected')==='true')loadFinancial();
  }catch(error){$('biFinStatus').textContent=error.message;$('biFinMetrics').textContent='Financial metrics unavailable.'}
 })();
}
})();
</script>
