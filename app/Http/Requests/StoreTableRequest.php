<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tabel wajib diisi.',
            'name.string' => 'Nama tabel harus berupa teks.',
            'name.max' => 'Nama tabel maksimal :max karakter.',
            'price_per_hour.required' => 'Harga per jam wajib diisi.',
            'price_per_hour.numeric' => 'Harga per jam harus berupa angka.',
            'price_per_hour.min' => 'Harga per jam minimal :min.',
        ];
    }
}
