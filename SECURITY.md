# Security Policy

## Supported versions

Only the latest `master` branch is supported. Deploy the current release on your server after pulling updates.

## Reporting a vulnerability

**Do not** open public GitHub issues for security vulnerabilities.

1. Use [GitHub Security Advisories](https://github.com/master3395/MAS_ChangeHub/security/advisories/new) for this repository.
2. Or contact the maintainer through a private channel you already use for NewsTargeted operations.

Include:

- What component is affected (archive bash, Contabo PHP, Discord webhook, cron)
- Steps to reproduce
- Impact assessment

We aim to acknowledge reports within a reasonable timeframe and patch serious issues promptly.

## Secrets handling

These files must **never** be committed:

- `snapshot_config.conf`
- `contabo/config.php`

They may contain Internet Archive credentials, Contabo API passwords, and Discord webhook URLs.

If a webhook or API key is exposed, rotate it immediately in the provider panel, then update your local config.

## Operator checklist

- `chmod 600` on config files
- Restrict server SSH and cron to trusted operators
- Review Discord webhook channels for least privilege
- Do not paste live tokens in issues, PRs, or logs shared publicly
