<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Webhook;

class StripeWebhookService
{
    public function __construct(
        private readonly string $webhookSecret,
    ) {}

    public function parseEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
    }

    public function handle(Event $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event),
            'payment_intent.succeeded' => $this->handlePaymentIntentSucceeded($event),
            default => null,
        };
    }

    private function handleCheckoutSessionCompleted(Event $event): void
    {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;
        $enrollmentId = $this->parseEnrollmentIdFromMetadata($session->metadata ?? null);

        if (! $enrollmentId) {
            Log::warning('stripe.webhook.missing_enrollment_id', [
                'event' => $event->id,
                'session_id' => $session->id ?? null,
            ]);

            return;
        }

        if (! $this->checkoutSessionIndicatesSuccess($session)) {
            Log::info('stripe.webhook.checkout_not_success', [
                'event' => $event->id,
                'session_id' => $session->id ?? null,
                'payment_status' => $session->payment_status ?? null,
            ]);

            return;
        }

        $this->markEnrollmentPaidIfNeeded(
            $event->id,
            $enrollmentId,
            (string) $session->id,
            json_decode(json_encode($session), true) ?? []
        );
    }

    private function handlePaymentIntentSucceeded(Event $event): void
    {
        /** @var \Stripe\PaymentIntent $pi */
        $pi = $event->data->object;
        $enrollmentId = $this->parseEnrollmentIdFromMetadata($pi->metadata ?? null);

        if (! $enrollmentId) {
            return;
        }

        $this->markEnrollmentPaidIfNeeded(
            $event->id,
            $enrollmentId,
            (string) $pi->id,
            json_decode(json_encode($pi), true) ?? []
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawPayload
     */
    private function markEnrollmentPaidIfNeeded(
        string $stripeEventId,
        int $enrollmentId,
        string $providerPaymentId,
        array $rawPayload,
    ): void {
        DB::transaction(function () use ($stripeEventId, $enrollmentId, $providerPaymentId, $rawPayload) {
            $rows = DB::table('stripe_webhook_events')->insertOrIgnore([
                'stripe_event_id' => $stripeEventId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($rows === 0) {
                return;
            }

            $enrollment = Enrollment::query()->lockForUpdate()->find($enrollmentId);
            if (! $enrollment) {
                Log::warning('stripe.webhook.enrollment_not_found', ['enrollment_id' => $enrollmentId]);

                return;
            }

            if ($enrollment->isPaid()) {
                return;
            }

            $enrollment->update([
                'payment_status' => PaymentStatus::Paid->value,
            ]);

            Payment::query()->create([
                'enrollment_id' => $enrollment->id,
                'provider' => 'stripe',
                'provider_payment_id' => $providerPaymentId,
                'amount_cents' => $enrollment->amount_cents,
                'status' => PaymentStatus::Paid->value,
                'raw_payload' => $rawPayload,
            ]);
        });
    }

    private function parseEnrollmentIdFromMetadata(mixed $metadata): ?int
    {
        if ($metadata === null) {
            return null;
        }

        $value = null;
        if (is_array($metadata)) {
            $value = $metadata['enrollment_id'] ?? null;
        } elseif (is_object($metadata)) {
            if (isset($metadata->enrollment_id)) {
                $value = $metadata->enrollment_id;
            } elseif (method_exists($metadata, 'toArray')) {
                $arr = $metadata->toArray();
                $value = $arr['enrollment_id'] ?? null;
            }
        }

        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Só ignora quando o Stripe indica explicitamente que não foi pago.
     * Em alguns payloads o payment_status pode vir vazio; nesse caso seguimos com o metadata.
     */
    private function checkoutSessionIndicatesSuccess(\Stripe\Checkout\Session $session): bool
    {
        $ps = $session->payment_status ?? null;
        if ($ps === 'unpaid') {
            return false;
        }

        if ($ps === 'paid' || $ps === 'no_payment_required') {
            return true;
        }

        if ($ps === null || $ps === '') {
            Log::warning('stripe.webhook.checkout_empty_payment_status', ['session_id' => $session->id ?? null]);

            return true;
        }

        return false;
    }
}
