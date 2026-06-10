<?php
/**
 * Discord webhook poster.
 *
 * Sends a Components V2 payload to a Discord webhook URL with retries +
 * structured error handling. Supports the ?wait=true query string so the
 * caller gets back the created message (with id) for logging.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Discord_Webhook
{
    private int $timeoutS;
    private int $retries;
    private int $retryMs;

    public function __construct(int $timeoutS = 12, int $retries = 3, int $retryMs = 1500)
    {
        $this->timeoutS = max(1, $timeoutS);
        $this->retries  = max(1, $retries);
        $this->retryMs  = max(100, $retryMs);
    }

    /**
     * POST a payload to a webhook URL.
     *
     * @param array<string,mixed> $payload
     * @return array{
     *   ok:bool, http:int, error:string, message_id:string,
     *   raw:string, attempts:int
     * }
     */
    public function send(string $webhookUrl, array $payload, bool $withWait = true): array
    {
        if (!preg_match('#^https://discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9_-]+#', $webhookUrl)) {
            return self::result(false, 0, 'invalid webhook URL', '', '', 0);
        }
        // Build URL. For Components V2 webhook posts Discord requires the
        // opt-in query param `with_components=true`; without it the engine
        // silently ignores the components array and rejects the request as
        // "Cannot send an empty message" (code 50006). Add `wait=true` too
        // when the caller wants the created message id back.
        $url = $webhookUrl;
        $qs = [];
        if ($withWait) $qs[] = 'wait=true';
        $flags = (int) ($payload['flags'] ?? 0);
        if (($flags & (1 << 15)) !== 0) {
            $qs[] = 'with_components=true';
        }
        if ($qs) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . implode('&', $qs);
        }
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return self::result(false, 0, 'json_encode failed: ' . json_last_error_msg(), '', '', 0);
        }
        if (strlen($json) > 65536) {
            return self::result(false, 0, 'payload too large (' . strlen($json) . ' bytes)', '', '', 0);
        }

        $attempts = 0;
        $last = ['http' => 0, 'body' => '', 'err' => '', 'retry_after' => 0];
        while ($attempts < $this->retries) {
            $attempts++;
            $res = $this->postOnce($url, $json);
            $last = $res;
            $http = (int) $res['http'];
            if ($http >= 200 && $http < 300) {
                $id = '';
                if ($withWait) {
                    $decoded = json_decode($res['body'], true);
                    if (is_array($decoded) && isset($decoded['id'])) {
                        $id = (string) $decoded['id'];
                    }
                }
                return self::result(true, $http, '', $id, $res['body'], $attempts);
            }
            if ($http === 429) {
                // Rate-limited: honor retry_after (seconds).
                $waitMs = (int) ($res['retry_after'] * 1000);
                if ($waitMs <= 0) $waitMs = $this->retryMs * $attempts;
                usleep(min(15000, $waitMs) * 1000);
                continue;
            }
            // Retry on 5xx and network errors; fail fast on 4xx other than 429.
            if ($http >= 500 || $http === 0) {
                usleep($this->retryMs * 1000 * $attempts);
                continue;
            }
            // Hard client error: no retry.
            break;
        }
        $err = $last['err'] !== '' ? $last['err'] : 'http ' . $last['http'];
        return self::result(false, (int) $last['http'], $err, '', (string) $last['body'], $attempts);
    }

    /**
     * @return array{http:int, body:string, err:string, retry_after:float}
     */
    private function postOnce(string $url, string $json): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['http' => 0, 'body' => '', 'err' => 'curl_init failed', 'retry_after' => 0.0];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: NewsTargeted-ChangelogAnnouncer/1.0 (+https://newstargeted.com)',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => $this->timeoutS,
            CURLOPT_CONNECTTIMEOUT => max(3, (int) ($this->timeoutS / 3)),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER         => true,
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if (!is_string($resp)) {
            return ['http' => $http, 'body' => '', 'err' => $err ?: 'curl_exec failed', 'retry_after' => 0.0];
        }
        $headerBlob = substr($resp, 0, $headerSize);
        $body       = substr($resp, $headerSize);
        $retryAfter = 0.0;
        if (preg_match('/^retry-after:\s*([0-9.]+)\s*$/im', $headerBlob, $m)) {
            $retryAfter = (float) $m[1];
        } else {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && isset($decoded['retry_after'])) {
                $retryAfter = (float) $decoded['retry_after'];
            }
        }
        return ['http' => $http, 'body' => (string) $body, 'err' => $err, 'retry_after' => $retryAfter];
    }

    /**
     * @return array{ok:bool, http:int, error:string, message_id:string, raw:string, attempts:int}
     */
    private static function result(bool $ok, int $http, string $error, string $messageId, string $raw, int $attempts): array
    {
        return [
            'ok'         => $ok,
            'http'       => $http,
            'error'      => $error,
            'message_id' => $messageId,
            'raw'        => $raw,
            'attempts'   => $attempts,
        ];
    }
}
