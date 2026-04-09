<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Enrollment */
class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_status' => $this->payment_status,
            'amount_cents' => $this->amount_cents,
            'currency' => $this->currency,
            'enrolled_at' => $this->enrolled_at,
            'stripe_checkout_session_id' => $this->stripe_checkout_session_id,
            'student' => [
                'id' => $this->student->id,
                'name' => $this->student->name,
                'email' => $this->student->email,
                'phone' => $this->student->phone,
                'document' => $this->student->document,
            ],
            'course' => new CourseResource($this->course),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
