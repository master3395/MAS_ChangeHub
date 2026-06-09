# Snapshot names (config.php)

Edit `/home/contabo-snapshots/config.php` only. No code changes needed for renames.

## Default list (3 snapshots)

```php
define('SNAPSHOT_NAMES', [
    'CP-2-4-4-Alma-sieve-1',
    'CP-2-4-4-Alma-sieve-2',
    'CP-2-4-4-Alma-sieve-3',
]);
```

The manager picks the first name not already in use on the instance.

## Per-instance overrides

```php
define('SNAPSHOT_NAMES_BY_INSTANCE', [
    'newstargeted.com' => [
        'my-snap-1',
        'my-snap-2',
        'my-snap-3',
    ],
]);
```

When set for a display name, that list replaces `SNAPSHOT_NAMES` for that instance.

## Fix existing wrong names

```bash
cd /home/contabo-snapshots
php fix-snapshot-names.php
```

## Count

Keep `count(SNAPSHOT_NAMES)` equal to `MAX_SNAPSHOTS_PER_INSTANCE` (default 3).
