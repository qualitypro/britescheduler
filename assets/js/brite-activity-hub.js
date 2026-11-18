/* Activity Hub header badge: navigation only, no popout. */
(() => {
 'use strict';
 const link=document.getElementById('briteActivityHubToggle');
 const badge=document.getElementById('briteActivityHubToggleCount');
 if(!link||!badge)return;
 const base=(window.BRITE_BASE||'/dashboard').replace(/\/$/,'');
 async function refresh(){
  try{
   const response=await fetch(base+'/api/user_notifications.php',{credentials:'same-origin',cache:'no-store'});
   if(!response.ok)return;
   const data=await response.json();
   if(!data.ok)return;
   const unread=Math.max(0,Number(data.unread_count)||0);
   badge.textContent=String(unread);
   link.setAttribute('aria-label','Activity Hub, '+unread+' unread notifications');
  }catch(error){/* Badge refresh must not interfere with navigation. */}
 }
 refresh();
 window.setInterval(()=>{if(document.visibilityState==='visible')refresh();},60000);
})();
