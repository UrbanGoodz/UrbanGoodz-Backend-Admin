<?php
namespace App\Contracts;
use App\Models\UrbanGoodzServiceRequest;
interface ServiceBookingPaymentGateway { public function charge(UrbanGoodzServiceRequest $booking, string $paymentToken, string $idempotencyKey): array; }
