<?php
/**
 * Components V2 helpers. Each builder returns a plain associative array
 * matching Discord's component schema. The flag bit IS_COMPONENTS_V2 lives
 * in the message envelope, not here.
 *
 *   type 17 = Container
 *   type 10 = Text Display
 *   type 14 = Separator (divider line)
 *   type  9 = Section (text children + accessory)
 *   type 11 = Thumbnail / media accessory
 *   type  1 = Action Row
 *   type  2 = Button
 *
 * Per Discord limits: ~40 total components, 4000 chars total text, 5 buttons
 * per action row. The futuristic layout uses ~10 components and stays well
 * below the text cap (we hard-truncate to keep payloads safe).
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Components_V2
{
    public const FLAG_IS_COMPONENTS_V2 = 1 << 15; // 32768
    public const FLAG_SUPPRESS_NOTIFICATIONS = 1 << 12; // 4096

    public const MAX_TEXT_CHARS_TOTAL  = 3800;  // safety budget under Discord's 4000
    public const MAX_TEXT_CHARS_BLOCK  = 1800;  // per text display block

    public static function container(int $accentColor, array $components): array
    {
        return [
            'type'        => 17,
            'accent_color'=> max(0, min(0xFFFFFF, $accentColor)),
            'components'  => array_values($components),
        ];
    }

    public static function textDisplay(string $content): array
    {
        $content = self::truncate($content, self::MAX_TEXT_CHARS_BLOCK);
        return [
            'type'    => 10,
            'content' => $content,
        ];
    }

    public static function separator(bool $divider = true, int $spacing = 1): array
    {
        return [
            'type'    => 14,
            'divider' => $divider,
            'spacing' => max(1, min(2, $spacing)),
        ];
    }

    /**
     * @param array<int, array<string,mixed>> $children
     * @param array<string,mixed>|null $accessory
     */
    public static function section(array $children, ?array $accessory = null): array
    {
        $node = [
            'type'       => 9,
            'components' => array_values($children),
        ];
        if ($accessory !== null) $node['accessory'] = $accessory;
        return $node;
    }

    public static function thumbnail(string $url, string $description = ''): array
    {
        $node = [
            'type'  => 11,
            'media' => ['url' => $url],
        ];
        if ($description !== '') $node['description'] = $description;
        return $node;
    }

    /**
     * Media Gallery (type 12): 1-10 items, each { media:{url}, description?, spoiler? }.
     *
     * @param array<int, array{url:string, description?:string, spoiler?:bool}> $items
     */
    public static function mediaGallery(array $items): array
    {
        $clean = [];
        foreach (array_values($items) as $it) {
            $url = isset($it['url']) ? (string) $it['url'] : '';
            if ($url === '' || !preg_match('#^https?://#i', $url)) continue;
            $node = ['media' => ['url' => $url]];
            if (isset($it['description']) && $it['description'] !== '') {
                $node['description'] = mb_substr((string) $it['description'], 0, 1024);
            }
            if (!empty($it['spoiler'])) $node['spoiler'] = true;
            $clean[] = $node;
            if (count($clean) >= 10) break;
        }
        if (!$clean) return [];
        return [
            'type'  => 12,
            'items' => $clean,
        ];
    }

    /** @param array<int, array<string,mixed>> $buttons */
    public static function actionRow(array $buttons): array
    {
        return [
            'type'       => 1,
            'components' => array_slice(array_values($buttons), 0, 5),
        ];
    }

    /**
     * Link button (style 5). Webhook messages can ONLY use link buttons
     * (interactive buttons require a bot application).
     */
    public static function linkButton(string $label, string $url, ?string $emoji = null, bool $disabled = false): array
    {
        $label = trim($label);
        if ($label === '') $label = 'Open';
        $btn = [
            'type'     => 2,
            'style'    => 5,
            'label'    => mb_substr($label, 0, 80),
            'url'      => $url,
            'disabled' => (bool) $disabled,
        ];
        if ($emoji !== null && $emoji !== '') {
            $btn['emoji'] = self::emoji($emoji);
        }
        return $btn;
    }

    /**
     * Accept either ":name:" (Unicode emoji string) or "name:id" for custom.
     * @return array<string,mixed>
     */
    public static function emoji(string $token): array
    {
        $token = trim($token);
        if ($token === '') return [];
        // Custom emoji syntax: name:id  (id is numeric)
        if (preg_match('/^([a-zA-Z0-9_]+):(\d{5,25})$/', $token, $m)) {
            return ['id' => $m[2], 'name' => $m[1]];
        }
        return ['name' => $token];
    }

    public static function truncate(string $text, int $max): string
    {
        if (function_exists('mb_strlen') && mb_strlen($text) > $max) {
            return rtrim(mb_substr($text, 0, $max - 1)) . "\u{2026}";
        }
        if (strlen($text) > $max) {
            return rtrim(substr($text, 0, $max - 1)) . "...";
        }
        return $text;
    }
}
