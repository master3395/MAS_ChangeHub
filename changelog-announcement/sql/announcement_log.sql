-- Optional: mirror the JSONL announcement log into MariaDB so dashboards
-- can query "what changelogs have been announced where?".
--
-- The tool does NOT need this table to function; the canonical log is
-- logs/announcements.jsonl. Run this once if you want the mirror.
--
-- Engine: InnoDB, utf8mb4.

CREATE TABLE IF NOT EXISTS `changelog_announcement_log` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ts`            DATETIME(3)     NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `channel`       VARCHAR(64)     NOT NULL,
    `version`       VARCHAR(32)     NOT NULL,
    `release_date`  DATE            NULL,
    `ok`            TINYINT(1)      NOT NULL DEFAULT 0,
    `http_status`   SMALLINT        NOT NULL DEFAULT 0,
    `attempts`      TINYINT         NOT NULL DEFAULT 0,
    `error`         VARCHAR(512)    NOT NULL DEFAULT '',
    `message_id`    VARCHAR(32)     NOT NULL DEFAULT '',
    `forced`        TINYINT(1)      NOT NULL DEFAULT 0,
    `payload_size`  INT UNSIGNED    NULL,
    PRIMARY KEY (`id`),
    KEY `idx_channel_version` (`channel`, `version`),
    KEY `idx_ts` (`ts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
