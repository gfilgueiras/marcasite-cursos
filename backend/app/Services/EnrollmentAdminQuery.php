<?php

namespace App\Services;

use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EnrollmentAdminQuery
{
    public function filterFromRequest(Request $request): Builder
    {
        $q = Enrollment::query()
            ->with(['student', 'course'])
            ->orderByDesc('enrolled_at');

        $q->when($request->filled('name'), function (Builder $b) use ($request) {
            $term = $request->string('name');
            $b->whereHas('student', fn (Builder $s) => $s->where('name', 'like', '%'.$term.'%'));
        });

        $q->when($request->filled('email'), function (Builder $b) use ($request) {
            $term = $request->string('email');
            $b->whereHas('student', fn (Builder $s) => $s->where('email', 'like', '%'.$term.'%'));
        });

        $q->when($request->filled('course_id'), function (Builder $b) use ($request) {
            $b->where('course_id', $request->integer('course_id'));
        });

        $q->when($request->filled('status'), function (Builder $b) use ($request) {
            $b->where('payment_status', $request->string('status'));
        });

        return $q;
    }
}
