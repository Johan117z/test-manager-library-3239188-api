<?php

namespace App\Console\Commands;

use App\Application\Auth\EnsureInitialAdminService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/** Run at container startup, after the migrations (T-17). Never prints the password. */
final class EnsureAdminCommand extends Command
{
    protected $signature = 'library:ensure-admin';

    protected $description = 'Create the initial admin from ADMIN_USERNAME / ADMIN_PASSWORD if it does not exist';

    public function handle(EnsureInitialAdminService $service): int
    {
        try {
            $created = $service->ensure(config('library.admin_username'), config('library.admin_password'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($created ? 'Initial admin created.' : 'Initial admin already exists; nothing changed.');

        return self::SUCCESS;
    }
}
