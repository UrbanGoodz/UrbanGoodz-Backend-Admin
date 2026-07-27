<?php

namespace App\Logging;

use App\Support\UgRequestId;
use Illuminate\Log\Logger;
use Monolog\LogRecord;

/**
 * Monolog tap that stamps every log record with the Urban Goodz request
 * reference shown on branded error pages.
 *
 * IMPORTANT: this only ADDS context. It never filters, downgrades, or drops
 * records — full exception detail continues to be written to the log exactly
 * as before, so a friendlier error page cannot hide a real server error.
 */
class UgRequestIdTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->pushProcessor(function (LogRecord $record) {
                return $record->with(extra: array_merge($record->extra, [
                    'ug_ref' => UgRequestId::get(),
                ]));
            });
        }
    }
}
