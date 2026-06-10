<?php
/**
 * Emoji registry: loads `assets/emoji-registry.json` and resolves logical
 * emoji names into the right thing to render.
 *
 * For each logical name (e.g. `nt_workflow`) the registry stores:
 *   - `local_file`            relative path to the source PNG
 *   - `application_emoji_id`  populated by `cli.php sync-emojis`
 *   - `fallback`              Unicode glyph used when no app emoji yet
 *
 * The renderer asks for one of three things:
 *   - `mention($name)`     -> `<:name:id>` if uploaded, else Unicode fallback
 *   - `forButton($name)`   -> `name:id` (custom) or Unicode name (passed to
 *                             Changelog_Components_V2::emoji())
 *   - `fallback($name)`    -> only the Unicode glyph
 *
 * The registry NEVER returns secrets and never writes to disk except via the
 * dedicated `saveIds()` method called by the sync command.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Emoji_Registry
{
    /** @var array<string, array<string,mixed>> */
    private array $emojis = [];
    private string $path;
    private string $root;

    public function __construct(string $registryPath, string $projectRoot)
    {
        $this->path = $registryPath;
        $this->root = rtrim($projectRoot, '/');
        $this->load();
    }

    private function load(): void
    {
        if (!is_readable($this->path)) {
            $this->emojis = [];
            return;
        }
        $raw = file_get_contents($this->path);
        if ($raw === false) { $this->emojis = []; return; }
        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['emojis']) || !is_array($data['emojis'])) {
            $this->emojis = [];
            return;
        }
        // Filter out "_section*" visual dividers and any non-array values.
        $this->emojis = [];
        foreach ($data['emojis'] as $name => $row) {
            if (!is_string($name) || $name === '' || $name[0] === '_') continue;
            if (!is_array($row)) continue;
            $this->emojis[$name] = $row;
        }
    }

    /** @return array<int,string> */
    public function names(): array
    {
        return array_keys($this->emojis);
    }

    /** @return array<string,mixed>|null */
    public function get(string $name): ?array
    {
        return $this->emojis[$name] ?? null;
    }

    /** Resolve absolute path of the local PNG for $name (or null). */
    public function localPath(string $name): ?string
    {
        $row = $this->get($name);
        if (!$row) return null;
        $rel = trim((string) ($row['local_file'] ?? ''));
        if ($rel === '') return null;
        $abs = $rel[0] === '/' ? $rel : ($this->root . '/' . $rel);
        return is_readable($abs) ? $abs : null;
    }

    /** Unicode fallback for $name, or '' if unknown. */
    public function fallback(string $name): string
    {
        $row = $this->get($name);
        return $row ? (string) ($row['fallback'] ?? '') : '';
    }

    /**
     * Inline mention string. Returns `<:name:id>` when the app emoji has
     * been uploaded; otherwise the Unicode fallback; or '' when unknown.
     */
    public function mention(string $name): string
    {
        $row = $this->get($name);
        if (!$row) return '';
        $id = trim((string) ($row['application_emoji_id'] ?? ''));
        if ($id !== '' && preg_match('/^\d{5,25}$/', $id)) {
            return '<:' . $name . ':' . $id . '>';
        }
        return (string) ($row['fallback'] ?? '');
    }

    /**
     * Token accepted by Changelog_Components_V2::emoji():
     *   - "name:id" when application_emoji_id is known (custom emoji)
     *   - "<unicode>" otherwise (e.g. "📥")
     *   - "" when unknown
     */
    public function forButton(string $name): string
    {
        $row = $this->get($name);
        if (!$row) return '';
        $id = trim((string) ($row['application_emoji_id'] ?? ''));
        if ($id !== '' && preg_match('/^\d{5,25}$/', $id)) {
            return $name . ':' . $id;
        }
        return (string) ($row['fallback'] ?? '');
    }

    /**
     * Update one emoji's application id and persist the registry file.
     */
    public function saveId(string $name, string $applicationEmojiId): bool
    {
        if (!isset($this->emojis[$name])) return false;
        if (!preg_match('/^\d{5,25}$/', $applicationEmojiId)) return false;
        $this->emojis[$name]['application_emoji_id'] = $applicationEmojiId;
        return $this->writeBack();
    }

    private function writeBack(): bool
    {
        if (!is_writable($this->path) && !is_writable(dirname($this->path))) {
            return false;
        }
        $raw = file_get_contents($this->path);
        $data = $raw === false ? [] : (json_decode($raw, true) ?: []);
        $data['emojis'] = $this->emojis;
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) return false;
        $tmp = $this->path . '.tmp';
        if (file_put_contents($tmp, $encoded . "\n", LOCK_EX) === false) return false;
        @chmod($tmp, 0640);
        return rename($tmp, $this->path);
    }
}
