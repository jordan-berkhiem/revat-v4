# CI/CD Pipeline & Production Safeguards

## Purpose

Establish a CI/CD pipeline and production safeguards for Revat, deployed on a dedicated OVH server with staging and production environments. The workflow is heavily agent-driven (Claude Code agents), so guardrails must protect production while enabling autonomous agent workflows.

## Environments

| Environment | Domain | Trigger | Purpose |
|-------------|--------|---------|---------|
| Development | localhost | — | Local Ubuntu desktop |
| Staging | `staging.revat.io` | Auto-deploy on merge to `main` | Verify deploys before production |
| Production | `app.revat.io` | Manual trigger (git tag or workflow dispatch) | Live application |

`revat.io` is reserved for marketing/blog/SEO — not the application.

## Server Environment Layout

The OVH dedicated server (Intel Xeon-E 2386G, 32GB ECC RAM, 2×512GB NVMe RAID 1) runs both staging and production as isolated deployments.

```
/var/www/
├── staging.revat.io/
│   ├── releases/              # timestamped release dirs (keep last 5)
│   │   ├── 20260319120000/
│   │   └── 20260319140000/
│   ├── current -> releases/20260319140000   # symlink to active release
│   ├── shared/
│   │   ├── storage/           # persistent Laravel storage (logs, cache, framework)
│   │   └── .env               # staging environment variables
│   └── .env.schema            # for varlock validation
├── app.revat.io/
│   ├── releases/
│   ├── current -> releases/...
│   ├── shared/
│   │   ├── storage/
│   │   └── .env
│   └── .env.schema
```

### Isolation

- **MySQL databases:** `revat_v4_staging` and `revat_v4_production`
- **Redis databases:** DB 0 for production, DB 1 for staging (queues, cache, sessions isolated)
- **Nginx vhosts:** separate configs for `staging.revat.io` and `app.revat.io`
- **Horizon processes:** separate supervisor per environment, each reading its own `.env`

## CI Pipeline (GitHub Actions)

Triggered on every push to a PR branch and on merge to `main`.

```
PR opened/pushed → Pint (format check)
                 → Larastan (static analysis)
                 → Pest (tests + architecture tests)
                 → Migration safety scan

All pass → agent can merge to main
Any fail → merge blocked
```

### Jobs

