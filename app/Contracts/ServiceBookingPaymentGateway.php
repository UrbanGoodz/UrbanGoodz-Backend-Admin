<?php
namespace App\Contracts;
use App\Models\UrbanGoodzServiceRequest;
interface ServiceBookingPaymentGateway { public function chargeSandbox(UrbanGoodzServiceRequest $booking, string $paymentToken, string $idempotencyKey): array; }
