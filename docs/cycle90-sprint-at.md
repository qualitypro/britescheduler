# Cycle 90AT — Automated screenshot capture

Adds a separate Playwright screenshot tool under `qa/screenshots/`, with a desktop/tablet/mobile capture matrix, authenticated test-account login, route-based capture, skip/error manifest, and HTML gallery. Captures default to gitignored local `qa/screenshots/runs/` to protect private data; explicitly reviewed and sanitized captures can be force-added to the repository. Does not alter the application UI or behavior. Requires local Node/npm and Chromium installation; no live browser run performed remotely.
