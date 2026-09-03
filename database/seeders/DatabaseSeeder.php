<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * There is nothing to seed: games are created by players and cleared away
     * again by the `games:prune` command.
     */
    public function run(): void
    {
        //
    }
}
