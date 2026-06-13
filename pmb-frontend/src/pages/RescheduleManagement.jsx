import { useState, useEffect } from 'react';
import Button from '../components/ui/Button';
import { jadwalAdminApi, rescheduleAdminApi } from '../utils/api';

const STATUS_LABELS = { pending: 'Menunggu', disetujui: 'Disetujui', ditolak: 'Ditolak' };
const STATUS_COLORS = {
  pending:   'bg-yellow-100 text-yellow-800',
  disetujui: 'bg-green-100 text-green-800',
  ditolak:   'bg-red-100 text-red-800',
};
const TIPE_LABELS = {
  tes_tertulis: 'Tes Tertulis',
  wawancara:    'Wawancara',
  tes_praktik:  'Tes Praktik',
};

const formatTanggal = (dateStr) =>
  new Date(dateStr + 'T00:00:00').toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric',
  });

const FILTER_TABS = [
  { key: 'pending',   label: 'Menunggu' },
  { key: 'disetujui', label: 'Disetujui' },
  { key: 'ditolak',   label: 'Ditolak' },
  { key: '',          label: 'Semua' },
];

const RescheduleManagement = () => {
  const [requests,      setRequests]      = useState([]);
  const [filter,        setFilter]        = useState('pending');
  const [selected,      setSelected]      = useState(null);
  const [jadwalOptions, setJadwalOptions] = useState([]);
  const [jadwalBaru,    setJadwalBaru]    = useState('');
  const [catatan,       setCatatan]       = useState('');
  const [loading,       setLoading]       = useState(false);
  const [error,         setError]         = useState('');
  const [actionMsg,     setActionMsg]     = useState('');

  const fetchRequests = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await rescheduleAdminApi.getAll(filter || undefined);
      setRequests(res.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat data reschedule');
    } finally {
      setLoading(false);
    }
  };

  const fetchJadwalOptions = async () => {
    try {
      const res = await jadwalAdminApi.getAll();
      setJadwalOptions(res.data.filter((j) => j.status === 'published' && j.sisa_kapasitas > 0));
    } catch { /* non-critical */ }
  };

  useEffect(() => { fetchRequests(); }, [filter]);

  const openModal = async (req) => {
    setSelected(req);
    setJadwalBaru('');
    setCatatan('');
    setError('');
    await fetchJadwalOptions();
  };

  const closeModal = () => { setSelected(null); setError(''); };

  const handleApprove = async () => {
    if (!jadwalBaru) { setError('Pilih jadwal baru terlebih dahulu.'); return; }
    setLoading(true);
    setError('');
    try {
      await rescheduleAdminApi.process(selected.id, {
        status:         'disetujui',
        jadwal_baru_id: parseInt(jadwalBaru, 10),
        catatan_admin:  catatan || null,
      });
      setActionMsg('Reschedule disetujui dan notifikasi jadwal baru dikirim.');
      closeModal();
      await fetchRequests();
    } catch (err) {
      setError(err.message || 'Gagal menyetujui reschedule');
    } finally {
      setLoading(false);
    }
  };

  const handleReject = async () => {
    if (!catatan.trim()) { setError('Isi catatan alasan penolakan terlebih dahulu.'); return; }
    setLoading(true);
    setError('');
    try {
      await rescheduleAdminApi.process(selected.id, {
        status:        'ditolak',
        catatan_admin: catatan,
      });
      setActionMsg('Reschedule ditolak.');
      closeModal();
      await fetchRequests();
    } catch (err) {
      setError(err.message || 'Gagal menolak reschedule');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">
      <h2 className="text-base font-semibold text-slate-800">Permintaan Reschedule</h2>

      {actionMsg && <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{actionMsg}</div>}

      {/* Filter tabs */}
      <div className="flex gap-0 border-b border-slate-200">
        {FILTER_TABS.map((tab) => (
          <button
            key={tab.key}
            onClick={() => { setFilter(tab.key); setActionMsg(''); }}
            className={`px-4 py-2.5 text-sm font-medium border-b-2 transition-colors ${
              filter === tab.key
                ? 'border-blue-600 text-blue-600'
                : 'border-transparent text-slate-500 hover:text-slate-700'
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {loading && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500 text-sm">Memuat...</div>
      )}

      {!loading && requests.length === 0 && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-400 text-sm">
          Tidak ada permintaan reschedule{filter ? ` dengan status "${STATUS_LABELS[filter]}"` : ''}.
        </div>
      )}

      {!loading && requests.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full bg-white border border-slate-200 rounded-xl overflow-hidden text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Peserta</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Jadwal Lama</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Alasan</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Tanggal</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {requests.map((req) => (
                <tr key={req.id} className="hover:bg-slate-50 transition-colors">
                  <td className="px-4 py-3">
                    <p className="font-medium text-slate-800">{req.peserta.nama}</p>
                    <p className="text-xs font-mono text-blue-600">{req.peserta.nomor_pendaftaran}</p>
                    <p className="text-xs text-slate-400">{req.peserta.prodi}</p>
                  </td>
                  <td className="px-4 py-3">
                    <p className="text-slate-700 font-medium">{req.jadwal_lama.judul}</p>
                    <p className="text-xs text-slate-400">{TIPE_LABELS[req.jadwal_lama.tipe]} · {formatTanggal(req.jadwal_lama.tanggal)}</p>
                  </td>
                  <td className="px-4 py-3 max-w-[200px]">
                    <p className="text-slate-600 line-clamp-2" title={req.alasan}>{req.alasan}</p>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[req.status]}`}>
                      {STATUS_LABELS[req.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-500 text-xs whitespace-nowrap">
                    {new Date(req.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                  </td>
                  <td className="px-4 py-3">
                    {req.status === 'pending' && (
                      <button
                        onClick={() => openModal(req)}
                        className="text-xs px-3 py-1.5 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors"
                      >
                        Proses
                      </button>
                    )}
                    {req.status !== 'pending' && req.catatan_admin && (
                      <p className="text-xs text-slate-400 italic max-w-[150px]" title={req.catatan_admin}>
                        {req.catatan_admin.length > 50 ? req.catatan_admin.slice(0, 50) + '...' : req.catatan_admin}
                      </p>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Process Modal */}
      {selected && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-semibold text-slate-800">Proses Permintaan Reschedule</h3>
              <button onClick={closeModal} className="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
            </div>
            <div className="p-4 space-y-4">
              {/* Summary */}
              <div className="bg-slate-50 rounded-lg p-3 text-sm space-y-1.5">
                <p><span className="text-slate-500">Peserta:</span> <span className="font-medium">{selected.peserta.nama}</span></p>
                <p><span className="text-slate-500">Jadwal lama:</span> <span className="font-medium">{selected.jadwal_lama.judul}</span></p>
                <p><span className="text-slate-500">Tanggal lama:</span> {formatTanggal(selected.jadwal_lama.tanggal)}</p>
              </div>

              {/* Alasan */}
              <div>
                <p className="text-xs font-medium text-slate-600 mb-1.5">Alasan peserta:</p>
                <p className="text-sm text-slate-700 bg-yellow-50 border border-yellow-200 rounded-lg p-3 leading-relaxed">{selected.alasan}</p>
              </div>

              {/* Jadwal baru */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1.5">
                  Jadwal Baru <span className="text-slate-400">(wajib diisi jika approve)</span>
                </label>
                <select
                  value={jadwalBaru}
                  onChange={(e) => setJadwalBaru(e.target.value)}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="">— Pilih jadwal baru —</option>
                  {jadwalOptions.map((j) => (
                    <option key={j.id} value={j.id}>
                      {j.judul} — {formatTanggal(j.tanggal)} ({j.sisa_kapasitas} sisa kapasitas)
                    </option>
                  ))}
                </select>
                {jadwalOptions.length === 0 && (
                  <p className="text-xs text-slate-400 mt-1">Tidak ada jadwal published dengan sisa kapasitas.</p>
                )}
              </div>

              {/* Catatan */}
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1.5">
                  Catatan Admin <span className="text-slate-400">(wajib jika tolak)</span>
                </label>
                <textarea
                  value={catatan}
                  onChange={(e) => setCatatan(e.target.value)}
                  rows={2}
                  placeholder="Alasan penolakan atau catatan tambahan..."
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                />
              </div>

              {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">{error}</div>}

              <div className="flex gap-2 pt-1">
                <Button variant="primary" disabled={loading} onClick={handleApprove} className="flex-1">
                  {loading ? 'Memproses...' : 'Setujui'}
                </Button>
                <Button variant="danger" disabled={loading} onClick={handleReject} className="flex-1">
                  Tolak
                </Button>
                <Button variant="secondary" onClick={closeModal}>Batal</Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default RescheduleManagement;
