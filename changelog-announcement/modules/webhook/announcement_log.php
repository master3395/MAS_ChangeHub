<?php
/**
 * Append-only JSONL announcement log.
 *
 * One JSON object per line; cheap to grep, easy to rotate (no DB needed).
 * Used by the CLI to refuse double-announcing the same {channel,version}
 * unless --force is passed.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

final class Changelog_Announcement_Log
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
    }

    /** Returns the most recent announcement for {channel,version}, or null. */
    public function find(string $channel, string $version): ?array
    {
        if (!is_readable($this->path)) return null;
        $fh = @fopen($this->path, 'rb');
        if (!$fh) return null;
        $latest = null;
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') continue;
            $row = json_decode($line, true);
            if (!is_array($row)) continue;
            if (($row['channel'] ?? '') === $channel && ($row['version'] ?? '') === $version) {
                $latest = $row;
            }
        }
        fclose($fh);
        return $latest;
    }

    /** Append one record. */
    public function record(array $row): void
    {
        $row['ts'] = $row['ts'] ?? (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $line = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) return;
        $fh = @fopen($this->path, 'ab');
        if (!$fh) return;
        if (@flock($fh, LOCK_EX)) {
            fwrite($fh, $line . "\n");
            @flock($fh, LOCK_UN);
        }
        fclose($fh);
        @chmod($this->path, 0640);
    }
}
