# Deploying Gin Rummy

A start-to-finish guide for putting this application on a production server.

It assumes one Ubuntu-style host running Nginx, PHP-FPM, MySQL and Redis. If you
are splitting those across machines, the one section that changes is
[Running more than one server](#running-more-than-one-server).

> **The one thing people get wrong**
> The server and the browser reach the WebSocket server at _different_
> addresses. `REVERB_HOST` is where PHP publishes events — the loopback.
> `VITE_REVERB_HOST` is where the browser connects — your public domain. The
> shipped `.env.example` ties them together, which is right for local
> development and wrong in production. [Section 4](#4-environment) covers it.

---

## Contents

1. [What you need](#1-what-you-need)
2. [Server preparation](#2-server-preparation)
3. [Getting the code in place](#3-getting-the-code-in-place)
4. [Environment](#4-environment)
5. [Database](#5-database)
6. [Building the frontend](#6-building-the-frontend)
7. [Nginx and TLS](#7-nginx-and-tls)
8. [Background processes](#8-background-processes)
9. [First run checklist](#9-first-run-checklist)
10. [Deploying a change](#10-deploying-a-change)
11. [Troubleshooting](#11-troubleshooting)
12. [Running more than one server](#12-running-more-than-one-server)
13. [Housekeeping and backups](#13-housekeeping-and-backups)

---

## 1. What you need

| Component | Version | Notes                                                                                              |
| --------- | ------- | -------------------------------------------------------------------------------------------------- |
| PHP       | 8.3+    | with `pdo_mysql`, `redis`, `mbstring`, `openssl`, `curl`, `pcntl`, `posix`, `sodium`, `xml`, `zip` |
| Composer  | 2.x     |                                                                                                    |
| Node.js   | 22+     | build machine only; not needed at runtime                                                          |
| MySQL     | 8.0+    |                                                                                                    |
| Redis     | 6+      | locks, cache, queue and sessions                                                                   |
| Nginx     | any     | terminates TLS, proxies the WebSocket                                                              |

`pcntl` and `posix` matter: Reverb uses them to shut down cleanly on a signal.
`curl` matters: that is how PHP publishes events to Reverb.

Check a prepared host with:

```bash
php -r 'foreach (["pdo_mysql","redis","mbstring","openssl","curl","pcntl","posix","sodium"] as $e) {
    printf("%-10s %s\n", $e, extension_loaded($e) ? "ok" : "MISSING");
}'
```

## 2. Server preparation

```bash
sudo apt update
sudo apt install -y nginx mysql-server redis-server \
    php8.3-fpm php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml \
    php8.3-curl php8.3-zip php8.3-bcmath unzip git
```

Make sure Redis and MySQL only listen on the loopback. Neither should be
reachable from the internet:

```bash
grep -E '^bind' /etc/redis/redis.conf        # expect: bind 127.0.0.1 ::1
grep -E '^bind-address' /etc/mysql/mysql.conf.d/mysqld.cnf   # expect 127.0.0.1
```

Create a user for the application and a place for its logs:

```bash
sudo mkdir -p /var/www/ginrummy /var/log/ginrummy
sudo chown -R www-data:www-data /var/www/ginrummy /var/log/ginrummy
```

## 3. Getting the code in place

```bash
sudo -u www-data git clone <your-repo> /var/www/ginrummy/current
cd /var/www/ginrummy/current

sudo -u www-data composer install --no-dev --optimize-autoloader
```

Two directories must be writable by the web user, and nothing else needs to be:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

The document root is `public/`. Never expose the project root — `.env` sits in
it.

## 4. Environment

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo chmod 640 .env
```

Then edit `.env`. The settings that matter in production:

```dotenv
APP_NAME="Gin Rummy"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ginrummy.example.com

# Nginx terminates TLS and forwards a plain HTTP request from the loopback.
# Without this the invitation link players copy out of the lobby comes out as
# http://, and every player looks like 127.0.0.1 to the rate limiter, so they
# all share one bucket.
TRUSTED_PROXIES=127.0.0.1

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=ginrummy
DB_USERNAME=ginrummy
DB_PASSWORD=<a long random password>

BROADCAST_CONNECTION=reverb
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# The identity cookie is what a player *is*. Keep it off plain HTTP.
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

LOG_CHANNEL=stack
LOG_LEVEL=warning
```

### The Reverb addresses

Generate credentials once and keep them secret. They are shared between the
application and the Reverb process:

```bash
php -r 'printf("REVERB_APP_ID=%d\nREVERB_APP_KEY=%s\nREVERB_APP_SECRET=%s\n",
    random_int(100000, 999999), bin2hex(random_bytes(10)), bin2hex(random_bytes(16)));'
```

Now the part to get right. There are three groups of variables and they are
**not** interchangeable:

```dotenv
# Where the Reverb process listens. Loopback only — Nginx is the way in.
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# Where PHP publishes events to. Same machine, so no TLS and no round trip
# through Nginx.
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Where the browser connects. Your public domain, over TLS, through Nginx.
# These are compiled into the JavaScript bundle at build time.
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=ginrummy.example.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

`.env.example` ships with `VITE_REVERB_HOST="${REVERB_HOST}"`. **Replace that
line with your domain.** Leaving it pointed at `127.0.0.1` means every player's
browser tries to open a WebSocket to itself, which silently fails and drops the
game onto its slow fallback.

Only `REVERB_APP_KEY` reaches the browser. The secret never does.

## 5. Database

```sql
CREATE DATABASE ginrummy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ginrummy'@'127.0.0.1' IDENTIFIED BY '<the password from .env>';
GRANT ALL PRIVILEGES ON ginrummy.* TO 'ginrummy'@'127.0.0.1';
FLUSH PRIVILEGES;
```

```bash
sudo -u www-data php artisan migrate --force
```

`--force` is required: `migrate` refuses to run unprompted in production
without it.

## 6. Building the frontend

The build bakes `VITE_REVERB_*` into the bundle, so **finish `.env` first**.

```bash
npm ci
npm run build
```

Node is only needed to produce `public/build`. If you would rather not install
it on the server, build on CI or a laptop and ship `public/build` with the
release.

Then cache the framework's own configuration:

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

> After `config:cache`, calls to `env()` outside config files return null. This
> application only reads env inside `config/`, so caching is safe — but it is
> the reason to re-run these three commands after every `.env` change.

## 7. Nginx and TLS

Copy the shipped vhost and adjust the domain and paths:

```bash
sudo cp deploy/nginx.conf /etc/nginx/sites-available/ginrummy
sudo ln -s /etc/nginx/sites-available/ginrummy /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Get a certificate:

```bash
sudo certbot --nginx -d ginrummy.example.com
```

What the vhost does, and why:

- **`location /app/`** proxies the WebSocket to Reverb with the `Upgrade` and
  `Connection` headers it needs. This is the only Reverb endpoint the public
  reaches, and it is why the browser needs no second port or subdomain.
- **`location /build/`** serves the content-hashed assets with a one-year
  cache.
- **Reverb's publishing API is deliberately not proxied.** PHP reaches Reverb
  over the loopback, so exposing `/apps/` would only widen the attack surface.
- `proxy_read_timeout 300s` on the WebSocket keeps idle games connected.

## 8. Background processes

Three processes must run alongside PHP-FPM. Supervisor configuration is in
`deploy/supervisor.conf`; systemd units are in `deploy/*.service` if you prefer
those.

```bash
sudo apt install -y supervisor
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/ginrummy.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status ginrummy:*
```

| Process     | What breaks without it                                                                                |
| ----------- | ----------------------------------------------------------------------------------------------------- |
| `reverb`    | Live updates. Games still work, but players only see each other's moves on a ~2 second fallback poll. |
| `queue`     | Nothing today — broadcasts are sent synchronously. It is here for future queued work.                 |
| `scheduler` | Abandoned lobbies and finished games are never deleted.                                               |

Reverb is the one to watch. Everything else degrades quietly; Reverb going away
is what players actually feel.

## 9. First run checklist

Work through these on the live site. Each one has failed for real at some point.

**The site loads and is not in debug mode**

```bash
curl -sI https://ginrummy.example.com | head -1        # 200
curl -s https://ginrummy.example.com | grep -c Whoops  # 0
```

**Links come out as https.** Create a game in a browser and look at the
invitation URL in the lobby. If it starts with `http://`, `TRUSTED_PROXIES` is
wrong or the config cache is stale.

**The WebSocket connects.** Open the game page, then the browser's Network tab,
filter for WS. You want one connection to
`wss://ginrummy.example.com/app/<your key>` in the _101 Switching Protocols_
state. From the server:

```bash
sudo supervisorctl status ginrummy:ginrummy-reverb   # RUNNING
ss -lntp | grep 8080                                 # 127.0.0.1:8080
```

**Live updates are actually live.** Open the same game in two different
browsers — not two tabs, because a player's identity lives in a cookie and tabs
share one. Move in one window. The other should update immediately, not after a
pause. A pause of a second or two means the WebSocket is not connected and the
fallback poll is carrying the game.

**No broadcast failures are being logged**

```bash
sudo -u www-data grep -c broadcast_failed storage/logs/laravel.log   # 0
```

Any count above zero means PHP cannot reach Reverb. Check `REVERB_HOST`,
`REVERB_PORT` and `REVERB_SCHEME` — the _server-side_ trio.

**Housekeeping is scheduled**

```bash
sudo -u www-data php artisan schedule:list   # games:prune, hourly
```

## 10. Deploying a change

```bash
cd /var/www/ginrummy/current
sudo -u www-data git pull

sudo -u www-data composer install --no-dev --optimize-autoloader
npm ci && npm run build
sudo -u www-data php artisan migrate --force

sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

sudo -u www-data php artisan queue:restart
sudo supervisorctl restart ginrummy:*
```

Restarting Reverb drops every open WebSocket. Browsers reconnect on their own,
and any move missed in the gap is picked up by the next heartbeat, so a game in
progress survives a deploy — players may just see a second of
"Live updates reconnecting…".

For a zero-interruption deploy, use a `releases/` directory with a `current`
symlink and reload PHP-FPM after switching it. Reverb still restarts.

## 11. Troubleshooting

**Moves take a few seconds to appear in the other window.**
Reverb is not reachable. This is the single most common problem, and it looks
like a slow game rather than a broken one, because the application deliberately
keeps working without it. In order:

1. `sudo supervisorctl status ginrummy:*` — is Reverb running?
2. `grep -c broadcast_failed storage/logs/laravel.log` — can PHP reach it?
   If yes, the _server-side_ `REVERB_*` values are wrong.
3. Browser Network tab, WS filter — is the socket open? If not, the
   `VITE_REVERB_*` values are wrong, or `location /app/` is missing from Nginx.
   Fixing `VITE_*` requires a rebuild, not just a config cache clear.

**The invitation link is `http://`.** `TRUSTED_PROXIES` is unset, or
`config:cache` was not re-run after setting it.

**Players are rate limited far too easily.** Same cause. Without trusted
proxies every request appears to come from the proxy, so all players share one
bucket of ten new games a minute.

**"Your session expired. Refresh the page to carry on."** The Redis session
store was flushed, or `APP_KEY` changed. Never rotate `APP_KEY` on a live site
without expecting every player to be logged out of their game — the identity
cookie is encrypted with it.

**500 on every page after a deploy.** Almost always a stale config cache or
`storage/` permissions. `php artisan config:clear` then re-cache, and confirm
`storage/logs` is writable by `www-data`.

**Reverb runs but nothing arrives.** Check the app credentials match on both
sides. PHP and Reverb read the same `REVERB_APP_*` values from the same `.env`,
so a mismatch usually means two different `.env` files, or a cached config from
before they were set.

## 12. Running more than one server

The application is stateless — MySQL holds the game, Redis holds the locks — so
web servers scale horizontally without further work, provided they share one
MySQL and one Redis.

Reverb needs one extra setting. Two Reverb processes hold different sets of
connections, so an event published to one would never reach players attached to
the other. Turn on Redis-backed scaling:

```dotenv
REVERB_SCALING_ENABLED=true
```

Then point every web server's `REVERB_HOST` at the Reverb host rather than
`127.0.0.1`, and make sure your load balancer keeps WebSocket connections
sticky to whichever Reverb node accepted them.

Raise the file descriptor limit before you need it: each connected player is an
open socket.

```ini
# /etc/security/limits.d/ginrummy.conf
www-data soft nofile 10000
www-data hard nofile 10000
```

## 13. Housekeeping and backups

The scheduler runs `games:prune` hourly, deleting idle lobbies after two hours,
abandoned games after twelve and finished games after twenty-four. Adjust in
`.env`:

```dotenv
GIN_RUMMY_WAITING_GAME_TTL_HOURS=2
GIN_RUMMY_PLAYING_GAME_TTL_HOURS=12
GIN_RUMMY_COMPLETED_GAME_TTL_HOURS=24
```

There are no user accounts and nothing personal beyond a nickname that is
deleted within a day, so backups are about being able to rebuild rather than
about protecting data. Keep `.env` somewhere safe — losing `APP_KEY` ends every
game in progress — and take a nightly dump if you want game history:

```bash
mysqldump --single-transaction ginrummy | gzip > /var/backups/ginrummy-$(date +%F).sql.gz
```

Rotate the application log so it cannot fill the disk:

```
# /etc/logrotate.d/ginrummy
/var/www/ginrummy/current/storage/logs/*.log /var/log/ginrummy/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
    su www-data www-data
}
```
