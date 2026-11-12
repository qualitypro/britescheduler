# Brite Scheduler screenshot automation

Read-only Playwright capture for the authenticated application. Does not change page handlers or application UI.

## Setup

From the repository root:

```bash
cd qa/screenshots
npm install
npx playwright install chromium
```

Use a **dedicated test account with synthetic data**. The test account's role and enabled tenant modules determine which pages can be captured. Credentials must never be committed.

```bash
export BRITE_URL=http://localhost/dashboard
export BRITE_SCREENSHOT_EMAIL='your-test-account@example.test'
read -rsp 'Test account password: ' BRITE_SCREENSHOT_PASSWORD; echo
export BRITE_SCREENSHOT_PASSWORD
npm run screenshots
unset BRITE_SCREENSHOT_PASSWORD
```

Reports: `qa/screenshots/runs/<timestamp>/index.html`, with PNG files organized by desktop/tablet/mobile and a JSON manifest. Capture runs are gitignored by default because authenticated screenshots may contain private information. To **explicitly version an approved, sanitized** run, use `git add -f qa/screenshots/runs/<timestamp>/` and commit/push yourself after reviewing all screenshots. Do not add `.auth`, `node_modules`, secrets or unsanitized customer data.

The tool does not automatically approve screenshots as regression baselines. Captures of role-restricted pages may be skipped. A running local XAMPP app and reachable database are required. Capture script uses no write APIs, but logging in updates the test account's last-login timestamp.
