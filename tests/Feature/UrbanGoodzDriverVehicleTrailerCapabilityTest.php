<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\UrbanGoodzDriverCapabilityController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UrbanGoodzDriverVehicleTrailerCapabilityTest extends TestCase
{
    public function test_vehicle_options_endpoint_returns_all_required_keys(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/vehicle-options');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'vehicle_types',
            'trailer_types',
            'hitch_types',
            'cdl_classes',
            'cdl_statuses',
            'capability_tags',
            'work_types',
            'availability_preferences',
            'validation_rules',
        ]);
    }

    public function test_vehicle_types_contain_all_required_types(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/vehicle-options');
        $data = $response->json('vehicle_types');

        $required = [
            'car', 'suv', 'pickup_truck', 'cargo_van', 'passenger_van',
            'sprinter_van', 'box_truck', 'straight_truck', 'bicycle',
            'motorcycle', 'scooter_moped', 'tractor_trailer_18_wheeler',
            'flatbed_truck', 'tow_truck', 'refrigerated_truck',
            'other_commercial_vehicle',
        ];

        foreach ($required as $type) {
            $this->assertArrayHasKey($type, $data, "Missing vehicle type: {$type}");
        }

        $this->assertCount(16, $data);
    }

    public function test_trailer_types_contain_all_required_types(): void
    {
        $response = $this->getJson('/api/v1/urban-goodz/driver/vehicle-options');
        $data = $response->json('trailer_types');

        $required = [
            'utility', 'enclosed', 'flatbed', 'car_hauler', 'gooseneck',
            'fifth_wheel', 'step_deck', 'lowboy', 'refrigerated', 'dry_van', 'other',
        ];

        foreach ($required as $type) {
            $this->assertArrayHasKey($type, $data, "Missing trailer type: {$type}");
        }

        $this->assertCount(11, $data);
    }

    public function test_bicycle_is_in_vehicle_types(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleOptions();
        $this->assertArrayHasKey('bicycle', $options['vehicle_types']);
        $this->assertEquals('Bicycle', $options['vehicle_types']['bicycle']);
    }

    public function test_bicycle_is_spelled_correctly_in_options(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleOptions();
        $this->assertArrayNotHasKey('bike', $options['vehicle_types'], 'Legacy "bike" key should be replaced by "bicycle"');
    }

    public function test_all_vehicle_types_are_snake_case(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleTypesForValidation();
        foreach ($options as $key => $label) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $key, "Vehicle type key '{$key}' is not snake_case");
        }
    }

    public function test_capability_tags_include_load_board_related(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleOptions();
        $this->assertContains('hotshot', $options['capability_tags']);
        $this->assertContains('expedited_freight', $options['capability_tags']);
    }

    public function test_work_types_include_load_board(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleOptions();
        $this->assertContains('load_board', $options['work_types']);
        $this->assertContains('hotshot', $options['work_types']);
    }

    public function test_vehicle_options_validates_all_trailer_types(): void
    {
        $options = UrbanGoodzDriverCapabilityController::vehicleOptions();
        foreach (array_keys($options['trailer_types']) as $type) {
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', $type, "Trailer type key '{$type}' is not snake_case");
        }
    }
}
