<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Services\EnrollmentCheckoutService;
use Illuminate\Http\JsonResponse;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentCheckoutService $enrollmentCheckoutService,
    ) {}

    public function store(StoreEnrollmentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->enrollmentCheckoutService->start(
            (int) $data['course_id'],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'document' => $data['document'] ?? null,
            ]
        );

        return response()->json([
            'checkout_url' => $result['checkout_url'],
            'enrollment' => new EnrollmentResource($result['enrollment']),
        ], 201);
    }
}
