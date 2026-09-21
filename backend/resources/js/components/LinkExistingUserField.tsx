import { useCallback, useEffect, useState } from 'react';
import { Link2, Loader2, X } from 'lucide-react';

import api from '@/services/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export interface LinkableUser {
    id: string;
    username: string;
    email: string;
    full_name: string;
    status: string;
}

interface Props {
    /** Jenis data master yang sedang dibuat. */
    type: 'teacher' | 'staff' | 'student';
    /** Akun yang dipilih; null = buat akun baru (perilaku lama). */
    value: LinkableUser | null;
    onChange: (user: LinkableUser | null) => void;
    /** Sebutan untuk teks, mis. "guru" / "staf" / "siswa". */
    label: string;
}

/**
 * Opsi "tautkan ke akun yang sudah ada" untuk form Tambah Guru/Staf/Siswa.
 *
 * Latar belakang: menu Pengguna dan menu data master membaca tabel berbeda.
 * Sebuah akun bisa bertipe `teacher` tanpa punya baris di `teachers` — tampil
 * di Pengguna, hilang di Daftar Guru. Sebelum ada opsi ini, form data master
 * selalu membuat akun baru, sehingga "memunculkan" orang tersebut berarti
 * membuat akun kedua yang duplikat.
 *
 * Daftarnya sengaja hanya memuat akun yang BELUM punya data master (lihat
 * UserController::linkableUsers), jadi tidak mungkin memilih yang sudah ada.
 */
export default function LinkExistingUserField({ type, value, onChange, label }: Props) {
    const [enabled, setEnabled] = useState(false);
    const [search, setSearch] = useState('');
    const [options, setOptions] = useState<LinkableUser[]>([]);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const load = useCallback(
        async (term: string) => {
            setLoading(true);
            try {
                const res = await api.get<{ data: LinkableUser[] }>('/admin/users/linkable', {
                    params: { type, search: term || undefined },
                });
                setOptions(res.data.data ?? []);
                setFailed(false);
            } catch {
                setFailed(true);
                setOptions([]);
            } finally {
                setLoading(false);
            }
        },
        [type]
    );

    // Debounce supaya tiap ketikan tidak menembak API.
    useEffect(() => {
        if (!enabled) return;

        const timer = setTimeout(() => load(search), 300);

        return () => clearTimeout(timer);
    }, [enabled, search, load]);

    const toggle = (on: boolean) => {
        setEnabled(on);
        if (!on) {
            onChange(null);
            setSearch('');
        }
    };

    return (
        <div className="space-y-3 rounded-md border bg-muted/30 p-3">
            <label className="flex items-start gap-3">
                <Switch checked={enabled} onCheckedChange={toggle} />
                <span className="space-y-0.5">
                    <span className="block text-sm font-medium">
                        Tautkan ke akun pengguna yang sudah ada
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        Untuk akun yang sudah terdaftar di menu Pengguna sebagai {label}, tapi belum
                        punya data {label}. Tanpa ini, akun baru akan dibuat.
                    </span>
                </span>
            </label>

            {enabled && (
                <div className="space-y-2">
                    {value ? (
                        <div className="flex items-center justify-between gap-2 rounded-md border bg-background p-2">
                            <div className="min-w-0">
                                <div className="truncate text-sm font-medium">{value.full_name}</div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {value.username} · {value.email}
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                onClick={() => onChange(null)}
                                aria-label="Batalkan pilihan akun"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                    ) : (
                        <>
                            <Label htmlFor="link-user-search" className="text-xs">
                                Cari akun
                            </Label>
                            <Input
                                id="link-user-search"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Nama, username, atau email"
                            />

                            <div className="max-h-48 overflow-y-auto rounded-md border bg-background">
                                {loading ? (
                                    <p className="flex items-center justify-center gap-2 p-3 text-sm text-muted-foreground">
                                        <Loader2 className="h-4 w-4 animate-spin" /> Memuat...
                                    </p>
                                ) : failed ? (
                                    <p className="p-3 text-sm text-muted-foreground">
                                        Gagal memuat daftar akun.
                                    </p>
                                ) : options.length === 0 ? (
                                    <p className="p-3 text-sm text-muted-foreground">
                                        Tidak ada akun {label} yang belum punya data {label}.
                                    </p>
                                ) : (
                                    options.map((u) => (
                                        <button
                                            key={u.id}
                                            type="button"
                                            onClick={() => onChange(u)}
                                            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent focus:bg-accent focus:outline-none"
                                        >
                                            <Link2 className="h-4 w-4 shrink-0 text-muted-foreground" />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium">
                                                    {u.full_name}
                                                </span>
                                                <span className="block truncate text-xs text-muted-foreground">
                                                    {u.username} · {u.email}
                                                </span>
                                            </span>
                                        </button>
                                    ))
                                )}
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
