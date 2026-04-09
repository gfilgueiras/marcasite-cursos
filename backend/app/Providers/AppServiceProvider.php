<?php

namespace App\Providers;

use App\Contracts\PaymentGatewayInterface;
use App\Services\FakePaymentGateway;
use App\Services\StripePaymentGateway;
use App\Services\StripeWebhookService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function () {
            if (app()->environment('testing')) {
                return new FakePaymentGateway;
            }

            $secret = (string) config('services.stripe.secret');
            if ($secret === '') {
                return new FakePaymentGateway;
            }

            return new StripePaymentGateway(
                $secret,
                (string) config('app.frontend_url'),
            );
        });

        $this->app->singleton(StripeWebhookService::class, function () {
            return new StripeWebhookService(
                (string) config('services.stripe.webhook_secret'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
