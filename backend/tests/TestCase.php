<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function adminToken(): string
    {
        $user = User::factory()->admin()->create();

        return $user->createToken('test')->plainTextToken;
    }

    protected function stripeSignatureHeader(string $payload, string $secret = 'test_webhook_secret'): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
