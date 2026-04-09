<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $frontendUrl,
    ) {}

    public function createCheckoutSession(Enrollment $enrollment, Course $course, Student $student): array
    {
        $stripe = new StripeClient($this->secretKey);

        $successUrl = rtrim($this->frontendUrl, '/').'/enrollment/success?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = rtrim($this->frontendUrl, '/').'/courses';

        $session = $stripe->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $student->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($course->currency),
                    'unit_amount' => $course->price_cents,
                    'product_data' => [
                        'name' => $course->name,
                        'description' => $course->description ? substr($course->description, 0, 500) : null,
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'enrollment_id' => (string) $enrollment->id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'enrollment_id' => (string) $enrollment->id,
                ],
            ],
        ]);

        return [
            'url' => $session->url,
            'session_id' => $session->id,
        ];
    }
}
