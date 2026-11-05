<?php
declare(strict_types=1);
if (!defined('BRITE_MODULE_DISPATCH')) { http_response_code(403); exit; }
$intelligenceSources = [
    'Scheduling' => ['Appointments created','Appointments completed','Cancellation rate','Scheduled hours'],
    'Contractors' => ['Assigned appointments','Completion rate','Contractor utilization'],
    'Clients' => ['Active clients','Repeat booking rate','New clients'],
    'Marketplace' => ['Open listings','Bids submitted','Award conversion'],
    'Finance' => ['Invoice totals','Outstanding balances','Revenue trend'],
];
?>
<style>
.brite-intelligence{display:grid;gap:16px;min-width:0}
.brite-intelligence .bi-header{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.brite-intelligence h1,.brite-intelligence h2{margin:0;color:var(--brite-text)}
.brite-intelligence .bi-grid{display:grid;grid-template-columns:minmax(240px,300px) minmax(0,1fr);gap:14px}
.brite-intelligence .bi-card{border:1px solid var(--brite-border);border-radius:14px;padding:18px;background:var(--brite-surface)}
.brite-intelligence .bi-series{display:grid;gap:8px;margin-top:12px}
.brite-intelligence .bi-series label{display:flex;gap:8px;align-items:center;font-size:.84rem}
.brite-intelligence .bi-series input{width:auto;min-width:0}
.brite-intelligence .bi-series-group{border-top:1px solid var(--brite-border);padding-top:10px}
.brite-intelligence .bi-series-group h3{margin:0 0 7px;font-size:.86rem}
.brite-intelligence .bi-preview{min-height:260px;display:grid;place-items:center;text-align:center;padding:20px;border:1px dashed var(--brite-border-strong);border-radius:12px;background:var(--brite-surface-muted)}
.brite-intelligence .bi-preview p{max-width:500px;color:var(--brite-text-secondary)}
.brite-intelligence .bi-controls{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.brite-intelligence .bi-controls select,.brite-intelligence .bi-controls button{max-width:100%;padding:8px 10px}
@media(max-width:780px){.brite-intelligence .bi-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<section class="brite-intelligence" aria-labelledby="biTitle">
  <div class="bi-header">
    <div><h1 id="biTitle">Intelligence</h1><p class="muted">Explore performance across your business and installed modules.</p></div>
    <span class="muted">BI foundation · 0.1.0</span>
  </div>
  <div class="bi-grid">
    <aside class="bi-card" aria-labelledby="biSeriesTitle">
      <h2 id="biSeriesTitle">Metric catalog</h2>
      <p class="muted">Select series for a future dashboard. Data connections are not enabled in this foundation sprint.</p>
      <div class="bi-series">
      <?php foreach ($intelligenceSources as $group => $metrics): ?>
        <div class="bi-series-group"><h3><?=htmlspecialchars($group,ENT_QUOTES,'UTF-8')?></h3>
          <?php foreach ($metrics as $metric): ?>
            <label><input type="checkbox" class="bi-metric" value="<?=htmlspecialchars($group.' · '.$metric,ENT_QUOTES,'UTF-8')?>"> <?=htmlspecialchars($metric,ENT_QUOTES,'UTF-8')?></label>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </aside>
    <div class="bi-card">
      <div class="bi-header"><h2>Analysis workspace</h2>
        <div class="bi-controls"><label for="biInterval">Group by</label>
          <select id="biInterval"><option>Day</option><option selected>Week</option><option>Month</option><option>Quarter</option></select>
        </div>
      </div>
      <p id="biSelection" aria-live="polite" class="muted">No series selected.</p>
      <div class="bi-preview">
        <div><strong>Data-source integration is next</strong><p>Series selection is available as a workspace foundation. Live charts, module metric providers, saved dashboards, and PNG/JPG/PDF exports will be delivered in subsequent BI sprints. No sample numbers are presented as live tenant data.</p></div>
      </div>
    </div>
  </div>
</section>
<script>
(() => {
  const metrics = [...document.querySelectorAll('.brite-intelligence .bi-metric')];
  const label = document.getElementById('biSelection');
  const update = () => {
    const selected = metrics.filter(input => input.checked).map(input => input.value);
    label.textContent = selected.length
      ? selected.length + ' selected: ' + selected.join(' · ')
      : 'No series selected.';
  };
  metrics.forEach(input => input.addEventListener('change', update));
})();
</script>
