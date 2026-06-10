<?php
/**
 * JSON-file source.
 *
 * Reads { "entries": [ { version, date, title, type, categories } , ... ] }
 * style files (e.g. the NT-main dashboard changelog).
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/source_interface.php';

final class Changelog_Json_Source implements Changelog_Source_Interface
{
    /** @var array<string,mixed> */
    private array $cfg;

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function describe(): string
    {
        return 'json file ' . (string) ($this->cfg['path'] ?? '?');
    }

    public function fetchByVersion(string $version): ?array
    {
        $list = $this->loadList();
        $vKey = (string) ($this->cfg['version_key'] ?? 'version');
        foreach ($list as $row) {
            if (!is_array($row)) continue;
            $v = isset($row[$vKey]) ? (string) $row[$vKey] : '';
            if ($v === $version) {
                return $this->normalize($row);
            }
        }
        return null;
    }

    public function fetchLatest(): ?array
    {
        $list = $this->loadList();
        if (!$list) return null;
        // Sort by date desc (then by appearance order) and return first.
        $dKey = (string) ($this->cfg['date_key'] ?? 'date');
        usort($list, function ($a, $b) use ($dKey) {
            $da = is_array($a) && isset($a[$dKey]) ? (string) $a[$dKey] : '';
            $db = is_array($b) && isset($b[$dKey]) ? (string) $b[$dKey] : '';
            return strcmp($db, $da);
        });
        return is_array($list[0]) ? $this->normalize($list[0]) : null;
    }

    /** @return array<int,mixed> */
    private function loadList(): array
    {
        $path = (string) ($this->cfg['path'] ?? '');
        if ($path === '' || !is_readable($path)) {
            throw new RuntimeException('json_source: file not readable: ' . $path);
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('json_source: read failed: ' . $path);
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('json_source: invalid JSON: ' . $path);
        }
        $listKey = (string) ($this->cfg['list_key'] ?? 'entries');
        $list = isset($data[$listKey]) && is_array($data[$listKey]) ? $data[$listKey] : [];
        return array_values($list);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalize(array $row): array
    {
        $vKey  = (string) ($this->cfg['version_key']  ?? 'version');
        $dKey  = (string) ($this->cfg['date_key']     ?? 'date');
        $tKey  = (string) ($this->cfg['title_key']    ?? 'title');
        $typeKey = (string) ($this->cfg['type_key']   ?? 'type');
        $catKey = (string) ($this->cfg['category_key'] ?? 'categories');
        $htmlKey = (string) ($this->cfg['html_key']   ?? 'content');

        $version = isset($row[$vKey]) ? (string) $row[$vKey] : '';
        $title   = isset($row[$tKey]) ? (string) $row[$tKey] : '';
        $date    = isset($row[$dKey]) ? (string) $row[$dKey] : '';

        $badge = 'Patch';
        if (isset($row[$typeKey])) {
            $t = strtolower((string) $row[$typeKey]);
            if ($t !== '') $badge = ucfirst($t);
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        $entry = [
            'version'       => $version,
            'release_date'  => $date,
            'release_time'  => isset($row['time']) ? trim((string) $row['time']) : '',
            'title'         => $title,
            'badge_type'    => $badge,
            'summary'       => isset($row['summary']) ? trim((string) $row['summary']) : '',
            'scope'         => isset($row['scope']) ? trim((string) $row['scope']) : '',
            'event'         => isset($row['event']) ? trim((string) $row['event']) : '',
            'operator'      => isset($row['operator']) ? trim((string) $row['operator']) : '',
            'legacy_backup' => isset($row['legacy_backup']) ? trim((string) $row['legacy_backup']) : '',
            'notes'         => [],
            'html'          => '',
            'categories'    => [],
        ];

        if (isset($row[$catKey]) && is_array($row[$catKey])) {
            $cats = [];
            foreach ($row[$catKey] as $name => $items) {
                if (!is_array($items)) continue;
                $clean = [];
                foreach ($items as $line) {
                    if (is_scalar($line) && trim((string) $line) !== '') {
                        $clean[] = trim((string) $line);
                    }
                }
                if ($clean) $cats[(string) $name] = $clean;
            }
            $entry['categories'] = $cats;
        }

        if (isset($row['notes']) && is_array($row['notes'])) {
            foreach ($row['notes'] as $note) {
                if (is_scalar($note) && trim((string) $note) !== '') {
                    $entry['notes'][] = trim((string) $note);
                }
            }
        }

        if (isset($row[$htmlKey]) && is_string($row[$htmlKey])) {
            $entry['html'] = $row[$htmlKey];
        }

        return $entry;
    }
}
