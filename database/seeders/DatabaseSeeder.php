<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Crea (o actualiza) el administrador de la plataforma con los datos del .env:
     * ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email || ! $password) {
            $this->command?->warn('Definí ADMIN_EMAIL y ADMIN_PASSWORD en el .env para crear el administrador.');

            return;
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->name = env('ADMIN_NAME', 'Administrador');
        $admin->password = $password;
        $admin->forceFill([
            'is_platform_admin' => true,
            'email_verified_at' => $admin->email_verified_at ?? now(),
        ])->save();

        $this->command?->info("Administrador listo: {$email}");
    }
}
