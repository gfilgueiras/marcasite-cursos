<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Throwable;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, StripeWebhookService $webhookService): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        try {
            $event = $webhookService->parseEvent($payload, $sigHeader);
            $webhookService->handle($event);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return response('Webhook inválido', 400);
        } catch (Throwable) {
            return response('Erro ao processar webhook', 500);
        }

        return response('OK', 200);
    }
}
