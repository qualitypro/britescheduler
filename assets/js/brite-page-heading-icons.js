/* Cycle 90AS — Page title icons. Decorative only; no existing handlers modified. */
(() => {
  'use strict';
  const paths = {
    'dashboard.v2.php':'<rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/>',
    'client-dashboard.php':'<rect x="3" y="3" width="7" height="8" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="6" rx="1"/>',
    'contractor-dashboard.php':'<path d="M3 9h18v12H3zM7 9V5h10v4M3 14h18"/>',
    'scheduling.php':'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M8 15h3"/>',
    'clients.v2.php':'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/>',
    'contractors.v2.php':'<path d="M3 21V7l9-4 9 4v14M3 10h18M9 21v-7h6v7"/>',
    'services.v2.php':'<path d="M4 7h16M4 12h16M4 17h16"/><circle cx="7" cy="7" r="1"/><circle cx="17" cy="12" r="1"/>',
    'billing.php':'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'invoice.php':'<path d="M5 2h14v20l-3-2-4 2-4-2-3 2zM8 8h8M8 12h8"/>',
    'activity-hub.php':'<path d="M3 12h4l3-7 4 14 3-7h4"/>',
    'settings.php':'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
    'my-settings.php':'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    'staff-management.php':'<circle cx="8" cy="8" r="3"/><circle cx="17" cy="9" r="2"/><path d="M2 21v-3a6 6 0 0 1 12 0v3M15 16a5 5 0 0 1 7 5"/>',
    'bidding.php':'<path d="m4 20 8-8M8 5l11 11M10 3l11 11M3 10l11 11"/>',
    'client-locations.php':'<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2"/>',
    'service-locations-map.php':'<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3zM9 3v15M15 6v15"/>',
    'contractor-service-areas.php':'<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    'contractor-availability.php':'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18m-11 6 2 2 4-4"/>',
    'my-availability.php':'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l4 2"/>',
    'contractor-profile.php':'<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    'contractor-qualification-review.php':'<path d="M12 2 4 5v7c0 5 4 8 8 10 4-2 8-5 8-10V5zM8 12l3 3 5-6"/>',
    'client-contractor-directory.php':'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 6h5M17 10h5"/>',
    'client-quote-request.php':'<path d="M5 3h14v18H5zM8 8h8M8 12h8M8 16h4"/>',
    'client-quote-lookup.php':'<path d="M5 3h14v18H5zM8 8h8M8 12h8M16 16l5 5"/>',
    'contractor-quote-inbox.php':'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m3 8 9 7 9-7"/>',
    'marketplace-contractor-profile.php':'<path d="M3 9h18v12H3zM7 9V5h10v4M3 14h18"/>',
    'trends.php':'<path d="M3 20h18M5 16l5-6 4 3 5-9"/>',
    'module.php':'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 17h7M17.5 14v7"/>'
  };
  const moduleIcons = {
    intelligence:'<path d="M3 20h18M5 15l5-6 4 3 5-8"/><circle cx="19" cy="4" r="1"/>',
    crm:'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M17 7h5M17 12h5M17 17h5"/>',
    messaging:'<path d="M3 4h18v14H8l-5 4zM7 9h10M7 13h7"/>'
  };
  const fallback = '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>';
  // Sidebar links reuse the exact SVG drawing used by their destination H1.
  // Keep role visibility, links, accordion behavior and all other nav sections intact.
  const syncedNavigationPages = new Set([
    'activity-hub.php', 'billing.php', 'settings.php', 'my-settings.php',
    'clients.v2.php', 'client-locations.php', 'service-locations-map.php',
    'contractors.v2.php', 'contractor-service-areas.php',
    'contractor-availability.php', 'services.v2.php', 'trends.php'
  ]);
  function syncNavigationIcons() {
    document.querySelectorAll('.brite-sidebar-nav a[href]').forEach(link => {
      const url = new URL(link.href, location.href);
      const page = url.pathname.split('/').pop();
      if (!syncedNavigationPages.has(page) || !paths[page]) return;
      const svg = link.querySelector('.brite-nav-icon svg');
      if (!svg) return;
      // Replace drawing only; retain existing SVG attributes and nav styling.
      svg.innerHTML = paths[page];
    });
  }
  function run() {
    syncNavigationIcons();
    const main = document.getElementById('briteMainContent');
    if (!main) return;
    const page = location.pathname.split('/').pop() || 'dashboard.v2.php';
    const module = new URLSearchParams(location.search).get('module');
    const drawing = page === 'module.php' ? (moduleIcons[module] || paths[page]) : (paths[page] || fallback);
    // Page-level h1 only: skip nested panel headings, dialogs and dynamically inserted cards.
    const title = Array.from(main.querySelectorAll('h1')).find(el =>
      !el.closest('[role="dialog"],dialog,.modal,.brite-activity-hub,.bi-panel,.crm-panel') &&
      !el.classList.contains('brite-page-icon-ready'));
    if (!title) return;
    const icon = document.createElement('span');
    icon.className = 'brite-page-heading-icon';
    icon.setAttribute('aria-hidden','true');
    icon.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + drawing + '</svg>';
    title.prepend(icon);
    title.classList.add('brite-page-icon-ready');
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',run,{once:true});
  else run();
})();
