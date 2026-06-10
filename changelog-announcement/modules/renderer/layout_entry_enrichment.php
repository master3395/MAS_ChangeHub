<?php
/**
 * Extra Components V2 blocks: release context, at-a-glance stats, promo footer.
 * Keeps futuristic_layout.php under the 500-line project cap.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/components_v2.php';
require_once __DIR__ . '/../util/text_helpers.php';

final class Changelog_Layout_Entry_Enrichment
{
    private const GLYPH_BULLET = '›';
    private const GLYPH_DOT    = '•';

    /**
     * Optional release time line for the header (when JSON has a "time" field).
     *
     * @param array<string,mixed> $entry
     * @return array<int, array<string,mixed>>
     */
    public static function headerTimeLine(array $entry): array
    {
        $time = trim((string) ($entry['release_time'] ?? ''));
        if ($time === '') {
            return [];
        }
        $date = self::norwegianDate((string) ($entry['release_date'] ?? ''));
        $line = sprintf('> -# Released %s kl. %s', self::escapeMd($date), self::escapeMd($time));
        return [Changelog_Components_V2::textDisplay($line)];
    }

    /**
     * Summary, scope, event, operator, legacy backup, and free-form notes.
     *
     * @param array<string,mixed> $entry
     * @return array<int, array<string,mixed>>
     */
    public static function contextBlocks(array $entry): array
    {
        $lines = [];
        $summary = self::oneLine((string) ($entry['summary'] ?? ''));
        if ($summary !== '') {
            $lines[] = '> ' . self::escapeMd($summary);
        }
        $event = self::oneLine((string) ($entry['event'] ?? ''));
        if ($event !== '') {
            $lines[] = '> **Event:** ' . self::escapeMd($event);
        }
        $scope = self::oneLine((string) ($entry['scope'] ?? ''));
        if ($scope !== '') {
            $lines[] = '> **Scope:** ' . self::escapeMd($scope);
        }
        $backup = self::oneLine((string) ($entry['legacy_backup'] ?? ''));
        if ($backup !== '') {
            $lines[] = '> **Legacy backup:** `' . self::escapeMd($backup) . '`';
        }
        $operator = self::oneLine((string) ($entry['operator'] ?? ''));
        if ($operator !== '') {
            $lines[] = '> **Operator:** ' . self::escapeMd($operator);
        }
        $notes = is_array($entry['notes'] ?? null) ? $entry['notes'] : [];
        foreach ($notes as $note) {
            $clean = self::oneLine((string) $note);
            if ($clean === '') {
                continue;
            }
            $lines[] = '> ' . self::GLYPH_BULLET . ' ' . self::escapeMd($clean);
        }
        if (!$lines) {
            return [];
        }
        $block = "### Details\n" . implode("\n", $lines);
        return [Changelog_Components_V2::textDisplay(self::truncate($block))];
    }

    /**
     * Quick stats before category bullets.
     *
     * @param array<string,mixed> $entry
     * @return array<int, array<string,mixed>>
     */
    public static function atAGlanceBlock(array $entry): array
    {
        $cats = is_array($entry['categories'] ?? null) ? $entry['categories'] : [];
        if (!$cats) {
            return [];
        }
        $parts = [];
        $total = 0;
        foreach ($cats as $name => $items) {
            if (!is_array($items) || !$items) {
                continue;
            }
            $count = count($items);
            $total += $count;
            $parts[] = sprintf('%d %s', $count, self::escapeMd((string) $name));
        }
        if ($total === 0) {
            return [];
        }
        $line = sprintf(
            '> **At a glance:** %d change%s (%s)',
            $total,
            $total === 1 ? '' : 's',
            implode(', ', $parts)
        );
        return [Changelog_Components_V2::textDisplay($line)];
    }

    /**
     * Promote the Discord bot and main website when channel URLs are set.
     *
     * @param array<string,mixed> $channel
     * @param array<string,mixed> $defaults
     * @return array<int, array<string,mixed>>
     */
    public static function promoFooterBlocks(array $channel, array $defaults): array
    {
        $botUrl = trim((string) ($channel['site_url'] ?? ''));
        $helpUrl = trim((string) ($channel['public_url'] ?? ''));
        $mainSite = trim((string) ($channel['promo_site_url'] ?? $defaults['promo_site_url'] ?? 'https://newstargeted.com/'));

        $lines = [];
        if ($botUrl !== '') {
            $label = trim((string) ($channel['bot_promo_label'] ?? 'News Targeted Bot'));
            $lines[] = '> ' . self::GLYPH_BULLET . ' Bot: [' . self::escapeMd($label) . '](' . $botUrl . ')';
        }
        if ($helpUrl !== '' && $helpUrl !== $botUrl) {
            $lines[] = '> ' . self::GLYPH_BULLET . ' Changelog: [View full changelog](' . $helpUrl . ')';
        }
        if ($mainSite !== '' && $mainSite !== $botUrl) {
            $lines[] = '> ' . self::GLYPH_BULLET . ' Website: [newstargeted.com](' . rtrim($mainSite, '/') . ')';
        }
        if (!$lines) {
            return [];
        }
        return [Changelog_Components_V2::textDisplay(implode("\n", $lines))];
    }

    private static function escapeMd(string $s): string
    {
        return Changelog_Text_Helpers::escapeMd($s);
    }

    private static function oneLine(string $s): string
    {
        return Changelog_Text_Helpers::oneLine($s);
    }

    private static function norwegianDate(string $iso): string
    {
        return Changelog_Text_Helpers::norwegianDate($iso);
    }

    private static function truncate(string $text): string
    {
        return Changelog_Components_V2::truncate($text, Changelog_Components_V2::MAX_TEXT_CHARS_BLOCK);
    }
}
