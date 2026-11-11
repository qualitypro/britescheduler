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
/* Keep the series selector aligned with the adjacent analysis card.
   Its own content scrolls instead of leaving unused space below a 400px cap. */
.bi-workspace .bi-layout > .bi-panel:first-child{display:flex;flex-direction:column;min-height:0}
.bi-workspace .bi-layout > .bi-panel:first-child > h2{flex:0 0 auto}
.bi-workspace .bi-metrics{display:grid;align-content:start;gap:8px;flex:1 1 auto;min-height:0;max-height:none;overflow-y:auto;overflow-x:hidden;overscroll-behavior:contain}
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

.bi-compare-heading{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.bi-compare-heading label{display:inline-flex;align-items:center;gap:6px;font-size:.75rem;cursor:pointer}
.bi-compare-heading input[type=checkbox]{appearance:checkbox!important;width:15px!important;height:15px!important;min-width:15px!important}
#biCompareTable th,#biCompareTable td{text-align:right;white-space:nowrap}
#biCompareTable th:first-child,#biCompareTable td:first-child{text-align:left}
/* Intelligence density pass: scoped to this module, preserve native accessible controls. */
section.bi-workspace{gap:8px;font-size:.84rem;line-height:1.35}
section.bi-workspace .bi-workspace{gap:8px}
section.bi-workspace .bi-heading{display:flex;align-items:baseline;flex-wrap:wrap;gap:4px 12px}
section.bi-workspace .bi-heading h1{font-size:1.3rem;line-height:1.2;margin:0}
section.bi-workspace .bi-heading p{font-size:.74rem;margin:0}
section.bi-workspace .bi-panel{padding:10px 12px;border-radius:7px}
section.bi-workspace .bi-panel h2{font-size:.95rem;line-height:1.3;margin:0 0 6px}
section.bi-workspace .bi-subtitle{font-size:.72rem;font-weight:400;color:var(--brite-text-secondary)}
section.bi-workspace .bi-tabs{gap:2px;padding:0}
section.bi-workspace .bi-tabs button{min-height:29px!important;padding:4px 10px!important;font-size:.76rem!important;gap:5px}
section.bi-workspace .bi-tab-icon{width:13px;height:13px}
section.bi-workspace .bi-toolbar,section.bi-workspace .bi-fin-config{gap:6px 8px;align-items:end}
section.bi-workspace .bi-toolbar label,section.bi-workspace .bi-fin-config label{font-size:.7rem!important;gap:3px!important;line-height:1.2!important}
section.bi-workspace .bi-toolbar input[type=date],section.bi-workspace .bi-toolbar select,
section.bi-workspace .bi-fin-config input[type=date],section.bi-workspace .bi-fin-config select{
 height:30px!important;min-height:30px!important;max-height:30px!important;
 min-width:112px!important;max-width:160px!important;width:auto!important;
 padding:3px 6px!important;font-size:12px!important;line-height:1.2!important
}
section.bi-workspace #biViewList,section.bi-workspace #biFinViewList{min-width:140px!important;max-width:200px!important}
section.bi-workspace #biInterval,section.bi-workspace #biFinInterval{min-width:78px!important}
section.bi-workspace .bi-toolbar button,section.bi-workspace .bi-fin-config button{
 min-height:30px!important;height:30px!important;padding:4px 8px!important;
 font-size:.73rem!important;line-height:1.1!important;white-space:nowrap!important
}
section.bi-workspace .bi-toolbar button:disabled,section.bi-workspace .bi-fin-config button:disabled{opacity:.45;cursor:not-allowed}
section.bi-workspace .bi-layout{grid-template-columns:minmax(175px,215px) minmax(0,1fr);gap:8px}
section.bi-workspace .bi-metrics{gap:2px;max-height:none;min-height:0;flex:1 1 auto;align-content:start;overflow-y:auto;scrollbar-width:thin}
/* The analysis panel sets the row height; the metric list scrolls inside it. */
@media(min-width:851px){
 section.bi-workspace .bi-layout{align-items:start}
 section.bi-workspace .bi-series-panel{box-sizing:border-box;height:0;min-height:0;overflow:hidden;display:flex;flex-direction:column}
 section.bi-workspace .bi-series-panel #biMetrics{flex:1 1 auto;min-height:0;max-height:none;overflow-y:auto;align-content:start}
}
section.bi-workspace .bi-metrics h3{font-size:.76rem;margin:8px 0 2px}
section.bi-workspace .bi-metrics label{min-height:24px!important;padding:2px 0!important;gap:6px!important;font-size:.76rem;line-height:1.2!important}
section.bi-workspace .bi-metrics input[type=checkbox],section.bi-workspace .bi-fin-metrics input[type=checkbox]{width:14px!important;height:14px!important;min-width:14px!important;flex:0 0 14px!important}
section.bi-workspace .bi-fin-metrics{grid-template-columns:repeat(auto-fit,minmax(165px,1fr));gap:2px 10px}
section.bi-workspace .bi-fin-metrics label{font-size:.75rem!important;padding:3px 0!important;gap:6px!important;line-height:1.2!important}
section.bi-workspace .bi-fin-help{margin:7px 0 5px}
section.bi-workspace .bi-fin-totals{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:6px}
section.bi-workspace .bi-fin-total{padding:7px 9px}
section.bi-workspace .bi-fin-total strong{font-size:1rem;line-height:1.2}
section.bi-workspace .bi-fin-total small{font-size:.69rem;margin-bottom:2px}
section.bi-workspace .bi-note{font-size:.71rem;line-height:1.3;margin:4px 0}
section.bi-workspace .bi-legend{gap:4px 10px;font-size:.7rem}
section.bi-workspace .bi-legend i{width:8px;height:8px}
section.bi-workspace #biChart,section.bi-workspace #biFinChart{max-height:390px;object-fit:contain;object-position:top center}
section.bi-workspace table{font-size:.73rem;width:100%;border-collapse:collapse}
section.bi-workspace table th,section.bi-workspace table td{padding:5px 7px}
section.bi-workspace .bi-fin-table{max-width:100%}
@media(max-width:850px){
 section.bi-workspace .bi-layout{grid-template-columns:1fr}
 section.bi-workspace .bi-metrics{grid-template-columns:repeat(auto-fit,minmax(150px,1fr));max-height:260px;flex:0 1 auto;gap:2px 8px}
 section.bi-workspace .bi-metrics h3{grid-column:1/-1}
}
@media(max-width:520px){
 section.bi-workspace .bi-toolbar,section.bi-workspace .bi-fin-config{gap:6px}
 section.bi-workspace .bi-toolbar label,section.bi-workspace .bi-fin-config label{flex:1 1 105px!important}
 section.bi-workspace .bi-toolbar input[type=date],section.bi-workspace .bi-toolbar select,
 section.bi-workspace .bi-fin-config input[type=date],section.bi-workspace .bi-fin-config select{width:100%!important;max-width:none!important;min-width:0!important}
 section.bi-workspace .bi-fin-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
 section.bi-workspace .bi-fin-totals{grid-template-columns:repeat(2,minmax(0,1fr))}
 section.bi-workspace #biChart,section.bi-workspace #biFinChart{max-height:290px}
}

/* Cycle 90AP: compact vertical BI workspace tabs, matching CRM navigation. */
section.bi-workspace[aria-labelledby="biTitle"]{
 display:grid;grid-template-columns:156px minmax(0,1fr);
 column-gap:10px;row-gap:9px;align-items:start;min-width:0;
}
section.bi-workspace[aria-labelledby="biTitle"]>.bi-heading{grid-column:1/-1}
section.bi-workspace[aria-labelledby="biTitle"]>.bi-tabs{
 grid-column:1;grid-row:2;display:flex;flex-direction:column;
 align-items:stretch;gap:3px;min-width:0;border:0;padding:4px;
 border:1px solid var(--brite-border);border-radius:7px;
 background:var(--brite-surface);position:sticky;top:72px;
}
section.bi-workspace[aria-labelledby="biTitle"]>.bi-tabs button{
 display:flex!important;justify-content:flex-start;text-align:left;
 width:100%;min-height:31px!important;padding:6px 8px!important;
 border:1px solid transparent!important;border-radius:5px!important;
 font-size:.75rem!important;white-space:normal;
}
section.bi-workspace[aria-labelledby="biTitle"]>.bi-tabs button[aria-selected="true"]{
 background:var(--brite-indigo-soft,#e0e7ff)!important;
 border-color:var(--brite-border)!important;
 box-shadow:inset 3px 0 var(--brite-accent,#2563eb)!important;
}
section.bi-workspace[aria-labelledby="biTitle"]>[role="tabpanel"]{
 grid-column:2;grid-row:2;min-width:0;max-width:100%;
}
section.bi-workspace[aria-labelledby="biTitle"] .bi-panel{min-width:0}
section.bi-workspace[aria-labelledby="biTitle"] .bi-fin-table{max-width:100%}
section.bi-workspace[aria-labelledby="biTitle"] .bi-heading h1{font-size:1.18rem!important}
@media(max-width:700px){
 section.bi-workspace[aria-labelledby="biTitle"]{grid-template-columns:minmax(0,1fr)}
 section.bi-workspace[aria-labelledby="biTitle"]>.bi-tabs{
  grid-column:1;grid-row:2;position:static;flex-direction:row;flex-wrap:wrap;
 }
 section.bi-workspace[aria-labelledby="biTitle"]>.bi-tabs button{width:auto;flex:1 1 110px}
 section.bi-workspace[aria-labelledby="biTitle"]>[role="tabpanel"]{grid-column:1;grid-row:3}
}
</style>
<link rel="stylesheet" href="<?=htmlspecialchars(app_url('/assets/css/brite-widget-layout.css'),ENT_QUOTES,'UTF-8')?>">
<script src="<?=htmlspecialchars(app_url('/assets/js/brite-widget-layout.js'),ENT_QUOTES,'UTF-8')?>"></script>
<style>
/* Intelligence widget containers retain the existing BI chart/selector layout. */
/* BI widgets wrap existing panels. The visible border belongs to the nested
   panel, not the wrapper: remove the shared wrapper's 34px header gap and
   reserve the header space INSIDE the visible BI panel instead. */
.bi-workspace .bi-widget-grid>.bi-widget{
 padding-top:0!important;
 position:relative!important;
}
.bi-workspace .bi-widget-grid>.bi-widget>.brite-widget-chrome{
 top:7px!important;
 height:30px!important;
 z-index:25!important;
}
.bi-workspace .bi-widget-grid>.bi-widget>.bi-panel{
 padding-top:44px!important;
}
.bi-workspace .bi-widget-grid>.bi-widget>.bi-layout>.bi-panel:first-child{
 padding-top:44px!important;
}
/* Where a BI widget contains an unboxed visualization rather than a panel,
   give the wrapper its own visible surface for the controls to sit within. */
.bi-workspace .bi-widget-grid>.bi-widget:not(:has(>.bi-panel,>.bi-layout)){
 border:1px solid var(--brite-border,#cbd5e1);
 border-radius:7px;
 background:var(--brite-surface,#fff);
 padding:44px 12px 12px!important;
 box-sizing:border-box;
}
@media(pointer:coarse){
 .bi-workspace .bi-widget-grid>.bi-widget>.brite-widget-chrome{height:40px!important}
 .bi-workspace .bi-widget-grid>.bi-widget>.bi-panel,
 .bi-workspace .bi-widget-grid>.bi-widget>.bi-layout>.bi-panel:first-child{padding-top:52px!important}
 .bi-workspace .bi-widget-grid>.bi-widget:not(:has(>.bi-panel,>.bi-layout)){padding-top:52px!important}
}

.bi-workspace .bi-widget-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;min-width:0}
.bi-workspace .bi-widget-grid>.bi-widget{grid-column:span 2;min-width:0}
.bi-workspace .bi-widget-grid>.bi-widget.brite-widget-wide{grid-column:span 1}
.bi-workspace .bi-widget>.brite-widget-controls{margin-bottom:10px}
.bi-workspace .bi-widget-grid .bi-widget[hidden]{display:none!important}
.bi-workspace .bi-widget .bi-panel{min-width:0}
.bi-workspace .bi-widget-grid>.bi-widget>.bi-panel{height:100%;box-sizing:border-box}
.bi-workspace .bi-widget-grid .bi-fin-totals{min-width:0}
@media(max-width:1100px){.bi-workspace .bi-widget-grid>.bi-widget.brite-widget-wide{grid-column:span 2}}
</style>
<section class="bi-workspace" aria-labelledby="biTitle" data-brite-pills="off">
 <div class="bi-heading"><h1 id="biTitle">Intelligence</h1><p class="muted">Tenant-scoped business analytics</p></div>
 <div class="bi-tabs" role="tablist" aria-label="Intelligence workspaces">
  <button type="button" id="biTabPerformance" role="tab" aria-selected="true" aria-controls="biPanelPerformance"><svg class="bi-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 18h18M5 15l5-6 4 3 5-8"/></svg>Performance</button>
  <?php if($biFinancialAccess): ?><button type="button" id="biTabFinancial" role="tab" aria-selected="false" aria-controls="biPanelFinancial" tabindex="-1"><svg class="bi-tab-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 10h18M16 15h2"/></svg>Financial</button><?php endif; ?>
 </div>
 <div id="biPanelPerformance" role="tabpanel" aria-labelledby="biTabPerformance" class="bi-workspace"> 
 <div class="bi-panel bi-toolbar">
  <label>From <input type="date" id="biFrom" value="<?=date('Y-m-01',strtotime('-5 months'))?>"></label>
  <label>To <input type="date" id="biTo" value="<?=date('Y-m-d')?>"></label>
  <label>Interval <select id="biInterval"><option value="day">Day</option><option value="week">Week</option><option value="month" selected>Month</option></select></label>
  <label>Saved view <select id="biViewList"><option value="">Choose view…</option></select></label>
  <button type="button" id="biSave">Save view</button>
  <button type="button" id="biDelete" disabled title="Select a saved view to delete">Delete view</button>
  <button type="button" id="biPng">PNG</button>
  <button type="button" id="biJpg">JPG</button>
  <button type="button" id="biPdf">PDF</button>
  <button type="button" id="biCsv">CSV data</button>
 </div>
 <div class="bi-layout">
  <aside class="bi-panel bi-series-panel"><h2>Available series <small class="bi-subtitle">· up to 8</small></h2><div id="biMetrics" class="bi-metrics" role="group" aria-label="Available metric series">Loading metrics…</div></aside>
  <div class="bi-panel">
   <h2>Performance analysis</h2><p id="biStatus" class="bi-note" role="status" aria-live="polite">Select metrics to begin.</p>
   <div id="biPerformanceKpis" class="bi-fin-totals" aria-label="Performance metric summary"></div>
   <canvas id="biChart" width="1100" height="540" role="img" aria-label="Intelligence multi-series trend chart"></canvas>
   <div id="biLegend" class="bi-legend"></div>
   <p class="bi-note">Each series is independently normalized for visual comparison; exact values appear in the data table below.</p>
  </div>
 </div>
 <div class="bi-panel"><div class="bi-compare-heading"><h2>Period comparison</h2><label><input id="biCompareEnabled" type="checkbox"> Compare with preceding equal-length period</label></div><p id="biCompareStatus" class="bi-note">Enable to compare selected metric totals with the immediately preceding period.</p><div class="bi-fin-table"><table id="biCompareTable"><thead></thead><tbody></tbody></table></div></div>
 <div class="bi-panel"><h2>Series data</h2><div style="overflow-x:auto"><table id="biTable"><thead></thead><tbody></tbody></table></div></div>
 </div>
 <?php if($biFinancialAccess): ?>
 <div id="biPanelFinancial" class="bi-workspace" role="tabpanel" aria-labelledby="biTabFinancial" hidden>
  <div class="bi-panel">
   <h2>Financial configuration</h2>
   <div class="bi-fin-config">
    <label>From <input type="date" id="biFinFrom" value="<?=date('Y-m-01',strtotime('-5 months'))?>"></label>
    <label>To <input type="date" id="biFinTo" value="<?=date('Y-m-d')?>"></label>
    <label>Interval <select id="biFinInterval"><option value="day">Day</option><option value="week">Week</option><option value="month" selected>Month</option></select></label>
    <label>Saved view <select id="biFinViewList"><option value="">Choose view…</option></select></label>
    <button type="button" id="biFinSave">Save view</button>
    <button type="button" id="biFinDelete" disabled>Delete view</button>
    <button type="button" id="biFinPng" disabled>PNG</button>
    <button type="button" id="biFinJpg" disabled>JPG</button>
    <button type="button" id="biFinPdf" disabled>PDF</button>
    <button type="button" id="biFinCsv" disabled>CSV data</button>
   </div>
   <p class="bi-note bi-fin-help">Select up to six series · Auto-refresh · Opportunity values are estimates, not booked revenue.</p>
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
  <div class="bi-panel"><div class="bi-compare-heading"><h2>Next-period outlook</h2><label><input id="biFinForecastEnabled" type="checkbox"> Show simple trend projection</label></div><p class="bi-note" id="biFinForecastNote">Enable to estimate the next reporting period from recent historical values.</p><div class="bi-fin-table"><table id="biFinForecastTable"><thead></thead><tbody></tbody></table></div></div>
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
/* Match the Available Series viewport to the neighboring chart card, including
   responsive resizes and chart/KPI changes, without letting the list set row height. */
(function(){
 const layout=document.querySelector('.bi-workspace .bi-layout');
 if(!layout)return;
 const selector=layout.querySelector('.bi-series-panel');
 const analysis=layout.querySelector('.bi-panel:not(.bi-series-panel)');
 if(!selector||!analysis)return;
 const sync=()=>{
  if(window.matchMedia('(max-width:850px)').matches){
   selector.style.height='';
   return;
  }
  const height=Math.ceil(analysis.getBoundingClientRect().height);
  if(height>0)selector.style.height=height+'px';
 };
 if(typeof ResizeObserver!=='undefined'){
  const observer=new ResizeObserver(sync);
  observer.observe(analysis);
 }
 window.addEventListener('resize',sync,{passive:true});
 sync();
})();
</script>
<script>
(() => {
'use strict';
const endpoint=<?=json_encode($biEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const viewsEndpoint=<?=json_encode($biViewsEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const financialEndpoint=<?=json_encode($biFinancialEndpoint,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const csrf=<?=json_encode(csrf_token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const $=id=>document.getElementById(id);
function exportTableCsv(tableId,filename){
 const table=$(tableId);if(!table)return;
 const safeCell=cell=>{
  let value=cell.textContent||'';
  // Prevent spreadsheet formula evaluation for exported tenant/module labels.
  if(/^[\s\uFEFF]*[=+@-]/.test(value))value="'"+value;
  return '"'+value.replace(/"/g,'""')+'"';
 };
 const rows=[...table.querySelectorAll('tr')].map(row=>[...row.querySelectorAll('th,td')].map(safeCell).join(','));
 if(!rows.length)return;
 const blob=new Blob(['\uFEFF'+rows.join('\r\n')+'\r\n'],{type:'text/csv;charset=utf-8'});
 const url=URL.createObjectURL(blob),a=document.createElement('a');
 a.href=url;a.download=filename;document.body.append(a);a.click();a.remove();
 setTimeout(()=>URL.revokeObjectURL(url),1000);
}
$('biCsv').addEventListener('click',()=>{if(current)exportTableCsv('biTable','brite-performance-'+safeSettings().from+'-'+safeSettings().to+'.csv')});

let savedViews=[];
function syncViewActions(){const selected=$('biViewList').value;const exists=!!selected&&savedViews.some(v=>v.name===selected);$('biDelete').disabled=!exists;$('biDelete').title=exists?'Delete selected saved view':'Select a saved view to delete';}
$('biViewList').addEventListener('change',async()=>{syncViewActions();const view=savedViews.find(v=>v.name===$('biViewList').value);if(view){applyView(view.config);await load()}});
async function refreshViews(){const r=await fetch(viewsEndpoint,{credentials:'same-origin'});const d=await r.json();if(!r.ok)throw Error(d.error||'Unable to list views');savedViews=(d.views||[]).filter(v=>!v.name.startsWith('Financial · '));const sel=$('biViewList');sel.replaceChildren(new Option('Choose view…',''));savedViews.forEach(v=>sel.add(new Option(v.name,v.name)));syncViewActions();}
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
function metricSummary(series){
 const values=series.points.map(p=>Number(p.value||0));
 if(series.rollup==='weighted'){const weight=series.points.reduce((sum,p)=>sum+Number(p.weight||0),0);return weight?series.points.reduce((sum,p)=>sum+Number(p.value||0)*Number(p.weight||0),0)/weight:0;}
 return values.reduce((a,b)=>a+b,0);
}
function metricDisplay(value,series){
 return value.toLocaleString(undefined,{maximumFractionDigits:2})+(series.unit==='percent'?'%':'');
}
function renderPerformanceKpis(){
 const container=$('biPerformanceKpis');container.replaceChildren();
 if(!current?.series)return;
 current.series.forEach(series=>{
  const card=document.createElement('div');card.className='bi-fin-total';
  const name=document.createElement('small');name.textContent=series.name;
  const total=document.createElement('strong');
  const sum=metricSummary(series);
  total.textContent=metricDisplay(sum,series);
  card.append(name,total);container.append(card);
 });
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
function previousPeriod(from,to){
 const start=new Date(from+'T12:00:00Z'),end=new Date(to+'T12:00:00Z');
 const days=Math.round((end-start)/86400000)+1;
 const format=d=>d.toISOString().slice(0,10);
 const priorEnd=new Date(start.getTime()-86400000);
 const priorStart=new Date(start.getTime()-days*86400000);
 return {from:format(priorStart),to:format(priorEnd)};
}
function renderComparison(currentData,priorData){
 const table=$('biCompareTable'),head=table.tHead||table.createTHead(),body=table.tBodies[0]||table.createTBody();
 head.replaceChildren();body.replaceChildren();
 const h=head.insertRow();
 ['Metric','Current','Previous','Change','Relative change / pp'].forEach(label=>{const th=document.createElement('th');th.textContent=label;h.append(th)});
 const previous=new Map(priorData.series.map(s=>[s.key,s]));
 currentData.series.forEach(series=>{
  const now=metricSummary(series);
  const before=metricSummary(previous.get(series.key)||{points:[],rollup:series.rollup});
  const diff=now-before;
  // Rate differences are percentage points; relative changes can be misleading.
  const percent=series.unit==='percent'
   ?(diff>0?'+':'')+diff.toFixed(2)+' pp'
   :before===0?(now===0?'0%':'N/A'):(diff/Math.abs(before)*100).toFixed(1)+'%';
  const row=body.insertRow();
  [series.name,metricDisplay(now,series),metricDisplay(before,series),(diff>0?'+':'')+metricDisplay(diff,series),percent].forEach(value=>{const td=row.insertCell();td.textContent=value});
 });
}
function resetComparison(){
 const table=$('biCompareTable');table.tHead?.replaceChildren();table.tBodies[0]?.replaceChildren();
}
$('biCompareEnabled').addEventListener('change',()=>{if($('biCompareEnabled').checked)load();else{resetComparison();$('biCompareStatus').textContent='Comparison disabled.'}});
async function load(){
 const id=++requestId;
 if(activeRequest)activeRequest.abort();
 activeRequest=new AbortController();
 const opts=safeSettings();
 if(!opts.metrics.length){current=null;paint();$('biPerformanceKpis').replaceChildren();$('biLegend').replaceChildren();$('biTable').tHead?.replaceChildren();$('biTable').tBodies[0]?.replaceChildren();$('biStatus').textContent='Select at least one series.';return}
 if(opts.metrics.length>8){$('biStatus').textContent='Maximum eight series.';return}
 $('biStatus').textContent='Loading tenant metrics…';
 try{
 const query=new URLSearchParams({...opts,metrics:opts.metrics.join(',')});
 const response=await fetch(endpoint+'?'+query,{credentials:'same-origin',headers:{Accept:'application/json'},signal:activeRequest.signal});
 const data=await response.json();if(!response.ok)throw Error(data.error||'Unable to load data');
 if(id!==requestId)return;
 current=data;paint();showTable();renderPerformanceKpis();$('biStatus').textContent='Loaded '+data.series.length+' series · '+data.from+' through '+data.to;
 if($('biCompareEnabled').checked){
  const prior=previousPeriod(opts.from,opts.to);
  $('biCompareStatus').textContent='Comparing '+prior.from+' through '+prior.to+'…';
  try{
   const queryPrior=new URLSearchParams({...opts,...prior,metrics:opts.metrics.join(',')});
   const responsePrior=await fetch(endpoint+'?'+queryPrior,{credentials:'same-origin',headers:{Accept:'application/json'},signal:activeRequest.signal});
   const previousData=await responsePrior.json();if(!responsePrior.ok)throw Error(previousData.error||'Unable to load preceding period');
   if(id!==requestId||!$('biCompareEnabled').checked)return;
   renderComparison(data,previousData);
   $('biCompareStatus').textContent='Current: '+opts.from+' to '+opts.to+' · Previous: '+prior.from+' to '+prior.to+'. Rates are weighted by appointment counts; other metrics use period totals.';
  }catch(error){if(id===requestId&&error.name!=='AbortError'){$('biCompareStatus').textContent=error.message;resetComparison()}}
 }else{resetComparison()}

 }catch(e){if(id===requestId&&e.name!=='AbortError')$('biStatus').textContent=e.message}
}
function scheduleRefresh(){clearTimeout(refreshTimer);refreshTimer=setTimeout(()=>{const opts=safeSettings();if(opts.from&&opts.to&&opts.from<=opts.to)load()},250)}
$('biMetrics').addEventListener('change',event=>{if(!event.target.matches('.bi-metric'))return;const selected=document.querySelectorAll('.bi-metric:checked');if(selected.length>8){event.target.checked=false;$('biStatus').textContent='Select a maximum of eight series.';return}scheduleRefresh()});
controls.forEach(id=>{['input','change'].forEach(type=>$(id).addEventListener(type,scheduleRefresh))});
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
function imageExport(type,source=chart,filename=null){if(source===chart&&!current)return;const name=filename||exportFilename(type==='image/png'?'png':'jpg');source.toBlob(b=>previewExport(b,name),type,.94)}
function pdfExport(source=chart,filename=null){
 if(source===chart&&!current)return;
 const data=source.toDataURL('image/jpeg',.9).split(',')[1];
 const raw=atob(data),bytes=Uint8Array.from(raw,c=>c.charCodeAt(0));
 const encoder=new TextEncoder(),chunks=[];let offset=0;const positions=[0];
 const add=x=>{const b=typeof x==='string'?encoder.encode(x):x;chunks.push(b);offset+=b.length};
 add('%PDF-1.4\n');
 const obj=(id,content)=>{positions[id]=offset;add(id+' 0 obj\n'+content+'\nendobj\n')};
 obj(1,'<< /Type /Catalog /Pages 2 0 R >>');
 obj(2,'<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
 obj(3,'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 792 612] /Resources << /XObject << /Im0 4 0 R >> >> /Contents 5 0 R >>');
 positions[4]=offset;add('4 0 obj\n<< /Type /XObject /Subtype /Image /Width '+source.width+' /Height '+source.height+' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '+bytes.length+' >>\nstream\n');add(bytes);add('\nendstream\nendobj\n');
 const scale=Math.min(740/source.width,560/source.height);const dw=source.width*scale,dh=source.height*scale;
 const command='q '+dw.toFixed(2)+' 0 0 '+dh.toFixed(2)+' '+((792-dw)/2).toFixed(2)+' '+((612-dh)/2).toFixed(2)+' cm /Im0 Do Q\n';
 obj(5,'<< /Length '+encoder.encode(command).length+' >>\nstream\n'+command+'endstream');
 const xref=offset;add('xref\n0 6\n0000000000 65535 f \n');for(let i=1;i<=5;i++)add(String(positions[i]).padStart(10,'0')+' 00000 n \n');
 add('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n'+xref+'\n%%EOF');
 previewExport(new Blob(chunks,{type:'application/pdf'}),filename||exportFilename('pdf'));
}
$('biPng').addEventListener('click',()=>imageExport('image/png'));
$('biJpg').addEventListener('click',()=>imageExport('image/jpeg'));
$('biPdf').addEventListener('click',pdfExport);
$('biSave').addEventListener('click',async()=>{const name=prompt('Name this Intelligence view:',$('biViewList').value||'My dashboard');if(!name)return;try{await mutateView('POST',name,safeSettings());$('biStatus').textContent='Saved view: '+name}catch(e){$('biStatus').textContent=e.message}});
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
 const financialViewPrefix='Financial · ';
 let financialViews=[];
 const financialViewName=name=>financialViewPrefix+name;
 function syncFinancialViewActions(){
  const name=$('biFinViewList').value;
  const exists=!!name&&financialViews.some(v=>v.name===name);
  $('biFinDelete').disabled=!exists;
  $('biFinDelete').title=exists?'Delete selected financial view':'Select a saved financial view to delete';
 }
 async function refreshFinancialViews(selected=''){
  const response=await fetch(viewsEndpoint,{credentials:'same-origin',headers:{Accept:'application/json'}});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'Unable to list financial views');
  financialViews=(data.views||[]).filter(v=>v.name.startsWith(financialViewPrefix));
  const list=$('biFinViewList');
  list.replaceChildren(new Option('Choose view…',''));
  financialViews.forEach(v=>list.add(new Option(v.name.slice(financialViewPrefix.length),v.name)));
  list.value=financialViews.some(v=>v.name===selected)?selected:'';
  syncFinancialViewActions();
 }
 $('biFinViewList').addEventListener('change',async()=>{
  syncFinancialViewActions();
  const view=financialViews.find(v=>v.name===$('biFinViewList').value);
  if(!view)return;
  const config=view.config||{};
  ['from','to','interval'].forEach(key=>{const id={from:'biFinFrom',to:'biFinTo',interval:'biFinInterval'}[key];if(config[key])$(id).value=config[key]});
  document.querySelectorAll('.bi-fin-metric').forEach(el=>el.checked=Array.isArray(config.metrics)&&config.metrics.includes(el.value));
  await loadFinancial();
 });
 $('biFinSave').addEventListener('click',async()=>{
  const currentName=$('biFinViewList').value.replace(/^Financial · /,'');
  const entered=prompt('Name this financial Intelligence view:',currentName||'My financial dashboard');
  if(entered===null)return;
  const name=entered.trim();
  if(!name){$('biFinStatus').textContent='Enter a name to save this view.';return}
  if(!document.querySelector('.bi-fin-metric:checked')){$('biFinStatus').textContent='Select a financial metric before saving.';return}
  try{
   const config={...finOptions(),view_type:'financial'};
   await mutateView('POST',financialViewName(name),config);
   await refreshFinancialViews(financialViewName(name));
   $('biFinStatus').textContent='Saved financial view: '+name;
  }catch(error){$('biFinStatus').textContent=error.message}
 });
 $('biFinDelete').addEventListener('click',async()=>{
  const name=$('biFinViewList').value;
  if(!financialViews.some(v=>v.name===name)||!confirm('Delete financial view '+name.slice(financialViewPrefix.length)+'?'))return;
  try{
   await mutateView('DELETE',name,null);
   await refreshFinancialViews();
   $('biFinStatus').textContent='Financial view deleted.';
  }catch(error){$('biFinStatus').textContent=error.message}
 });
 const finCanvas=$('biFinChart'),finCtx=finCanvas.getContext('2d');
 const usd=value=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:2}).format(value);
 const axisUsd=value=>Math.abs(value)>=1000000?'USD '+(value/1000000).toFixed(1)+'m':Math.abs(value)>=1000?'USD '+(value/1000).toFixed(1)+'k':usd(value);
 let finCurrent=null,finRequest=null,finTimer=null,finLoaded=false,finSeq=0;
 const finColors=['#2563eb','#059669','#d97706','#9333ea','#dc2626','#0891b2'];
 $('biFinCsv').addEventListener('click',()=>{if(finCurrent)exportTableCsv('biFinTable','brite-financial-'+$('biFinFrom').value+'-'+$('biFinTo').value+'.csv')});
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
 function renderFinancialForecast(){
  const table=$('biFinForecastTable'),head=table.tHead||table.createTHead(),body=table.tBodies[0]||table.createTBody();
  head.replaceChildren();body.replaceChildren();
  if(!$('biFinForecastEnabled').checked||!finCurrent?.series?.length)return;
  const heading=head.insertRow();
  ['Series','Latest period','3-period average','Next-period estimate'].forEach(label=>{const th=document.createElement('th');th.textContent=label;heading.append(th)});
  finCurrent.series.forEach(series=>{
   const buckets=[...new Set(finCurrent.series.flatMap(s=>s.points.map(p=>p.bucket)))].sort();
   const values=new Map(series.points.map(p=>[p.bucket,Number(p.value||0)]));
   const recent=buckets.slice(-3).map(bucket=>values.get(bucket)||0);
   if(!recent.length)return;
   const latest=recent[recent.length-1],average=recent.reduce((a,b)=>a+b,0)/recent.length;
   const row=body.insertRow();
   [series.name,usd(latest),usd(average),usd(Math.max(0,average))].forEach(label=>row.insertCell().textContent=label);
  });
  $('biFinForecastNote').textContent='Illustrative next-period estimate = arithmetic mean of the latest '+Math.min(3,new Set(finCurrent.series.flatMap(s=>s.points.map(p=>p.bucket))).size)+' reporting buckets, treating missing buckets as zero. No seasonality or confidence interval. Outstanding balances and opportunity valuations are snapshots/cohorts, so projections should not be interpreted as expected cash receipts.';
 }
 $('biFinForecastEnabled').addEventListener('change',()=>{
  if(!$('biFinForecastEnabled').checked)$('biFinForecastNote').textContent='Projection disabled.';
  renderFinancialForecast();
 });
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
  if(!options.metrics.length){finCurrent=null;['biFinPng','biFinJpg','biFinPdf','biFinCsv'].forEach(id=>$(id).disabled=true);renderFinancial();renderFinancialDetails();renderFinancialForecast();$('biFinStatus').textContent='Select a financial metric.';return}
  $('biFinStatus').textContent='Loading financial metrics…';
  try{
   const query=new URLSearchParams({...options,metrics:options.metrics.join(',')});
   const response=await fetch(financialEndpoint+'?'+query,{credentials:'same-origin',headers:{Accept:'application/json'},signal:finRequest.signal});
   const data=await response.json();
   if(!response.ok)throw Error(data.error||'Unable to load financial metrics');
   if(seq!==finSeq)return;
   finCurrent=data;finLoaded=true;renderFinancial();renderFinancialDetails();renderFinancialForecast();
   ['biFinPng','biFinJpg','biFinPdf','biFinCsv'].forEach(id=>$(id).disabled=false);
   $('biFinStatus').textContent='Loaded '+data.series.length+' dollar-valued series · '+data.from+' through '+data.to;
  }catch(error){if(seq===finSeq&&error.name!=='AbortError')$('biFinStatus').textContent=error.message}
 }
 function scheduleFinancial(){clearTimeout(finTimer);finTimer=setTimeout(()=>{const opts=finOptions();if(opts.from&&opts.to&&opts.from<=opts.to)loadFinancial()},250)}
 ['biFinFrom','biFinTo','biFinInterval'].forEach(id=>{['input','change'].forEach(type=>$(id).addEventListener(type,scheduleFinancial))});

 const finFilename=ext=>{
  const options=finOptions();
  const slug=value=>String(value).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,45);
  const names=(finCurrent?.series||[]).map(s=>slug(s.name));
  const segment=names.length===1?names[0]:names.length<=3?names.join('-'):names.slice(0,2).join('-')+'-plus-'+(names.length-2);
  return ['brite-intelligence-financial',segment||'overview',options.from+'-to-'+options.to,slug(options.interval)].join('_')+'.'+ext;
 };
 $('biFinPng').addEventListener('click',()=>{if(finCurrent)imageExport('image/png',finCanvas,finFilename('png'))});
 $('biFinJpg').addEventListener('click',()=>{if(finCurrent)imageExport('image/jpeg',finCanvas,finFilename('jpg'))});
 $('biFinPdf').addEventListener('click',()=>{if(finCurrent)pdfExport(finCanvas,finFilename('pdf'))});

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
   try{await refreshFinancialViews()}catch(error){$('biFinStatus').textContent=error.message}
   if(finTab.getAttribute('aria-selected')==='true')loadFinancial();
  }catch(error){$('biFinStatus').textContent=error.message;$('biFinMetrics').textContent='Financial metrics unavailable.'}
 })();
}
})();
</script>

<script>
/* Keep BI's existing chart and series elements intact: move complete panels,
   not their controls, so existing BI handlers and exports keep their references. */
document.addEventListener('DOMContentLoaded',function(){
 if(!window.BriteWidgetLayout)return;
 const makeWorkspace=(panelId,key,definitions)=>{
  const panel=document.getElementById(panelId);
  if(!panel)return;
  const resolved=definitions.map(([id,label,selector,closestPanel])=>{
   const found=panel.querySelector(selector);
   return [id,label,closestPanel?found?.closest('.bi-panel'):found];
  });
  if(resolved.some(entry=>!entry[2]))return;
  const grid=document.createElement('div');
  grid.className='bi-widget-grid';
  grid.id='biWidgetGrid-'+key;
  resolved[0][2].before(grid);
  resolved.forEach(([id,label,node])=>{
   const wrapper=document.createElement('section');
   wrapper.className='bi-widget';
   wrapper.dataset.widgetId=id;
   wrapper.dataset.widgetLabel=label;
   wrapper.setAttribute('aria-label',label+' widget');
   wrapper.append(node);
   grid.append(wrapper);
  });
  window.BriteWidgetLayout.mount(grid,{
   scope: <?=json_encode((string)($tenant['tenant_id']??0).'.'.(string)($user['id']??0),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>+'.intelligence_'+key,
   defaultOrder:definitions.map(entry=>entry[0]),
   endpoint: <?=json_encode(app_url('/api/dashboard_widget_layout.php?dashboard=intelligence_'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>+key,
   csrf: <?=json_encode(csrf_token(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>
  });
 };
 makeWorkspace('biPanelPerformance','performance',[
  ['analysis','Performance analysis','.bi-layout'],
  ['comparison','Period comparison','.bi-layout + .bi-panel'],
  ['series_data','Series data','#biTable',true]
 ]);
 // The series table is nested; move its containing panel instead.
 const financial=document.getElementById('biPanelFinancial');
 if(financial){
  makeWorkspace('biPanelFinancial','financial',[
   ['configuration','Financial configuration','#biFinMetrics',true],
   ['totals','Financial totals','#biFinTotals'],
   ['chart','Financial chart','.bi-fin-plot'],
   ['forecast','Next-period outlook','#biFinForecastTable',true],
   ['series_data','Financial series','#biFinTable',true]
  ]);
 }
 window.dispatchEvent(new Event('resize'));
});
</script>
