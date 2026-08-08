import { Head, router, usePage } from '@inertiajs/react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Separator } from '@/components/ui/separator';
import { toast } from 'sonner';
import {
    KeyRound,
    Loader2,
    RefreshCw,
    Save,
    Trash2,
    Upload,
    User as UserIcon,
} from 'lucide-react';
import { authApi } from '@/services/api';
import type { User } from '@/types';

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Admin',
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

const MAX_AVATAR_BYTES = 2 * 1024 * 1024;

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

const formatDateTime = (value?: string | null) =>
    value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—';

/**
 * Ubah error validasi Laravel (422) jadi map field → pesan pertama.
 * Selain 422, kembalikan null supaya pemanggil menampilkan toast umum.
 */
const fieldErrorsFrom = (error: unknown): Record<string, string> | null => {
    if (!axios.isAxiosError(error) || !error.response) return null;
    const validationErrors = error.response.data?.errors as Record<string, string[]> | undefined;
    if (!validationErrors) return null;

    return Object.fromEntries(
        Object.entries(validationErrors).map(([field, messages]) => [field, messages[0]])
    );
};

const messageFrom = (error: unknown, fallback: string) =>
    (axios.isAxiosError(error) && error.response?.data?.message) || fallback;

const tabFromUrl = (url: string) =>
    url.split('?')[1]?.split('&').includes('tab=security') ? 'security' : 'account';

interface ProfileForm {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
    phone: string;
}

