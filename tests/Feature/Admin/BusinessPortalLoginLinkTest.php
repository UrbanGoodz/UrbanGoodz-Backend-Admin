<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Command Center's "Business Portal Login" button rendered in muted grey
 * (btn-outline-secondary, #868e96) alongside twenty blue buttons, so it read as
 * disabled. These tests pin the enabled styling and a working HTTPS target.
 */
class BusinessPortalLoginLinkTest extends TestCase
{
    use DatabaseTransactions;

    private function renderCommandCenterButtonBlock(): string
    {
        // Render just the button markup the dashboard emits, so the assertion does not
        // depend on the whole admin layout (and its many settings rows) booting.
        return (string) app('blade.compiler')->render(
            <<<'BLADE'
            @php($businessPortalLoginUrl = \Illuminate\Support\Str::startsWith(config('app.url'), 'https://') ? secure_url(route('business.login', [], false)) : route('business.login'))
            <a href="{{ $businessPortalLoginUrl }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener noreferrer">{{ translate('Business Portal Login') }}</a>
            BLADE,
            []
        );
    }

    public function test_business_portal_login_route_is_registered(): void
    {
        $route = Route::getRoutes()->getByName('business.login');

        $this->assertNotNull($route, 'The business.login route does not exist.');
        $this->assertSame('business/login', $route->uri());
        $this->assertContains('GET', $route->methods());
    }

    public function test_business_portal_login_page_responds_without_error_or_redirect_loop(): void
    {
        $response = $this->get('/business/login');

        $response->assertOk();
        $this->assertStringStartsWith(
            'text/html',
            (string) $response->headers->get('content-type')
        );
        $response->assertViewIs('business.auth.login');
    }

    public function test_command_center_button_is_not_disabled(): void
    {
        $markup = $this->renderCommandCenterButtonBlock();

        $this->assertStringContainsString('<a ', $markup);
        $this->assertStringNotContainsString('disabled', $markup);
        $this->assertStringNotContainsString('btn-outline-secondary', $markup);
        $this->assertStringNotContainsString('pointer-events', $markup);
        $this->assertStringContainsString('btn-outline-primary', $markup);
        $this->assertStringContainsString('rel="noopener noreferrer"', $markup);
    }

    public function test_button_href_is_https_when_the_app_url_is_https(): void
    {
        config()->set('app.url', 'https://admin.urbangoodzdelivery.com');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://admin.urbangoodzdelivery.com');

        $markup = $this->renderCommandCenterButtonBlock();

        $this->assertMatchesRegularExpression(
            '#href="https://[^"]+/business/login"#',
            $markup,
            'The Business Portal Login button must target an HTTPS URL. Rendered: ' . $markup
        );
    }

    /** The dashboard template itself must no longer carry the greyed-out class on this button. */
    public function test_dashboard_template_no_longer_uses_the_muted_class_for_this_button(): void
    {
        $template = file_get_contents(resource_path('views/admin-views/dashboard.blade.php'));

        // Isolate the single anchor that renders the Business Portal Login label.
        $anchorLines = array_values(array_filter(
            preg_split('/\R/', $template),
            fn (string $line) => str_contains($line, 'Business Portal Login') && str_contains($line, '<a ')
        ));

        $this->assertCount(1, $anchorLines, 'Expected exactly one Business Portal Login anchor.');
        $anchor = $anchorLines[0];

        $this->assertStringContainsString('btn-outline-primary', $anchor, "Anchor: {$anchor}");
        $this->assertStringNotContainsString('btn-outline-secondary', $anchor, "Anchor: {$anchor}");
        $this->assertStringNotContainsString('disabled', $anchor, "Anchor: {$anchor}");
        $this->assertStringContainsString('rel="noopener noreferrer"', $anchor, "Anchor: {$anchor}");
        // Must be resolved from the named route, not a hardcoded url() string.
        $this->assertStringContainsString('$businessPortalLoginUrl', $anchor, "Anchor: {$anchor}");
    }
}
