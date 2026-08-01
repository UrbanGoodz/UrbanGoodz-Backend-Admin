<?php

namespace Tests\Feature;

use App\Contracts\Repositories\DeliveryManRepositoryInterface;
use App\Contracts\Repositories\DmReviewRepositoryInterface;
use App\Http\Controllers\Admin\DeliveryMan\DeliveryManController;
use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Services\DeliveryManRatingSummary;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Mockery;
use Tests\TestCase;

class AdminDriverProfileRatingTest extends TestCase
{
    private array $labels = [
        5 => 'Excellent',
        4 => 'Good',
        3 => 'Average',
        2 => 'Below average',
        1 => 'Poor',
    ];

    public function test_driver_profile_route_is_the_authorized_admin_preview_route(): void
    {
        $route = Route::getRoutes()->getByName('admin.users.delivery-man.preview');

        $this->assertNotNull($route);
        $this->assertSame('admin/users/delivery-man/preview/{id}/{tab?}', $route->uri());
        $this->assertSame(
            'App\\Http\\Controllers\\Admin\\DeliveryMan\\DeliveryManController@getPreview',
            $route->getActionName()
        );
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertContains('module:deliveryman', $route->gatherMiddleware());
    }

    public function test_authorized_admin_can_open_the_driver_profile_controller_with_eager_loaded_rating_data(): void
    {
        $admin = new Admin();
        $admin->id = 1;
        $this->actingAs($admin, 'admin');

        $deliveryMan = new DeliveryMan([
            'id' => 42,
            'type' => 'zone_wise',
            'application_status' => 'approved',
            'f_name' => 'Jordan',
            'l_name' => 'Driver',
            'email' => 'driver@example.test',
            'phone' => '+15555550123',
        ]);
        $deliveryMan->setRelation('reviews', collect([
            (object) ['rating' => 5],
            (object) ['rating' => 3],
        ]));

        $deliveryManRepo = Mockery::mock(DeliveryManRepositoryInterface::class);
        $deliveryManRepo->shouldReceive('getFirstWhere')
            ->once()
            ->with(['type' => 'zone_wise', 'id' => 42], ['reviews', 'zone', 'vehicle'])
            ->andReturn($deliveryMan);

        $reviews = new LengthAwarePaginator([], 0, 25);
        $dmReviewRepo = Mockery::mock(DmReviewRepositoryInterface::class);
        $dmReviewRepo->shouldReceive('getListWhere')
            ->once()
            ->with(null, ['delivery_man_id' => 42], ['customer', 'order'], 25)
            ->andReturn($reviews);

        $this->app->instance(DeliveryManRepositoryInterface::class, $deliveryManRepo);
        $this->app->instance(DmReviewRepositoryInterface::class, $dmReviewRepo);

        $view = $this->app->make(DeliveryManController::class)
            ->getPreview(Request::create('/admin/users/delivery-man/preview/42'), 42);

        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertSame('admin-views.delivery-man.view.info', $view->name());
        $this->assertSame(4.0, $view->getData()['ratingSummary']['average']);
        $this->assertSame(2, $view->getData()['ratingSummary']['total']);
        $this->assertSame($reviews, $view->getData()['reviews']);
    }

    public function test_zero_reviews_render_a_safe_zero_state_without_raw_blade(): void
    {
        $summary = (new DeliveryManRatingSummary())->build(null);
        $html = $this->renderSummary($summary);

        $this->assertSame(0.0, $summary['average']);
        $this->assertSame(0, $summary['total']);
        $this->assertStringContainsString('0.0', $html);
        $this->assertStringContainsString('/5', $html);
        $this->assertStringContainsString('No reviews or ratings yet', $html);
        $this->assertStringContainsString('data-testid="driver-rating-distribution"', $html);
        $this->assertNoRawBlade($html);
    }

