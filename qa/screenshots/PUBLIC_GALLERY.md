# Publishing approved screenshots to GitHub Pages

The public gallery is served from `docs/gallery/` and contains a keyboard-accessible lightbox, previous/next buttons and role/viewport filters. **Captures in `qa/screenshots/runs/` are private by default and are never automatically published.**

1. Run the existing Playwright capture for test accounts.
2. Open each screenshot and check for names, addresses, emails, tokens, real customer records, or other sensitive data. Use only synthetic/approved content. Redact images before copying if needed.
3. Copy **only approved PNG files** into `docs/gallery/images/` with unique descriptive filenames such as `owner-dashboard-desktop.png`. Never publish whole run directories or session data.
4. Add each approved image to `docs/gallery/manifest.json`, e.g.:

```json
{
  "screenshots": [
    {
      "title": "Owner Dashboard",
      "role": "owner",
      "viewport": "desktop",
      "src": "images/owner-dashboard-desktop.png"
    }
  ]
}
```

5. Commit and push the approved PNGs and manifest. The workflow `.github/workflows/screenshot-gallery-pages.yml` deploys the gallery on pushes to the configured branches or manually. **Repository Settings → Pages → Build and deployment → Source must be set to GitHub Actions** by a repository administrator. GitHub Pages and workflow permissions must be enabled.
6. Gallery URL after successful deployment is normally `https://qualitypro.github.io/britescheduler/` when using this workflow. Confirm the actual deployment URL in Actions → Publish approved screenshot gallery.

Important: the workflow runs for both `main` and the current feature branch; whichever run deploys last determines the live gallery. For a stable public release, restrict it to `main` once the feature is merged. Do not upload unreviewed screenshots.
