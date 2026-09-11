import { Head, Link, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import {
    Ban,
    Building2,
    ChevronDown,
    Clock,
    ListChecks,
    Loader2,
    Plus,
    Power,
    Save,
    Trash2,
    Upload,
    Users,
} from 'lucide-react';
import { toast } from 'sonner';
import { schoolApi, type LoginBrand, type SchoolListItem, type SchoolProfile } from '@/services/school';
import type { PageProps } from '@/types';

const LEVELS: { value: string; label: string }[] = [
    { value: 'tk', label: 'TK' },
    { value: 'sd', label: 'SD' },
    { value: 'smp', label: 'SMP' },
    { value: 'sma', label: 'SMA' },
    { value: 'smk', label: 'SMK' },
    { value: 'university', label: 'Perguruan Tinggi' },
];

const levelLabel = (v: string | null) => LEVELS.find((l) => l.value === v)?.label ?? '-';

type SchoolForm = {
    name: string;
    npsn: string;
    level: string;
    email: string;
    phone: string;
    address: string;
    email_domain: string;
};

const emptyForm: SchoolForm = { name: '', npsn: '', level: '', email: '', phone: '', address: '', email_domain: '' };

/**
 * Field identitas sekolah, dipakai bersama oleh form edit & dialog tambah.
 *
 * `showEmailDomain` hanya dinyalakan pada form edit: domain email otomatis
 * disimpan di tabel `settings` milik tenant yang sudah ada, sedangkan dialog
 * tambah sekolah memakai endpoint lain yang tidak menerimanya.
 */
function SchoolFields({
    values,
    onChange,
    disabled,
    idPrefix,
    showEmailDomain,
}: {
    values: SchoolForm;
    onChange: (patch: Partial<SchoolForm>) => void;
    disabled?: boolean;
    idPrefix: string;
    showEmailDomain?: boolean;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2 md:col-span-2">
                <Label htmlFor={`${idPrefix}-name`}>
                    Nama Sekolah <span className="text-destructive">*</span>
                </Label>
                <Input
                    id={`${idPrefix}-name`}
                    value={values.name}
                    disabled={disabled}
                    onChange={(e) => onChange({ name: e.target.value })}
                    placeholder="Nama sekolah"
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-npsn`}>NPSN</Label>
                <Input
                    id={`${idPrefix}-npsn`}
                    value={values.npsn}
                    disabled={disabled}
                    onChange={(e) => onChange({ npsn: e.target.value })}
                    placeholder="Nomor Pokok Sekolah Nasional"
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-level`}>Jenjang</Label>
                <Select
                    value={values.level}
                    disabled={disabled}
                    onValueChange={(v) => onChange({ level: v })}
                >
                    <SelectTrigger id={`${idPrefix}-level`}>
                        <SelectValue placeholder="Pilih jenjang" />
                    </SelectTrigger>
                    <SelectContent>
                        {LEVELS.map((l) => (
                            <SelectItem key={l.value} value={l.value}>
                                {l.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-email`}>
                    Email <span className="text-destructive">*</span>
                </Label>
                <Input
                    id={`${idPrefix}-email`}
                    type="email"
                    value={values.email}
                    disabled={disabled}
                    onChange={(e) => onChange({ email: e.target.value })}
                    placeholder="email@sekolah.sch.id"
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor={`${idPrefix}-phone`}>Telepon</Label>
                <Input
                    id={`${idPrefix}-phone`}
                    value={values.phone}
                    disabled={disabled}
                    onChange={(e) => onChange({ phone: e.target.value })}
                    placeholder="0812xxxxxxxx"
                />
            </div>
            <div className="space-y-2 md:col-span-2">
                <Label htmlFor={`${idPrefix}-address`}>Alamat</Label>
                <Textarea
                    id={`${idPrefix}-address`}
                    value={values.address}
                    disabled={disabled}
                    onChange={(e) => onChange({ address: e.target.value })}
                    placeholder="Alamat lengkap sekolah"
                    rows={3}
                />
            </div>
            {showEmailDomain && (
                <div className="space-y-2 md:col-span-2">
                    <Label htmlFor={`${idPrefix}-email-domain`}>Domain Email Otomatis</Label>
                    <Input
                        id={`${idPrefix}-email-domain`}
                        value={values.email_domain}
                        disabled={disabled}
                        onChange={(e) => onChange({ email_domain: e.target.value })}
                        placeholder="almuawanah.sch.id"
                    />
                    <p className="text-xs text-muted-foreground">
                        Dipakai membuat email login siswa &amp; guru saat kolom emailnya dikosongkan —
                        misalnya <code>ahmad.2024001@almuawanah.sch.id</code> dari nama depan + NIS.
                        Alamat ini hanya identitas untuk masuk aplikasi, tidak dikirimi surat; surat
                        (OTP, notifikasi) dikirim ke <strong>Email Kontak</strong> masing-masing akun.
                        Isi tanpa tanda <code>@</code>.
                    </p>
                </div>
            )}
        </div>
    );
}

/** Kotak preview + tombol unggah/hapus logo. */
function LogoPicker({
    previewUrl,
    fallback,
    onPick,
    onRemove,
    disabled,
    hint = 'JPG, PNG, WEBP, atau SVG. Maksimal 2 MB.',
}: {
    previewUrl: string | null;
    fallback: string;
    onPick: (file: File) => void;
    onRemove?: () => void;
    disabled?: boolean;
    hint?: string;
}) {
    const ref = useRef<HTMLInputElement>(null);
    return (
        <div className="flex items-center gap-4">
            <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl border bg-muted">
                {previewUrl ? (
                    <img src={previewUrl} alt="Logo sekolah" className="h-full w-full object-cover" />
                ) : (
                    <span className="text-2xl font-semibold text-muted-foreground">{fallback}</span>
                )}
            </div>
            {!disabled && (
                <div className="space-y-2">
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" size="sm" onClick={() => ref.current?.click()}>
                            <Upload className="mr-2 h-4 w-4" />
                            Unggah Logo
                        </Button>
                        {previewUrl && onRemove && (
                            <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                Hapus
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">{hint}</p>
                    <input
                        ref={ref}
                        type="file"
                        accept="image/png,image/jpeg,image/webp,image/svg+xml"
                        className="hidden"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) onPick(file);
                        }}
                    />
                </div>
            )}
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    if (status === 'active') {
        return (
            <Badge className="border-transparent bg-green-500/15 text-green-600 hover:bg-green-500/15 dark:text-green-400">
                Aktif
            </Badge>
        );
    }
    if (status === 'inactive') {
        return <Badge variant="secondary">Nonaktif</Badge>;
    }
    return <Badge variant="outline">{status}</Badge>;
}

/**
 * Halaman "Umum" (Pengaturan → Umum). Identitas sekolah aktif (bisa
 * expand/collapse), pintasan pengaturan, plus manajemen sekolah untuk super
 * admin (tambah, aktif/nonaktif, hapus).
 */
export default function GeneralSettings() {
    const { tenant, app, auth } = usePage<PageProps>().props;

    const isSuperAdmin = auth.user?.user_type === 'super_admin';
    const canEdit = isSuperAdmin || (auth.user?.permissions?.includes('settings.school') ?? false);
    // Tenant aktif: user biasa dari shared prop; super admin dari pilihan switcher
    // (localStorage 'active_tenant_id' — sama dengan X-Tenant-ID yang dipakai backend).
    const activeTenantId =
        tenant?.id ??
        (typeof window !== 'undefined' ? window.localStorage.getItem('active_tenant_id') : null);

    // --- Edit sekolah aktif ---
    const [form, setForm] = useState<SchoolForm>(emptyForm);
    const [logoUrl, setLogoUrl] = useState<string | null>(null);
    const [logoFile, setLogoFile] = useState<File | null>(null);
    const [removeLogo, setRemoveLogo] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [identityOpen, setIdentityOpen] = useState(true);

    // --- Tambah sekolah baru (super admin) ---
    const [createOpen, setCreateOpen] = useState(false);
    const [createForm, setCreateForm] = useState<SchoolForm>(emptyForm);
    const [createLogoFile, setCreateLogoFile] = useState<File | null>(null);
    const [createLogoUrl, setCreateLogoUrl] = useState<string | null>(null);
    const [creating, setCreating] = useState(false);

    // --- Kelola sekolah (super admin) ---
    const [schools, setSchools] = useState<SchoolListItem[] | null>(null);
    const [actioningId, setActioningId] = useState<string | null>(null);
    const [deleteTarget, setDeleteTarget] = useState<SchoolListItem | null>(null);

    // --- Branding halaman login (super admin) ---
    const [loginBrand, setLoginBrand] = useState<LoginBrand | null>(null);
    const [loginBrandLoading, setLoginBrandLoading] = useState(true);
    const [loginBrandSaving, setLoginBrandSaving] = useState(false);

    const applyProfile = (p: SchoolProfile) => {
        setForm({
            name: p.name ?? '',
            npsn: p.npsn ?? '',
            level: p.level ?? '',
            email: p.email ?? '',
            phone: p.phone ?? '',
            address: p.address ?? '',
            email_domain: p.email_domain ?? '',
        });
        setLogoUrl(p.logo_url);
        setLogoFile(null);
        setRemoveLogo(false);
    };

    useEffect(() => {
        let active = true;
        schoolApi
            .get()
            .then((res) => {
                if (active && res.data.data) applyProfile(res.data.data);
            })
            .catch(() => toast.error('Gagal memuat data sekolah'))
            .finally(() => active && setLoading(false));
        return () => {
            active = false;
        };
    }, []);

    const fetchSchools = useCallback(() => {
        schoolApi
            .list()
            .then((res) => setSchools(res.data.data ?? []))
            .catch(() => toast.error('Gagal memuat daftar sekolah'));
    }, []);

    useEffect(() => {
        if (isSuperAdmin) fetchSchools();
    }, [isSuperAdmin, fetchSchools]);

    // Fetch login brand for super admin
    useEffect(() => {
        if (!isSuperAdmin) {
            setLoginBrandLoading(false);
            return;
        }
        schoolApi
            .getLoginBrand()
            .then((res) => setLoginBrand(res.data.data ?? null))
            .catch(() => toast.error('Gagal memuat branding halaman login'))
            .finally(() => setLoginBrandLoading(false));
    }, [isSuperAdmin]);

    const handleLoginBrandChange = async (schoolId: string) => {
        setLoginBrandSaving(true);
        try {
            const res = await schoolApi.setLoginBrand(schoolId === '' ? null : schoolId);
            setLoginBrand(res.data.data ?? null);
            // Update local schools state to reflect the change
            setSchools((prev) =>
                prev?.map((s) => ({
                    ...s,
                    is_login_brand: s.id === schoolId,
                })) ?? null
            );
            toast.success(
                schoolId === ''
                    ? 'Halaman login akan menampilkan bawaan sistem'
                    : 'Branding halaman login diperbarui'
            );
        } catch (err: unknown) {
            toast.error(errorMessage(err, 'Gagal mengubah branding halaman login'));
        } finally {
            setLoginBrandSaving(false);
        }
    };

    const pickLogo = (file: File) => {
        setLogoFile(file);
        setRemoveLogo(false);
        setLogoUrl(URL.createObjectURL(file));
    };

    const clearLogo = () => {
        setLogoFile(null);
        setRemoveLogo(true);
        setLogoUrl(null);
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            const fd = new FormData();
            fd.append('name', form.name);
            fd.append('npsn', form.npsn);
            fd.append('level', form.level);
            fd.append('email', form.email);
            fd.append('phone', form.phone);
            fd.append('address', form.address);
            fd.append('email_domain', form.email_domain);
            if (logoFile) fd.append('logo', logoFile);
            if (removeLogo) fd.append('remove_logo', '1');

            await schoolApi.update(fd);
            toast.success('Profil sekolah berhasil disimpan');
            window.location.reload();
        } catch (err: unknown) {
            toast.error(errorMessage(err, 'Gagal menyimpan profil sekolah'));
            setSaving(false);
        }
    };

    const handleCreate = async () => {
        setCreating(true);
        try {
            const fd = new FormData();
            fd.append('name', createForm.name);
            fd.append('npsn', createForm.npsn);
            fd.append('level', createForm.level);
            fd.append('email', createForm.email);
            fd.append('phone', createForm.phone);
            fd.append('address', createForm.address);
            if (createLogoFile) fd.append('logo', createLogoFile);

            await schoolApi.create(fd);
            toast.success('Sekolah baru berhasil ditambahkan');
            window.location.reload();
        } catch (err: unknown) {
            toast.error(errorMessage(err, 'Gagal menambahkan sekolah'));
            setCreating(false);
        }
    };

    const toggleStatus = async (school: SchoolListItem) => {
        setActioningId(school.id);
        try {
            if (school.status === 'active') {
                await schoolApi.deactivate(school.id);
                toast.success(`${school.name} dinonaktifkan`);
            } else {
                await schoolApi.activate(school.id);
                toast.success(`${school.name} diaktifkan`);
            }
            fetchSchools();
        } catch (err: unknown) {
            toast.error(errorMessage(err, 'Gagal mengubah status sekolah'));
        } finally {
            setActioningId(null);
        }
    };

    const confirmDelete = async () => {
        if (!deleteTarget) return;
        const target = deleteTarget;
        setActioningId(target.id);
        try {
            await schoolApi.remove(target.id);
            toast.success(`${target.name} berhasil dihapus`);
            setDeleteTarget(null);
            // Reload agar pemilih sekolah (shared prop) ikut diperbarui.
            window.location.reload();
        } catch (err: unknown) {
            toast.error(errorMessage(err, 'Gagal menghapus sekolah'));
            setActioningId(null);
        }
    };

    const shortcuts = [
        {
            title: 'Pengaturan Absensi',
            description: 'Jam masuk/pulang, lokasi, dan notifikasi (WA/Telegram/Email)',
            href: '/attendance/settings',
            icon: Clock,
        },
        {
            title: 'Pengaturan Menu',
            description: 'Atur menu yang tampil untuk tiap role',
            href: '/settings/menu',
            icon: ListChecks,
        },
        {
            title: 'Pengguna',
            description: 'Kelola akun pengguna (khusus super admin)',
            href: '/settings/users',
            icon: Users,
        },
    ];

    const initials = (form.name || tenant?.name || app?.name || 'S').charAt(0).toUpperCase();
    const createValid = createForm.name.trim() !== '' && createForm.email.trim() !== '';

    return (
        <MainLayout title="Pengaturan Umum">
            <Head title="Pengaturan Umum" />

            <div className="mx-auto max-w-3xl space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pengaturan Umum</h1>
                        <p className="text-muted-foreground">Identitas sekolah dan pintasan pengaturan</p>
                    </div>
                    {isSuperAdmin && (
                        <Button onClick={() => setCreateOpen(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Sekolah
                        </Button>
                    )}
                </div>

                {/* Identitas Sekolah (collapsible) */}
                <Collapsible open={identityOpen} onOpenChange={setIdentityOpen}>
                    <Card>
                        <CollapsibleTrigger className="w-full text-left">
                            <CardHeader className="flex flex-row items-start justify-between space-y-0">
                                <div className="space-y-1.5">
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Identitas Sekolah
                                    </CardTitle>
                                    <CardDescription>
                                        {canEdit
                                            ? 'Nama & logo ini tampil di navigasi, header, dan cetakan.'
                                            : 'Data sekolah yang sedang aktif (hanya baca).'}
                                    </CardDescription>
                                </div>
                                <ChevronDown
                                    className={`mt-1 h-5 w-5 shrink-0 text-muted-foreground transition-transform ${
                                        identityOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CardHeader>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <CardContent className="space-y-6">
                                {loading ? (
                                    <div className="flex h-40 items-center justify-center">
                                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                                    </div>
                                ) : (
                                    <>
                                        <LogoPicker
                                            previewUrl={logoUrl}
                                            fallback={initials}
                                            onPick={pickLogo}
                                            onRemove={clearLogo}
                                            disabled={!canEdit}
                                        />
                                        <SchoolFields
                                            values={form}
                                            onChange={(patch) => setForm((f) => ({ ...f, ...patch }))}
                                            disabled={!canEdit}
                                            idPrefix="edit"
                                            showEmailDomain
                                        />
                                        {canEdit && (
                                            <div className="flex justify-end">
                                                <Button onClick={handleSave} disabled={saving}>
                                                    {saving ? (
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    ) : (
                                                        <Save className="mr-2 h-4 w-4" />
                                                    )}
                                                    {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
                                                </Button>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </CollapsibleContent>
                    </Card>
                </Collapsible>

                {/* Kelola Sekolah (super admin) */}
                {isSuperAdmin && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Kelola Sekolah</CardTitle>
                            <CardDescription>
                                Aktif/nonaktifkan atau hapus sekolah. Sekolah yang sedang aktif tidak
                                bisa dinonaktifkan/dihapus — pindah dulu lewat pemilih sekolah di header.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {schools === null ? (
                                <div className="flex h-20 items-center justify-center">
                                    <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
                                </div>
                            ) : (
                                schools.map((s) => {
                                    const isActive = s.id === activeTenantId;
                                    const busy = actioningId === s.id;
                                    return (
                                        <div
                                            key={s.id}
                                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                                        >
                                            <div className="flex min-w-0 items-center gap-3">
                                                <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border bg-muted">
                                                    {s.logo_url ? (
                                                        <img src={s.logo_url} alt="" className="h-full w-full object-cover" />
                                                    ) : (
                                                        <span className="text-sm font-semibold text-muted-foreground">
                                                            {s.name.charAt(0).toUpperCase()}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="min-w-0">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate font-medium">{s.name}</span>
                                                        {isActive && (
                                                            <Badge variant="outline" className="shrink-0">
                                                                Sedang aktif
                                                            </Badge>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                        <span>{levelLabel(s.level)}</span>
                                                        <span>·</span>
                                                        <StatusBadge status={s.status} />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={busy || (isActive && s.status === 'active')}
                                                    title={
                                                        isActive && s.status === 'active'
                                                            ? 'Tidak bisa menonaktifkan sekolah yang sedang aktif'
                                                            : undefined
                                                    }
                                                    onClick={() => toggleStatus(s)}
                                                >
                                                    {busy ? (
                                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    ) : s.status === 'active' ? (
                                                        <Ban className="mr-2 h-4 w-4" />
                                                    ) : (
                                                        <Power className="mr-2 h-4 w-4" />
                                                    )}
                                                    {s.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    disabled={busy || isActive}
                                                    title={isActive ? 'Tidak bisa menghapus sekolah yang sedang aktif' : undefined}
                                                    onClick={() => setDeleteTarget(s)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Branding Halaman Login (super admin) */}
                {isSuperAdmin && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Branding Halaman Login</CardTitle>
                            <CardDescription>
                                Pilih sekolah yang logo dan namanya ditampilkan di halaman login (/login).
                                Ini tidak mengubah status aktif sekolah — sekolah lain tetap bisa diakses.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {loginBrandLoading ? (
                                <div className="flex h-12 items-center justify-center">
                                    <Loader2 className="h-5 w-5 animate-spin text-muted-foreground" />
                                </div>
                            ) : (
                                <div className="flex flex-wrap items-center gap-4">
                                    <div className="min-w-[200px] flex-1">
                                        <Select
                                            value={loginBrand?.id ?? ''}
                                            onValueChange={handleLoginBrandChange}
                                            disabled={loginBrandSaving || !schools}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih sekolah...">
                                                    {loginBrand ? (
                                                        <span className="flex items-center gap-2">
                                                            {loginBrand.logo_url && (
                                                                <img
                                                                    src={loginBrand.logo_url}
                                                                    alt=""
                                                                    className="h-5 w-5 rounded object-cover"
                                                                />
                                                            )}
                                                            {loginBrand.name}
                                                        </span>
                                                    ) : (
                                                        'Gunakan bawaan sistem'
                                                    )}
                                                </SelectValue>
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">
                                                    <span className="text-muted-foreground">Gunakan bawaan sistem</span>
                                                </SelectItem>
                                                {schools?.map((s) => (
                                                    <SelectItem key={s.id} value={s.id}>
                                                        <span className="flex items-center gap-2">
                                                            {s.logo_url && (
                                                                <img
                                                                    src={s.logo_url}
                                                                    alt=""
                                                                    className="h-5 w-5 rounded object-cover"
                                                                />
                                                            )}
                                                            {s.name}
                                                        </span>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {loginBrandSaving && (
                                        <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Pintasan Pengaturan (selalu tampil) */}
                <div className="space-y-2">
                    <h2 className="text-sm font-medium text-muted-foreground">Pintasan Pengaturan</h2>
                    <div className="grid gap-3 md:grid-cols-3">
                        {shortcuts.map((s) => (
                            <Link key={s.href} href={s.href}>
                                <Card className="h-full transition-colors hover:border-primary">
                                    <CardHeader>
                                        <s.icon className="h-6 w-6 text-muted-foreground" />
                                        <CardTitle className="text-base">{s.title}</CardTitle>
                                        <CardDescription>{s.description}</CardDescription>
                                    </CardHeader>
                                </Card>
                            </Link>
                        ))}
                    </div>
                </div>
            </div>

            {/* Dialog Tambah Sekolah */}
            <Dialog open={createOpen} onOpenChange={(o) => !creating && setCreateOpen(o)}>
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tambah Sekolah Baru</DialogTitle>
                        <DialogDescription>
                            Buat data sekolah (tenant) baru. Setelah dibuat, Anda bisa berpindah ke
                            sekolah ini lewat pemilih sekolah di header.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-2">
                        <LogoPicker
                            previewUrl={createLogoUrl}
                            fallback={(createForm.name || 'S').charAt(0).toUpperCase()}
                            onPick={(file) => {
                                setCreateLogoFile(file);
                                setCreateLogoUrl(URL.createObjectURL(file));
                            }}
                            onRemove={() => {
                                setCreateLogoFile(null);
                                setCreateLogoUrl(null);
                            }}
                        />
                        <SchoolFields
                            values={createForm}
                            onChange={(patch) => setCreateForm((f) => ({ ...f, ...patch }))}
                            idPrefix="create"
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCreateOpen(false)} disabled={creating}>
                            Batal
                        </Button>
                        <Button onClick={handleCreate} disabled={creating || !createValid}>
                            {creating ? (
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Plus className="mr-2 h-4 w-4" />
                            )}
                            {creating ? 'Menyimpan...' : 'Simpan Sekolah'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Hapus */}
            <AlertDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus sekolah ini?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Sekolah <strong>{deleteTarget?.name}</strong> akan dihapus (soft delete) dan
                            hilang dari daftar. Datanya tetap tersimpan dan bisa dipulihkan oleh
                            administrator sistem bila diperlukan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={actioningId !== null}>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                confirmDelete();
                            }}
                            disabled={actioningId !== null}
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                        >
                            {actioningId !== null ? 'Menghapus...' : 'Ya, Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}

function errorMessage(err: unknown, fallback: string): string {
    return (
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ?? fallback
    );
}
