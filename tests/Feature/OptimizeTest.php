<?php

declare(strict_types=1);

/**
 * `php artisan optimize` is the first thing run on a deploy, and it compiles
 * every Blade template in one process. The root layout uses Inertia's
 * <x-inertia::app /> component, so the component namespace has to be attached
 * to the compiler doing the work — not to whichever compiler the Blade facade
 * happens to point at, which `config:cache` quietly replaces mid-run.
 *
 * The swap only happens across two application instances in one process, which
 * these tests cannot stage faithfully; `composer ci:check` runs the real
 * `php artisan optimize` for that.
 */
it('registers the inertia component namespace on the compiler itself', function (): void {
    $blade = app('blade.compiler');

    expect($blade->getClassComponentNamespaces())
        ->toHaveKey('inertia', 'Inertia\View\Components');
});

it('compiles the root layout without losing the inertia components', function (): void {
    $compiled = app('blade.compiler')->compileString(
        file_get_contents(resource_path('views/app.blade.php')),
    );

    expect($compiled)->toContain('Inertia\View\Components\App')
        ->and($compiled)->toContain('Inertia\View\Components\Head')
        ->and($compiled)->not->toContain('x-inertia::');
});
