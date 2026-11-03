/* Brite Scheduler: contextual tooltips for shared navigation and controls. */
(() => {
 'use strict';
 const descriptions={
  'Brite Scheduler':'Return to your dashboard.',
  'Marketplace':'Browse available appointments, bids, and awards.',
  'Activity Hub':'Review, filter, and dismiss your notifications.',
  'Dashboard':'View your workspace overview and key metrics.',
  'My Dashboard':'View your appointments and personal dashboard.',
  'Schedule':'View and manage scheduled appointments.',
  'Clients':'Browse and manage client records.',
  'Contractors':'Browse contractor profiles and assignments.',
  'Locations':'Manage client service addresses.',
  'My Locations':'Manage the addresses where you receive services.',
  'Service Locations Map':'Explore client service locations on a map.',
  'Service Areas':'Manage contractor coverage areas and distances.',
  'My Service Areas':'Review and update the areas where you work.',
  'Availability':'Manage contractor working availability.',
  'My Availability':'Set the times you are available for work.',
  'Services':'Manage available services and their prices.',
  'Trends':'Explore booking, assignment, and revenue trends.',
  'Billing':'Review invoices and billing activity.',
  'Settings':'Configure tenant and application settings.',
  'My Settings':'Update your account preferences and display options.',
  'Book a Service':'Start a new service booking request.',
  'Sign Out':'End your session securely.',
  'Refresh':'Reload the latest information from the server.',
  'Previous':'Go to the previous page of results.',
  'Next':'Go to the next page of results.',
  'Previous month':'View trends for the preceding month.',
  'Next month':'View trends for the following month.',
  'Add client':'Open the form to create a client record.',
  'Create client':'Save a new client record.',
  'Save changes':'Save the changes you made to this record.',
  'Cancel':'Discard unsaved edits and close this editor.',
  'Dismiss':'Dismiss this notification without changing the underlying task.',
  'Outstanding':'Show notifications that have not been dismissed.',
  'All':'Show both outstanding and dismissed notifications.',
  'Open full Activity Hub':'Open the full notification history and filters.',
  'Review booking':'Open the appointment to review its details.',
  'Review Booking':'Open the appointment to review its details.',
  'Open navigation':'Open the application navigation menu.',
  'Close navigation':'Close the application navigation menu.',
  'Close activity hub':'Close the notification panel.',
  'Open activity hub':'Open your recent notifications.',
  'Collapse activity hub':'Close the notification panel.'
 };
 const clean=value=>String(value||'').replace(/\\s+/g,' ').trim().replace(/ [▲▼]$/,'');
 const selector='a[href],button,[role="button"],[role="menuitem"],summary,input[type="submit"],input[type="button"]';
 let active=null,tip=null,owner=null;
 function labelFor(el){
  return clean(el.dataset.tooltip||el.getAttribute('aria-label')||el.textContent||el.value||'');
 }
 function description(el){
  if(el.dataset.tooltip)return clean(el.dataset.tooltip);
  const label=labelFor(el);
  if(!label)return '';
  const direct=descriptions[label]||descriptions[label.replace(/\\s*\\(\\d+\\)$/,'')];
  if(direct)return direct;
  if(el.matches('.brite-list-sort,[data-sort]')||/^sort by /i.test(label))return 'Sort the list by '+label.replace(/^sort by /i,'').replace(/ [▲▼]$/,'')+'. Select again to reverse the order.';
  if(el.matches('.brite-sidebar-nav a'))return 'Open '+label+' in your workspace.';
  if(el.matches('summary'))return 'Expand or collapse '+label.toLowerCase()+' details.';
  if(/^edit\\b/i.test(label))return 'Edit '+label.replace(/^edit\\s*/i,'').toLowerCase()+' details.';
  if(/^delete\\b|^remove\\b/i.test(label))return 'Remove the selected item. Review any confirmation before continuing.';
  if(/^save\\b/i.test(label))return 'Save your current changes.';
  if(/^add\\b|^new\\b|^create\\b/i.test(label))return 'Open or create '+label.replace(/^(add|new|create)\\s*/i,'').toLowerCase()+'.';
  if(/^view\\b|^open\\b|^show\\b/i.test(label))return 'Open '+label.replace(/^(view|open|show)\\s*/i,'').toLowerCase()+' details.';
  if(/^filter\\b/i.test(label))return 'Filter the displayed results.';
  if(/^search\\b/i.test(label))return 'Search the current records.';
  if(/^refresh\\b|^reload\\b/i.test(label))return 'Reload the latest information.';
  if(/^submit\\b/i.test(label))return 'Submit the information in this form.';
  if(/^back\\b/i.test(label))return 'Return to the previous step or screen.';
  if(/^next\\b/i.test(label))return 'Continue to the next step or page.';
  if(/^previous\\b/i.test(label))return 'Return to the previous step or page.';
  if(el.matches('a[href]'))return 'Open '+label+'.';
  if(el.matches('button,input[type="submit"],input[type="button"]'))return label+' — activate this action.';
  return '';
 }
 function hide(){
  if(owner&&tip&&owner.getAttribute('aria-describedby')===tip.id)owner.removeAttribute('aria-describedby');
  if(tip)tip.hidden=true;
  owner=null;
 }
 function show(el){
  if(!el||el.disabled||el.getAttribute('aria-disabled')==='true'||el.closest('[hidden],[inert]')){hide();return;}
  const message=description(el);
  if(!message){hide();return;}
  if(!tip){tip=document.createElement('div');tip.id='brite-global-tooltip';tip.className='brite-global-tooltip';tip.setAttribute('role','tooltip');tip.hidden=true;document.body.append(tip);}
  if(owner!==el)hide();
  owner=el;tip.textContent=message;tip.hidden=false;
  if(!el.hasAttribute('aria-describedby'))el.setAttribute('aria-describedby',tip.id);
  const r=el.getBoundingClientRect(),box=tip.getBoundingClientRect(),margin=8;
  const x=Math.max(margin,Math.min(window.innerWidth-box.width-margin,r.left+r.width/2-box.width/2));
  const above=r.top-box.height-10,below=r.bottom+10;
  const y=above>=margin?above:Math.min(below,window.innerHeight-box.height-margin);
  tip.style.left=Math.round(x)+'px';tip.style.top=Math.round(Math.max(margin,y))+'px';
 }
 document.addEventListener('pointerover',event=>{if(event.pointerType==='touch')return;const el=event.target.closest?.(selector);if(el!==owner)show(el);});
 document.addEventListener('pointerout',event=>{if(owner&&!owner.contains(event.relatedTarget))hide();});
 document.addEventListener('focusin',event=>show(event.target.closest?.(selector)));
 document.addEventListener('focusout',event=>{if(owner&&owner.contains(event.target))hide();});
 document.addEventListener('keydown',event=>{if(event.key==='Escape')hide();});
 document.addEventListener('scroll',hide,true);
 window.addEventListener('resize',hide);
 document.addEventListener('pointerdown',hide);
})();
