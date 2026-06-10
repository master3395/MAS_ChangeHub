<?php
/**
 * Minimal HTML -> Discord-Markdown converter.
 *
 * Designed for the small subset of HTML that the webhook proxy stores in
 * changelog_entries.content: <h4>, <h5>, <p>, <ul>/<ol>/<li>, <code>, <br>,
 * <strong>/<b>, <em>/<i>, <a>. Anything unknown is stripped.
 *
 * Output is grouped by H5 sub-section so the renderer can emit one block per
 * sub-section. Each block is { 'heading': 'Public homepage and ...',
 * 'lines': ['- ...', '- ...'] }.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Html_To_Markdown
{
    /**
     * @return array<int, array{heading:string, lines: array<int,string>}>
     */
    public static function toSections(string $html): array
    {
        if (trim($html) === '') return [];
        $tmp = $html;
        // Normalize line endings and collapse whitespace runs (but keep newlines we add).
        $tmp = str_replace(["\r\n", "\r"], "\n", $tmp);

        // <code> -> `...`
        // Decode entities and re-encode angle brackets so a later strip_tags
        // call cannot mistake `<NEW>` (or similar placeholders) for HTML.
        $tmp = preg_replace_callback('#<code\b[^>]*>(.*?)</code>#is', function ($m) {
            // Order matters: strip tags FIRST while angle-brackets are still
            // escaped as entities (so literal placeholders like `&lt;NEW&gt;`
            // survive), then decode entities, then re-encode angle brackets
            // so a later strip_tags pass on the surrounding line cannot eat
            // them.
            $inner = strip_tags((string) $m[1]);
            $inner = html_entity_decode($inner, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $inner = str_replace(['<', '>'], ['&lt;', '&gt;'], $inner);
            return '`' . $inner . '`';
        }, $tmp);

        // <a href="X">Y</a> -> [Y](X)
        $tmp = preg_replace_callback('#<a\b[^>]*href\s*=\s*"([^"]+)"[^>]*>(.*?)</a>#is', function ($m) {
            $label = trim(self::stripTags($m[2]));
            $href  = trim($m[1]);
            if ($label === '') $label = $href;
            return '[' . $label . '](' . $href . ')';
        }, $tmp);

        // <strong>/<b> and <em>/<i>
        $tmp = preg_replace('#<(?:strong|b)\b[^>]*>(.*?)</(?:strong|b)>#is', '**$1**', $tmp);
        $tmp = preg_replace('#<(?:em|i)\b[^>]*>(.*?)</(?:em|i)>#is', '*$1*', $tmp);

        // <br> -> newline
        $tmp = preg_replace('#<br\s*/?\s*>#i', "\n", $tmp);

        // Split on <h4> (top heading) and <h5> (sub-section heading). We
        // treat <h4> at the top as the "title" already known to the
        // renderer, and use only <h5> blocks to chunk the body.
        $bodyOnly = preg_replace('#<h4\b[^>]*>.*?</h4>#is', '', $tmp);

        if (!preg_match_all('#<h5\b[^>]*>(.*?)</h5>(.*?)(?=<h5\b|$)#is', (string) $bodyOnly, $matches, PREG_SET_ORDER)) {
            // No h5 sections: treat whole body as one block.
            return [[
                'heading' => '',
                'lines'   => self::extractLines($bodyOnly),
            ]];
        }

        $sections = [];
        foreach ($matches as $m) {
            $heading = trim(self::stripTags($m[1]));
            if ($heading !== '' && self::isInternalSectionHeading($heading)) continue;
            $lines   = self::extractLines($m[2]);
            if ($heading === '' && !$lines) continue;
            $sections[] = ['heading' => $heading, 'lines' => $lines];
        }
        return $sections;
    }

    /** @return array<int,string> */
    private static function extractLines(string $html): array
    {
        $lines = [];
        if (preg_match_all('#<li\b[^>]*>(.*?)</li>#is', $html, $liMatches)) {
            foreach ($liMatches[1] as $raw) {
                $text = self::stripTags($raw);
                $text = self::collapseSpace($text);
                if ($text !== '') $lines[] = $text;
            }
            return $lines;
        }
        // No <li>: split paragraphs.
        $html = preg_replace('#</?p\b[^>]*>#i', "\n", $html);
        $text = self::stripTags((string) $html);
        foreach (preg_split('/\n+/', $text) as $piece) {
            $piece = self::collapseSpace($piece);
            if ($piece !== '') $lines[] = $piece;
        }
        return $lines;
    }

    private static function stripTags(string $s): string
    {
        // Allow none. Strip remaining HTML, decode entities.
        $s = strip_tags($s);
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $s;
    }

    /** Skip operator-only sections in public Discord announcements. */
    private static function isInternalSectionHeading(string $heading): bool
    {
        return preg_match('/^operator\s+notes$/iu', trim($heading)) === 1;
    }

    private static function collapseSpace(string $s): string
    {
        $s = preg_replace('/[ \t]+/u', ' ', $s);
        return trim((string) $s);
    }
}
