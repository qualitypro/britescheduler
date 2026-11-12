import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';

const root = path.resolve(import.meta.dirname);
const base = (process.env.BRITE_URL || 'http://localhost/dashboard').replace(/\/$/, '');
const email = process.env.BRITE_SCREENSHOT_EMAIL;
const password = process.env.BRITE_SCREENSHOT_PASSWORD;
const allow = process.env.BRITE_SCREENSHOT_ALLOW_GIT === '1';
const run = new Date().toISOString().replace(/[:.]/g, '-');
const out = path.join(root, 'runs', run);
const viewports = [
  { name: 'desktop', width: 1440, height: 900 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 390, height: 844 }
];
const pages = [
  ['dashboard','dashboard.v2.php'],['schedule','scheduling.php'],
  ['clients','clients.v2.php'],['contractors','contractors.v2.php'],
  ['services','services.v2.php'],['billing','billing.php'],
  ['activity','activity-hub.php'],['settings','settings.php'],
  ['my-settings','my-settings.php'],['staff','staff-management.php'],
  ['marketplace','bidding.php'],['locations','client-locations.php'],
  ['client-dashboard','client-dashboard.php'],
  ['contractor-dashboard','contractor-dashboard.php'],
  ['contractor-profile','contractor-profile.php'],
  ['intelligence','module.php?module=intelligence'],
  ['crm','module.php?module=crm'],['messaging','module.php?module=messaging']
];
const escape = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
if (!email || !password) {
  console.error('Set BRITE_SCREENSHOT_EMAIL and BRITE_SCREENSHOT_PASSWORD using a dedicated synthetic-data test account.');
  process.exit(2);
}
const browser = await chromium.launch({ headless: true });
const results = [];
try {
  await fs.mkdir(out,{recursive:true});
  for (const viewport of viewports) {
    const context = await browser.newContext({ viewport: {width:viewport.width,height:viewport.height}, reducedMotion:'reduce', colorScheme:'light', serviceWorkers:'block' });
    const page = await context.newPage();
    try {
      await page.goto(base + '/sign-in.v2.php', { waitUntil:'domcontentloaded', timeout:30000 });
      await page.locator('input[name="email"]').fill(email);
      await page.locator('input[name="password"]').fill(password);
      await Promise.all([
        page.waitForURL(url => !url.pathname.endsWith('/sign-in.v2.php'), {timeout:30000}),
        page.locator('form button').first().click()
      ]);
      for (const [name,route] of pages) {
        try {
          const response = await page.goto(base + '/' + route,{waitUntil:'domcontentloaded',timeout:30000});
          await page.waitForTimeout(650);
          if (!response?.ok() || page.url().includes('sign-in.v2.php') || await page.locator('body').innerText().then(s=>/^\s*(Forbidden|Unauthenticated)\s*$/i.test(s))) {
            results.push({viewport:viewport.name,name,status:'skipped (unauthorized/unavailable)'});
            continue;
          }
          const heading = await page.locator('#briteMainContent h1').first().textContent().catch(()=>null);
          const file = viewport.name + '/' + name + '.png';
          await fs.mkdir(path.dirname(path.join(out,file)),{recursive:true});
          await page.screenshot({path:path.join(out,file),fullPage:true,animations:'disabled',timeout:30000});
          results.push({viewport:viewport.name,name,status:'captured',file,heading:heading?.trim()||''});
          console.log('CAPTURED',file);
        } catch(err) {
          results.push({viewport:viewport.name,name,status:'error: '+String(err.message).slice(0,150)});
          console.warn('SKIP',viewport.name,name,String(err.message).slice(0,150));
        }
      }
    } finally {
      await context.close();
    }
  }
  await fs.writeFile(path.join(out,'manifest.json'),JSON.stringify({created:run,base,results},null,2));
  const cards = results.map(x => '<article><h3>'+escape(x.viewport+' / '+x.name)+'</h3><p>'+escape(x.status)+'</p>'+(x.file?'<a href="'+escape(x.file)+'"><img loading="lazy" src="'+escape(x.file)+'" alt="'+escape(x.name)+'"></a>':'')+'</article>').join('\n');
  await fs.writeFile(path.join(out,'index.html'),'<!doctype html><html><head><meta charset="utf-8"><title>Brite Screenshot Gallery</title><style>body{font:14px system-ui;margin:24px;background:#f5f7fb;color:#172554}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px}article{background:white;padding:12px;border-radius:9px;border:1px solid #ddd}img{width:100%;height:240px;object-fit:contain;object-position:top;border:1px solid #ddd}h3{margin:0}p{font-size:12px}</style></head><body><h1>Brite Screenshot Gallery</h1><p>'+escape(run)+'</p><main>'+cards+'</main></body></html>');
  console.log('REPORT',path.join(out,'index.html'));
  console.log('CAPTURED_COUNT',results.filter(x=>x.file).length);
  console.log('SKIPPED_OR_ERRORS',results.filter(x=>!x.file).length);
  if (allow) console.log('To version screenshots: git add -f qa/screenshots/runs/'+run+' && git commit -m "Add approved visual baseline"');
  else console.log('Screenshots remain local by default. Set BRITE_SCREENSHOT_ALLOW_GIT=1 to display explicit Git staging instructions.');
} finally { await browser.close(); }
