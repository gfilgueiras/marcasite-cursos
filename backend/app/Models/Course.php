<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    /** @use HasFactory<\Database\Factories\CourseFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price_cents',
        'currency',
        'active',
        'enrollment_starts_at',
        'enrollment_ends_at',
        'max_seats',
        'banner_path',
        'materials',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'price_cents' => 'integer',
            'enrollment_starts_at' => 'date',
            'enrollment_ends_at' => 'date',
            'max_seats' => 'integer',
            'materials' => 'array',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Inscrições abertas no dia (data do servidor): dentro do período configurado, se houver.
     */
    public function isOpenForEnrollment(): bool
    {
        $today = now()->startOfDay();
        if ($this->enrollment_starts_at !== null) {
            $start = $this->enrollment_starts_at->copy()->startOfDay();
            if ($today->lt($start)) {
                return false;
            }
        }
        if ($this->enrollment_ends_at !== null) {
            $end = $this->enrollment_ends_at->copy()->startOfDay();
            if ($today->gt($end)) {
                return false;
            }
        }

        return true;
    }

    public function scopeOpenForEnrollment(Builder $query): void
    {
        $today = now()->toDateString();
        $query
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('enrollment_starts_at')
                    ->orWhereDate('enrollment_starts_at', '<=', $today);
            })
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('enrollment_ends_at')
                    ->orWhereDate('enrollment_ends_at', '>=', $today);
            });
    }
}
