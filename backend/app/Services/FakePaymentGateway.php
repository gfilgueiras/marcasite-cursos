<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public function createCheckoutSession(Enrollment $enrollment, Course $course, Student $student): array
    {
        $sessionId = 'cs_fake_'.$enrollment->id;

        return [
            'url' => 'https://checkout.stripe.test/fake?enrollment='.$enrollment->id,
            'session_id' => $sessionId,
        ];
    }
}
