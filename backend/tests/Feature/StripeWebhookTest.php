<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Garante o mesmo segredo do TestCase::stripeSignatureHeader mesmo com .env do Docker.
        config(['services.stripe.webhook_secret' => 'test_webhook_secret']);
    }

    public function test_rejects_invalid_signature(): void
    {
        $response = $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 'invalid',
        ], '{}');

        $response->assertStatus(400);
    }

    public function test_marks_enrollment_paid_on_checkout_completed(): void
    {
        $enrollment = Enrollment::factory()->create([
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_webhook_1',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_1',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'metadata' => [
                        'enrollment_id' => (string) $enrollment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignatureHeader($payload),
        ], $payload)->assertOk();

        $enrollment->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $enrollment->payment_status);
        $this->assertSame(1, Payment::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_duplicate_event_is_idempotent(): void
    {
        $enrollment = Enrollment::factory()->create([
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $payload = json_encode([
            'id' => 'evt_duplicate',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_dup',
                    'object' => 'checkout.session',
                    'payment_status' => 'paid',
                    'metadata' => [
                        'enrollment_id' => (string) $enrollment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignatureHeader($payload),
        ];

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], $server, $payload)->assertOk();

        $this->assertSame(1, Payment::query()->where('enrollment_id', $enrollment->id)->count());
    }

    public function test_marks_enrollment_paid_on_payment_intent_succeeded(): void
    {
        $enrollment = Enrollment::factory()->create([
            'payment_status' => PaymentStatus::Pending->value,
        ]);

        $payload = json_encode([
            'id' => 'evt_test_pi_1',
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test_1',
                    'object' => 'payment_intent',
                    'metadata' => [
                        'enrollment_id' => (string) $enrollment->id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/v1/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $this->stripeSignatureHeader($payload),
        ], $payload)->assertOk();

        $enrollment->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $enrollment->payment_status);
    }
}
