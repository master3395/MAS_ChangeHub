# Changelog (tool)

This is the changelog of the **Changelog Announcement tool itself**, not the
log of announcements sent (see `logs/announcements.jsonl`).


## 09.06.2026

### Richer Components V2 announcements

- **Added:** `modules/renderer/layout_entry_enrichment.php` for Details, At a glance, release time, and footer bot/website promo blocks.
- **Changed:** `futuristic_layout.php` composes enrichment blocks before category bullets.
- **Changed:** `json_source.php` reads `summary`, `scope`, `event`, `operator`, `legacy_backup`, `notes[]`, and `time`.
- **Added:** Default `promo_site_url` in `config.php`.
- **Added:** Cursor rule `components-v2-promo.mdc`.

## 1.4.0 - 25/05/2026

### Added

- 7 new category logical emojis covering the standard Keep-a-Changelog
  categories (each ships with a sensible Unicode fallback today; drop a
  `.png` into `assets/icons8/<name>.png` and re-run `sync-emojis` to
  swap to a Discord application emoji):
  - `nt_added`        (`➕`)   header "Added"
  - `nt_changed`      (`🔄`)   header "Changed"
  - `nt_fixed`        (`🔧`)   header "Fixed"
  - `nt_removed`      (`🗑️`)  header "Removed"
  - `nt_security`     (`🛡️`)  header "Security"
  - `nt_deprecated`   (`⚠️`)  header "Deprecated"
  - `nt_performance`  (`🚀`)   header "Performance"
- New module `modules/emoji/category_map.php`: resolves a body heading
  ("Added", "Changed", "Fixed", or free-form text) into a registry
  emoji name with exact, substring, and verified-intent matching.
- Registry can include `_section*` divider keys for readability; the
  loader filters them out so they never look like real emojis.

### Changed

- Renamed logical emoji `nt_external_link` -> `nt_internal_link` (same
  PNG asset, accurate name: every button points back to NewsTargeted
  internal pages, not third-party sites). The file moved from
  `assets/icons8/nt_external_link.png` to
  `assets/icons8/nt_internal_link.png`.
- Layout: the heading mapping helper now delegates to
  `Changelog_Category_Map::resolve()`; the layout module dropped a few
  lines to make room for future glyph helpers.

### Operator notes

- Re-announced dashboard v5.1.7 (`1508312636629057586`) and
  webhook-proxy v4.0.10 (`1508312641557368894`) to MAS_ChangeHub to
  verify the new category headers render correctly.

## 1.3.0 - 25/05/2026

### Added

- Application emoji support. Logical names (`nt_workflow`, `nt_settings`,
  `nt_verified`, `nt_download`, `nt_external_link`) render as
  `<:name:application_emoji_id>` when uploaded, otherwise fall back to
  Unicode glyphs so the layout still works without any setup.
