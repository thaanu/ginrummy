---
paths:
    - app/Services/GameBroadcaster.php
---

# Services

## Broadcast failures must never fail a move

Moves are committed before anything is broadcast, so a `BroadcastException` (typically Reverb not running) is caught and logged as `gin-rummy.broadcast_failed` rather than thrown. Do not let it propagate: it turns a successful, already-persisted move into a 500 and leaves the player confused about whether it happened. Clients recover from the action response or the 15 second heartbeat.

`php artisan dev` does not start Reverb by default; AppServiceProvider registers it as a dev command so `composer run dev` brings it up.
