# Cycle 90AN — Persist accordion navigation state

Persist the expanded main-sidebar section in sessionStorage, scoped by tenant label and role. Section changes and submenu navigation save state; page reloads restore it. First visit opens the section with the active link. Fix active-page matching so `client-dashboard.php?book=1` does not also mark the plain dashboard link as selected. Keep exclusive accordion, icons, and indentation. Browser QA pending.