export default function ProfileEdit() {
    // `/profile?tab=security` dipakai menu "Ganti Password" di navbar. Tab dibuat
    // controlled supaya tautan itu tetap berpindah walau halaman ini sudah terbuka
    // (Inertia hanya mengganti URL, komponennya tidak di-mount ulang).
    const { url } = usePage();
    const [tab, setTab] = useState(() => tabFromUrl(url));
    useEffect(() => setTab(tabFromUrl(url)), [url]);

    const [user, setUser] = useState<User | null>(null);
    const [loading, setLoading] = useState(true);

    const [form, setForm] = useState<ProfileForm>({
        first_name: '',
        last_name: '',
        username: '',
        email: '',
        phone: '',
    });
    const [profileErrors, setProfileErrors] = useState<Record<string, string>>({});
    const [savingProfile, setSavingProfile] = useState(false);

    // Avatar yang dipilih tapi belum disimpan — preview-nya object URL lokal.
    const [avatarFile, setAvatarFile] = useState<File | null>(null);
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [removingAvatar, setRemovingAvatar] = useState(false);
    const avatarInputRef = useRef<HTMLInputElement>(null);

    const [passwordForm, setPasswordForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [passwordErrors, setPasswordErrors] = useState<Record<string, string>>({});
    const [savingPassword, setSavingPassword] = useState(false);

    const applyUser = (data: User) => {
        setUser(data);
        setForm({
            first_name: data.first_name ?? '',
            last_name: data.last_name ?? '',
            username: data.username ?? '',
            email: data.email ?? '',
            phone: data.phone ?? '',
        });
    };

    useEffect(() => {
        authApi
            .profile()
            .then((res) => {
                if (res.data.data) applyUser(res.data.data);
            })
            .catch(() => toast.error('Gagal memuat data profil'))
            .finally(() => setLoading(false));
    }, []);

    // Object URL preview avatar wajib dilepas agar tidak bocor memori.
    useEffect(() => {
        if (!avatarFile) {
            setAvatarPreview(null);
            return;
        }
        const url = URL.createObjectURL(avatarFile);
        setAvatarPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [avatarFile]);

    const pickAvatar = (file: File) => {
        if (file.size > MAX_AVATAR_BYTES) {
            toast.error('Ukuran foto maksimal 2 MB.');
            return;
        }
        setAvatarFile(file);
    };

    const submitProfile = async (e: FormEvent) => {
        e.preventDefault();
        setSavingProfile(true);
        setProfileErrors({});

        const payload = new FormData();
        payload.append('first_name', form.first_name);
        payload.append('last_name', form.last_name);
        payload.append('username', form.username);
        payload.append('email', form.email);
        payload.append('phone', form.phone);
        if (avatarFile) payload.append('avatar', avatarFile);

        try {
            const res = await authApi.updateProfile(payload);
            if (res.data.data) applyUser(res.data.data);
            setAvatarFile(null);
            if (avatarInputRef.current) avatarInputRef.current.value = '';
            toast.success('Profil berhasil disimpan');
            // Muat ulang shared props Inertia supaya nama & avatar di navbar ikut berubah.
            router.reload({ only: ['auth'] });
        } catch (error) {
            const fields = fieldErrorsFrom(error);
            if (fields) {
                setProfileErrors(fields);
                toast.error('Periksa kembali isian yang ditandai.');
            } else {
                toast.error(messageFrom(error, 'Gagal menyimpan profil'));
            }
        } finally {
            setSavingProfile(false);
        }
    };

    const removeAvatar = async () => {
        // Foto yang baru dipilih tapi belum disimpan cukup dibuang di sisi klien.
        if (avatarFile) {
            setAvatarFile(null);
            if (avatarInputRef.current) avatarInputRef.current.value = '';
            return;
        }
        if (!user?.avatar_url) return;
        if (!confirm('Hapus foto profil?')) return;

        setRemovingAvatar(true);
        try {
            await authApi.deleteAvatar();
            setUser((prev) => (prev ? { ...prev, avatar: null, avatar_url: null } : prev));
            toast.success('Foto profil dihapus');
            router.reload({ only: ['auth'] });
        } catch (error) {
            toast.error(messageFrom(error, 'Gagal menghapus foto profil'));
        } finally {
            setRemovingAvatar(false);
        }
    };

    const submitPassword = async (e: FormEvent) => {
        e.preventDefault();

        if (passwordForm.password !== passwordForm.password_confirmation) {
            setPasswordErrors({ password_confirmation: 'Konfirmasi password tidak cocok.' });
            return;
        }

        setSavingPassword(true);
        setPasswordErrors({});

        try {
            await authApi.changePassword(passwordForm);
            setPasswordForm({ current_password: '', password: '', password_confirmation: '' });
            toast.success('Password berhasil diubah');
        } catch (error) {
            const fields = fieldErrorsFrom(error);
            if (fields) {
                setPasswordErrors(fields);
            } else {
                toast.error(messageFrom(error, 'Gagal mengubah password'));
            }
        } finally {
            setSavingPassword(false);
        }
    };

    if (loading) {
        return (
            <MainLayout title="Profil Saya">
                <Head title="Profil Saya" />
                <div className="flex h-64 items-center justify-center">
                    <RefreshCw className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            </MainLayout>
        );
    }

    const displayName = user?.full_name || user?.username || 'Pengguna';
    const currentAvatar = avatarPreview ?? user?.avatar_url ?? undefined;

    return (
        <MainLayout title="Profil Saya">
            <Head title="Profil Saya" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Profil Saya</h1>
                    <p className="text-muted-foreground">Kelola data diri, foto, dan password akun Anda</p>
                </div>

                {/* Ringkasan akun */}
                <Card>
                    <CardContent className="flex flex-wrap items-center gap-4 pt-6">
                        <Avatar className="h-16 w-16 ring-1 ring-border">
                            <AvatarImage src={currentAvatar} alt={displayName} />
                            <AvatarFallback className="bg-primary/10 text-lg font-semibold text-primary">
                                {getInitials(displayName)}
                            </AvatarFallback>
                        </Avatar>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-lg font-semibold">{displayName}</p>
                            <p className="text-muted-foreground truncate text-sm">{user?.email}</p>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {(user?.roles ?? []).map((role) => (
                                    <Badge key={role} variant="secondary">
                                        {roleLabels[role] ?? role}
                                    </Badge>
                                ))}
                            </div>
                        </div>
                        <div className="text-muted-foreground text-sm sm:text-right">
                            <p>Login terakhir</p>
                            <p className="font-medium text-foreground">{formatDateTime(user?.last_login_at)}</p>
                        </div>
                    </CardContent>
                </Card>

                <Tabs value={tab} onValueChange={setTab}>
                    <TabsList>
                        <TabsTrigger value="account" className="gap-2">
                            <UserIcon className="h-4 w-4" />
                            Data Diri
                        </TabsTrigger>
                        <TabsTrigger value="security" className="gap-2">
                            <KeyRound className="h-4 w-4" />
                            Keamanan
                        </TabsTrigger>
                    </TabsList>

                    {/* Data diri + foto */}
                    <TabsContent value="account" className="space-y-4">
                        <form onSubmit={submitProfile}>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Data Diri</CardTitle>
                                    <CardDescription>
                                        Nama, kontak, dan foto yang tampil di seluruh aplikasi.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Foto profil */}
                                    <div className="flex items-center gap-4">
                                        <Avatar className="h-20 w-20 ring-1 ring-border">
                                            <AvatarImage src={currentAvatar} alt={displayName} />
                                            <AvatarFallback className="bg-primary/10 text-xl font-semibold text-primary">
                                                {getInitials(displayName)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div className="space-y-2">
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => avatarInputRef.current?.click()}
                                                >
                                                    <Upload className="mr-2 h-4 w-4" />
                                                    Unggah Foto
                                                </Button>
                                                {(avatarFile || user?.avatar_url) && (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={removeAvatar}
                                                        disabled={removingAvatar}
                                                    >
                                                        {removingAvatar ? (
                                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                        ) : (
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                        )}
                                                        Hapus
                                                    </Button>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground text-xs">
                                                JPG, PNG, atau GIF. Maksimal 2 MB.
                                                {avatarFile && ' Foto baru tersimpan setelah klik Simpan Perubahan.'}
                                            </p>
                                            {profileErrors.avatar && (
                                                <p className="text-sm text-destructive">{profileErrors.avatar}</p>
                                            )}
                                            <input
                                                ref={avatarInputRef}
                                                type="file"
                                                accept="image/jpeg,image/png,image/jpg,image/gif"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) pickAvatar(file);
                                                }}
                                            />
                                        </div>
                                    </div>

                                    <Separator />

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="first_name">Nama Depan</Label>
                                            <Input
                                                id="first_name"
                                                value={form.first_name}
                                                onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                                                className={profileErrors.first_name ? 'border-destructive' : ''}
                                            />
                                            {profileErrors.first_name && (
                                                <p className="text-sm text-destructive">{profileErrors.first_name}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="last_name">Nama Belakang</Label>
                                            <Input
                                                id="last_name"
                                                value={form.last_name}
                                                onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                                                className={profileErrors.last_name ? 'border-destructive' : ''}
                                            />
                                            {profileErrors.last_name && (
                                                <p className="text-sm text-destructive">{profileErrors.last_name}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="username">Username</Label>
                                            <Input
                                                id="username"
                                                value={form.username}
                                                onChange={(e) => setForm({ ...form, username: e.target.value })}
                                                className={profileErrors.username ? 'border-destructive' : ''}
                                            />
                                            {profileErrors.username ? (
                                                <p className="text-sm text-destructive">{profileErrors.username}</p>
                                            ) : (
                                                <p className="text-muted-foreground text-xs">
                                                    Huruf, angka, titik, dash, dan underscore. Minimal 3 karakter.
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={form.email}
                                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                                                className={profileErrors.email ? 'border-destructive' : ''}
                                            />
                                            {profileErrors.email ? (
                                                <p className="text-sm text-destructive">{profileErrors.email}</p>
                                            ) : (
                                                <p className="text-muted-foreground text-xs">
                                                    Dipakai untuk login — pastikan tetap bisa diakses.
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="phone">Nomor HP</Label>
                                            <Input
                                                id="phone"
                                                value={form.phone}
                                                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                                                placeholder="08xxxxxxxxxx"
                                                className={profileErrors.phone ? 'border-destructive' : ''}
                                            />
                                            {profileErrors.phone && (
                                                <p className="text-sm text-destructive">{profileErrors.phone}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={savingProfile}>
                                            {savingProfile ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <Save className="mr-2 h-4 w-4" />
                                            )}
                                            Simpan Perubahan
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>
                    </TabsContent>

                    {/* Ganti password */}
                    <TabsContent value="security" className="space-y-4">
                        <form onSubmit={submitPassword}>
                            <Card>
                                <CardHeader>
                                    <CardTitle>Ganti Password</CardTitle>
                                    <CardDescription>
                                        Password baru berlaku segera. Perangkat lain yang sudah login tidak ikut
                                        ter-logout.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 md:max-w-md">
                                        <div className="space-y-2">
                                            <Label htmlFor="current_password">Password Saat Ini *</Label>
                                            <Input
                                                id="current_password"
                                                type="password"
                                                autoComplete="current-password"
                                                value={passwordForm.current_password}
                                                onChange={(e) =>
                                                    setPasswordForm({
                                                        ...passwordForm,
                                                        current_password: e.target.value,
                                                    })
                                                }
                                                className={passwordErrors.current_password ? 'border-destructive' : ''}
                                            />
                                            {passwordErrors.current_password && (
                                                <p className="text-sm text-destructive">
                                                    {passwordErrors.current_password}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="password">Password Baru *</Label>
                                            <Input
                                                id="password"
                                                type="password"
                                                autoComplete="new-password"
                                                placeholder="Minimal 8 karakter"
                                                value={passwordForm.password}
                                                onChange={(e) =>
                                                    setPasswordForm({ ...passwordForm, password: e.target.value })
                                                }
                                                className={passwordErrors.password ? 'border-destructive' : ''}
                                            />
                                            {passwordErrors.password && (
                                                <p className="text-sm text-destructive">{passwordErrors.password}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="password_confirmation">Konfirmasi Password Baru *</Label>
                                            <Input
                                                id="password_confirmation"
                                                type="password"
                                                autoComplete="new-password"
                                                value={passwordForm.password_confirmation}
                                                onChange={(e) =>
                                                    setPasswordForm({
                                                        ...passwordForm,
                                                        password_confirmation: e.target.value,
                                                    })
                                                }
                                                className={
                                                    passwordErrors.password_confirmation ? 'border-destructive' : ''
                                                }
                                            />
                                            {passwordErrors.password_confirmation && (
                                                <p className="text-sm text-destructive">
                                                    {passwordErrors.password_confirmation}
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={savingPassword}>
                                            {savingPassword ? (
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            ) : (
                                                <KeyRound className="mr-2 h-4 w-4" />
                                            )}
                                            Ganti Password
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </form>

                        <Card>
                            <CardHeader>
                                <CardTitle>Informasi Akun</CardTitle>
                                <CardDescription>Data akun yang hanya bisa diubah administrator.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">Status Akun</dt>
                                        <dd className="mt-1">
                                            {user?.is_active ? (
                                                <Badge className="border-transparent bg-green-500/15 text-green-600 hover:bg-green-500/15 dark:text-green-400">
                                                    Aktif
                                                </Badge>
                                            ) : (
                                                <Badge variant="secondary">Nonaktif</Badge>
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Peran</dt>
                                        <dd className="mt-1 flex flex-wrap gap-1.5">
                                            {(user?.roles ?? []).length === 0
                                                ? '—'
                                                : (user?.roles ?? []).map((role) => (
                                                      <Badge key={role} variant="outline">
                                                          {roleLabels[role] ?? role}
                                                      </Badge>
                                                  ))}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Email Terverifikasi</dt>
                                        <dd className="mt-1">{formatDateTime(user?.email_verified_at)}</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">Terdaftar Sejak</dt>
                                        <dd className="mt-1">{formatDateTime(user?.created_at)}</dd>
                                    </div>
                                </dl>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}
