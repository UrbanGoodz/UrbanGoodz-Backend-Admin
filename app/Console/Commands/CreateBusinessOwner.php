<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;

class CreateBusinessOwner extends Command
{
    protected $signature = 'urban-goods:create-business-owner
                            {--company= : Business/company name}
                            {--first-name= : Owner first name}
                            {--last-name= : Owner last name}
                            {--email= : Owner email}
                            {--phone= : Owner phone}
                            {--password= : Owner password (auto-generated if omitted)}
                            {--client-id= : Attach to existing business_client_id}
                            {--account-type=business : Account type (business or dispatch_company)}';
    protected $description = 'Create a Business Portal owner account for UrbanGoodz';

    public function handle()
    {
        $this->info('=== UrbanGoodz Business Portal Owner Setup ===');
        $this->newLine();

        $companyName = $this->option('company') ?: $this->ask('Business/company name');
        $firstName = $this->option('first-name') ?: $this->ask('Owner first name');
        $lastName = $this->option('last-name') ?: $this->ask('Owner last name');
        $email = $this->option('email') ?: $this->ask('Owner email');
        $phone = $this->option('phone') ?: $this->ask('Owner phone (leave blank for none)', '');
        $password = $this->option('password') ?: Str::random(16);
        $clientId = $this->option('client-id');
        $accountType = $this->option('account-type');

        // Validate email uniqueness
        $existing = UrbanGoodzBusinessClientUser::where('email', $email)->first();
        if ($existing) {
            $this->error("A business user with email [{$email}] already exists (ID: {$existing->id}).");
            $this->warn('Aborting to prevent duplicate accounts.');
            return 1;
        }

        DB::beginTransaction();
        try {
            // Step 1: Business Client
            if ($clientId) {
                $client = UrbanGoodzBusinessClient::find($clientId);
                if (!$client) {
                    $this->error("Business client ID [{$clientId}] not found.");
                    DB::rollBack();
                    return 1;
                }
                $this->info("Attaching to existing client: {$client->company_name} (ID: {$client->id})");
            } else {
                $client = UrbanGoodzBusinessClient::create([
                    'company_name' => $companyName,
                    'legal_name' => $companyName,
                    'contact_name' => trim($firstName . ' ' . $lastName),
                    'email' => $email,
                    'contact_email' => $email,
                    'phone' => $phone ?: null,
                    'contact_phone' => $phone ?: null,
                    'account_type' => $accountType,
                    'status' => 'approved',
                    'approved_at' => now(),
                    'notes' => 'Created via artisan bootstrap command',
                ]);
                $this->info("Created business client: {$client->company_name} (ID: {$client->id})");
            }

            // Step 2: Owner User
            $user = UrbanGoodzBusinessClientUser::create([
                'business_client_id' => $client->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone ?: null,
                'password' => Hash::make($password),
                'role' => 'owner_admin',
                'permissions' => null,
                'is_active' => true,
                'status' => 'active',
            ]);

            $this->info("Created owner user: {$user->first_name} {$user->last_name} (ID: {$user->id})");

            DB::commit();

            $this->newLine();
            $this->info('========================================');
            $this->info('BUSINESS PORTAL OWNER CREDENTIALS');
            $this->info('========================================');
            $this->info("Company:   {$client->company_name}");
            $this->info("Client ID: {$client->id}");
            $this->info("Name:      {$user->first_name} {$user->last_name}");
            $this->info("Email:     {$email}");
            $this->info("Password:  {$password}");
            $this->info("Role:      owner_admin");
            $this->info("Status:    active");
            $this->info("Login URL: /business/login");
            $this->info('========================================');
            $this->newLine();

            $this->warn('IMPORTANT: Save this password securely. It will not be shown again.');
            $this->info('You can now log in at: /business/login');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to create business owner: {$e->getMessage()}");
            return 1;
        }
    }
}
