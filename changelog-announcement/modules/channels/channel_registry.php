<?php
/**
 * Channel registry: resolves a channel name from config.php into a ready-to-
 * use { channel, source } pair, and exposes a `list()` for the CLI.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/../sources/source_interface.php';
require_once __DIR__ . '/../sources/mariadb_source.php';
require_once __DIR__ . '/../sources/json_source.php';
require_once __DIR__ . '/../sources/http_source.php';

final class Changelog_Channel_Registry
{
    /** @var array<string,mixed> */
    private array $config;

    /** @param array<string,mixed> $config Full config.php return value */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /** @return array<int,string> */
    public function listNames(): array
    {
        $channels = (array) ($this->config['channels'] ?? []);
        return array_keys($channels);
    }

    /** @return array<string,mixed> The named channel block */
    public function get(string $name): array
    {
        $channels = (array) ($this->config['channels'] ?? []);
        if (!isset($channels[$name]) || !is_array($channels[$name])) {
            throw new InvalidArgumentException('Unknown channel: ' . $name);
        }
        return $channels[$name];
    }

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        return (array) ($this->config['defaults'] ?? []);
    }

    /**
     * Resolve the list of Discord webhook URLs for a channel.
     *
     * Accepts (in order of priority):
     *   1. `webhook_urls` array on the channel,
     *   2. `webhook_url` string OR comma-separated string on the channel.
     *
     * Duplicates removed; only well-formed Discord webhook URLs kept.
     *
     * @return array<int,string>
     */
    public function webhookUrls(string $name): array
    {
        $ch = $this->get($name);
        $candidates = [];
        if (isset($ch['webhook_urls']) && is_array($ch['webhook_urls'])) {
            foreach ($ch['webhook_urls'] as $u) {
                $candidates[] = (string) $u;
            }
        }
        if (isset($ch['webhook_url']) && is_string($ch['webhook_url']) && $ch['webhook_url'] !== '') {
            // Allow CSV in the single field too.
            foreach (preg_split('/\s*,\s*/', $ch['webhook_url']) as $u) {
                $candidates[] = (string) $u;
            }
        }
        $seen = [];
        $out = [];
        foreach ($candidates as $u) {
            $u = trim($u);
            if ($u === '') continue;
            if (!preg_match('#^https://discord(?:app)?\.com/api/webhooks/\d+/[A-Za-z0-9_-]+#', $u)) {
                continue;
            }
            if (isset($seen[$u])) continue;
            $seen[$u] = true;
            $out[] = $u;
        }
        return $out;
    }

    /**
     * Build a source adapter for a channel.
     */
    public function buildSource(string $name): Changelog_Source_Interface
    {
        $ch = $this->get($name);
        $src = (array) ($ch['source'] ?? []);
        $type = (string) ($src['type'] ?? '');
        switch ($type) {
            case 'mariadb':
                return new Changelog_Mariadb_Source($src);
            case 'json':
                return new Changelog_Json_Source($src);
            case 'http':
                return new Changelog_Http_Source($src);
            default:
                throw new InvalidArgumentException('Unknown source.type for channel ' . $name . ': ' . $type);
        }
    }
}
