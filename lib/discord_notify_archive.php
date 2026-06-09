<?php

require_once __DIR__ . '/discord_cv2_builder.php';

function discord_notify_archive_send(array $options)
{
    $webhookUrl = $options['webhook_url'] ?? '';
    if (empty($webhookUrl)) {
        return ['ok' => false, 'error' => 'Webhook URL missing'];
    }

    $useCv2 = !isset($options['use_cv2']) || (bool) $options['use_cv2'];
    if (!$useCv2) {
        return ['ok' => false, 'error' => 'Classic embed fallback not implemented'];
    }

    $successCount = (int) ($options['success_count'] ?? 0);
    $totalCount = (int) ($options['total_count'] ?? 0);
    $failedCount = (int) ($options['failed_count'] ?? 0);
    $skippedCount = (int) ($options['skipped_count'] ?? 0);
    $failedDetails = (string) ($options['failed_details'] ?? '');
    $websitesList = (string) ($options['websites_list'] ?? '');
    $captureOptions = (string) ($options['capture_options'] ?? '');
    $timezone = $options['timezone'] ?? 'Europe/Oslo';
    $heroImage = $options['hero_image_url'] ?? 'https://newstargeted.com/assets/status-cv2/archive.png';
    $promoBot = $options['promo_bot_url'] ?? 'https://newstargeted.com/news-targeted-bot';
    $promoSite = $options['promo_site_url'] ?? 'https://newstargeted.com/';

    if ($failedCount === 0) {
        $emoji = '✅';
        $statusText = 'Completed Successfully';
        $accent = 3066993;
    } elseif ($successCount > 0 || $skippedCount > 0) {
        $emoji = '⚠️';
        $statusText = 'Completed with Warnings';
        $accent = 16776960;
    } else {
        $emoji = '❌';
        $statusText = 'Completed with Errors';
        $accent = 15158332;
    }

    $announced = discord_cv2_format_datetime($timezone);
    $headerLines = [
        discord_cv2_text('-# `▰▰▰  ▱▱▱  INTERNET ARCHIVE  ▱▱▱  ▰▰▰`'),
        discord_cv2_text('# ' . $emoji . ' **Internet Archive Snapshot Manager**'),
        discord_cv2_text('> -# ' . $statusText . '  •  ' . $announced),
    ];
    $thumb = discord_cv2_thumbnail($heroImage, 'Wayback Machine snapshots');
    $inner = $thumb ? [discord_cv2_section($headerLines, $thumb)] : $headerLines;
    $inner[] = discord_cv2_separator(true, 2);

    $statLines = [
        '**Total websites:** ' . $totalCount,
        '**Successful snapshots:** ' . $successCount,
        '**Failed snapshots:** ' . $failedCount,
    ];
    if ($skippedCount > 0) {
        $statLines[] = '**Skipped (recent/cooldown):** ' . $skippedCount;
    }
    $inner[] = discord_cv2_text_section('Statistics', $statLines);

    if ($websitesList !== '') {
        $inner[] = discord_cv2_text("### Websites archived\n" . discord_cv2_truncate($websitesList, 1700));
    }

    if ($captureOptions !== '') {
        $inner[] = discord_cv2_text("### Capture options\n> " . str_replace("\n", "\n> ", trim($captureOptions)));
    }

    if ($failedCount > 0 && $failedDetails !== '') {
        $inner[] = discord_cv2_text("### Failed URLs\n" . discord_cv2_truncate($failedDetails, 1700));
    }

    $inner[] = discord_cv2_text("### View snapshots\n> Click any domain link above to open its Wayback Machine calendar.");

    $inner[] = discord_cv2_separator(true, 2);
    $inner[] = discord_cv2_text('> -# Internet Archive Snapshot Manager v1.2  •  Announced ' . $announced);
    $inner[] = discord_cv2_text('> [News Targeted Bot](' . $promoBot . ') · [newstargeted.com](' . $promoSite . ')');

    $buttons = [
        discord_cv2_link_button('Wayback Machine', 'https://web.archive.org/', '📦'),
        discord_cv2_link_button('newstargeted.com', $promoSite, '🌐'),
    ];
    $inner[] = discord_cv2_action_row($buttons);

    $payload = [
        'username' => $options['username'] ?? 'Archive Snapshot Manager',
        'flags' => DISCORD_CV2_FLAG,
        'components' => [discord_cv2_container($accent, $inner)],
        'allowed_mentions' => ['parse' => []],
    ];
    if (!empty($options['avatar_url'])) {
        $payload['avatar_url'] = $options['avatar_url'];
    }

    return discord_cv2_post_webhook($webhookUrl, $payload, (int) ($options['timeout'] ?? 15));
}
