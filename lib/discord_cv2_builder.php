<?php

if (!defined('MAS_CHANGEHUB_CV2')) {
    define('MAS_CHANGEHUB_CV2', true);
}

const DISCORD_CV2_FLAG = 32768;
const DISCORD_CV2_MAX_TEXT = 1800;

function discord_cv2_truncate($text, $max = DISCORD_CV2_MAX_TEXT)
{
    $clean = (string) $text;
    if (mb_strlen($clean) <= $max) {
        return $clean;
    }
    $slice = mb_substr($clean, 0, $max - 1);
    $lastSpace = mb_strrpos($slice, ' ');
    if ($lastSpace !== false && $lastSpace > (int) ($max * 0.6)) {
        return mb_substr($slice, 0, $lastSpace) . '…';
    }
    return $slice . '…';
}

function discord_cv2_container($accentColor, array $components)
{
    return [
        'type' => 17,
        'accent_color' => max(0, min(0xffffff, (int) $accentColor)),
        'components' => array_values(array_filter($components)),
    ];
}

function discord_cv2_text($content)
{
    return [
        'type' => 10,
        'content' => discord_cv2_truncate($content),
    ];
}

function discord_cv2_separator($divider = true, $spacing = 1)
{
    return [
        'type' => 14,
        'divider' => (bool) $divider,
        'spacing' => max(1, min(2, (int) $spacing)),
    ];
}

function discord_cv2_section(array $children, $accessory = null)
{
    $node = [
        'type' => 9,
        'components' => array_slice(array_values(array_filter($children)), 0, 3),
    ];
    if ($accessory) {
        $node['accessory'] = $accessory;
    }
    return $node;
}

function discord_cv2_thumbnail($url, $description = '')
{
    if (!$url || !preg_match('#^https?://#i', (string) $url)) {
        return null;
    }
    $node = [
        'type' => 11,
        'media' => ['url' => (string) $url],
    ];
    if ($description !== '') {
        $node['description'] = discord_cv2_truncate($description, 1024);
    }
    return $node;
}

function discord_cv2_action_row(array $buttons)
{
    return [
        'type' => 1,
        'components' => array_slice(array_values(array_filter($buttons)), 0, 5),
    ];
}

function discord_cv2_link_button($label, $url, $emoji = null)
{
    $btn = [
        'type' => 2,
        'style' => 5,
        'label' => discord_cv2_truncate($label ?: 'Open', 80),
        'url' => $url,
        'disabled' => false,
    ];
    if ($emoji) {
        $btn['emoji'] = is_array($emoji) ? $emoji : ['name' => (string) $emoji];
    }
    return $btn;
}

function discord_cv2_text_section($heading, array $lines)
{
    $body = [];
    foreach ($lines as $line) {
        if ($line === null || $line === '') {
            continue;
        }
        $body[] = '> ' . $line;
    }
    return discord_cv2_text('### ' . $heading . "\n" . implode("\n", $body));
}

function discord_cv2_build_webhook_url($webhookUrl, array $payload)
{
    $flags = (int) ($payload['flags'] ?? 0);
    if (($flags & DISCORD_CV2_FLAG) === 0) {
        return $webhookUrl;
    }
    $separator = strpos($webhookUrl, '?') !== false ? '&' : '?';
    return $webhookUrl . $separator . 'with_components=true';
}

function discord_cv2_format_datetime($timezone = 'Europe/Oslo')
{
    try {
        $date = new DateTime('now', new DateTimeZone($timezone));
        return $date->format('d/m/Y') . ' kl. ' . $date->format('H:i');
    } catch (Exception $e) {
        return gmdate('d/m/Y') . ' kl. ' . gmdate('H:i');
    }
}

function discord_cv2_post_webhook($webhookUrl, array $payload, $timeout = 15)
{
    $target = discord_cv2_build_webhook_url($webhookUrl, $payload);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $target,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => (int) $timeout,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    return [
        'ok' => !$error && $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'error' => $error,
        'response' => $response,
    ];
}

function discord_cv2_split_lines(array $lines, $maxChars = 1600)
{
    $chunks = [];
    $current = [];
    $length = 0;
    foreach ($lines as $line) {
        $line = (string) $line;
        $add = ($length > 0 ? 1 : 0) + mb_strlen($line);
        if ($length + $add > $maxChars && !empty($current)) {
            $chunks[] = $current;
            $current = [];
            $length = 0;
        }
        $current[] = $line;
        $length += ($length > 0 ? 1 : 0) + mb_strlen($line);
    }
    if (!empty($current)) {
        $chunks[] = $current;
    }
    return $chunks;
}
