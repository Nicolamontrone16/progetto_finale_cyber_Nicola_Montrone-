<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Steven Manson (User)', 'user@aulab.it', false, false, false],
            ['Daria Richardson (Writer)', 'writer@aulab.it', false, false, true],
            ['Antony Delgado (Revisor)', 'revisor@aulab.it', false, true, false],
            ['Steve Lorren (Admin)', 'admin@aulab.it', true, false, false],
            ['Mario Bianchi (Super admin)', 'super.admin@aulab.it', true, true, true],
            ['Kevin Ross (Attacker)', 'kvrs@gmail.com', false, false, false],
        ];

        foreach ($users as [$name, $email, $isAdmin, $isRevisor, $isWriter]) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'),
            ]);

            $user->is_admin = $isAdmin;
            $user->is_revisor = $isRevisor;
            $user->is_writer = $isWriter;
            $user->save();
        }
    }
}
