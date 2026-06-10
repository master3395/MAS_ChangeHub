<?php
/**
 * MariaDB / MySQL source.
 *
 * Pulls credentials by re-reading the target project's own config.php (so
 * the announcer never holds duplicate copies of DB passwords). The source
 * config in our config.php tells us which getter calls to evaluate.
 */

declare(strict_types=1);

if (!defined('CHANGELOG_ANNOUNCEMENT_APP_INIT')) {
    http_response_code(404); exit;
}

require_once __DIR__ . '/source_interface.php';

final class Changelog_Mariadb_Source implements Changelog_Source_Interface
{
    /** @var array<string,mixed> */
    private array $cfg;
    private ?\mysqli $link = null;

    /** @param array<string,mixed> $cfg */
    public function __construct(array $cfg)
    {
        $this->cfg = $cfg;
    }

    public function describe(): string
    {
        $cols = $this->cfg['columns'] ?? [];
        $table = (string) ($this->cfg['table'] ?? 'changelog_entries');
        return sprintf('mariadb table %s (via %s)', $table, (string) ($this->cfg['config_php'] ?? '?'));
    }

    public function fetchByVersion(string $version): ?array
    {
        if ($version === '') return null;
        $sql = $this->selectSql() . ' WHERE ' . $this->col('version') . ' = ? LIMIT 1';
        return $this->queryOne($sql, [$version]);
    }

    public function fetchLatest(): ?array
    {
        $sql = $this->selectSql() . ' ORDER BY '
            . $this->col('release_date') . ' DESC, ' . $this->col('version') . ' DESC LIMIT 1';
        return $this->queryOne($sql, []);
    }

    private function col(string $logical): string
    {
        $cols = (array) ($this->cfg['columns'] ?? []);
        $name = (string) ($cols[$logical] ?? $logical);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new RuntimeException('mariadb_source: invalid column name for ' . $logical);
        }
        return '`' . $name . '`';
    }

    private function selectSql(): string
    {
        $table = (string) ($this->cfg['table'] ?? 'changelog_entries');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new RuntimeException('mariadb_source: invalid table name');
        }
        return 'SELECT '
            . $this->col('version') . ' AS version, '
            . $this->col('release_date') . ' AS release_date, '
            . $this->col('title') . ' AS title, '
            . $this->col('html') . ' AS html, '
            . $this->col('badge_type') . ' AS badge_type'
            . ' FROM `' . $table . '`';
    }

    /**
     * @param array<int,string> $params
     * @return array<string,mixed>|null
     */
    private function queryOne(string $sql, array $params): ?array
    {
        $link = $this->connect();
        $stmt = $link->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('mariadb_source: prepare failed: ' . $link->error);
        }
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('mariadb_source: execute failed: ' . $err);
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$row) return null;

        $version = isset($row['version']) ? (string) $row['version'] : '';
        $date    = isset($row['release_date']) ? (string) $row['release_date'] : '';
        $title   = isset($row['title']) ? (string) $row['title'] : '';
        $html    = isset($row['html']) ? (string) $row['html'] : '';
        $badge   = isset($row['badge_type']) && $row['badge_type'] !== ''
            ? (string) $row['badge_type'] : 'Patch';

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $date, $m)) {
            $date = $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return [
            'version'      => $version,
            'release_date' => $date,
            'title'        => $title,
            'badge_type'   => $badge,
            'html'         => $html,
        ];
    }

    private function connect(): \mysqli
    {
        if ($this->link instanceof \mysqli) return $this->link;
        $configPhp = (string) ($this->cfg['config_php'] ?? '');
        if ($configPhp === '' || !is_readable($configPhp)) {
            throw new RuntimeException('mariadb_source: project config.php not readable');
        }
        $host = $this->readProjectValue($configPhp, (string) ($this->cfg['host_from'] ?? "'127.0.0.1'"));
        $port = (int) $this->readProjectValue($configPhp, (string) ($this->cfg['port_from'] ?? "'3306'"));
        $user = $this->readProjectValue($configPhp, (string) ($this->cfg['user_from'] ?? "''"));
        $pass = $this->readProjectValue($configPhp, (string) ($this->cfg['pass_from'] ?? "''"));
        $name = $this->readProjectValue($configPhp, (string) ($this->cfg['name_from'] ?? "''"));
        if ($user === '' || $name === '') {
            throw new RuntimeException('mariadb_source: missing user/name from project config');
        }
        if ($port <= 0) $port = 3306;
        if ($host === '') $host = '127.0.0.1';

        mysqli_report(MYSQLI_REPORT_OFF);
        $link = @new \mysqli($host, $user, $pass, $name, $port);
        if ($link->connect_errno) {
            throw new RuntimeException('mariadb_source: connect failed: ' . $link->connect_error);
        }
        $link->set_charset('utf8mb4');
        $this->link = $link;
        return $link;
    }

    private function readProjectValue(string $configPhp, string $getterCall): string
    {
        if (!function_exists('changelog_announcement_read_project_value')) {
            throw new RuntimeException('mariadb_source: helper not loaded');
        }
        return changelog_announcement_read_project_value($configPhp, $getterCall);
    }
}