1. **Pint** — `vendor/bin/pint --test` (check-only mode, fails if code isn't formatted)
2. **Larastan** — `vendor/bin/phpstan analyse` (catches type errors, undefined methods, wrong argument counts)
3. **Pest** — `vendor/bin/pest` (unit, feature, architecture tests against a MySQL test database on the GitHub runner)
4. **Migration safety scan** — grep new migration files for `dropColumn`, `dropTable`, `renameColumn`, `change()`. If found, add a `destructive-migration` label to the PR and block merge until a human approves.

### Nightly Scheduled Run

- Mutation testing via `vendor/bin/pest --mutate`
- Results posted to a dedicated Slack channel

### Runner

GitHub-hosted Ubuntu runner. Free tier provides 2,000 minutes/month for private repos.

## CD Pipeline (Laravel Envoy)

### Staging — Auto-deploy on merge to `main`

```
merge to main → GitHub Action SSHs to server
             → envoy run deploy --on=staging
             → health check (HTTP 200 on staging.revat.io)
             → success: Slack notification
             → failure: auto-rollback + Slack alert
```

### Production — Manual trigger only

```
git tag v1.x.x → GitHub Action triggers (or manual workflow_dispatch)
              → envoy run deploy --on=production
              → health check (HTTP 200 on app.revat.io)
              → success: Slack notification
              → failure: auto-rollback + Slack alert
```

### Envoy Deploy Task Steps

1. `git clone` the release into `releases/{timestamp}/`
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. Link shared dirs (`storage/`, `.env`) into the release
5. `php artisan migrate --force`
6. `php artisan config:cache && route:cache && view:cache`
7. Swap `current` symlink to new release
8. `php artisan horizon:terminate` (graceful restart)
9. Reload PHP-FPM
10. Health check — curl the site, verify HTTP 200
11. If health check fails: swap symlink back, alert via Slack

### Release Retention

Keep last 5 releases per environment, prune older ones after successful deploy.

## Restore Methods

### Release Rollback (instant, for bad deploys)

- Envoy `rollback` task swaps the `current` symlink to the previous release
- Restarts PHP-FPM and Horizon
- Does **not** rollback migrations (handle manually if a migration caused the problem)
- Triggered manually: `envoy run rollback --on=production`

### Database Restore (for data-level recovery)

- Daily automated MySQL dumps to OVH Object Storage (compressed, timestamped)
- Restore script downloads a specific backup and imports it
- Triggered manually: `envoy run db:restore --backup=2026-03-19-020000 --on=production`
- Before restoring, automatically takes a snapshot of the current database (so you can undo the restore)

### Full Restore (release + database, catastrophic recovery)

- Combines both: restore a specific database backup, then rollback to a matching release
- Manual process, not automated — requires choosing which backup matches which release

### Restore Safeguards

- Restore commands require explicit environment flag (`--on=production`) — no default
- Database restore takes a pre-restore snapshot automatically
- Slack notification sent on any restore action

### SOP

A Standard Operating Procedure document will be created covering step-by-step instructions for each restore scenario (release rollback, database restore, full restore).

## Backups

### Automated Daily MySQL Dumps

- Run at 02:00 UTC via cron on the server
- Dump both `revat_v4_production` and `revat_v4_staging` databases
- Compress with gzip, timestamp the filename (e.g., `production-2026-03-19-020000.sql.gz`)
- Upload to OVH Object Storage bucket
- Retain last 30 days, auto-delete older backups

### Pre-deploy Snapshot

- Before every production deploy, take a quick MySQL dump
- Stored locally in `/var/backups/revat/pre-deploy/`
- Keeps last 5 (matches release retention)
- Provides a matched pair: release + database state at deploy time

### Pre-restore Snapshot

- Before any database restore, automatically dump the current database
- Prevents "the restore made it worse and now I can't get back"

### What's NOT Backed Up

- **Redis** — cache/queue/sessions are ephemeral and rebuildable
- **Laravel storage/logs** — low value, logs rotate at 14 days

## Agent Safeguards

### Branch Protection on `main`

- PRs required (no direct pushes)
- CI status checks must pass before merge
- No force pushes

### Claude Code Hooks (`.claude/settings.json`)

- Block `git push --force` and `git push -f`
- Block `git reset --hard`
- Block direct pushes to `main`
- Block commits containing `.env` files

### Agent Workflow Rules

- Agents work in feature branches only
- Agents can create PRs and merge to `main` after CI passes
- Agents **cannot** trigger production deploys (tag creation is human-only)
- Agents **cannot** run restore commands

### Slack Notifications

- On merge to `main` — what was merged, by whom (agent or human)
- On staging deploy — success or failure with rollback status
- On production deploy — success or failure
- On any restore action
- Nightly mutation testing results

## Monitoring

### Laravel Pulse (already installed)

- Dashboard accessible at `/pulse` in production
- Monitors slow queries, slow requests, exceptions, queue health, server resources
- No additional setup needed beyond ensuring it runs in production

### Uptime Monitoring

- UptimeRobot (free tier) — HTTP ping `app.revat.io` and `staging.revat.io` every 5 minutes
- Alerts via Slack if either goes down
- Also monitors the health check endpoint (`/up` — Laravel's built-in)

### Horizon Dashboard

- Accessible at `/horizon` in production (behind admin auth)
- Monitors queue workers, job throughput, failed jobs, wait times

### Future Considerations

- **OpenTelemetry** — revisit when connector integrations are live and need tracing across external APIs
- **Sentry** — revisit when user count grows and error tracking at scale is needed

## New Files

| File | Purpose |
|------|---------|
| `.github/workflows/ci.yml` | CI pipeline (Pint, Larastan, Pest, migration scan) |
| `.github/workflows/deploy-staging.yml` | Auto-deploy staging on merge to main |
| `.github/workflows/deploy-production.yml` | Manual production deploy on tag/dispatch |
| `.github/workflows/nightly.yml` | Mutation testing schedule |
| `Envoy.blade.php` | Deploy, rollback, and restore task definitions |
| `phpstan.neon` | Larastan configuration |
| `docs/sop/restore-procedures.md` | Standard Operating Procedure for restore scenarios |

## Modified Files

| File | Change |
|------|--------|
| `.claude/settings.json` | Add hooks for agent safeguards |
| `composer.json` | Add `larastan/larastan` dev dependency |

## Dependencies

| Tool | Purpose | Cost |
|------|---------|------|
| GitHub Actions | CI/CD runner | Free (2,000 min/month private repos) |
| Laravel Envoy | SSH task runner for deploys | Free (composer package) |
| Larastan | Static analysis | Free (composer package) |
| OVH Object Storage | Backup storage | ~€0.01/GB/month |
| UptimeRobot | Availability monitoring | Free tier |
| Slack webhook | Notifications | Free |
| Cloudflare | SSL/DNS | Free tier |
