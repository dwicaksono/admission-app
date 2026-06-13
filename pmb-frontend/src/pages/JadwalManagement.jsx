import { useState, useEffect } from 'react';
import Button from '../components/ui/Button';
import { JadwalStatusBadge, PesertaStatusBadge } from '../components/pmb/JadwalStatusBadge';
import { jadwalAdminApi } from '../utils/api';

const TIPE_LABELS = {
  tes_tertulis: 'Tes Tertulis',
  wawancara:    'Wawancara',
  tes_praktik:  'Tes Praktik',
};

const formatTanggal = (dateStr) =>
  new Date(dateStr + 'T00:00:00').toLocaleDateString('id-ID', {
    day: 'numeric', month: 'long', year: 'numeric',
  });

const INITIAL_FORM = {
  judul: '', tipe: 'tes_tertulis', tanggal: '', jam_mulai: '', jam_selesai: '',
  lokasi: '', kapasitas: 30, prodi_filter: '', jalur_filter: '', catatan: '',
};

const JadwalManagement = () => {
  const [jadwalList,  setJadwalList]  = useState([]);
  const [selected,    setSelected]    = useState(null);
  const [pesertaList, setPesertaList] = useState([]);
  const [showForm,    setShowForm]    = useState(false);
  const [form,        setForm]        = useState(INITIAL_FORM);
  const [loading,     setLoading]     = useState(false);
  const [actionMsg,   setActionMsg]   = useState('');
  const [error,       setError]       = useState('');

  const fetchJadwal = async () => {
    setLoading(true);
    setError('');
    try {
      const res = await jadwalAdminApi.getAll();
      setJadwalList(res.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat jadwal');
    } finally {
      setLoading(false);
    }
  };

  const fetchPeserta = async (id) => {
    try {
      const res = await jadwalAdminApi.getPeserta(id);
      setPesertaList(res.data);
    } catch (err) {
      setError(err.message || 'Gagal memuat peserta');
    }
  };

  useEffect(() => { fetchJadwal(); }, []);

  const handleSelect = async (jadwal) => {
    setSelected(jadwal);
    setActionMsg('');
    setError('');
    await fetchPeserta(jadwal.id);
  };

  const handleBack = () => {
    setSelected(null);
    setPesertaList([]);
    setActionMsg('');
    setError('');
    fetchJadwal();
  };

  const handleFormChange = (e) => {
    const { name, value } = e.target;
    setForm((prev) => ({ ...prev, [name]: value }));
  };

  const handleCreateSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      await jadwalAdminApi.store(form);
      setShowForm(false);
      setForm(INITIAL_FORM);
      await fetchJadwal();
      setActionMsg('Jadwal berhasil dibuat.');
    } catch (err) {
      setError(err.message || 'Gagal membuat jadwal');
    } finally {
      setLoading(false);
    }
  };

  const handlePublish = async (id) => {
    if (!window.confirm('Publish jadwal ini dan kirim notifikasi email ke semua peserta?')) return;
    setLoading(true);
    setError('');
    try {
      const res = await jadwalAdminApi.publish(id);
      setActionMsg(res.message || 'Jadwal berhasil dipublish.');
      const updated = await jadwalAdminApi.show(id);
      setSelected(updated.data);
    } catch (err) {
      setError(err.message || 'Gagal mempublish jadwal');
    } finally {
      setLoading(false);
    }
  };

  const handleAutoAssign = async (id) => {
    setLoading(true);
    setError('');
    try {
      const res = await jadwalAdminApi.assignAuto(id);
      setActionMsg(res.message || 'Auto-assign selesai.');
      await fetchPeserta(id);
      const updated = await jadwalAdminApi.show(id);
      setSelected(updated.data);
    } catch (err) {
      setError(err.message || 'Gagal auto-assign');
    } finally {
      setLoading(false);
    }
  };

  const handleResend = async (id) => {
    setLoading(true);
    try {
      const res = await jadwalAdminApi.resendNotifikasi(id);
      setActionMsg(res.message || 'Notifikasi dikirim ulang.');
    } catch (err) {
      setError(err.message || 'Gagal kirim ulang notifikasi');
    } finally {
      setLoading(false);
    }
  };

  const handleUnassign = async (jadwalId, pjId) => {
    if (!window.confirm('Hapus peserta ini dari jadwal?')) return;
    try {
      await jadwalAdminApi.unassign(jadwalId, pjId);
      await fetchPeserta(jadwalId);
      const updated = await jadwalAdminApi.show(jadwalId);
      setSelected(updated.data);
      setActionMsg('Peserta dihapus dari jadwal.');
    } catch (err) {
      setError(err.message || 'Gagal menghapus peserta');
    }
  };

  const handleKehadiran = async (jadwalId, pjId, status) => {
    try {
      await jadwalAdminApi.updateKehadiran(jadwalId, pjId, status);
      await fetchPeserta(jadwalId);
      setActionMsg('Status kehadiran diperbarui.');
    } catch (err) {
      setError(err.message || 'Gagal update kehadiran');
    }
  };

  const handleDelete = async (id) => {
    if (!window.confirm('Hapus jadwal ini? Aksi ini tidak dapat dibatalkan.')) return;
    try {
      await jadwalAdminApi.destroy(id);
      await fetchJadwal();
      setActionMsg('Jadwal dihapus.');
    } catch (err) {
      setError(err.message || 'Gagal menghapus jadwal');
    }
  };

  /* ─── Detail View ─── */
  if (selected) {
    return (
      <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">
        <button onClick={handleBack} className="text-sm text-slate-500 hover:text-slate-700 flex items-center gap-1 transition-colors">
          ← Kembali ke Daftar Jadwal
        </button>

        {actionMsg && <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{actionMsg}</div>}
        {error     && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{error}</div>}

        {/* Jadwal detail card */}
        <div className="bg-white border border-slate-200 rounded-xl p-4 space-y-3">
          <div className="flex items-start justify-between gap-3 flex-wrap">
            <div>
              <p className="text-xs text-slate-400 font-mono mb-0.5">{selected.kode_jadwal}</p>
              <h2 className="font-semibold text-slate-800 text-base">{selected.judul}</h2>
            </div>
            <JadwalStatusBadge status={selected.status} />
          </div>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm pt-1">
            <div><p className="text-xs text-slate-400 mb-0.5">Tipe</p><p className="font-medium text-slate-800">{TIPE_LABELS[selected.tipe]}</p></div>
            <div><p className="text-xs text-slate-400 mb-0.5">Tanggal</p><p className="font-medium text-slate-800">{formatTanggal(selected.tanggal)}</p></div>
            <div><p className="text-xs text-slate-400 mb-0.5">Waktu</p><p className="font-medium text-slate-800">{selected.jam_mulai} – {selected.jam_selesai}</p></div>
            <div><p className="text-xs text-slate-400 mb-0.5">Lokasi</p><p className="font-medium text-slate-800">{selected.lokasi}</p></div>
            <div>
              <p className="text-xs text-slate-400 mb-0.5">Kapasitas</p>
              <p className="font-medium text-slate-800">{selected.jumlah_peserta ?? 0}/{selected.kapasitas}</p>
            </div>
            {selected.prodi_filter && <div><p className="text-xs text-slate-400 mb-0.5">Filter Prodi</p><p className="font-medium text-slate-800">{selected.prodi_filter}</p></div>}
            {selected.jalur_filter && <div><p className="text-xs text-slate-400 mb-0.5">Filter Jalur</p><p className="font-medium text-slate-800">{selected.jalur_filter}</p></div>}
          </div>
        </div>

        {/* Action bar */}
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" disabled={loading} onClick={() => handleAutoAssign(selected.id)}>
            Auto-Assign Peserta
          </Button>
          {selected.status === 'draft' && (
            <Button variant="primary" disabled={loading} onClick={() => handlePublish(selected.id)}>
              Publish & Kirim Notifikasi
            </Button>
          )}
          {selected.status === 'published' && (
            <Button variant="secondary" disabled={loading} onClick={() => handleResend(selected.id)}>
              Kirim Ulang Notifikasi
            </Button>
          )}
        </div>

        {/* Peserta table */}
        <div>
          <h3 className="text-sm font-semibold text-slate-700 mb-3">Daftar Peserta ({pesertaList.length})</h3>
          {pesertaList.length === 0 ? (
            <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-400 text-sm">
              Belum ada peserta. Klik <strong>Auto-Assign</strong> untuk otomatis menambahkan pendaftar Lolos Seleksi.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full bg-white border border-slate-200 rounded-xl overflow-hidden text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Nama</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Nomor Daftar</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Prodi</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                    <th className="px-4 py-3 text-center text-xs font-semibold text-slate-500">Notif</th>
                    <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {pesertaList.map((pj) => (
                    <tr key={pj.id} className="hover:bg-slate-50 transition-colors">
                      <td className="px-4 py-3 font-medium text-slate-800">{pj.pendaftar.nama}</td>
                      <td className="px-4 py-3 font-mono text-blue-600 text-xs">{pj.pendaftar.nomor_pendaftaran}</td>
                      <td className="px-4 py-3 text-slate-600 text-sm">{pj.pendaftar.prodi}</td>
                      <td className="px-4 py-3"><PesertaStatusBadge status={pj.status} /></td>
                      <td className="px-4 py-3 text-center text-sm">{pj.notifikasi_dikirim ? '✓' : '–'}</td>
                      <td className="px-4 py-3">
                        <div className="flex gap-1 flex-wrap">
                          {['assigned', 'confirmed'].includes(pj.status) && (
                            <>
                              <button
                                onClick={() => handleKehadiran(selected.id, pj.id, 'hadir')}
                                className="text-xs px-2 py-1 bg-green-100 text-green-700 rounded-md hover:bg-green-200 transition-colors"
                              >Hadir</button>
                              <button
                                onClick={() => handleKehadiran(selected.id, pj.id, 'tidak_hadir')}
                                className="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-colors"
                              >Tidak Hadir</button>
                            </>
                          )}
                          {selected.status === 'draft' && (
                            <button
                              onClick={() => handleUnassign(selected.id, pj.id)}
                              className="text-xs px-2 py-1 bg-slate-100 text-slate-600 rounded-md hover:bg-slate-200 transition-colors"
                            >Hapus</button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    );
  }

  /* ─── List View ─── */
  return (
    <div className="max-w-6xl mx-auto px-4 py-6 space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-base font-semibold text-slate-800">Jadwal Tes</h2>
        <Button variant="primary" onClick={() => { setShowForm(true); setError(''); setActionMsg(''); }}>
          + Buat Jadwal Baru
        </Button>
      </div>

      {actionMsg && <div className="p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{actionMsg}</div>}
      {error     && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{error}</div>}

      {loading && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-500 text-sm">Memuat...</div>
      )}

      {!loading && jadwalList.length === 0 && (
        <div className="bg-white border border-slate-200 rounded-xl p-8 text-center text-slate-400 text-sm">
          Belum ada jadwal tes. Klik <strong>Buat Jadwal Baru</strong> untuk memulai.
        </div>
      )}

      {!loading && jadwalList.length > 0 && (
        <div className="overflow-x-auto">
          <table className="w-full bg-white border border-slate-200 rounded-xl overflow-hidden text-sm">
            <thead className="bg-slate-50">
              <tr>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Kode</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Judul</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Tipe</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Tanggal</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Kapasitas</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Status</th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {jadwalList.map((j) => (
                <tr key={j.id} className="hover:bg-slate-50 transition-colors">
                  <td className="px-4 py-3 font-mono text-xs text-slate-500">{j.kode_jadwal}</td>
                  <td className="px-4 py-3 font-medium text-slate-800">{j.judul}</td>
                  <td className="px-4 py-3 text-slate-600">{TIPE_LABELS[j.tipe]}</td>
                  <td className="px-4 py-3 text-slate-600">{formatTanggal(j.tanggal)}</td>
                  <td className="px-4 py-3">
                    <span className="text-slate-700 font-medium">{j.jumlah_peserta}/{j.kapasitas}</span>
                    <div className="h-1 bg-slate-100 rounded-full mt-1 w-20">
                      <div
                        className="h-1 bg-blue-500 rounded-full transition-all"
                        style={{ width: `${Math.min(100, Math.round((j.jumlah_peserta / j.kapasitas) * 100))}%` }}
                      />
                    </div>
                  </td>
                  <td className="px-4 py-3"><JadwalStatusBadge status={j.status} /></td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1 flex-wrap">
                      <button
                        onClick={() => handleSelect(j)}
                        className="text-xs px-2 py-1 bg-blue-100 text-blue-700 rounded-md hover:bg-blue-200 transition-colors"
                      >Lihat Peserta</button>
                      {j.status === 'draft' && (
                        <button
                          onClick={() => handleDelete(j.id)}
                          className="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-md hover:bg-red-200 transition-colors"
                        >Hapus</button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create Jadwal Modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between">
              <h3 className="font-semibold text-slate-800">Buat Jadwal Baru</h3>
              <button onClick={() => { setShowForm(false); setError(''); }} className="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
            </div>
            <form onSubmit={handleCreateSubmit} className="p-4 space-y-4">
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Judul *</label>
                <input name="judul" value={form.judul} onChange={handleFormChange} required
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Contoh: Tes Seleksi Gelombang 1 - Teknik Informatika" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Jenis Tes *</label>
                  <select name="tipe" value={form.tipe} onChange={handleFormChange}
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="tes_tertulis">Tes Tertulis</option>
                    <option value="wawancara">Wawancara</option>
                    <option value="tes_praktik">Tes Praktik</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Kapasitas *</label>
                  <input name="kapasitas" type="number" min="1" max="500" value={form.kapasitas} onChange={handleFormChange} required
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Tanggal *</label>
                  <input name="tanggal" type="date" value={form.tanggal} onChange={handleFormChange} required
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Jam Mulai *</label>
                  <input name="jam_mulai" type="time" value={form.jam_mulai} onChange={handleFormChange} required
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Jam Selesai *</label>
                  <input name="jam_selesai" type="time" value={form.jam_selesai} onChange={handleFormChange} required
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Lokasi *</label>
                <input name="lokasi" value={form.lokasi} onChange={handleFormChange} required
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Contoh: Gedung A - Ruang 101" />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Filter Prodi</label>
                  <input name="prodi_filter" value={form.prodi_filter} onChange={handleFormChange}
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Kosongkan = semua prodi" />
                </div>
                <div>
                  <label className="block text-xs font-medium text-slate-600 mb-1">Filter Jalur</label>
                  <input name="jalur_filter" value={form.jalur_filter} onChange={handleFormChange}
                    className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Kosongkan = semua jalur" />
                </div>
              </div>
              <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Catatan</label>
                <textarea name="catatan" value={form.catatan} onChange={handleFormChange} rows={2}
                  className="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                  placeholder="Opsional" />
              </div>
              {error && <div className="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-xs">{error}</div>}
              <div className="flex gap-2 pt-1">
                <Button type="submit" variant="primary" disabled={loading} className="flex-1">
                  {loading ? 'Menyimpan...' : 'Buat Jadwal'}
                </Button>
                <Button type="button" variant="secondary" onClick={() => { setShowForm(false); setError(''); }}>
                  Batal
                </Button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};

export default JadwalManagement;
