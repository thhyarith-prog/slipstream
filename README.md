<p align="center"><img src="assets/img/icon-slipstream.png" width="96" height="96" alt="Slipstream icon"></p>

<h1 align="center">Slipstream</h1>

<p align="center">Make WordPress fast: page cache with automatic clearing and preloading, browser caching, deferred or delayed JavaScript, lazy media, YouTube click-to-load, CDN and database clean-up.</p>

<p align="center">Version 1.0.4 · WordPress 6.0+ · PHP 7.4+ · GPL-2.0-or-later · by <a href="https://www.prasatdigital.com">Prasat Digital</a></p>

---

Slipstream is the performance plugin by Prasat Digital. Safe settings are on from the moment you activate it; the options that could affect a theme start off and explain what they do.

**Page cache**
* Pages are stored as static HTML (plus a gzip copy) and sent before WordPress even starts, typically in 1–5 ms instead of hundreds.
* Logged-in users, shopping carts, checkout, account pages, password-protected posts and any response that sets a cookie always get a live page.
* Clearing is automatic and precise: editing a post clears that post, the home page and its archives, not the whole site. Comments, stock changes, menus, widgets, theme and plugin updates are handled too.
* Preload visits your pages in the background so the first visitor already gets the fast version.
* Tracking parameters (utm_*, fbclid, gclid…) still get the cached page.
* If another plugin owns advanced-cache.php or wp-config.php is read-only, Slipstream still serves cached pages as soon as it loads.

**Optimize**
* HTML minify that never touches code or preformatted text.
* Defer JavaScript, keeping the original order: inline code after a deferred script waits for it.
* Delay JavaScript until the first interaction: the biggest PageSpeed gain, with exceptions.
* Lazy-load images and embeds below the fold; YouTube click-to-load saves about 1 MB per video.
* Preconnect and font preload hints, and CDN rewriting.
* Browser caching and compression rules for Apache / LiteSpeed (a ready-to-paste snippet for Nginx).

**Tweaks and database**
* Remove the emoji script, WordPress embeds, admin icons for visitors and head clutter; control the Heartbeat and the number of revisions kept.
* Clean revisions, auto-drafts, trash, spam, expired transients and orphaned data, now or on a schedule.

**Speed test**
The dashboard times your home page built by WordPress and served by Slipstream, so you can see the difference.

## Installation

1. Download the latest release ZIP (or this repository as a ZIP and rename the folder to `slipstream`).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the ZIP and click **Install Now**.
3. Activate it. It appears under the **Prasat Digital** menu, together with the other Prasat Digital plugins you use.

## The Prasat Digital plugin family

| Plugin | What it does |
|---|---|
| [Bastion Security](https://github.com/thhyarith-prog/bastion-security) | Firewall, login security, malware scan, comment spam shield, visitor stats |
| [Lifeboat Backup](https://github.com/thhyarith-prog/lifeboat-backup) | Automatic cloud backups and one-click restore |
| [Slipstream](https://github.com/thhyarith-prog/slipstream) | Page cache and speed optimizations |
| [Compass](https://github.com/thhyarith-prog/compass) | SEO: titles, descriptions, schema, sitemap, redirects |
| [Featherweight](https://github.com/thhyarith-prog/featherweight) | WebP images and WebM videos |

All five share one **Prasat Digital** menu with a one-click **Check my website** score.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

<p align="center"><em>“Every website deserves to stand strong, load fast and stay safe.”</em><br>Made by <a href="https://www.prasatdigital.com">Prasat Digital</a> · Founded by Thyyarith</p>