- Local PNG icons committed under `assets/icons8/` (Icons8 Fluent Systems
  Filled style, all under 2 KB each, well below Discord's 256 KB limit).
- `assets/emoji-registry.json`: declarative source of truth that maps
  each logical name to its local PNG, Icons8 metadata, Unicode fallback,
  and `application_emoji_id` (filled in by sync).
- New module `modules/emoji/emoji_registry.php`: read/write the registry,
  resolve mentions (`<:name:id>`) and button-emoji tokens (`name:id`).
- New module `modules/emoji/emoji_sync.php`: uploads local PNGs as
  Discord application emojis via
  `POST /applications/{app_id}/emojis` (Discord API v10), reuses any
  emoji whose name already exists, and writes resolved IDs back into
  the registry.
- New CLI subcommands:
  - `emoji-list` shows the registry state (which emojis are mapped,
    which still fall back to Unicode).
  - `sync-emojis` runs the upload/sync (requires
    `CHANGELOG_ANNOUNCEMENT_DISCORD_APP_ID` and
    `CHANGELOG_ANNOUNCEMENT_DISCORD_BOT_TOKEN`).
- Renderer integrates the registry for header glyph, body section
  headers (heuristic: headings matching `/verif|test|pass|done|added|fix/i`
  use `nt_verified`, everything else uses `nt_settings`), footer metadata
  glyph, and button emojis (`nt_download` for "View Full Changelog",
  `nt_external_link` for "Visit Site" and "Jump to vX").

### Changed

- Removed the inline `CATEGORY_STYLE` constant in the renderer; the
  emoji choice for each section is now driven by the registry helper
  `sectionEmojiName()` so operators can add new categories without
  editing the layout.

### Security

- Bot token is never accepted from CLI flags or logged; it is only read
  via `CHANGELOG_ANNOUNCEMENT_DISCORD_BOT_TOKEN` in `config.php` /
  `bin/announce-changelog.env` (mode 600). Sync uses
  `Authorization: Bot ...`, never embeds the token in URLs.
- Application emoji IDs are validated against the snowflake regex
  before being written back into the registry.
- Registry write is atomic (`.tmp` + `rename`) with restrictive perms.

## 1.2.0 - 25/05/2026

### Added

- **Per-channel branding** (all optional):
  - `webhook_username` (env `*_NAME`): overrides the Discord webhook
    display name per channel.
  - `avatar_url` (env `*_AVATAR`): overrides the per-message avatar.
  - `logo_url` (env `*_LOGO`): renders a full-width hero image at the
    very top of the message using a Components V2 MediaGallery (type 12).
  - `thumbnail_url` (env `*_THUMB`): wraps the title block in a Section
    with a right-side Thumbnail accessory (type 9 + type 11).
  - `footer_message` (env `*_FOOTER`): extra subtle text line under the
    auto metadata line (still inside the third row).
- **Multi-webhook fan-out per channel**:
  - `webhook_url` now accepts a single URL or a comma-separated list.
  - New `webhook_urls` array (env `*_URLS`, comma-separated) for explicit
    list-form configuration.
  - The sender POSTs the same payload to every resolved URL; per-URL
    result is tracked in the announcement log under `destinations[]`.
  - Dedupe key is still `{channel, version}` (not per-URL), so a single
    logical announcement is recorded once per channel even when fanned
    out to several Discord channels.
- **CLI override** `--to=URL,URL,...` lets you bypass the channel's
  configured webhook list for one send (e.g. for ad-hoc tests).
- `list` output now shows the count of webhooks and which branding
  fields are populated per channel.

### Changed

- Renderer: title block now becomes a Section (type 9) when a thumbnail
  is configured; otherwise stays as a plain TextDisplay.
- Renderer: hero logo (when set) renders before the neon banner line.
- Footer row layout: ping line, metadata line, optional custom footer
  message, then the action row of link buttons.
- Layout module split: text helpers (`escapeMd`, `oneLine`,
  `norwegianDate`) extracted to `modules/util/text_helpers.php` to keep
  every module strictly under 500 lines.

### Operator notes

- `webhook-proxy` v4.0.10 was re-announced live during the upgrade and
  rendered as expected (message id `1508308442094243910`); `dashboard`
  v5.1.7 (`1508308456195489875`) and `bot` v5.1.7 (`1508308460750635029`)
  also verified.

## 1.1.0 - 25/05/2026

### Added

- Multiple role pings per channel via `notify_role_ids` (array). The
  existing `notify_role_id` (singular) keeps working and is folded into
  the same list.
- User pings per channel via `notify_user_ids` (array).
- Per-channel env support: `*_ROLES` and `*_USERS` accept a comma-
  separated list of snowflake IDs, e.g.
  `CHANGELOG_ANNOUNCEMENT_WEBHOOK_PROXY_ROLES='111...,222...'`.
- CLI overrides for one-off sends:
  - `--ping-roles=ID,ID,...` adds extra roles for this send.
  - `--ping-users=ID,ID,...` adds extra users for this send.
  - `--no-pings` suppresses every configured ping (silent post).
- Footer line now shows the actual ping list as one compact row:
  `🔔 <@&111> <@&222>  •  👤 <@333>`.
- `list` output now shows `roles=N users=N` per channel.
- Announcement log entries (`logs/announcements.jsonl`) now include the
  resolved `pinged.{roles,users}` arrays for audit.

### Security

- `allowed_mentions.roles` and `.users` are still a strict whitelist of
  only the IDs we resolved (5-25 digit snowflake validation). `@everyone`,
  `@here`, and any mention that appears in the changelog body cannot ping
  anyone unintended. Each list is capped at 100 (Discord's limit).

## 1.0.1 - 25/05/2026

### Fixed

- Webhook POSTs of Components V2 messages now always include
  `with_components=true` on the request URL when the
  `IS_COMPONENTS_V2` (`1 << 15`) flag is set on the payload. Without it
  Discord silently ignores the components array and rejects the request as
  HTTP 400 "Cannot send an empty message" (code 50006). Verified by sending
  the v4.0.10 entry through the test webhook (HTTP 200, message id
  `1508305287894012046`).

## 1.0.0 - 25/05/2026

### Added

- Initial CLI (`cli.php`) with `help`, `list`, `show`, `preview`, `send`.
- Channel registry in `config.php` with 4 default channels:
  `webhook-proxy` (MariaDB), `dashboard` (JSON), `api` (HTTPS feed),
  `bot` (JSON + `@Notify` role).
- Source adapters: `mariadb`, `json`, `http`, behind a single interface.
- HTML to Discord-markdown converter for `<h5>/<ul>/<li>/<code>/<a>` blocks.
- Futuristic Components V2 layout with three logical rows:
  - Header (neon banner, project tag, version pill, badge, release date,
    summary).
  - Body (sub-sections from HTML or Added/Changed/Fixed categories).
  - Footer (notify role ping, compact metadata, link buttons).
- Discord webhook poster with retries, 429 backoff, and `?wait=true` so the
  caller receives the message id.
- Append-only `logs/announcements.jsonl` to dedupe re-runs (override with
  `--force`).
- Optional `bin/announce-changelog` bash wrapper that sources
  `bin/announce-changelog.env` for per-host secrets.
- `.htaccess` denies all HTTP access; CLI refuses to run under PHP-FPM.
- SQL mirror schema in `sql/announcement_log.sql`.

### Security

- Secrets read from `config.php` (and the per-project `config.php` files
  via short subprocess includes); never stored anywhere else.
- `allowed_mentions` only ever includes the explicit notify role per channel;
  `@everyone`, `@here`, random users, and unrelated roles cannot be pinged.
- Payloads are byte-capped (64 KB) and text-capped (per-block and total)
  before send.

### Norwegian dates

- All operator-visible dates use the `dd/mm/yyyy` form (workspace rule).
- Footer timestamps use `dd/mm/yyyy kl. HH:MM` (Europe/Oslo).
