      # Going live: the checklist

What has to be true on the production server, in order. The code side was
prepared on 2026-11-11/12; this is what only the server, and you, can do.

## 1. Before anything else

- [ ] **Rotate every secret that was pasted into a chat:** the OpenAI key, the Google
      OAuth client secret, the MySQL password, `APP_KEY`, the Reverb app secret, and the
      Metered/TURN keys. Rotating `APP_KEY` signs everyone out once; that's expected.
- [ ] **Decide the privacy-policy wording** (§6). It is legal exposure, not polish.

## 2. The `.env` on the server

Check each line; none of these values are in the repo.

| Key | Must be | Why |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | `true` shows stack traces, with config values, to any visitor. |
| `APP_URL` | `https://cameroonnetwork.org` | Also makes the session cookie HTTPS-only (`config/session.php`). |
| `LOG_LEVEL` | `warning` (or `error`) | `debug` grew an 845k-line log. |
| `LOG_STACK` | `daily` | One file a day, old ones pruned (`LOG_DAILY_DAYS`, default 14). |
| `MAIL_MAILER` + `MAIL_*` | a real mailer (SMTP, Postmark, SES…) | With `log`, password resets and saved-search alerts are never sent. |
| `TELESCOPE_ENABLED` | `false` or absent | It records every request and query, members' messages included. Now off by default. |
| `SESSION_SECURE_COOKIE` | absent (follows `APP_URL`) or `true` | |
| `BROADCAST_CONNECTION` | `reverb` | |
| `QUEUE_CONNECTION` | anything | Nothing is queued today; every broadcast is immediate. |
| `SOLIDARITY_LIVE` | absent | Turn on the day Solidarity launches; it brings back its profile stats. |

## 3. Deploying this release

```bash
git pull
composer install --no-dev --optimize-autoloader   # Telescope and Debugbar are dev-only
php artisan migrate --force                        # 4 new migrations, below
php artisan users:localise-avatars                 # copies old Google photos to our disk
php artisan storage:link                           # if not already linked
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`public/build` is committed, so there is no `npm run build` on the server, but the new
bundle in `public/build/assets` must come up with the code.

The migrations in this release:

| Migration | Does |
|---|---|
| `2026_11_06_000001_fold_counter_offers_into_their_offer` | Moves open counter-offers onto the offer they answer. |
| `2026_11_11_000001_name_the_site_cameroon_network` | `site_name` "Cameroon Community" → "Cameroon Network" (only that exact value). |
| `2026_11_11_000002_add_anonymised_at_to_users` | For account deletion. |
| `2026_11_12_000001_add_last_seen_at_to_marketplace_saved_searches` | Saved-search badges. |

`config:cache` matters beyond speed: without it, PHP on Windows sometimes fails to read
`.env` under concurrent requests and runs on defaults (seen in the local logs). Linux
with PHP-FPM is not known to do this, but cache anyway.

## 4. Processes that must keep running

- [ ] **Reverb**, under Supervisor or systemd: `php artisan reverb:start`. Without it there is
      no realtime at all (new messages, receipts, typing, connection requests).
- [ ] **The scheduler**, one cron line:
      `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`.
      It prunes location history after 90 days (the privacy policy relies on that) and runs
      saved-search alerts hourly.
- [ ] After every deploy, **restart Reverb** so it loads the new code.

## 5. Check it after deploying

- [ ] Sign in, open a chat, send a message from a second account: it arrives without a
      reload. Room and personal channels are now **private**, so this is the check that
      `/broadcasting/auth` works behind the proxy.
- [ ] `/admin` → Health: Reverb connects.
- [ ] Request a password reset: the email arrives.
- [ ] Visit a page that doesn't exist: the branded 404, not a stack trace.
- [ ] `curl -I https://cameroonnetwork.org` shows `Strict-Transport-Security`,
      `X-Frame-Options` and `X-Content-Type-Options`.

## 6. Decisions before going live

**~~The homepage's testimonials and stats aren't real.~~ Done 2026-11-12** (the owner chose
real numbers). The stats band and the hero's map card now show live counts from
`HomeController::communityStats()` (active members, countries, regions, live listings,
messages; cached ten minutes, no floors), and the invented testimonials are removed.
Bring testimonials back only with quotes real members have agreed to share.

**The privacy policy says things the code doesn't do** (docs/ui-parity/09-people-static.md,
finding 7). It says messages aren't read and reports are reviewed by humans, but every
message is sent to OpenAI for automated moderation, and translation and summaries send
text there too. OpenAI isn't named as a processor. It says "approximate" location, but
registration takes a precise GPS fix and a 90-day location history is kept. It also
offers a "clear your location" control that doesn't exist. Account deletion now exists
in Settings, so the policy can say so. This needs your wording, ideally a lawyer's,
and it's what App Store review checks against your App Privacy answers.

## 7. Recommended soon after

- **Backups:** a nightly `mysqldump` and a copy of `storage/app/public` (photos), kept off
  the server.
- **Error tracking:** something like Sentry or Flare, so a 500 reaches you before a member does.
- **Uptime check** on `/up` (Laravel's health route).
- **A Content-Security-Policy**, in report-only mode first: Alpine's inline handlers need care.
