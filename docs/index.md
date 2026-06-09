---
layout: default
---

<div class="nt-page">

<div class="nt-hero-wrap">
  <img class="hero-banner" src="{{ '/assets/banner.png' | relative_url }}" width="1280" height="640" alt="MAS ChangeHub: Internet Archive and Contabo VPS snapshots">
</div>

<p class="nt-lead">
  Unified snapshot tooling for <strong>NewsTargeted</strong>: Internet Archive (Wayback Machine) captures,
  <strong>Contabo VPS</strong> backups, and Discord Components V2 notifications.
</p>

<div class="nt-actions">
  <a class="nt-btn nt-btn-primary" href="https://github.com/master3395/MAS_ChangeHub">View on GitHub</a>
  <a class="nt-btn nt-btn-ghost" href="https://github.com/master3395/MAS_ChangeHub/releases">Releases</a>
  <a class="nt-btn nt-btn-ghost" href="https://github.com/sponsors/master3395">GitHub Sponsors</a>
</div>

<div class="nt-support-panel">
  <h2>Support development</h2>
  <p>MAS ChangeHub is open source. If it saves you time on backups and monitoring, consider supporting ongoing work.</p>
  <div class="nt-support-grid">
    <a class="nt-btn nt-btn-support" href="https://www.paypal.com/paypalme/KimBS" target="_blank" rel="noopener noreferrer">PayPal</a>
    <a class="nt-btn nt-btn-support" href="https://www.patreon.com/newstargeted" target="_blank" rel="noopener noreferrer">Patreon</a>
    <a class="nt-btn nt-btn-support" href="https://github.com/sponsors/master3395" target="_blank" rel="noopener noreferrer">GitHub</a>
  </div>
</div>

<hr class="nt-divider">

## What it includes

<div class="nt-cards">
  <div class="nt-card">
    <p class="nt-card-title">Internet Archive</p>
    <p class="nt-card-entry"><code>website_snapshot.sh</code> / <code>./menu.sh</code></p>
    <a href="../guides/README.md">Archive guide</a>
  </div>
  <div class="nt-card">
    <p class="nt-card-title">Contabo VPS</p>
    <p class="nt-card-entry"><code>contabo/snapshot-manager.php</code></p>
    <a href="../guides/CONTABO-README.md">Contabo guide</a>
  </div>
  <div class="nt-card">
    <p class="nt-card-title">Discord CV2</p>
    <p class="nt-card-entry"><code>lib/discord_cv2_*</code></p>
    <a href="../guides/DISCORD-CV2-INTEGRATION.md">CV2 guide</a>
  </div>
</div>

## Quick start

```bash
git clone https://github.com/master3395/MAS_ChangeHub.git
cd MAS_ChangeHub
cp config/snapshot_config.conf.example config/snapshot_config.conf
cp contabo/config.php.example contabo/config.php
chmod 600 config/snapshot_config.conf contabo/config.php
./menu.sh
```

## Documentation index

- [Configuration file guide](../guides/CONFIG-FILE-GUIDE.md)
- [Schedule and cron](../guides/SCHEDULE-FREQUENCY-GUIDE.md)
- [Contabo API setup](../guides/CONTABO-API-SETUP-GUIDE.md)
- [Quick start schedule](../guides/QUICK-START-SCHEDULE.txt)
- [Full README on GitHub](../README.md)

## Community

- [Contributing](../CONTRIBUTING.md)
- [Security policy](../SECURITY.md)
- [Code of Conduct](../CODE_OF_CONDUCT.md)
- [Report a bug](https://github.com/master3395/MAS_ChangeHub/issues/new/choose)

## License

MIT. See [LICENSE](../LICENSE).

</div>
