# Gin Rummy

A real-time, multiplayer Gin Rummy table for two to four players. No accounts,
no matchmaking: you pick a nickname, get an eight digit code, and share it.

Laravel is the authoritative game server. The browser never shuffles, deals,
decides whose turn it is, or judges a winning hand — it renders what the server
sends and asks the server to make moves.

- [1. Requirements](#1-requirements)
- [2. Installation](#2-installation)
- [3. Environment configuration](#3-environment-configuration)
- [4. MySQL setup](#4-mysql-setup)
- [5. Redis setup](#5-redis-setup)
- [6. Laravel Reverb setup](#6-laravel-reverb-setup)
- [7. Queue worker and scheduler](#7-queue-worker-and-scheduler)
- [8. Local development](#8-local-development)
- [9. Production deployment](#9-production-deployment)
- [10. Running tests](#10-running-tests)
- [11. Game architecture](#11-game-architecture)
- [12. Game rules](#12-game-rules)
- [13. Design decisions](#13-design-decisions)

## 1. Requirements

| Component | Version                                              |
| --------- | ---------------------------------------------------- |
| PHP       | 8.3+ with `pdo_mysql`, `redis`, `mbstring`, `sodium` |
| Composer  | 2.x                                                  |
| Node.js   | 22+ (the Vite toolchain expects it)                  |
| MySQL     | 8.0+                                                 |
| Redis     | 6+                                                   |

## 2. Installation

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

php artisan migrate
npm run build
```

## 3. Environment configuration

Beyond the standard Laravel keys, this application expects:

| Variable                                                 | Purpose                                                      |
| -------------------------------------------------------- | ------------------------------------------------------------ |
| `BROADCAST_CONNECTION=reverb`                            | Sends game events through Reverb.                            |
| `CACHE_STORE=redis`                                      | Backs the per-game locks that serialise moves.               |
| `QUEUE_CONNECTION=redis`                                 | Runs scheduled housekeeping jobs.                            |
| `SESSION_DRIVER=redis`                                   | Sessions carry CSRF protection only, not identity.           |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | Credentials shared between the app and the WebSocket server. |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME`          | Where the **server** publishes events.                       |
| `VITE_REVERB_*`                                          | The same values, compiled into the frontend bundle.          |
| `GIN_RUMMY_WAITING_GAME_TTL_HOURS`                       | Idle lobbies are deleted after this (default 2).             |
| `GIN_RUMMY_PLAYING_GAME_TTL_HOURS`                       | Abandoned games in progress (default 12).                    |
| `GIN_RUMMY_COMPLETED_GAME_TTL_HOURS`                     | Finished games (default 24).                                 |

The `VITE_*` values are baked in at build time, so re-run `npm run build` after
changing them.

## 4. MySQL setup

```sql
CREATE DATABASE ginrummydb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ginrummy'@'127.0.0.1' IDENTIFIED BY 'a-strong-password';
GRANT ALL PRIVILEGES ON ginrummydb.* TO 'ginrummy'@'127.0.0.1';
```

Point `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` at it and run
`php artisan migrate`. Three tables carry the game: `games`, `players` and
`game_events`.

## 5. Redis setup

Redis is not a second source of truth — MySQL is. Redis provides:

- **Atomic locks** (`gin-rummy:game:{id}`) so two simultaneous requests for the
  same table can never interleave.
- The cache and queue backends.
- The session store.

A default local Redis needs no configuration beyond `REDIS_HOST`.

## 6. Laravel Reverb setup

Reverb is already installed and configured. Start it with:

```bash
php artisan reverb:start
```

The application publishes events to Reverb over HTTP, and browsers subscribe
over WebSockets. Both channels are private:

| Channel                           | Who may listen                 | What it carries    |
| --------------------------------- | ------------------------------ | ------------------ |
| `private-game.{code}`             | Any player seated at that game | Public table state |
| `private-game.{code}.player.{id}` | Only that player               | That player's hand |

Channel authorization runs through a custom `player` guard
(`AppServiceProvider::registerPlayerGuard`) that resolves the identity from the
browser's cookie — never from a request parameter.

## 7. Queue worker and scheduler

```bash
php artisan queue:work redis
php artisan schedule:work
```

The scheduler runs `games:prune` hourly to delete abandoned and finished games.
Broadcasts themselves are sent synchronously (`ShouldBroadcastNow`) so a move is
visible immediately even if no worker is running.

## 8. Local development

```bash
composer run dev
```

That starts everything at once: the web server, **Reverb**, the queue listener,
the log viewer and Vite. Reverb is registered as a dev process in
`AppServiceProvider`, because `php artisan dev` does not include it by default.

If you serve the site another way (Herd, Valet, Sail), you still need Reverb
running alongside it:

```bash
php artisan reverb:start
```

With Reverb running, a move reaches the other players' screens in well under a
tenth of a second. Without it, moves are still applied and every player's own
screen stays correct, but the others only catch up on the fallback heartbeat —
a couple of seconds rather than an instant, and the table shows "Live updates
reconnecting…". If play feels laggy, that is the first thing to check; look for
`gin-rummy.broadcast_failed` in the log.

> **Testing with two players on one machine:** a player's identity lives in an
> http-only cookie, and two tabs of the same browser share a cookie jar. Open the
> invitation in a second browser or a private window so each player gets its own
> seat.

## 9. Production deployment

**[how-to-deploy.md](how-to-deploy.md) is the full step-by-step guide**, from a
bare Ubuntu host to a running game, with a first-run checklist and a
troubleshooting section. What follows is the short version.

Ready-made configuration lives in [`deploy/`](deploy):

| File                 | Purpose                                                                                       |
| -------------------- | --------------------------------------------------------------------------------------------- |
| `nginx.conf`         | TLS virtual host; proxies `/app/` and `/apps/` to Reverb so the browser uses a single origin. |
| `supervisor.conf`    | Reverb, two queue workers and the scheduler.                                                  |
| `ginrummy-*.service` | systemd equivalents if you prefer them to Supervisor.                                         |

Release steps:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
supervisorctl restart ginrummy:*
```

Set `APP_ENV=production`, `APP_DEBUG=false`, an `APP_URL` on HTTPS,
`TRUSTED_PROXIES=127.0.0.1` and `SESSION_SECURE_COOKIE=true`.

The trap worth repeating: the server and the browser reach Reverb at different
addresses. `REVERB_HOST=127.0.0.1` with `REVERB_SCHEME=http` is where PHP
publishes; `VITE_REVERB_HOST=your-domain` with `VITE_REVERB_PORT=443` and
`VITE_REVERB_SCHEME=https` is where the browser connects. `.env.example` ties
them together, which is right locally and wrong in production.

## 10. Running tests

```bash
php artisan test          # the whole suite
vendor/bin/pest tests/Unit  # game rules only, no database
composer test             # Pint, PHPStan and the suite
composer ci:check         # the above, plus frontend lint, types and `optimize`
```

The suite covers game creation, joining, seat limits, duplicate nicknames,
dealing, turn order, every legal and illegal move, ace-low runs, sets, mixed
melds, card conservation, hand privacy, channel authorization, reconnection,
concurrency and housekeeping.

`tests/Unit/Domain` exercises the engine directly — no HTTP, no database — which
is the point of keeping the rules free of framework code.

## 11. Game architecture

```
app/
├── Domain/GinRummy/          The rules. No database, HTTP or broadcasting.
│   ├── Card.php              An immutable card, coded "7H", "QS", "10D".
│   ├── Deck.php              A standard 52 card deck.
│   ├── Hand.php              The cards one player holds.
│   ├── GameState.php         The whole game, immutable; transitions return copies.
│   ├── MeldValidator.php     Which melds are in these cards, and do they cover
│   │                         all of them?
│   ├── GinRummyEngine.php    start / draw / discard / declareDone / sortHand.
│   ├── SecureShuffler.php    Fisher-Yates driven by the platform CSPRNG.
│   └── Enums/                GameStatus, TurnPhase, DrawSource, Rank, Suit…
├── Actions/Games/            One class per player action. Lock, mutate, broadcast.
├── Services/
│   ├── PlayerIdentity.php    Issues and resolves temporary player tokens.
│   ├── GameLock.php          Redis lock + transaction around every mutation.
│   ├── GamePresenter.php     The public and private payload shapes.
│   ├── GameBroadcaster.php   Who gets told what.
│   └── GameJournal.php       The game_events audit trail.
├── Events/                   GameStateChanged, PlayerHandChanged.
├── Http/                     Controllers that validate, authorize and delegate.
└── Console/Commands/         games:prune.

resources/js/
├── composables/useGame.ts    Client state. Holds no rules of its own.
├── components/game/          PlayingCard, CardHand, DeckPile, DiscardPile,
│                             PlayerSeat, GameLobby, GameTable.
└── pages/                    Home, Join, Game, Error.
```

**Request flow for a move.** The controller validates the request and identifies
the player from their cookie. The action takes a Redis lock on the game, opens a
transaction, re-reads the row `FOR UPDATE`, hands the state to the engine, writes
the result back, records the event and broadcasts. A rejected move throws a
`GameRuleException`, the transaction rolls back, and the player gets a 422 with a
sentence they can read.

**State privacy.** Two payloads exist and only two. The public one carries card
_counts_; the private one carries one player's cards and goes only to that
player's channel. There is no code path that puts a hand on the table channel,
and tests assert it.

## 12. Game rules

Version 1 uses a simplified "go out with a perfect hand" ruleset.

**Setup.** Ten cards to each player, eleven to a randomly chosen starting
player, the rest becomes the stock. The discard pile starts empty, so the
starting player opens by discarding.

**A turn.** Take one card — from the top of the stock or the top of the discard
pile — then play one back. Ten cards in, eleven, ten again, and the turn passes
clockwise. You cannot draw twice, discard before drawing, or act out of turn.

**Drawing.** Click either pile, or drag a card out of it and into your hand. A
card leaves the stock face up the instant you start dragging: it is yours from
that moment and cannot be put back, exactly as at a real table.

**Discarding.** Drag a card out of your hand and drop it on the discard pile.
The pile lights up while it is your turn to play one, and again as a card is
held over it. Tapping a card and then tapping the pile does the same thing, so
discarding never depends on being able to drag. The turn passes as soon as the
card lands.

The card you just picked up is briefly enlarged, so it is easy to spot in a
fanned hand.

**Sorting your hand.** Drag cards to rearrange them, with a mouse or a finger,
at any point in the game including while waiting for someone else. The card
being dragged lifts clear of the fan and rides above the others, so it stays
readable and out from under the finger holding it, while the rest slide aside to
open a gap. The order is saved, so it survives a refresh. It has no effect on the rules; it is only how
you like to look at your cards. Cards that already form a run or a set are
outlined, a colour per meld, so the shape of a hand is visible at a glance.

**Winning.** Press **Gin** when your ten cards can be split entirely into
melds:

- a **run** of three or more cards in one suit in consecutive rank order, and
- a **set** of three or four cards of the same rank.

Every card must belong to a meld; no deadwood is allowed. Aces are low, so
`A-2-3` is a run and `Q-K-A` is not, and runs never wrap around.

If you are holding eleven cards, put your odd card down on the pile: when the
ten it leaves behind go out, that _is_ going gin, and the pile says so before
you let go. Selecting the card and pressing **Gin** does the same thing.

The button only becomes available when the hand actually goes out, so it can
never be pressed in hope. The server works that out: it reports, privately to
each player, which of their own cards could be put down to win. If a declaration
is somehow made anyway, the server rejects it, nothing changes, and play carries
on.

The server decides all of this itself, by searching for a partition of the hand
into melds. No arrangement supplied by the browser is trusted.

**Running out of cards.** When the stock empties, everything below the top
discard is shuffled into a new stock. If that leaves nothing, the game ends
without a winner rather than deadlocking.

## 13. Design decisions

**MySQL is the source of truth; Redis is for coordination.** The brief suggested
holding live state in Redis. Keeping it in MySQL means a completed game is never
lost to an eviction or a restart, and `SELECT … FOR UPDATE` gives correctness for
free. Redis does what it is uniquely good at: an atomic lock per game, plus cache,
queue and sessions.

**Identity is a cookie, not a session or a parameter.** Each player gets a
64 character random token, stored in one encrypted, http-only cookie that maps
game codes to tokens, so a browser can hold several games at once. Only the
SHA-256 hash is stored. A player id in a URL or body proves nothing.

**The engine is immutable and framework free.** Every transition returns a new
`GameState`, so a rejected move cannot leave a half-applied change behind, and
the rules can be tested without booting Laravel.

**Broadcasts are sent immediately.** `ShouldBroadcastNow` keeps a card landing on
the table without waiting for a queue worker. Queues are reserved for
housekeeping, where latency does not matter.

**Every response also carries state.** A move returns the same payload it
broadcasts, and a heartbeat refetches it. If a WebSocket message is ever missed,
the next interaction repairs the view.

**The heartbeat speeds up when the socket is down.** While Echo is connected it
is only a safety net and presence ping, so fifteen seconds is plenty. While it is
disconnected it is the only thing moving the game along, so it drops to two and a
half. The difference is between a game that feels broken and one that merely
feels slow.

**Turn order beats seat order at the table.** Players are listed in seating order
in the lobby and in turn order once the game starts, so the table reads the way
play actually moves.

**Putting down the last card is going gin, not discarding it.** Discarding and
going gin are the same physical act, so treating them as different buttons let a
player end their turn on a winning hand and lose the win until it came round
again. The pile now declares the win when the card dropped on it completes the
hand, and says "Drop here to go gin" first so nobody is surprised by it.

**A card leaves the pile when the drag starts, not when it lands.** A face-down
stock card cannot be turned over until the server has handed it out, so the draw
is committed the moment the drag begins. That also answers what happens if a
player changes their mind halfway: nothing does. The card is theirs.

**A phone held sideways gets its own layout.** Landscape is not just a wider
portrait: height becomes the scarce dimension, and Tailwind's `sm:` breakpoint
only knows about width, so a wide-but-short screen would otherwise be handed
desktop-sized cards it has no room for. A `landscape-phone` variant, bounded by
height so tablets and desktops never match it, turns the layout on its side —
opponents and piles share one band and the Gin button moves beside the hand.

**The fan is measured, not guessed.** A hand always stays on one row, and no
fixed overlap can do that: eleven cards need a tighter fan on a 320 pixel phone
than on a desktop, and a value that fits the phone wastes half a monitor. The
spacing is computed from the row's own width — the widest that still fits, never
wider than the cards themselves, never tighter than a strip that can still be
grabbed. It falls out at 22 pixels on the narrowest phone and a full 80 with no
overlap at all on a desktop.

**Discarding is a place, not a button.** A disabled button explains nothing: a
player who has not yet chosen a card, or who has not yet drawn, just sees
something greyed out and concludes the game is broken. Dropping a card on the
pile is the move itself, the pile says what it is for at every moment, and there
is no hidden first step to discover.

**Hand order lives on the server, not in the browser.** A hand is already stored
as an ordered list, so rearranging it is just rewriting that list — validated as
the same set of cards, under the same lock every other move takes. That means a
sorted hand survives a refresh and a new device, and the client needs no logic to
merge its own ordering with cards arriving from the server.

**Meld hints come from the engine.** The browser is never told the rules, so the
melds it outlines and the state of the Gin button are both computed by the same
`MeldValidator` that decides who wins. One implementation, no chance of the hint
and the verdict disagreeing.

**One search answers both meld questions.** Highlighting needs the best partial
arrangement; winning needs a complete one. Rather than two algorithms, there is a
single maximum-coverage search, and a winning hand is one where it leaves nothing
over.
