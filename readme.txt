=== Slipstream ===
Tags: cache, performance, speed, minify, lazy load
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Make WordPress fast: page cache with automatic clearing and preloading, browser caching, deferred or delayed JavaScript, lazy media, YouTube click-to-load, CDN and database clean-up.

== Description ==

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

== Frequently Asked Questions ==

= Something looks broken after turning on Defer or Delay JavaScript =
Add the script's file name (or a word from the inline code) to the exceptions under Optimize, or switch the option off. Add ?slipstream_off to any URL to see the page without optimizations.

= Does it change wp-config.php? =
It adds one line, define( 'WP_CACHE', true ); // Added by Slipstream, and removes exactly that line when deactivated. If the file is read-only, Slipstream still works in a slightly slower mode.

= What happens when I deactivate it? =
The advanced-cache.php drop-in, the WP_CACHE line, the .htaccess rules and every cached page are removed. Uninstalling also removes the settings.

== Credits ==

* Interface fonts: Inter (The Inter Project Authors) and Noto Sans Khmer (Google), SIL Open Font License 1.1, bundled in assets/fonts.

== Changelog ==

= 1.0.7 =
* Improved: the "Optimize with AI" button has a new Prasat temple design that builds up while your plan is being written and shows a tick when it is ready.
* Improved: the plan now says which AI wrote it.

= 1.0.6 =
* New: Smart AI Optimize (Prasat Premium). Add your Premium key on the Prasat Digital dashboard, then "Optimize with AI" runs a fresh check and Claude writes a step-by-step plan for your site, with a button to each fix. Only scores and check titles are sent: no user names, emails, IP addresses or site address.
* Improved: "Check again" on Dashboard > Updates now looks for new Prasat releases straight away.

= 1.0.5 =
* New: the Prasat Digital overview is now a full dashboard: website health with a severity breakdown, "What to fix first", live tiles for every plugin, recent activity, 7-day charts and the Smart AI Optimize (Premium) card.
* New: Prasat temple loading animations for checks, scans, backups and every progress bar.
* New: cached pages and cache mode appear on the Prasat Digital dashboard.

= 1.0.4 =
* New: updates now come from the plugin's GitHub releases (github.com/thhyarith-prog), with the normal one-click "Update now" and automatic updates.

= 1.0.3 =
* Fix: declares "Update URI", so WordPress never offers an unrelated WordPress.org plugin with the same folder name as an update for this one.

= 1.0.2 =
* New: reports to the one-click "Check my website" on the Prasat Digital page.

= 1.0.1 =
* New: a new app icon for Slipstream and for the Prasat Digital menu, plus a short motto and credit (Made by Prasat Digital, founded by Thyyarith) at the bottom of every Prasat Digital screen.

= 1.0.0 =
* First release.
