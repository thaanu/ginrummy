---
paths:
    - 'tests/**'
---

# Tests

## Acting as a player, and testing channel auth

Use the `asPlayer($gameCode, $token)` helper in tests/Pest.php. It calls `withCredentials()` before `withCookie()` because Laravel's `postJson`/`getJson` send NO cookies unless `withCredentials()` is set — without it every game action returns 403.

Channel authorization tests must swap the broadcaster: the `null` driver used by phpunit.xml skips authorization entirely and returns 200 for everyone. Set `broadcasting.default` to `reverb` (plus key/secret/app_id) AND re-`require base_path('routes/channels.php')`, because channels register onto whichever broadcaster was default at boot, not onto the manager.
