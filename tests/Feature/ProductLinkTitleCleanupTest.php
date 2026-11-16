<?php

namespace Tests\Feature;

use App\Services\ProductLinkParser;
use PHPUnit\Framework\TestCase;

class ProductLinkTitleCleanupTest extends TestCase
{
    private function title(string $t, string $url, string $extra = ''): ?string
    {
        $html = "<html><head><title>{$t}</title>{$extra}</head><body></body></html>";

        return (new ProductLinkParser)->parse($html, $url)['title'];
    }

    public function test_pipe_suffix_is_removed(): void
    {
        $this->assertSame('A Light in the Attic', $this->title('A Light in the Attic | Books to Scrape - Sandbox', 'https://books.toscrape.com/x.html'));
    }

    public function test_dash_suffix_removed_only_when_it_names_the_site(): void
    {
        $this->assertSame('Blue Mug', $this->title('Blue Mug - Acme Store', 'https://www.acmestore.com/p/1'));
        $this->assertSame('Mug - Large Blue', $this->title('Mug - Large Blue', 'https://www.acmestore.com/p/1'));
    }

    public function test_plain_titles_are_untouched(): void
    {
        $this->assertSame('Blue Mug', $this->title('Blue Mug', 'https://shop.example.com/p/1'));
    }
}
