<?php
/**
 * Application Emoji sync.
 *
 * Uses Discord's Application Emoji REST API to mirror every entry in the
 * registry as a bot/app-owned application emoji:
 *
 *   GET    /applications/{app_id}/emojis
 *   POST   /applications/{app_id}/emojis    { name, image: "data:image/png;base64,..." }
 *
 * Requires a Discord application id + bot token (both from config.php),
 * NEVER from CLI flags. Reuses existing app emojis when the name already
 * matches. Writes resolved IDs back into the registry so future renders
 * use `<:name:id>` without re-uploading.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/emoji_registry.php';

final class Changelog_Emoji_Sync
{
    private const API_BASE = 'https://discord.com/api/v10';

    private string $appId;
    private string $botToken;
    private Changelog_Emoji_Registry $reg;

    public function __construct(string $appId, string $botToken, Changelog_Emoji_Registry $reg)
    {
        $this->appId    = trim($appId);
        $this->botToken = trim($botToken);
        $this->reg      = $reg;
    }

    public function ready(): bool
    {
        return preg_match('/^\d{5,25}$/', $this->appId) === 1 && $this->botToken !== '';
    }

    /**
     * Sync every emoji in the registry.
     *
     * @return array<int, array{name:string, action:string, id:string, http:int, error:string}>
     */
    public function syncAll(): array
    {
        if (!$this->ready()) {
            throw new RuntimeException('emoji_sync: app id or bot token missing in config.php');
        }
        $existing = $this->fetchExisting();   // name => id
        $results = [];
        foreach ($this->reg->names() as $name) {
            $path = $this->reg->localPath($name);
            if (!$path) {
                $results[] = ['name'=>$name, 'action'=>'skip', 'id'=>'', 'http'=>0, 'error'=>'no local file'];
                continue;
            }
            if (isset($existing[$name])) {
                $id = (string) $existing[$name];
                $this->reg->saveId($name, $id);
                $results[] = ['name'=>$name, 'action'=>'reuse', 'id'=>$id, 'http'=>200, 'error'=>''];
                continue;
            }
            [$id, $http, $err] = $this->createOne($name, $path);
            if ($id !== '') {
                $this->reg->saveId($name, $id);
                $results[] = ['name'=>$name, 'action'=>'upload', 'id'=>$id, 'http'=>$http, 'error'=>''];
            } else {
                $results[] = ['name'=>$name, 'action'=>'fail', 'id'=>'', 'http'=>$http, 'error'=>$err];
            }
        }
        return $results;
    }

    /** @return array<string,string> name -> id */
    private function fetchExisting(): array
    {
        $url = self::API_BASE . '/applications/' . $this->appId . '/emojis';
        [$http, $body, $err] = $this->request('GET', $url);
        if ($http < 200 || $http >= 300) {
            throw new RuntimeException('emoji_sync: GET /emojis failed http=' . $http . ' err=' . $err . ' body=' . substr($body, 0, 200));
        }
        $data = json_decode($body, true);
        // Discord returns { "items": [ {id,name,...}, ... ] }
        $items = is_array($data) && isset($data['items']) ? $data['items']
               : (is_array($data) ? $data : []);
        $out = [];
        foreach ($items as $row) {
            if (is_array($row) && isset($row['name'], $row['id'])) {
                $out[(string) $row['name']] = (string) $row['id'];
            }
        }
        return $out;
    }

    /** @return array{0:string,1:int,2:string} id, http, error */
    private function createOne(string $name, string $localPath): array
    {
        $bytes = @file_get_contents($localPath);
        if ($bytes === false || $bytes === '') return ['', 0, 'cannot read ' . $localPath];
        if (strlen($bytes) > 256 * 1024) return ['', 0, 'PNG > 256KB (Discord limit)'];
        $mime = $this->detectMime($bytes);
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($bytes);
        $payload = ['name' => $name, 'image' => $dataUri];
        $url = self::API_BASE . '/applications/' . $this->appId . '/emojis';
        [$http, $body, $err] = $this->request('POST', $url, $payload);
        if ($http < 200 || $http >= 300) {
            $brief = substr($body, 0, 300);
            return ['', $http, ($err !== '' ? $err . ' ' : '') . $brief];
        }
        $data = json_decode($body, true);
        $id = is_array($data) && isset($data['id']) ? (string) $data['id'] : '';
        return [$id, $http, $id === '' ? 'no id in response' : ''];
    }

    /**
     * Minimal HTTP client. JSON body when $payload is given.
     *
     * @return array{0:int,1:string,2:string} http, body, error
     */
    private function request(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) return [0, '', 'curl_init failed'];
        $headers = [
            'Authorization: Bot ' . $this->botToken,
            'User-Agent: NewsTargeted-ChangelogAnnouncer/1.0 (emoji-sync)',
            'Accept: application/json',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($payload !== null) {
            $headers[] = 'Content-Type: application/json';
            $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $opts[CURLOPT_POSTFIELDS] = $json === false ? '{}' : $json;
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return [$http, is_string($body) ? $body : '', $err];
    }

    private function detectMime(string $bytes): string
    {
        if (strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) === 0) return 'image/png';
        if (strncmp($bytes, "\xFF\xD8", 2) === 0) return 'image/jpeg';
        if (strncmp($bytes, "GIF8", 4) === 0) return 'image/gif';
        return 'image/png';
    }
}
