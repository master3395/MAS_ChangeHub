<?php
/**
 * HTTP source.
 *
 * Fetches a JSON feed over HTTPS (e.g. /api/changelog) and reuses the same
 * key-mapping logic as the json_source. Useful when the announcer host is
 * not the same host that owns the changelog file.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/source_interface.php';

final class Changelog_Http_Source implements Changelog_Source_Interface
{
    /** @var array<string,mixed> */
    private array $cfg;
    /** @var array<int,mixed>|null */
    private ?array $cachedList = null;

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function describe(): string
    {
        return 'http feed ' . (string) ($this->cfg['url'] ?? '?');
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
        if (!$list || !is_array($list[0])) return null;
        return $this->normalize($list[0]);
    }

    /** @return array<int,mixed> */
    private function loadList(): array
    {
        if ($this->cachedList !== null) return $this->cachedList;
        $url = (string) ($this->cfg['url'] ?? '');
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException('http_source: invalid url');
        }
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('http_source: curl_init failed');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => (int) ($this->cfg['timeout'] ?? 10),
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'User-Agent: NewsTargeted-ChangelogAnnouncer/1.0',
            ],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $code < 200 || $code >= 300) {
            throw new RuntimeException('http_source: HTTP ' . $code . ' ' . $err);
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('http_source: invalid JSON body');
        }
        $listKey = (string) ($this->cfg['list_key'] ?? 'entries');
        $list = isset($data[$listKey]) && is_array($data[$listKey]) ? $data[$listKey] : [];
        $this->cachedList = array_values($list);
        return $this->cachedList;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function normalize(array $row): array
    {
        $vKey  = (string) ($this->cfg['version_key']  ?? 'version');
        $dKey  = (string) ($this->cfg['date_key']     ?? 'release_date');
        $tKey  = (string) ($this->cfg['title_key']    ?? 'title');
        $htmlKey = (string) ($this->cfg['html_key']   ?? 'content');
        $badgeKey = (string) ($this->cfg['badge_key'] ?? 'badge_type');

        $date = isset($row[$dKey]) ? (string) $row[$dKey] : '';
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return [
            'version'      => isset($row[$vKey]) ? (string) $row[$vKey] : '',
            'release_date' => $date,
            'title'        => isset($row[$tKey]) ? (string) $row[$tKey] : '',
            'badge_type'   => isset($row[$badgeKey]) && $row[$badgeKey] !== ''
                ? (string) $row[$badgeKey] : 'Patch',
            'html'         => isset($row[$htmlKey]) ? (string) $row[$htmlKey] : '',
        ];
    }
}
