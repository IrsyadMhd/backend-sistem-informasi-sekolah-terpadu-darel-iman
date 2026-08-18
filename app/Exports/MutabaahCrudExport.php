<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MutabaahCrudExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Collection $rows, private readonly array $headings) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn ($row) => collect($this->headings)->map(fn ($heading) => data_get($row, $heading))->all());
    }
}
