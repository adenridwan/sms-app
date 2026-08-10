import { usePage } from '@inertiajs/react';
import type { PageProps } from '@/types';

/**
 * Pengecekan izin di sisi tampilan.
 *
 * Pola `isSuperAdmin || permissions.includes(...)` sebelumnya disalin ulang di
 * tiap halaman; disatukan di sini supaya tombol yang muncul konsisten dengan
 * izin yang sebenarnya dimiliki.
 *
 * INI HANYA UNTUK TAMPILAN. Penjagaan sebenarnya tetap di server (middleware
 * `permission:` pada rute web + `abort_unless`/FormRequest pada endpoint API) —
 * menyembunyikan tombol saja tidak menghentikan siapa pun yang memanggil API
 * langsung.
 */
export function usePermissions() {
    const { auth } = usePage<PageProps>().props;
    const user = auth?.user ?? null;

    const isSuperAdmin = user?.user_type === 'super_admin';
    const permissions = user?.permissions ?? [];
    const roles = user?.roles ?? [];

    const can = (permission: string) => isSuperAdmin || permissions.includes(permission);

    return {
        user,
        roles,
        isSuperAdmin,
        can,
        /** true bila memegang salah satu dari izin yang disebut. */
        canAny: (...list: string[]) => list.some(can),
    };
}
