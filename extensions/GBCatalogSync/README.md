# GBCatalogSync

Pushes `{guid, title, action}` to giant-bomb-next's `POST /api/wiki/catalog-sync`
whenever a game page (NS_MAIN, direct child of `Games/`) is saved, deleted,
moved, or undeleted, so the site's catalog row appears/refreshes within seconds.

- `guid` comes from the page's `| Guid=` template param (legacy `3030-<id>` or
  UUID). Pages without one are skipped.
- `title` is a seed only (`| Name=` param, else the title leaf); the receiver
  re-hydrates the real title shortly after.
- Delivery is fire-and-forget: queued post-send, one retry on 5xx/transport
  failure. The receiver is idempotent (60s per-key cooldown) and a reconcile
  job on the site backstops anything missed here.
- CLI/maintenance edits are skipped (deferred updates run inline there and
  would serialize bulk runs); bulk loads go through the site's one-time prime
  script instead.

## Guid uniqueness enforcement

Catalog rows key on guid, so a duplicate lets one page hijack another's row.
`EditFilterMergedContent` (the same hook point AbuseFilter uses, so it covers
wikitext editor, VE, PageForms, and API edits) rejects a save that introduces
a `| Guid=` another NS_MAIN page already owns, looked up via SMW `Has guid`.

- Only NEW or CHANGED guids are checked; pages already carrying a duplicate
  stay editable (that's an audit problem, not a block).
- The `gbcatalogsync-guid-override` right (sysop + bot by default) skips the
  check, so identifier merges and bot repairs keep working.
- Fails open if SMW can't answer, and SMW job-queue lag leaves a small race
  window after a save - a slipped duplicate is caught by reconcile/audit, not
  here.
- Toggle: `$wgGBCatalogSyncEnforceUniqueGuid` (default true, independent of
  the push being enabled).

## Config

| Setting | Env | Default |
| --- | --- | --- |
| `$wgGBCatalogSyncEnabled` | set when key present | `false` |
| `$wgGBCatalogSyncEndpoint` | `WIKI_CATALOG_SYNC_ENDPOINT` | `https://giantbomb.com/api/wiki/catalog-sync` |
| `$wgGBCatalogSyncKey` | `WIKI_CATALOG_HOOK_KEY` | empty |

The key is a dedicated shared secret (deliberately not the resolve key, so
either direction can rotate alone). The same value must be configured on the
giant-bomb-next service; its receiver returns 503 until it is.

Failures log through `wfLogWarning` with the guid, action, and HTTP status
(a 401 there means the keys don't match).

Dev note: the default endpoint is production. If you set the key locally for
testing, also point `WIKI_CATALOG_SYNC_ENDPOINT` at your local Next instance
(e.g. `http://host.docker.internal:3000/api/wiki/catalog-sync`) so local edits
don't push at prod.
