<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exports\EnrollmentsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\Enrollment;
use App\Services\EnrollmentAdminQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollmentAdminQuery $enrollmentAdminQuery,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $this->enrollmentAdminQuery->filterFromRequest($request);
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        return EnrollmentResource::collection($query->paginate($perPage));
    }

    public function show(Enrollment $enrollment): EnrollmentResource
    {
        $enrollment->load(['student', 'course']);

        return new EnrollmentResource($enrollment);
    }

    public function update(UpdateEnrollmentRequest $request, Enrollment $enrollment): EnrollmentResource
    {
        $data = $request->validated();

        if (isset($data['payment_status'])) {
            $enrollment->update(['payment_status' => $data['payment_status']]);
        }

        if (isset($data['student'])) {
            $patch = [];
            foreach (['name', 'email', 'phone', 'document'] as $field) {
                if (array_key_exists($field, $data['student'])) {
                    $patch[$field] = $data['student'][$field];
                }
            }
            if ($patch !== []) {
                $enrollment->student->update($patch);
            }
        }

        return new EnrollmentResource($enrollment->fresh(['student', 'course']));
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();

        return response()->json(null, 204);
    }

    public function export(Request $request): BinaryFileResponse|Response
    {
        $query = $this->enrollmentAdminQuery->filterFromRequest($request);
        $rows = $query->get();

        $format = strtolower((string) $request->query('format', 'xlsx'));

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('exports.enrollments-pdf', ['enrollments' => $rows]);

            return $pdf->download('inscricoes-marcasite.pdf');
        }

        return Excel::download(new EnrollmentsExport($rows), 'inscricoes-marcasite.xlsx');
    }
}
