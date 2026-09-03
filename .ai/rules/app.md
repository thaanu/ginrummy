---
paths:
    - 'app/**'
---

# App

## Player identity, game codes and the server-authoritative boundary

Identity comes only from the encrypted http-only cookie handled by `App\Services\PlayerIdentity` — never from a route parameter, body field or nickname. Broadcasting authorizes private channels through the custom `player` guard registered in AppServiceProvider; `config/auth.php` has no user provider because the app has no accounts.

Game codes are 8 digit strings. When they are used as array keys in JSON (the identity cookie is a `{code: token}` map) PHP decodes them as INTEGER keys, so cast back with `(string) $code` before comparing. An `is_string($key)` guard silently drops every entry.

Game rules live in `app/Domain/GinRummy` and must stay free of Eloquent, HTTP and broadcasting so they stay testable without booting the framework. Every mutation goes through an action that wraps `GameLock` (Redis lock + transaction + `lockForUpdate`); never mutate a game outside one.
