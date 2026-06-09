# MAS ChangeHub tests

Debug and validation scripts only. Not used by production cron.

Run from project root via `./menu.sh` (option **3**), or invoke scripts directly:

| Script | Purpose |
|--------|---------|
| `test_snapshot.sh` | Archive connectivity and config smoke test |
| `test-discord-webhook.sh` | Archive Discord CV2 webhook |
| `test_enhanced_snapshot.sh` | Internet Archive enhanced options |
| `test_manual_snapshot.sh` | Manual URL snapshot smoke test |
| `test_wayback_links.sh` | Wayback link resolution |
| `contabo/test-discord-webhook.php` | Contabo Discord CV2 webhook |
| `contabo/test-snapshot-api.php` | Contabo snapshot API auth |
| `contabo/test-snapshots.sh` | Contabo manager integration run |
| `contabo/test-delete.php` | Contabo delete API (destructive) |

Archive test log: `../logs/test_snapshot.log`
