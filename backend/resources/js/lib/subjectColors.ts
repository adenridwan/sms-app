// Warna konsisten per mata pelajaran di grid Jadwal, supaya polanya kebaca
// sekilas tanpa harus baca teks tiap sel. Hash sederhana dari subject_id
// (uuid) -> index palet, jadi mapel yang sama selalu dapat warna yang sama
// selama sesi (dan antar sesi, karena id-nya stabil).
const SUBJECT_PALETTE = [
    'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/40 dark:border-blue-800 dark:text-blue-200',
    'bg-emerald-50 border-emerald-200 text-emerald-900 dark:bg-emerald-950/40 dark:border-emerald-800 dark:text-emerald-200',
    'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/40 dark:border-amber-800 dark:text-amber-200',
    'bg-violet-50 border-violet-200 text-violet-900 dark:bg-violet-950/40 dark:border-violet-800 dark:text-violet-200',
    'bg-rose-50 border-rose-200 text-rose-900 dark:bg-rose-950/40 dark:border-rose-800 dark:text-rose-200',
    'bg-cyan-50 border-cyan-200 text-cyan-900 dark:bg-cyan-950/40 dark:border-cyan-800 dark:text-cyan-200',
    'bg-lime-50 border-lime-200 text-lime-900 dark:bg-lime-950/40 dark:border-lime-800 dark:text-lime-200',
    'bg-fuchsia-50 border-fuchsia-200 text-fuchsia-900 dark:bg-fuchsia-950/40 dark:border-fuchsia-800 dark:text-fuchsia-200',
];

export function subjectColorClasses(subjectId: string): string {
    let hash = 0;
    for (let i = 0; i < subjectId.length; i++) {
        hash = (hash * 31 + subjectId.charCodeAt(i)) >>> 0;
    }
    return SUBJECT_PALETTE[hash % SUBJECT_PALETTE.length];
}
