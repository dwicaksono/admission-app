<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJadwalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'judul'        => 'required|string|max:200',
            'tipe'         => 'required|in:tes_tertulis,wawancara,tes_praktik',
            'tanggal'      => 'required|date',
            'jam_mulai'    => 'required|date_format:H:i',
            'jam_selesai'  => 'required|date_format:H:i|after:jam_mulai',
            'lokasi'       => 'required|string|max:150',
            'kapasitas'    => 'required|integer|min:1|max:500',
            'prodi_filter' => 'nullable|string|max:50',
            'jalur_filter' => 'nullable|string|max:20',
            'catatan'      => 'nullable|string|max:1000',
        ];
    }
}
