<?php

namespace App\Exports;

use App\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CustomerExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Customer::all(['id', 'name', 'address', 'phone_1', 'phone_2', 'code', 'created_at', 'updated_at']);
    }

    public function headings(): array
    {
        return ['ID', 'Name', 'Address', 'Phone 1', 'Phone 2', 'Code', 'Created At', 'Updated At'];
    }
}
