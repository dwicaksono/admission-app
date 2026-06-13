<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateJadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'        => 'sometimes|required|string|max:200',
            'tipe'         => 'sometimes|required|in:tes_tertulis,wawancara,tes_praktik',
            'tanggal'      => 'sometimes|required|date',
            'jam_mulai'    => 'sometimes|required|date_format:H:i',
            'jam_selesai'  => 'sometimes|required|date_format:H:i|after:jam_mulai',
            'lokasi'       => 'sometimes|required|string|max:150',
            'kapasitas'    => 'sometimes|required|integer|min:1|max:500',
            'prodi_filter' => 'nullable|string|max:50',
            'jalur_filter' => 'nullable|string|max:20',
            'catatan'      => 'nullable|string|max:1000',
        ];
    }
}
