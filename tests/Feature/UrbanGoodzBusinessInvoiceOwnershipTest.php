<?php

namespace Tests\Feature;

use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzClientInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class UrbanGoodzBusinessInvoiceOwnershipTest extends TestCase
{
    use DatabaseTransactions;

    public function test_business_cannot_view_another_business_invoice(): void
    {
        $this->withoutMiddleware();

        $ownerClient = UrbanGoodzBusinessClient::firstOrCreate(
            ['email' => 'session9-business-owner@urbangoodz.test'],
            [
                'company_name' => 'Session 9 Owner Business',
                'status' => 'approved',
            ]
        );
        $otherClient = UrbanGoodzBusinessClient::firstOrCreate(
            ['email' => 'session9-business-other@urbangoodz.test'],
            [
                'company_name' => 'Session 9 Other Business',
                'status' => 'approved',
            ]
        );
        $ownerUser = UrbanGoodzBusinessClientUser::firstOrCreate(
            [
                'business_client_id' => $ownerClient->id,
                'email' => 'session9-owner-user@urbangoodz.test',
            ],
            [
                'first_name' => 'Business',
                'last_name' => 'Owner',
                'password' => bcrypt('password'),
                'role' => 'owner_admin',
                'is_active' => true,
            ]
        );
        $otherInvoice = UrbanGoodzClientInvoice::create([
            'invoice_number' => 'INV-SESSION-9-OTHER-' . random_int(1000, 9999),
            'business_client_id' => $otherClient->id,
            'invoice_type' => 'custom',
            'subtotal' => 75.00,
            'tax' => 0.00,
            'total' => 75.00,
            'currency' => 'USD',
            'status' => 'sent',
        ]);

        $this->actingAs($ownerUser, 'business');

        $this->get("/business/invoices/{$otherInvoice->id}")
            ->assertNotFound();
    }
}