    public function test_reviews_render_the_correct_average_total_and_distribution(): void
    {
        $reviews = collect([
            (object) ['rating' => 5, 'customer' => null],
            (object) ['rating' => 5, 'customer' => (object) ['f_name' => 'A']],
            (object) ['rating' => 3, 'customer' => null],
            (object) ['rating' => 1, 'customer' => null],
        ]);

        $summary = (new DeliveryManRatingSummary())->build($reviews);
        $html = $this->renderSummary($summary);

        $this->assertSame(3.5, $summary['average']);
        $this->assertSame(4, $summary['total']);
        $this->assertSame(2, $summary['distribution'][5]['count']);
        $this->assertSame(50.0, $summary['distribution'][5]['percentage']);
        $this->assertSame(1, $summary['distribution'][3]['count']);
        $this->assertSame(25.0, $summary['distribution'][3]['percentage']);
        $this->assertSame(1, $summary['distribution'][1]['count']);
        $this->assertSame(25.0, $summary['distribution'][1]['percentage']);
        $this->assertSame(0, $summary['distribution'][4]['count']);
        $this->assertSame(0, $summary['distribution'][2]['count']);
        $this->assertStringContainsString('3.5', $html);
        $this->assertStringContainsString('4 Reviews', $html);
        $this->assertNoRawBlade($html);
    }

    public function test_null_and_missing_rating_values_do_not_throw(): void
    {
        $summary = (new DeliveryManRatingSummary())->build([
            null,
            (object) ['rating' => null],
            [],
        ]);

        $this->assertSame(0.0, $summary['average']);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(0, array_sum(array_column($summary['distribution'], 'count')));
        $this->assertNoRawBlade($this->renderSummary($summary));
    }

    public function test_profile_template_preserves_identity_metadata_and_compiles_without_the_malformed_block(): void
    {
        $source = file_get_contents(resource_path('views/admin-views/delivery-man/view/info.blade.php'));
        $compiled = Blade::compileString($source);

        $this->assertStringContainsString("\$deliveryMan['f_name']", $source);
        $this->assertStringContainsString("\$deliveryMan['email']", $source);
        $this->assertStringContainsString("\$deliveryMan['phone']", $source);
        $this->assertStringContainsString("\$deliveryMan?->vehicle?->type", $source);
        $this->assertStringContainsString("\$deliveryMan->zone", $source);
        $this->assertStringContainsString('driver-profile-overview', $source);
        $this->assertStringContainsString('flex-xl-row', $source);
        $this->assertStringNotContainsString('@php($total', $source);
        $this->assertStringNotContainsString('dm_rating_count(', $source);
        $this->assertStringNotContainsString('$deliveryMan->rating[0]', $source);
        $this->assertStringNotContainsString('{{', $compiled);
        $this->assertStringNotContainsString('@endif', $compiled);
        $this->assertStringNotContainsString('@foreach', $compiled);
        $this->assertStringNotContainsString('@php', $compiled);
    }

    public function test_rating_partial_has_mobile_safe_bounded_markup(): void
    {
        $source = file_get_contents(resource_path('views/admin-views/delivery-man/partials/_rating_summary.blade.php'));
        $profile = file_get_contents(resource_path('views/admin-views/delivery-man/view/info.blade.php'));

        $this->assertStringContainsString('w-100', $source);
        $this->assertStringContainsString('flex-column flex-sm-row', $source);
        $this->assertStringContainsString('flex-grow-1', $source);
        $this->assertStringContainsString('overflow-hidden', $profile);
        $this->assertStringContainsString('@media (max-width: 575.98px)', $profile);
        $this->assertStringContainsString('overflow-wrap: anywhere', $profile);
    }

    private function renderSummary(array $summary): string
    {
        return view('admin-views.delivery-man.partials._rating_summary', [
            'ratingSummary' => $summary,
            'ratingLabels' => $this->labels,
            'reviewLabel' => 'Reviews',
            'emptyRatingMessage' => 'No reviews or ratings yet',
        ])->render();
    }

    private function assertNoRawBlade(string $html): void
    {
        foreach ([
            '{{',
            '}}',
            '@endif',
            '@foreach',
            '@php',
            '$deliveryMan->',
            'dm_rating_count(',
            'count($deliveryMan->rating)',
            'ErrorException',
            'Undefined offset',
            'Trying to access array offset',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }
}
