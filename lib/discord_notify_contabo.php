<?php

require_once __DIR__ . '/discord_cv2_builder.php';

function discord_notify_contabo_send(array $options)
{
    $webhookUrl = $options['webhook_url'] ?? '';
    if (empty($webhookUrl)) {
        return ['ok' => false, 'error' => 'Webhook URL missing'];
    }

    $useCv2 = !isset($options['use_cv2']) || (bool) $options['use_cv2'];
    if (!$useCv2) {
        return discord_notify_contabo_send_embed($options);
    }

    $stats = $options['stats'] ?? [];
    $instances = $options['instances'] ?? [];
    $success = !empty($options['success']);
    $timezone = $options['timezone'] ?? 'Europe/Oslo';
    $heroImage = $options['hero_image_url'] ?? 'https://newstargeted.com/assets/status-cv2/contabo.png';
    $promoBot = $options['promo_bot_url'] ?? 'https://newstargeted.com/news-targeted-bot';
    $promoSite = $options['promo_site_url'] ?? 'https://newstargeted.com/';
    $errorLog = $options['error_log_path'] ?? '';

    $accent = $success ? 3066993 : 15158332;
    $emoji = $success ? '✅' : '❌';
    $statusText = $success ? 'Completed Successfully' : 'Completed with Errors';
    $announced = discord_cv2_format_datetime($timezone);

    $headerLines = [
        discord_cv2_text('-# `▰▰▰  ▱▱▱  CONTABO SNAPSHOT  ▱▱▱  ▰▰▰`'),
        discord_cv2_text('# ' . $emoji . ' **Contabo Snapshot Manager**'),
        discord_cv2_text('> -# ' . $statusText . '  •  ' . $announced),
    ];
    $thumb = discord_cv2_thumbnail($heroImage, 'Contabo Snapshot Manager');
    $inner = $thumb ? [discord_cv2_section($headerLines, $thumb)] : $headerLines;
    $inner[] = discord_cv2_separator(true, 2);

    $statLines = [
        '**Instances processed:** ' . (int) ($stats['instances_processed'] ?? 0),
        '**Snapshots created:** ' . (int) ($stats['snapshots_created'] ?? 0),
        '**Snapshots deleted:** ' . (int) ($stats['snapshots_deleted'] ?? 0),
        '**Total snapshots:** ' . (int) ($stats['total_snapshots'] ?? 0),
        '**Errors:** ' . (int) ($stats['errors'] ?? 0),
    ];
    $inner[] = discord_cv2_text_section('Statistics', $statLines);

    $instanceLines = [];
    foreach ($instances as $instance) {
        $name = $instance['name'] ?? 'Unknown';
        $id = $instance['id'] ?? '';
        $instanceLines[] = '• **' . $name . '** (ID: ' . $id . ')';
    }
    if (!empty($instanceLines)) {
        $inner[] = discord_cv2_text_section('Instances', $instanceLines);
    }

    $snapshots = $stats['snapshot_details'] ?? [];
    if (!empty($snapshots)) {
        usort($snapshots, function ($a, $b) {
            $dateA = !empty($a['createdDate']) ? strtotime($a['createdDate']) : 0;
            $dateB = !empty($b['createdDate']) ? strtotime($b['createdDate']) : 0;
            return $dateB - $dateA;
        });
        $snapLines = [];
        foreach ($snapshots as $snapshot) {
            $created = discord_notify_contabo_format_date($snapshot['createdDate'] ?? null, $timezone);
            $delete = discord_notify_contabo_format_date($snapshot['autoDeleteDate'] ?? null, $timezone);
            $snapLines[] = '💾 **' . ($snapshot['name'] ?? 'Unknown') . '**: created ' . $created . ', auto-delete ' . $delete;
        }
        foreach (discord_cv2_split_lines($snapLines) as $chunk) {
            $inner[] = discord_cv2_text_section('Snapshots', $chunk);
        }
    }

    if ((int) ($stats['errors'] ?? 0) > 0 && $errorLog !== '') {
        $inner[] = discord_cv2_text_section('Error notice', [
            'Check the error log: `' . $errorLog . '`',
        ]);
    }

    $inner[] = discord_cv2_separator(true, 2);
    $inner[] = discord_cv2_text('> -# Contabo Snapshot Manager v1.2  •  Announced ' . $announced);
    $inner[] = discord_cv2_text('> [News Targeted Bot](' . $promoBot . ') · [newstargeted.com](' . $promoSite . ')');

    $buttons = [
        discord_cv2_link_button('newstargeted.com', $promoSite, '🌐'),
    ];
    if (!empty($options['panel_url'])) {
        $buttons[] = discord_cv2_link_button('Contabo panel', $options['panel_url'], '🖥️');
    }
    $inner[] = discord_cv2_action_row($buttons);

    $payload = [
        'username' => $options['username'] ?? 'Contabo Snapshot Manager',
        'flags' => DISCORD_CV2_FLAG,
        'components' => [discord_cv2_container($accent, $inner)],
        'allowed_mentions' => ['parse' => []],
    ];
    if (!empty($options['avatar_url'])) {
        $payload['avatar_url'] = $options['avatar_url'];
    }

    return discord_cv2_post_webhook($webhookUrl, $payload, (int) ($options['timeout'] ?? 15));
}

function discord_notify_contabo_format_date($value, $timezone)
{
    if (!$value) {
        return 'N/A';
    }
    try {
        $date = new DateTime($value, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($timezone));
        return $date->format('d/m/Y') . ' kl. ' . $date->format('H:i');
    } catch (Exception $e) {
        return 'N/A';
    }
}

function discord_notify_contabo_send_embed(array $options)
{
    return ['ok' => false, 'error' => 'Classic embed fallback not implemented'];
}
