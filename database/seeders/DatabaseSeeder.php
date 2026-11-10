<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * System galleries + the first administrator from config/cms.php (ADMIN_* in .env).
     */
    public function run(): void
    {
        $this->call(GallerySeeder::class);

        $email = config('cms.admin.email');
        $password = config('cms.admin.password');

        if (! $email || ! $password) {
            throw new RuntimeException('Set ADMIN_EMAIL and ADMIN_PASSWORD in .env before seeding.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => config('cms.admin.name'),
                'password' => $password,
                'role' => Role::Admin,
                'email_verified_at' => now(),
            ],
        );
    }
}
