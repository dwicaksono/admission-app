const JADWAL_STATUS_COLORS = {
  draft:       'bg-slate-100 text-slate-700',
  published:   'bg-blue-100 text-blue-800',
  selesai:     'bg-green-100 text-green-800',
  dibatalkan:  'bg-red-100 text-red-800',
};

const JADWAL_STATUS_LABELS = {
  draft:       'Draft',
  published:   'Dipublish',
  selesai:     'Selesai',
  dibatalkan:  'Dibatalkan',
};

const PESERTA_STATUS_COLORS = {
  assigned:           'bg-yellow-100 text-yellow-800',
  confirmed:          'bg-blue-100 text-blue-800',
  hadir:              'bg-green-100 text-green-800',
  tidak_hadir:        'bg-red-100 text-red-800',
  reschedule_diminta: 'bg-orange-100 text-orange-800',
};

const PESERTA_STATUS_LABELS = {
  assigned:           'Belum Konfirmasi',
  confirmed:          'Dikonfirmasi',
  hadir:              'Hadir',
  tidak_hadir:        'Tidak Hadir',
  reschedule_diminta: 'Minta Reschedule',
};

export const JadwalStatusBadge = ({ status }) => {
  const color = JADWAL_STATUS_COLORS[status] || 'bg-slate-100 text-slate-700';
  const label = JADWAL_STATUS_LABELS[status] || status;
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color}`}>
      {label}
    </span>
  );
};

export const PesertaStatusBadge = ({ status }) => {
  const color = PESERTA_STATUS_COLORS[status] || 'bg-slate-100 text-slate-700';
  const label = PESERTA_STATUS_LABELS[status] || status;
  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${color}`}>
      {label}
    </span>
  );
};
