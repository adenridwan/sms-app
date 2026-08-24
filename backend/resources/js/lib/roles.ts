/**
 * Label peran untuk ditampilkan ke pengguna.
 *
 * Kunci harus sama persis dengan `name` di database/seeders/RoleSeeder.php —
 * nama teknis (`wali_kelas`) tidak pernah ditampilkan mentah ke layar.
 */
const ROLE_LABELS: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Administrator Sekolah',
    kepala_sekolah: 'Kepala Sekolah',
    wakil_kepala_sekolah: 'Wakil Kepala Sekolah',
    guru: 'Guru',
    wali_kelas: 'Wali Kelas',
    tata_usaha: 'Tata Usaha',
    bendahara: 'Bendahara',
    pustakawan: 'Pustakawan',
    siswa: 'Siswa',
    orang_tua: 'Orang Tua',
};

/** Nama peran yang enak dibaca; peran tak dikenal dirapikan seadanya. */
export function roleLabel(role: string): string {
    return ROLE_LABELS[role] ?? role.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

/**
 * Ringkasan peran untuk ditampilkan di satu baris.
 *
 * Satu akun bisa memegang beberapa peran sekaligus (mis. guru + wali kelas),
 * jadi semuanya digabung — menampilkan satu saja akan menyesatkan.
 */
export function roleSummary(roles: string[] | undefined, userType?: string): string {
    if (roles && roles.length > 0) {
        return roles.map(roleLabel).join(' · ');
    }

    // Akun tanpa role (mis. pendaftar yang belum diaktifkan) tetap punya
    // user_type, jadi masih bisa diberi keterangan yang jujur.
    return userType ? roleLabel(userType) : 'Tanpa peran';
}

/**
 * Role "akses penuh" lintas data (bisa lihat/kelola milik orang lain, bukan
 * cuma dirinya sendiri) — harus SAMA PERSIS dengan
 * Student::ALL_ACCESS_ROLES di backend (dipakai ulang di banyak modul
 * absensi/perizinan sebagai kanonik R4). Ini hanya untuk tampilan (mis.
 * menyembunyikan tombol Setujui/Tolak) — penjagaan sebenarnya tetap di
 * server.
 */
const ALL_ACCESS_ROLES = [
    'super_admin',
    'admin',
    'kepala_sekolah',
    'wakil_kepala_sekolah',
    'tata_usaha',
    'bendahara',
    'pustakawan',
];

export function isFullAccessRole(roles: string[] | undefined): boolean {
    return (roles ?? []).some((r) => ALL_ACCESS_ROLES.includes(r));
}
