<?php
/**
 * Source interface: every changelog source returns the same canonical
 * "entry" shape so the renderer does not care where the data came from.
 *
 * Canonical entry shape (associative array):
 *   [
 *     'version'      => '4.0.10',
 *     'release_date' => '2026-05-25',   // YYYY-MM-DD
 *     'title'        => 'Lift CPU quota on the webhook slice',
 *     'badge_type'   => 'Patch',        // Major | Minor | Patch | Hotfix
 *     // ONE of the next two must be set:
 *     'html'         => '<p>...</p>',
 *     'categories'   => [
 *         'Added'   => ['line', 'line'],
 *         'Changed' => ['line'],
 *         'Fixed'   => ['line'],
 *     ],
 *   ]
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

interface Changelog_Source_Interface {
    /**
     * Fetch a single entry by version (e.g. "4.0.10"). Return null if missing.
     *
     * @return array<string,mixed>|null
     */
    public function fetchByVersion(string $version): ?array;

    /**
     * Fetch the most recent entry. Return null if none.
     *
     * @return array<string,mixed>|null
     */
    public function fetchLatest(): ?array;

    /**
     * Cheap human-readable describe of where this source reads from.
     */
    public function describe(): string;
}
