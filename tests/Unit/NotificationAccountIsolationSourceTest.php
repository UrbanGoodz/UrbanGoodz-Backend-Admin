<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class NotificationAccountIsolationSourceTest extends TestCase
{
    private function controllerSource(): string
    {
        return (string) file_get_contents(
            __DIR__.'/../../app/Http/Controllers/Api/V1/UrbanGoodz/NotificationAIController.php'
        );
    }

    public function test_history_requires_the_authenticated_recipient_scope(): void
    {
        $source = $this->controllerSource();
        $history = $this->methodBody($source, 'history', 'markAsRead');

        $this->assertStringContainsString(
            "\$this->checkAuthorization(\$data['recipient_type'], \$data['recipient_id'])",
            $history
        );
        $this->assertStringContainsString("'recipient_type', \$data['recipient_type']", $history);
        $this->assertStringContainsString("'recipient_id', \$data['recipient_id']", $history);
    }

    public function test_mark_read_is_scoped_to_authenticated_customer_and_id(): void
    {
        $source = $this->controllerSource();
        $markRead = $this->methodBody($source, 'markAsRead', 'previewTemplate');

        $this->assertStringContainsString("\$recipientId = (int) auth('api')->id();", $markRead);
        $this->assertStringContainsString("->where('recipient_type', 'customer')", $markRead);
        $this->assertStringContainsString("->where('recipient_id', \$recipientId)", $markRead);
        $this->assertStringNotContainsString('getMorphClass()', $markRead);
    }

    public function test_auth_api_targets_only_the_authenticated_customer(): void
    {
        $source = $this->controllerSource();
        $authorization = $this->methodBody($source, 'checkAuthorization', 'getBaseTemplate');

        $this->assertStringContainsString("\$recipientType === 'customer'", $authorization);
        $this->assertStringContainsString("\$recipientId === (int) auth('api')->id()", $authorization);
        $this->assertStringNotContainsString('::where(', $authorization);
    }

    private function methodBody(string $source, string $method, string $nextMethod): string
    {
        $start = strpos($source, "function {$method}(");
        $end = strpos($source, "function {$nextMethod}(", (int) $start);

        $this->assertNotFalse($start, "Method {$method} was not found.");
        $this->assertNotFalse($end, "Method {$nextMethod} was not found.");

        return substr($source, (int) $start, (int) $end - (int) $start);
    }
}
