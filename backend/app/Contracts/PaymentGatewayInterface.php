<?php

namespace App\Contracts;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;

interface PaymentGatewayInterface
{
    /**
     * @return array{url: string, session_id: string}
     */
    public function createCheckoutSession(Enrollment $enrollment, Course $course, Student $student): array;
}
