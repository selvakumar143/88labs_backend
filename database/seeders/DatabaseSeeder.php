<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Call Roles Seeder
        $this->call(RoleSeeder::class);

        DB::transaction(function (): void {
            $admin = User::updateOrCreate(
                ['email' => 'admin@88labs.com'],
                [
                    'name' => 'Test Admin',
                    'password' => Hash::make('paws@123'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );
            $admin->syncRoles(['admin']);

            $clientUser = User::updateOrCreate(
                ['email' => 'customer@88labs.com'],
                [
                    'name' => 'Test Client',
                    'password' => Hash::make('paws@123'),
                    'email_verified_at' => now(),
                    'status' => 'active',
                ]
            );
            $clientUser->syncRoles(['client_admin']);

            $client = Client::updateOrCreate(
                ['email' => 'customer@88labs.com'],
                [
                    'clientCode' => 'CL-TEST-0001',
                    'clientName' => 'Test Client',
                    'country' => 'India',
                    'phone' => '9999999999',
                    'clientType' => 'Agency',
                    'niche' => 'General',
                    'marketCountry' => 'India',
                    'settlementMode' => 'Bank Transfer',
                    'statementCycle' => 'Monthly',
                    'settlementCurrency' => 'INR',
                    'cooperationStart' => now()->toDateString(),
                    'serviceFeePercent' => 5,
                    'serviceFeeEffectiveTime' => now(),
                    'enabled' => true,
                    'primary_admin_user_id' => $clientUser->id,
                    'admin_created_by' => $admin->id,
                ]
            );

            $clientUser->forceFill([
                'client_id' => $client->id,
                'created_by' => $client->id,
            ])->save();
        });
    }
}
