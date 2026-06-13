/**
 * api.js — helper untuk fetch ke Laravel API backend
 * Base URL diambil dari env variable atau default ke localhost:8000
 */
const BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
const TOKEN_KEY = 'pmb_admin_token';

/** Ambil token yang tersimpan di sessionStorage */
export const getToken = () => sessionStorage.getItem(TOKEN_KEY);
/** Simpan token ke sessionStorage */
export const setToken = (token) => sessionStorage.setItem(TOKEN_KEY, token);
/** Hapus token dari sessionStorage */
export const removeToken = () => sessionStorage.removeItem(TOKEN_KEY);

/**
 * Fetch wrapper dengan format response standar dari backend PMB
 * Menyertakan Bearer token jika tersedia
 */
const apiFetch = async (path, options = {}) => {
  const token = getToken();
  const headers = { 'Content-Type': 'application/json', ...options.headers };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const res = await fetch(`${BASE_URL}${path}`, { ...options, headers });
  const json = await res.json();
  if (!res.ok || !json.success) {
    const err = new Error(json.message || 'Terjadi kesalahan pada server');
    err.errors = json.errors || null;
    err.status = res.status;
    throw err;
  }
  return json;
};

export const authApi = {
  /** POST /api/auth/login */
  login: (username, password) =>
    apiFetch('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    }),

  /** POST /api/auth/logout */
  logout: () => apiFetch('/auth/logout', { method: 'POST' }),
};

export const pendaftarApi = {
  /** GET /api/pendaftar — ambil semua pendaftar (perlu token) */
  getAll: () => apiFetch('/pendaftar'),

  /** GET /api/pendaftar/{nomor} — cari berdasarkan nomor pendaftaran */
  getByNomor: (nomor) => apiFetch(`/pendaftar/${encodeURIComponent(nomor)}`),

  /** POST /api/pendaftar — daftar baru */
  store: (data) =>
    apiFetch('/pendaftar', {
      method: 'POST',
      body: JSON.stringify(data),
    }),

  /** PATCH /api/pendaftar/{id}/status — ubah status (perlu token) */
  updateStatus: (id, status) =>
    apiFetch(`/pendaftar/${id}/status`, {
      method: 'PATCH',
      body: JSON.stringify({ status }),
    }),

  /** POST /api/pendaftar/{nomor}/heregistrasi — heregistrasi mahasiswa lolos */
  heregistrasi: (nomor) =>
    apiFetch(`/pendaftar/${encodeURIComponent(nomor)}/heregistrasi`, {
      method: 'POST',
    }),
};

export const statistikApi = {
  /** GET /api/statistik — statistik per prodi, jalur, status (perlu token) */
  get: () => apiFetch('/statistik'),
};

/** URL langsung untuk download CSV (buka di tab baru dengan token di header tidak bisa — gunakan query param workaround) */
export const getExportCsvUrl = () =>
  `${BASE_URL}/pendaftar/export/csv`;

export const jadwalAdminApi = {
  getAll: () => apiFetch('/admin/jadwal'),
  store: (data) => apiFetch('/admin/jadwal', { method: 'POST', body: JSON.stringify(data) }),
  show: (id) => apiFetch(`/admin/jadwal/${id}`),
  update: (id, data) => apiFetch(`/admin/jadwal/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  destroy: (id) => apiFetch(`/admin/jadwal/${id}`, { method: 'DELETE' }),
  publish: (id) => apiFetch(`/admin/jadwal/${id}/publish`, { method: 'POST' }),
  assignAuto: (id) => apiFetch(`/admin/jadwal/${id}/assign-auto`, { method: 'POST' }),
  assignManual: (id, pendaftarIds) => apiFetch(`/admin/jadwal/${id}/assign`, { method: 'POST', body: JSON.stringify({ pendaftar_ids: pendaftarIds }) }),
  getPeserta: (id) => apiFetch(`/admin/jadwal/${id}/peserta`),
  unassign: (jadwalId, pjId) => apiFetch(`/admin/jadwal/${jadwalId}/peserta/${pjId}`, { method: 'DELETE' }),
  updateKehadiran: (jadwalId, pjId, status) => apiFetch(`/admin/jadwal/${jadwalId}/peserta/${pjId}/kehadiran`, { method: 'PATCH', body: JSON.stringify({ status }) }),
  resendNotifikasi: (id) => apiFetch(`/admin/jadwal/${id}/kirim-notifikasi`, { method: 'POST' }),
};

export const rescheduleAdminApi = {
  getAll: (status) => apiFetch(`/admin/reschedule${status ? `?status=${status}` : ''}`),
  process: (id, data) => apiFetch(`/admin/reschedule/${id}`, { method: 'PATCH', body: JSON.stringify(data) }),
};

export const jadwalPublikApi = {
  getByNomor: (nomor) => apiFetch(`/jadwal/${encodeURIComponent(nomor)}`),
  konfirmasi: (nomor, token) => apiFetch(`/jadwal/${encodeURIComponent(nomor)}/konfirmasi`, { method: 'POST', body: JSON.stringify({ token_konfirmasi: token }) }),
  requestReschedule: (nomor, alasan) => apiFetch(`/jadwal/${encodeURIComponent(nomor)}/reschedule`, { method: 'POST', body: JSON.stringify({ alasan }) }),
};
