<?php

namespace Tests\Feature;

use App\Models\MeasurementRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InsertsRows;
use Tests\TestCase;

class StylistRequestOwnershipTest extends TestCase
{
    use DatabaseTransactions;
    use InsertsRows;

    private function user(string $t): User
    {
        return User::create(['f_name' => 'St', 'l_name' => $t, 'email' => "st-{$t}-".uniqid().'@urbangoodz.test', 'phone' => '+1555'.random_int(1000000, 9999999), 'password' => bcrypt('x')]);
    }

    private function url(int $id): string
    {
        return "/api/v1/urban-goodz/fashion/stylist-requests/{$id}/status";
    }

    public function test_a_customer_cannot_change_someone_elses_stylist_request_or_set_arbitrary_statuses(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $owner = $this->user('owner');
        $other = $this->user('other');
        $id = $this->insertRow('urban_goodz_measurement_requests', ['customer_id' => $owner->id, 'review_status' => 'pending']);

        $this->actingAs($other, 'api')->postJson($this->url($id), ['status' => 'cancelled'])->assertNotFound();
        $this->actingAs($owner, 'api')->postJson($this->url($id), ['status' => 'approved'])->assertStatus(422);
        $this->assertSame('pending', MeasurementRequest::find($id)->review_status);

        $this->actingAs($owner, 'api')->postJson($this->url($id), ['status' => 'cancelled'])->assertOk();
        $this->assertSame('cancelled', MeasurementRequest::find($id)->review_status);
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }
}
