<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Jadwal Tes Seleksi PMB</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f5f7fa; margin: 0; padding: 20px;">
<div style="max-width: 560px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0;">

  <div style="background: #2563eb; padding: 24px 32px;">
    <h1 style="color: #fff; margin: 0; font-size: 18px;">Jadwal Tes Seleksi PMB</h1>
    <p style="color: #bfdbfe; margin: 4px 0 0; font-size: 13px;">Penerimaan Mahasiswa Baru</p>
  </div>

  <div style="padding: 32px;">
    <p style="color: #374151; margin: 0 0 16px;">Yth. <strong>{{ $pesertaJadwal->pendaftar->nama }}</strong>,</p>
    <p style="color: #374151; margin: 0 0 24px; line-height: 1.6;">
      Anda telah dijadwalkan untuk mengikuti tes seleksi PMB. Berikut adalah detail jadwal Anda:
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-bottom: 24px;">
      <tr style="background: #f8fafc;">
        <td style="padding: 10px 14px; font-size: 13px; color: #6b7280; font-weight: 600; width: 40%; border: 1px solid #e2e8f0;">Nomor Pendaftaran</td>
        <td style="padding: 10px 14px; font-size: 13px; color: #1e40af; font-weight: 700; border: 1px solid #e2e8f0; font-family: monospace;">{{ $pesertaJadwal->pendaftar->nomor_pendaftaran }}</td>
      </tr>
      <tr>
        <td style="padding: 10px 14px; font-size: 13px; color: #6b7280; font-weight: 600; border: 1px solid #e2e8f0;">Jenis Tes</td>
        <td style="padding: 10px 14px; font-size: 13px; color: #111827; border: 1px solid #e2e8f0;">
          @php $tipaLabels = ['tes_tertulis' => 'Tes Tertulis', 'wawancara' => 'Wawancara', 'tes_praktik' => 'Tes Praktik']; @endphp
          {{ $tipaLabels[$pesertaJadwal->jadwalTes->tipe] ?? $pesertaJadwal->jadwalTes->tipe }}
        </td>
      </tr>
      <tr style="background: #f8fafc;">
        <td style="padding: 10px 14px; font-size: 13px; color: #6b7280; font-weight: 600; border: 1px solid #e2e8f0;">Tanggal</td>
        <td style="padding: 10px 14px; font-size: 13px; color: #111827; border: 1px solid #e2e8f0;">
          {{ \Carbon\Carbon::parse($pesertaJadwal->jadwalTes->tanggal)->translatedFormat('l, d F Y') }}
        </td>
      </tr>
      <tr>
        <td style="padding: 10px 14px; font-size: 13px; color: #6b7280; font-weight: 600; border: 1px solid #e2e8f0;">Waktu</td>
        <td style="padding: 10px 14px; font-size: 13px; color: #111827; border: 1px solid #e2e8f0;">
          {{ $pesertaJadwal->jadwalTes->jam_mulai }} — {{ $pesertaJadwal->jadwalTes->jam_selesai }} WIB
        </td>
      </tr>
      <tr style="background: #f8fafc;">
        <td style="padding: 10px 14px; font-size: 13px; color: #6b7280; font-weight: 600; border: 1px solid #e2e8f0;">Lokasi</td>
        <td style="padding: 10px 14px; font-size: 13px; color: #111827; border: 1px solid #e2e8f0;">{{ $pesertaJadwal->jadwalTes->lokasi }}</td>
      </tr>
    </table>

    @if($pesertaJadwal->token_konfirmasi)
    <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 16px; margin-bottom: 24px;">
      <p style="margin: 0 0 8px; font-size: 13px; color: #1d4ed8; font-weight: 600;">Token Konfirmasi Kehadiran</p>
      <p style="margin: 0 0 10px; font-size: 13px; color: #374151; line-height: 1.6;">
        Gunakan token berikut di halaman <strong>Cek Status</strong> untuk mengkonfirmasi kehadiran Anda:
      </p>
      <div style="background: #fff; border: 1px solid #bfdbfe; border-radius: 4px; padding: 10px 14px; font-family: monospace; font-size: 12px; color: #1e40af; word-break: break-all;">
        {{ $pesertaJadwal->token_konfirmasi }}
      </div>
    </div>
    @endif

    <p style="color: #374151; font-size: 13px; line-height: 1.6; margin: 0 0 8px;">
      Harap datang tepat waktu dan membawa kartu identitas asli.
    </p>
    <p style="color: #374151; font-size: 13px; line-height: 1.6; margin: 0;">
      Jika ada kendala, Anda dapat mengajukan perubahan jadwal melalui halaman <strong>Cek Status</strong> di website PMB.
    </p>
  </div>

  <div style="background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 32px; text-align: center;">
    <p style="margin: 0; font-size: 12px; color: #9ca3af;">Email ini dikirim otomatis. Jangan balas email ini.</p>
  </div>
</div>
</body>
</html>
