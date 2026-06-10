<?php
/**
 * Futuristic 3-row Components V2 layout for a changelog entry.
 *
 * Visual structure (one Container, three logical rows separated by full-width
 * dividers):
 *
 *   ROW 1 -- HEADER
 *     Neon banner line:    ▰▰▰  CHANGELOG  ▰▰▰
 *     Title:               # 🛰️ {project_tag} » v{version}
 *     Sub-line:            > -# {Patch/Major}  •  Released {dd/mm/yyyy}
 *     Summary:             "{title}"
 *
 *   ROW 2 -- BODY
 *     Either:
 *       - Multiple sub-sections from HTML (h5 + bullets), or
 *       - Categories block (Added / Changed / Fixed) from JSON sources.
 *
 *   ROW 3 -- FOOTER
 *     Notify ping (optional): role mention pinned at top of row
 *     Compact metadata line:  > -# ⚡ NewsTargeted • {dd/mm/yyyy kl. HH:MM}
 *     Action row buttons:  [View Full Changelog]  [Visit Site]
 *
 * Norwegian Bokmal date convention: dd/mm/yyyy and 24-hour times (workspace
 * rule). Footer date uses this format; release-date display also uses it.
 *
 * Pure renderer: no I/O. The webhook poster wraps the result.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/components_v2.php';
require_once __DIR__ . '/layout_entry_enrichment.php';
require_once __DIR__ . '/../util/html_to_markdown.php';
require_once __DIR__ . '/../util/text_helpers.php';
require_once __DIR__ . '/../emoji/emoji_registry.php';
require_once __DIR__ . '/../emoji/category_map.php';

final class Changelog_Futuristic_Layout
{
    /** Glyphs for the futuristic style. */
    private const GLYPH_HEADER_BAR = '▰▰▰';
    private const GLYPH_HEADER_GAP = '▱▱▱';
    private const GLYPH_ARROW      = '»';
    private const GLYPH_BULLET     = '›';
    private const GLYPH_DOT        = '•';
    // Default Unicode glyphs (overridden by the emoji registry when an
    // application emoji has been uploaded for the matching logical name).
    private const GLYPH_SAT   = "\u{1F6F0}\u{FE0F}"; // 🛰  -> nt_workflow
    private const GLYPH_SPARK = "\u{26A1}";          // ⚡  -> nt_settings
    private const GLYPH_LINK  = "\u{1F517}";         // 🔗  -> nt_external_link
    private const GLYPH_GLOBE = "\u{1F310}";         // 🌐  -> nt_external_link
    private const GLYPH_BELL  = "\u{1F514}";         // 🔔  -> nt_workflow
    private const GLYPH_DOWN  = "\u{1F4E5}";         // 📥  -> nt_download
    private const GLYPH_OK    = "\u{2705}";          // ✅  -> nt_verified

    /** @var Changelog_Emoji_Registry|null */
    private static ?Changelog_Emoji_Registry $emojiRegistry = null;

    public static function useEmojiRegistry(?Changelog_Emoji_Registry $reg): void
    {
        self::$emojiRegistry = $reg;
    }

    /** Resolve a logical emoji name to inline text (mention or fallback). */
    private static function emo(string $name, string $unicodeFallback): string
    {
        if (self::$emojiRegistry === null) return $unicodeFallback;
        $m = self::$emojiRegistry->mention($name);
        return $m !== '' ? $m : $unicodeFallback;
    }

    /** Resolve a logical emoji name to a button-emoji token (name:id or Unicode). */
    private static function emoBtn(string $name, string $unicodeFallback): string
    {
        if (self::$emojiRegistry === null) return $unicodeFallback;
        $t = self::$emojiRegistry->forButton($name);
        return $t !== '' ? $t : $unicodeFallback;
    }

    /** @see Changelog_Category_Map (extracted so layout stays tidy). */
    private static function sectionEmojiName(string $heading): string
    {
        return Changelog_Category_Map::resolve($heading);
    }

    /**
     * Build a full Components V2 payload for one channel+entry pair.
     *
     * The optional $overrides array lets the CLI tweak pings on a per-send
     * basis without editing config.php:
     *
     *   [
     *     'ping_role_ids' => ['111...', '222...'],  // additive
     *     'ping_user_ids' => ['333...'],            // additive
     *     'no_pings'      => true,                  // drops every ping
     *   ]
     *
     * @param array<string,mixed> $entry     Canonical entry from a source
     * @param array<string,mixed> $channel   Channel config (from config.php)
     * @param array<string,mixed> $defaults  Defaults config
     * @param array<string,mixed> $overrides Per-send overrides (optional)
     * @return array<string,mixed> Discord webhook JSON body (ready to POST)
     */
    public static function build(array $entry, array $channel, array $defaults, array $overrides = []): array
    {
        $accent = (int) ($channel['accent_color'] ?? $defaults['accent_color'] ?? 0x00E5FF);
        $pings  = self::resolvePings($channel, $overrides);

        $header = self::headerRow($entry, $channel);
        $body   = self::bodyRow($entry);
        $footer = self::footerRow($entry, $channel, $defaults, $pings);

        // Compose container with explicit separators between the three rows.
        $components = [];
        foreach ($header as $node) $components[] = $node;
        foreach (Changelog_Layout_Entry_Enrichment::headerTimeLine($entry) as $node) {
            $components[] = $node;
        }
        $components[] = Changelog_Components_V2::separator(true, 2);
        foreach ($body as $node)   $components[] = $node;
        $components[] = Changelog_Components_V2::separator(true, 2);
        foreach ($footer as $node) $components[] = $node;

        $container = Changelog_Components_V2::container($accent, $components);

        $body = [
            'username'   => (string) ($channel['webhook_username'] ?? $defaults['username'] ?? 'Changelog Announcer'),
            'avatar_url' => (string) ($channel['avatar_url'] ?? $defaults['avatar_url'] ?? ''),
            'flags'      => Changelog_Components_V2::FLAG_IS_COMPONENTS_V2,
            'components' => [$container],
            'allowed_mentions' => self::allowedMentions($pings),
        ];

        return $body;
    }

    /**
     * Merge channel config + CLI overrides into a normalized ping list.
     *
     * Returns:
     *   [
     *     'roles' => ['111...', '222...'],   // unique, validated, capped at 100
     *     'users' => ['333...'],
     *   ]
     *
     * @param array<string,mixed> $channel
     * @param array<string,mixed> $overrides
     * @return array{roles: array<int,string>, users: array<int,string>}
     */
    private static function resolvePings(array $channel, array $overrides): array
    {
        if (!empty($overrides['no_pings'])) {
            return ['roles' => [], 'users' => []];
        }
        $roles = [];
        // Backward-compat: singular notify_role_id.
        $single = trim((string) ($channel['notify_role_id'] ?? ''));
        if ($single !== '') $roles[] = $single;
        foreach ((array) ($channel['notify_role_ids'] ?? []) as $id) {
            $roles[] = (string) $id;
        }
        foreach ((array) ($overrides['ping_role_ids'] ?? []) as $id) {
            $roles[] = (string) $id;
        }
        $users = [];
        foreach ((array) ($channel['notify_user_ids'] ?? []) as $id) {
            $users[] = (string) $id;
        }
        foreach ((array) ($overrides['ping_user_ids'] ?? []) as $id) {
            $users[] = (string) $id;
        }
        return [
            'roles' => self::sanitizeIds($roles),
            'users' => self::sanitizeIds($users),
        ];
    }

    /**
     * Keep only valid Discord snowflakes (5-25 digits), unique, capped at
     * 100 (Discord's allowed_mentions limit is 100 per category).
     *
     * @param array<int,string> $ids
     * @return array<int,string>
     */
    private static function sanitizeIds(array $ids): array
    {
        // We deliberately keep IDs as strings end-to-end. PHP auto-coerces
        // numeric string array keys to integers, and Discord snowflakes
        // (~2^60) exceed JavaScript's safe integer range (2^53), so any
        // round-trip via JSON numbers risks precision loss on Discord's
        // side. Discord's API expects allowed_mentions ids as strings.
        $seen = [];
        $clean = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') continue;
            if (!preg_match('/^\d{5,25}$/', $id)) continue;
            if (isset($seen[$id])) continue;
            $seen[$id] = true;
            $clean[] = (string) $id;
            if (count($clean) >= 100) break;
        }
        return $clean;
    }

    /* ------------------------------------------------------------------ */
    /* ROW 1: HEADER                                                       */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array<string,mixed>> */
    private static function headerRow(array $entry, array $channel): array
    {
        $version  = (string) ($entry['version'] ?? '');
        $title    = (string) ($entry['title'] ?? '');
        $badge    = (string) ($entry['badge_type'] ?? 'Patch');
        $date     = (string) ($entry['release_date'] ?? '');
        $project  = (string) ($channel['project_tag'] ?? 'NewsTargeted');
        $logoUrl  = trim((string) ($channel['logo_url'] ?? ''));
        $thumbUrl = trim((string) ($channel['thumbnail_url'] ?? ''));

        $bar = self::GLYPH_HEADER_BAR . '  ' . self::GLYPH_HEADER_GAP
            . '  CHANGELOG  '
            . self::GLYPH_HEADER_GAP . '  ' . self::GLYPH_HEADER_BAR;

        $titleLine = sprintf(
            '# %s  %s %s `v%s`',
            self::emo('nt_workflow', self::GLYPH_SAT),
            self::escapeMd($project),
            self::GLYPH_ARROW,
            self::escapeMd($version === '' ? '—' : $version)
        );

        $subLine = sprintf(
            '> -# `%s`  %s  Released %s',
            self::escapeMd($badge),
            self::GLYPH_DOT,
            self::escapeMd(self::norwegianDate($date))
        );

        $summary = $title !== ''
            ? '> ' . self::escapeMd(self::oneLine($title))
            : '> *No title provided.*';

        $out = [];

        // Optional logo: full-width media gallery hero at the very top.
        if ($logoUrl !== '' && preg_match('#^https?://#i', $logoUrl)) {
            $gallery = Changelog_Components_V2::mediaGallery([
                ['url' => $logoUrl, 'description' => $project . ' logo'],
            ]);
            if ($gallery) $out[] = $gallery;
        }

        $out[] = Changelog_Components_V2::textDisplay('-# `' . $bar . '`');

        // Title block: with optional right-side thumbnail wrapped in a Section.
        $titleText = Changelog_Components_V2::textDisplay($titleLine . "\n" . $subLine);
        if ($thumbUrl !== '' && preg_match('#^https?://#i', $thumbUrl)) {
            $out[] = Changelog_Components_V2::section(
                [$titleText],
                Changelog_Components_V2::thumbnail($thumbUrl, $project . ' thumbnail')
            );
        } else {
            $out[] = $titleText;
        }

        $out[] = Changelog_Components_V2::textDisplay($summary);
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* ROW 2: BODY                                                         */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array<string,mixed>> */
    private static function bodyRow(array $entry): array
    {
        $out = Changelog_Layout_Entry_Enrichment::contextBlocks($entry);
        $out = array_merge($out, Changelog_Layout_Entry_Enrichment::atAGlanceBlock($entry));

        // Path 1: structured categories (e.g. dashboard JSON)
        $cats = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        if ($cats) {
            return array_merge($out, self::bodyFromCategories($cats));
        }
        // Path 2: HTML content (e.g. webhook proxy MariaDB)
        $html = (string) ($entry['html'] ?? '');
        if (trim($html) !== '') {
            return array_merge($out, self::bodyFromHtml($html));
        }
        if ($out) {
            return $out;
        }
        return [Changelog_Components_V2::textDisplay('> *No change details provided.*')];
    }

    /** @param array<string, array<int,string>> $cats @return array<int, array<string,mixed>> */
    private static function bodyFromCategories(array $cats): array
    {
        $out = [];
        $budget = Changelog_Components_V2::MAX_TEXT_CHARS_TOTAL - 600;
        $used = 0;
        foreach ($cats as $rawName => $items) {
            if (!is_array($items) || !$items) continue;
            $label = ucfirst((string) $rawName);
            $emo = self::emo(self::sectionEmojiName($label), self::GLYPH_SPARK);
            $heading = sprintf('### %s %s %s', $emo, self::GLYPH_ARROW, self::escapeMd($label));
            $lines = [];
            foreach ($items as $line) {
                $clean = self::oneLine((string) $line);
                if ($clean === '') continue;
                $lines[] = '> ' . self::GLYPH_BULLET . ' ' . self::escapeMd($clean);
            }
            if (!$lines) continue;
            $block = $heading . "\n" . implode("\n", $lines);
            if ($used + strlen($block) > $budget) {
                $block = mb_substr($block, 0, max(0, $budget - $used));
                $block = rtrim($block) . "\n> -# ... (truncated, see full changelog).";
                $out[] = Changelog_Components_V2::textDisplay($block);
                $used = $budget;
                break;
            }
            $used += strlen($block);
            $out[] = Changelog_Components_V2::textDisplay($block);
        }
        if (!$out) {
            $out[] = Changelog_Components_V2::textDisplay('> *No items in any category.*');
        }
        return $out;
    }

    /** @return array<int, array<string,mixed>> */
    private static function bodyFromHtml(string $html): array
    {
        $sections = Changelog_Html_To_Markdown::toSections($html);
        if (!$sections) {
            return [Changelog_Components_V2::textDisplay('> *No change details provided.*')];
        }
        $out = [];
        $budget = Changelog_Components_V2::MAX_TEXT_CHARS_TOTAL - 700;
        $used = 0;
        foreach ($sections as $s) {
            $heading = '';
            if ($s['heading'] !== '') {
                $emo = self::emo(self::sectionEmojiName($s['heading']), self::GLYPH_SPARK);
                $heading = sprintf('### %s %s %s', $emo, self::GLYPH_ARROW, self::escapeMd($s['heading']));
            }
            $lines = [];
            foreach ($s['lines'] as $line) {
                $clean = self::oneLine($line);
                if ($clean === '') continue;
                $lines[] = '> ' . self::GLYPH_BULLET . ' ' . self::escapeMd($clean);
            }
            if (!$lines) continue;
            $block = trim($heading . "\n" . implode("\n", $lines));
            if ($used + strlen($block) > $budget) {
                $block = mb_substr($block, 0, max(0, $budget - $used));
                $block = rtrim($block) . "\n> -# ... (truncated, see full changelog).";
                $out[] = Changelog_Components_V2::textDisplay($block);
                $used = $budget;
                break;
            }
            $used += strlen($block);
            $out[] = Changelog_Components_V2::textDisplay($block);
        }
        if (!$out) {
            $out[] = Changelog_Components_V2::textDisplay('> *No change details provided.*');
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* ROW 3: FOOTER                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * @param array{roles: array<int,string>, users: array<int,string>} $pings
     * @return array<int, array<string,mixed>>
     */
    private static function footerRow(array $entry, array $channel, array $defaults, array $pings): array
    {
        $out = [];
        $pingLine = self::pingLine($pings);
        if ($pingLine !== '') {
            $out[] = Changelog_Components_V2::textDisplay($pingLine);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Europe/Oslo'));
        $stamp = $now->format('d/m/Y') . ' kl. ' . $now->format('H:i');
        $project = (string) ($channel['label'] ?? 'NewsTargeted');
        $meta = sprintf(
            '> -# %s %s  %s  Announced %s',
            self::emo('nt_workflow', self::GLYPH_SPARK),
            self::escapeMd($project),
            self::GLYPH_DOT,
            self::escapeMd($stamp)
        );
        $out[] = Changelog_Components_V2::textDisplay($meta);

        foreach (Changelog_Layout_Entry_Enrichment::promoFooterBlocks($channel, $defaults) as $node) {
            $out[] = $node;
        }

        // Optional custom footer line (kept below the auto metadata).
        $footerMsg = trim((string) ($channel['footer_message'] ?? ''));
        if ($footerMsg !== '') {
            $out[] = Changelog_Components_V2::textDisplay(
                '> -# ' . self::escapeMd(self::oneLine($footerMsg))
            );
        }

        $buttons = [];
        $publicUrl = (string) ($channel['public_url'] ?? '');
        if ($publicUrl !== '') {
            $buttons[] = Changelog_Components_V2::linkButton(
                'View Full Changelog', $publicUrl, self::emoBtn('nt_download', self::GLYPH_DOWN)
            );
        }
        $siteUrl = (string) ($channel['site_url'] ?? '');
        if ($siteUrl !== '' && $siteUrl !== $publicUrl) {
            $buttons[] = Changelog_Components_V2::linkButton(
                'Visit Site', $siteUrl, self::emoBtn('nt_internal_link', self::GLYPH_GLOBE)
            );
        }
        $version = (string) ($entry['version'] ?? '');
        if ($publicUrl !== '' && $version !== '') {
            $deep = rtrim($publicUrl, '/') . '#v' . preg_replace('/[^A-Za-z0-9._-]/', '', $version);
            $buttons[] = Changelog_Components_V2::linkButton(
                'Jump to v' . $version, $deep, self::emoBtn('nt_internal_link', self::GLYPH_LINK)
            );
        }
        if ($buttons) {
            $out[] = Changelog_Components_V2::actionRow($buttons);
        }
        return $out;
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /** @see Changelog_Text_Helpers (extracted to keep this file <500 lines). */
    private static function escapeMd(string $s): string { return Changelog_Text_Helpers::escapeMd($s); }
    private static function oneLine(string $s): string { return Changelog_Text_Helpers::oneLine($s); }
    private static function norwegianDate(string $iso): string { return Changelog_Text_Helpers::norwegianDate($iso); }

    /**
     * Build a single compact line that visibly mentions every role and user
     * we plan to ping (so members see "who is being pinged" right above the
     * footer metadata). Returns '' when there are no pings.
     *
     * @param array{roles: array<int,string>, users: array<int,string>} $pings
     */
    private static function pingLine(array $pings): string
    {
        $rolesTxt = '';
        if ($pings['roles']) {
            $parts = [];
            foreach ($pings['roles'] as $id) $parts[] = '<@&' . $id . '>';
            $rolesTxt = self::GLYPH_BELL . ' ' . implode(' ', $parts);
        }
        $usersTxt = '';
        if ($pings['users']) {
            $parts = [];
            foreach ($pings['users'] as $id) $parts[] = '<@' . $id . '>';
            // 👤 user glyph
            $usersTxt = "\u{1F464} " . implode(' ', $parts);
        }
        if ($rolesTxt === '' && $usersTxt === '') return '';
        if ($rolesTxt !== '' && $usersTxt !== '') {
            return $rolesTxt . '  ' . self::GLYPH_DOT . '  ' . $usersTxt;
        }
        return $rolesTxt . $usersTxt;
    }

    /**
     * Whitelist exactly the ids we resolved. Anything else (including
     * @everyone, @here, mentions hidden inside the body) cannot ping.
     *
     * @param array{roles: array<int,string>, users: array<int,string>} $pings
     * @return array<string,mixed>
     */
    private static function allowedMentions(array $pings): array
    {
        return [
            'parse' => [],
            'roles' => array_values($pings['roles']),
            'users' => array_values($pings['users']),
        ];
    }
}
