# GBCloudflarePurge

Purges the Cloudflare edge cache when wiki content changes. Instead of hooking
individual page events, `CloudflareEventRelayer` subscribes to core's
`cdn-url-purges` relay channel (`$wgEventRelayerConfig` in LocalSettings), so
it receives every URL MediaWiki itself decides to purge: direct edits, deletes,
moves, undeletes, `?action=purge`, redirects, the HTMLCacheUpdateJob fan-out
for pages transcluding an edited template/module, and file + thumbnail URLs on
re-upload. Core batches and de-duplicates URLs (CdnCacheUpdate is a mergeable
post-send deferred update), and the relayer chunks them 30 per API call.
The extension is a no-op when the Cloudflare credentials are unset, so it's
safe to load in dev.

## Configuration

Set via env in `config/LocalSettings.php`:

| Variable | Purpose |
|---|---|
| `CLOUDFLARE_ZONE_ID` | Zone id for giantbomb.com (dashboard → Overview) |
| `CLOUDFLARE_API_TOKEN` | API token with `Zone → Cache Purge → Purge` permission only |
| `CDN_MAX_AGE` | `$wgCdnMaxAge` in seconds (default 3600) |

## Required Cloudflare zone setup

MediaWiki only emits `Cache-Control: s-maxage=...` for anonymous page views
(that's what `$wgUseCdn = true` does), but Cloudflare does not cache HTML by
default and **ignores `Vary: Cookie`** for non-image content. Both rules below
are required — the second one is what keeps logged-in users from being served
anonymous cached pages.

1. **Cache Rule — make wiki HTML cacheable**
   - When: URI Path starts with `/wiki/`
   - Then: Eligible for cache, "Respect origin" edge TTL (honors `s-maxage`)

2. **Cache Rule — bypass for sessions (MUST be ordered AFTER rule 1: Cloudflare
   cache rules are last-match-wins, so the bypass only sticks if it comes later
   in the list than the eligible-for-cache rule)**
   - When: URI Path starts with `/wiki/` AND the Cookie header contains any of:
     - `mwSession` (core session, `$wgSessionName`; as a substring this also
       matches GbSessionProvider's `mwSessionCookieName` session cookie)
     - `gb_wiki` (GBN JWT auth cookie read by GbSessionProvider)
     - `UserID` or `Token` (core remember-me cookies, unprefixed since
       `$wgCookiePrefix = ""`)
   - Then: Bypass cache
   - Expression form:
     `starts_with(http.request.uri.path, "/wiki/") and not starts_with(http.request.uri.path, "/wiki/load.php") and (http.cookie contains "mwSession" or http.cookie contains "gb_wiki" or http.cookie contains "UserID" or http.cookie contains "Token")`
   - The `load.php` carve-out keeps ResourceLoader CSS/JS edge-cached for
     logged-in users too: RL responses are keyed entirely by URL, and
     user-private module requests come back `Cache-Control: private`, which
     "Respect origin" refuses to store.

   Missing any of these means a logged-in user can be served the anonymous
   cached copy of a page (Cloudflare ignores `Vary: Cookie`).

Do not enable "Cache Everything" zone-wide; `s-maxage=0` responses (edit forms,
special pages, logged-in views) must stay uncached.

## File cache interaction

Prod also runs `$wgUseFileCache`. File-cache hits are served before core sets
the CDN TTL, so without help they'd emit `Cache-Control: private` and the edge
would never cache real page views. This extension's `HTMLFileCache::useFileCache`
hook sets the TTL on that path, so both layers emit `s-maxage` consistently.

## What is NOT purged

Pages whose rendering depends on SMW `#ask`/`mw.smw` query results affected by
an edit to *another* page are not purged at the edge (SMW's dependency store
invalidates the parser cache, but that invalidation does not flow through the
CDN purge relay). They stay stale until `$wgCdnMaxAge` expires — the reason
the TTL is a modest 1 hour rather than matching the 7-day parser cache.
Template-transclusion fan-out IS covered (core's HTMLCacheUpdateJob relays
those URLs), as are file/thumbnail URLs on re-upload.

## Credential gating

`$wgUseCdn` is only enabled in prod when `CLOUDFLARE_ZONE_ID` and
`CLOUDFLARE_API_TOKEN` are both set — edge-caching without working purges
would leave edits invisible to anons until TTL expiry with no error anywhere.
If the token is ever revoked, rotate it in the environment rather than
removing it; an invalid token logs `purge failed` to the GBCloudflarePurge
debug channel.

## Verifying

```sh
# anon view should show s-maxage and (after two hits) an edge HIT
curl -sI https://giantbomb.com/wiki/Main_Page | grep -i 'cache-control\|cf-cache-status'

# after editing a page, its cf-cache-status should return to MISS/EXPIRED
```
