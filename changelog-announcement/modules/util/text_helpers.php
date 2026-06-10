<?php
/**
 * Tiny text helpers shared by the renderer (markdown escape that respects
 * inline code spans, single-line collapse, and Norwegian-bokmaal date
 * formatting). Kept here to keep the layout module under 500 lines.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Text_Helpers
{
    /**
     * Markdown-safe escape. Skips content inside `inline code spans` because
     * Discord does not interpret formatting chars inside backticks, and
     * extra backslashes there render literally.
     */
    public static function escapeMd(string $s): string
    {
        $out = '';
        $len = strlen($s);
        $i = 0;
        while ($i < $len) {
            if ($s[$i] === '`') {
                $close = strpos($s, '`', $i + 1);
                if ($close === false) {
                    $out .= self::escapeOutsideCode(substr($s, $i));
                    break;
                }
                $out .= substr($s, $i, $close - $i + 1);
                $i = $close + 1;
                continue;
            }
            $j = strpos($s, '`', $i);
            $end = ($j === false) ? $len : $j;
            $out .= self::escapeOutsideCode(substr($s, $i, $end - $i));
            $i = $end;
        }
        return $out;
    }

    public static function escapeOutsideCode(string $s): string
    {
        return str_replace(
            ['\\', '_', '*', '~', '|'],
            ['\\\\', '\\_', '\\*', '\\~', '\\|'],
            $s
        );
    }

    public static function oneLine(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        return trim((string) $s);
    }

    /**
     * Render an ISO-style date as Norwegian dd/mm/yyyy. Returns the input
     * verbatim when the format is unknown, or em-dash on empty.
     */
    public static function norwegianDate(string $iso): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }
        return $iso === '' ? '—' : $iso;
    }
}
