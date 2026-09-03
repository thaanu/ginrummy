---
paths:
    - app/Providers/AppServiceProvider.php
---

# Providers

## Register Blade components against the compiler, not the facade

`php artisan optimize` runs every cache in one process. `config:cache` builds a throwaway Application to read fresh config from, and that instance becomes the facade root and container instance without ever being handed back. Any `callAfterResolving('blade.compiler', ...)` callback that then uses the `Blade` _facade_ attaches to the throwaway compiler, so `view:cache` compiling with this application's compiler cannot find the component — the reported `Unable to locate a class or view for component [inertia::app]`.

inertia-laravel v3 does exactly that, so `registerInertiaBladeComponents()` re-registers the namespace using the resolved `$blade` instance. Copy that pattern for any package that hits the same thing; never use the facade inside such a callback.

`composer ci:check` runs the real `php artisan optimize` because no in-process test can stage two application instances faithfully.
