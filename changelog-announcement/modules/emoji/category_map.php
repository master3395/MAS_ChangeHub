<?php
/**
 * Resolves a body section heading (free text or a structured category like
 * "Added", "Changed", "Fixed", ...) into a logical emoji name from the
 * registry. Kept separate from the layout so adding new categories does
 * not require touching the layout code.
 *
 * Mapping rules, evaluated in order:
 *   1. Exact match on the canonical category name (case-insensitive).
 *   2. Substring match on the same canonical category names.
 *   3. Generic "verification"/"test" words -> nt_verified.
 *   4. Anything else -> nt_settings (default operational icon).
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Category_Map
{
    /** Canonical CHANGELOG categories -> registry emoji names. */
    private const CATEGORY_MAP = [
        'added'       => 'nt_added',
        'changed'     => 'nt_changed',
        'fixed'       => 'nt_fixed',
        'removed'     => 'nt_removed',
        'security'    => 'nt_security',
        'deprecated' => 'nt_deprecated',
        'performance' => 'nt_performance',
    ];

    /** Generic "verified" intent words (used when no category matches). */
    private const VERIFIED_WORDS = '/\b(verif|test|pass|done|complete|valid)/u';

    public const DEFAULT = 'nt_settings';
    public const VERIFIED = 'nt_verified';

    /**
     * Returns the registry name (e.g. "nt_added") to use for the given
     * heading. The caller passes it to Changelog_Emoji_Registry::mention().
     */
    public static function resolve(string $heading): string
    {
        $h = mb_strtolower(trim($heading));
        if ($h === '') return self::DEFAULT;

        if (isset(self::CATEGORY_MAP[$h])) return self::CATEGORY_MAP[$h];

        foreach (self::CATEGORY_MAP as $needle => $name) {
            if (mb_strpos($h, $needle) !== false) return $name;
        }

        if (preg_match(self::VERIFIED_WORDS, $h) === 1) return self::VERIFIED;

        return self::DEFAULT;
    }
}
