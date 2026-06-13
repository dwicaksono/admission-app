import { useState } from 'react';
import Button from '../ui/Button';
import { PesertaStatusBadge } from './JadwalStatusBadge';

const TIPE_LABELS = {
  tes_tertulis: 'Tes Tertulis',
  wawancara:    'Wawancara',
  tes_praktik:  'Tes Praktik',
};

const formatTanggal = (dateStr) =>
  new Date(dateStr + 'T00:00:00').toLocaleDateString('id-ID', {
    weekday: 'long',
    day:     'numeric',
    month:   'long',
    year:    'numeric',
  });

const JadwalCard = ({ nomor, jadwal, onKonfirmasi, onReschedule, loading }) => {
  const [showKonfirmasiForm, setShowKonfirmasiForm] = useState(false);
  const [showRescheduleForm, setShowRescheduleForm] = useState(false);
  const [token,        setToken]        = useState('');
  const [alasan,       setAlasan]       = useState('');
  const [localError,   setLocalError]   = useState('');
  const [localSuccess, setLocalSuccess] = useState('');

  const handleKonfirmasiSubmit = async () => {
    if (!token.trim()) { setLocalError('Masukkan token konfirmasi dari email.'); return; }
    setLocalError('');
    try {
      await onKonfirmasi(token.trim());
      setLocalSuccess('Kehadiran berhasil dikonfirmasi!');
      setShowKonfirmasiForm(false);
      setToken('');
    } catch (err) {
      setLocalError(err.message || 'Token tidak valid.');
    }
  };

  const handleRescheduleSubmit = async () => {
    if (alasan.trim().length < 10) { setLocalError('Alasan minimal 10 karakter.'); return; }
    setLocalError('');
    try {
      await onReschedule(alasan.trim());
      setLocalSuccess('Permintaan reschedule berhasil dikirim. Tunggu konfirmasi admin.');
      setShowRescheduleForm(false);
      setAlasan('');
    } catch (err) {
      setLocalError(err.message || 'Gagal mengirim permintaan reschedule.');
    }
  };

  const canKonfirmasi = jadwal.status_peserta === 'assigned';
  const canReschedule =
    ['assigned', 'confirmed'].includes(jadwal.status_peserta) &&
    !jadwal.sudah_lewat &&
    !jadwal.has_pending_reschedule;

  return (
    <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 space-y-4">
      {/* Header */}
      <div className="flex items-start gap-3">
        <div className="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
          <svg className="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-xs text-blue-500 font-medium">Jadwal Tes Seleksi</p>
          <h3 className="font-semibold text-slate-800 text-sm truncate">{jadwal.judul}</h3>
        </div>
        <PesertaStatusBadge status={jadwal.status_peserta} />
      </div>

      {/* Detail grid */}
      <div className="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p className="text-xs text-slate-400 mb-0.5">Jenis Tes</p>
          <p className="font-medium text-slate-800">{TIPE_LABELS[jadwal.tipe] || jadwal.tipe}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">Tanggal</p>
          <p className="font-medium text-slate-800">{formatTanggal(jadwal.tanggal)}</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">Waktu</p>
          <p className="font-medium text-slate-800">{jadwal.jam_mulai} – {jadwal.jam_selesai} WIB</p>
        </div>
        <div>
          <p className="text-xs text-slate-400 mb-0.5">Lokasi</p>
          <p className="font-medium text-slate-800">{jadwal.lokasi}</p>
        </div>
      </div>

      {/* Sudah lewat notice */}
      {jadwal.sudah_lewat && (
        <p className="text-xs text-slate-500 bg-slate-100 border border-slate-200 rounded-lg px-3 py-2">
          Jadwal tes ini sudah berlangsung.
        </p>
      )}

      {/* Pending reschedule notice */}
      {jadwal.has_pending_reschedule && (
        <p className="text-xs text-orange-700 bg-orange-50 border border-orange-200 rounded-lg px-3 py-2">
          Permintaan reschedule Anda sedang diproses admin.
        </p>
      )}

      {/* Success / Error messages */}
      {localSuccess && (
        <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-xs">
          {localSuccess}
        </div>
      )}
      {localError && (
        <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">
          {localError}
        </div>
      )}

      {/* Konfirmasi section */}
      {canKonfirmasi && !showKonfirmasiForm && !localSuccess && (
        <Button
          variant="primary"
          className="w-full text-sm py-2"
          disabled={loading}
          onClick={() => { setShowKonfirmasiForm(true); setShowRescheduleForm(false); setLocalError(''); }}
        >
          Konfirmasi Kehadiran
        </Button>
      )}

      {showKonfirmasiForm && (
        <div className="space-y-2">
          <label className="block text-xs font-medium text-slate-600">
            Masukkan token dari email konfirmasi:
          </label>
          <input
            type="text"
            value={token}
            onChange={(e) => setToken(e.target.value)}
            placeholder="Token konfirmasi (64 karakter)"
            className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500"
          />
          <div className="flex gap-2">
            <Button variant="primary" className="flex-1 text-sm py-2" disabled={loading} onClick={handleKonfirmasiSubmit}>
              {loading ? 'Memproses...' : 'Kirim Konfirmasi'}
            </Button>
            <Button variant="secondary" className="text-sm py-2" onClick={() => { setShowKonfirmasiForm(false); setLocalError(''); }}>
              Batal
            </Button>
          </div>
        </div>
      )}

      {/* Reschedule section */}
      {canReschedule && !showRescheduleForm && (
        <Button
          variant="secondary"
          className="w-full text-sm py-2"
          disabled={loading}
          onClick={() => { setShowRescheduleForm(true); setShowKonfirmasiForm(false); setLocalError(''); }}
        >
          Minta Reschedule
        </Button>
      )}

      {showRescheduleForm && (
        <div className="space-y-2">
          <label className="block text-xs font-medium text-slate-600">
            Alasan reschedule (minimal 10 karakter):
          </label>
          <textarea
            value={alasan}
            onChange={(e) => setAlasan(e.target.value)}
            rows={3}
            placeholder="Jelaskan alasan Anda meminta perubahan jadwal..."
            className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
          />
          <div className="flex gap-2">
            <Button variant="primary" className="flex-1 text-sm py-2" disabled={loading} onClick={handleRescheduleSubmit}>
              {loading ? 'Mengirim...' : 'Kirim Permintaan'}
            </Button>
            <Button variant="secondary" className="text-sm py-2" onClick={() => { setShowRescheduleForm(false); setLocalError(''); }}>
              Batal
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};

export default JadwalCard;
