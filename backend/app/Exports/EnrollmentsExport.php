<?php

namespace App\Exports;

use App\Models\Enrollment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EnrollmentsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Collection $enrollments,
    ) {}

    public function collection(): Collection
    {
        return $this->enrollments;
    }

    public function headings(): array
    {
        return [
            'ID inscrição',
            'Aluno',
            'Email',
            'Telefone',
            'Curso',
            'Status pagamento',
            'Valor (centavos)',
            'Data inscrição',
        ];
    }

    /**
     * @param  Enrollment  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->student->name,
            $row->student->email,
            $row->student->phone,
            $row->course->name,
            $row->payment_status,
            $row->amount_cents,
            $row->enrolled_at?->toDateTimeString(),
        ];
    }
}
