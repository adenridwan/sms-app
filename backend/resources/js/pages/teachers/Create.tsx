import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { DatePicker } from '@/components/ui/date-picker';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from 'sonner';
import { ArrowLeft, Info, KeyRound, Loader2, Save, User as UserIcon, Briefcase, ImagePlus } from 'lucide-react';
import { teachersApi, type CreateTeacherResponse } from '@/services/api';
import type { TeacherFormData } from '@/types';

const emptyForm: TeacherFormData = {
    first_name: '',
    last_name: '',
    email: '',
    contact_email: '',
    phone: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    religion: '',
    address: '',
    id_number: '',
    nip: '',
    nuptk: '',
    join_date: new Date().toISOString().slice(0, 10),
    employment_status: 'permanent',
    status: 'active',
    certification_status: 'not_certified',
    certification_number: '',
    education_level: '',
    education_major: '',
    university: '',
    teaching_experience_years: '0',
};

const employmentLabels: Record<string, string> = {
    permanent: 'Tetap',
    contract: 'Kontrak',
    honorary: 'Honorer',
    part_time: 'Paruh Waktu',
};

const statusLabels: Record<string, string> = {
    active: 'Aktif',
    inactive: 'Tidak Aktif',
    on_leave: 'Cuti',
    retired: 'Pensiun',
    terminated: 'Berhenti',
};

const certificationLabels: Record<string, string> = {
    certified: 'Sudah Sertifikasi',
    not_certified: 'Belum Sertifikasi',
    in_progress: 'Proses Sertifikasi',
};

/**
 * Jenjang pendidikan. `teachers.education_level` adalah kolom string biasa
 * (tanpa enum di DB), jadi jenjang di luar daftar ini — mis. PESANTREN —
 * disimpan apa adanya sebagai keterangan pada kolom yang sama. Tidak ada
 * kode khusus yang perlu ditampilkan ke pengguna.
 */
const educationLevels = ['d3', 'd4', 's1', 's2', 's3'];

const EDUCATION_OTHER = 'other';

/**
 * Isian wajib menurut StoreTeacherRequest. Dicek di sisi klien lebih dulu
 * supaya pengguna langsung dapat notifikasi yang seragam dengan halaman
 * lain (toast + ringkasan di atas form), bukan hanya border merah setelah
 * pulang-pergi ke server.
 */
const requiredFields: Array<{ field: keyof TeacherFormData; label: string; tab: string }> = [
    { field: 'first_name', label: 'Nama Depan', tab: 'akun' },
    // `email` tidak lagi wajib: dikosongkan berarti dibuatkan otomatis dari
    // username + domain sekolah (docs/EMAIL-OTOMATIS-AKUN.md).
    { field: 'gender', label: 'Jenis Kelamin', tab: 'akun' },
    { field: 'birth_date', label: 'Tanggal Lahir', tab: 'akun' },
];

function getErrors(error: unknown): { fieldErrors: Record<string, string>; message: string | null } {
    if (axios.isAxiosError(error) && error.response) {
        const { message, errors } = error.response.data ?? {};
        if (errors) {
            const fieldErrors: Record<string, string> = {};
            Object.entries(errors as Record<string, string[]>).forEach(([field, messages]) => {
                fieldErrors[field] = messages[0];
            });
            return { fieldErrors, message: null };
        }
        return { fieldErrors: {}, message: message ?? 'Gagal menyimpan data guru' };
    }
    return { fieldErrors: {}, message: 'Tidak dapat terhubung ke server' };
}

export default function CreateTeacher() {
    const [data, setData] = useState<TeacherFormData>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState('akun');

    // Kredensial ditampilkan sekali setelah berhasil dibuat (tidak disimpan ulang)
    const [credentials, setCredentials] = useState<CreateTeacherResponse | null>(null);

    // Jenjang "Lainnya" hanya status tampilan; nilainya sendiri (mis.
    // "PESANTREN") tetap disimpan di data.education_level.
    const [educationOther, setEducationOther] = useState(false);

    const update = <K extends keyof TeacherFormData>(field: K, value: TeacherFormData[K]) => {
        setData((d) => ({ ...d, [field]: value }));
    };

    const handleEducationLevelChange = (value: string) => {
        if (value === EDUCATION_OTHER) {
            setEducationOther(true);
            update('education_level', '');

            return;
        }

        setEducationOther(false);
        update('education_level', value);
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setFormError(null);

        const missing = requiredFields.filter(({ field }) => !String(data[field] ?? '').trim());
        if (missing.length > 0) {
            setErrors(
                Object.fromEntries(missing.map(({ field, label }) => [field, `${label} wajib diisi.`]))
            );
            setFormError('Lengkapi isian wajib yang ditandai merah.');
            setTab(missing[0].tab);
            toast.error(`Isian wajib belum lengkap: ${missing.map((m) => m.label).join(', ')}`);
            return;
        }

        setProcessing(true);

        try {
            const response = await teachersApi.create(data);
            setCredentials(response.data.data);
            toast.success('Guru berhasil ditambahkan');
        } catch (error) {
            const { fieldErrors, message } = getErrors(error);
            setErrors(fieldErrors);

            if (message) {
                setFormError(message);
                toast.error(message);
            } else {
                setFormError('Periksa kembali isian yang ditandai merah.');
                toast.error('Data guru gagal disimpan, periksa isian yang ditandai merah.');
            }

            // Pindah ke tab yang mengandung field bermasalah agar terlihat
            if (
                ['first_name', 'last_name', 'email', 'phone', 'gender', 'birth_date', 'birth_place', 'religion', 'address', 'id_number'].some(
                    (f) => fieldErrors[f]
                )
            ) {
                setTab('akun');
            } else if (Object.keys(fieldErrors).length > 0) {
                setTab('kepegawaian');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title="Tambah Guru" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/teachers">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tambah Guru Baru</h1>
                        <p className="text-muted-foreground">
                            Akun login dibuat otomatis dari data di bawah
                        </p>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Guru</CardTitle>
                            <CardDescription>
                                Penempatan kelas dan mata pelajaran diatur belakangan dari menu
                                Kelas &amp; Mata Pelajaran, bukan di sini.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {formError && (
                                <Alert variant="destructive" className="mb-4">
                                    <AlertDescription>{formError}</AlertDescription>
                                </Alert>
                            )}

                            <Tabs value={tab} onValueChange={setTab}>
                                <TabsList className="mb-4">
                                    <TabsTrigger value="akun">
                                        <UserIcon className="mr-2 h-4 w-4" />
                                        Akun &amp; Pribadi
                                    </TabsTrigger>
                                    <TabsTrigger value="kepegawaian">
                                        <Briefcase className="mr-2 h-4 w-4" />
                                        Kepegawaian
                                    </TabsTrigger>
                                </TabsList>

                                {/* Tab 1: Akun & Pribadi */}
                                <TabsContent value="akun" className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="first_name">Nama Depan *</Label>
                                            <Input
                                                id="first_name"
                                                value={data.first_name}
                                                onChange={(e) => update('first_name', e.target.value)}
                                                className={errors.first_name ? 'border-destructive' : ''}
                                            />
                                            {errors.first_name && (
                                                <p className="text-sm text-destructive">{errors.first_name}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="last_name">Nama Belakang</Label>
                                            <Input
                                                id="last_name"
                                                value={data.last_name}
                                                onChange={(e) => update('last_name', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="email">Email Login</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                placeholder="Kosongkan untuk dibuatkan otomatis"
                                                value={data.email}
                                                onChange={(e) => update('email', e.target.value)}
                                                className={errors.email ? 'border-destructive' : ''}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Diisi = dipakai untuk login sekaligus tujuan notifikasi.
                                                Dikosongkan = dibuat otomatis dari nama, dan notifikasi
                                                dikirim ke Email Kontak.
                                            </p>
                                            {errors.email && (
                                                <p className="text-sm text-destructive">{errors.email}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="contact_email">Email Kontak</Label>
                                            <Input
                                                id="contact_email"
                                                type="email"
                                                placeholder="Alamat asli untuk notifikasi"
                                                value={data.contact_email}
                                                onChange={(e) => update('contact_email', e.target.value)}
                                                className={errors.contact_email ? 'border-destructive' : ''}
                                            />
                                            {errors.contact_email && (
                                                <p className="text-sm text-destructive">{errors.contact_email}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="phone">No. HP</Label>
                                            <Input
                                                id="phone"
                                                placeholder="08xxxxxxxxxx"
                                                value={data.phone}
                                                onChange={(e) => update('phone', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="gender">Jenis Kelamin *</Label>
                                            <Select value={data.gender} onValueChange={(v) => update('gender', v)}>
                                                <SelectTrigger
                                                    id="gender"
                                                    className={errors.gender ? 'border-destructive' : ''}
                                                >
                                                    <SelectValue placeholder="Pilih jenis kelamin" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="male">Laki-laki</SelectItem>
                                                    <SelectItem value="female">Perempuan</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {errors.gender && (
                                                <p className="text-sm text-destructive">{errors.gender}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="id_number">NIK</Label>
                                            <Input
                                                id="id_number"
                                                maxLength={16}
                                                value={data.id_number}
                                                onChange={(e) => update('id_number', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="birth_place">Tempat Lahir</Label>
                                            <Input
                                                id="birth_place"
                                                value={data.birth_place}
                                                onChange={(e) => update('birth_place', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="birth_date">Tanggal Lahir *</Label>
                                            <DatePicker
                                                id="birth_date"
                                                value={data.birth_date}
                                                onChange={(value) => update('birth_date', value)}
                                                placeholder="Pilih tanggal lahir"
                                                invalid={!!errors.birth_date}
                                                toYear={new Date().getFullYear()}
                                            />
                                            {errors.birth_date ? (
                                                <p className="text-sm text-destructive">{errors.birth_date}</p>
                                            ) : (
                                                <p className="text-xs text-muted-foreground">
                                                    Dipakai sebagai password awal (format ddmmyyyy)
                                                </p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="religion">Agama</Label>
                                        <Select value={data.religion} onValueChange={(v) => update('religion', v)}>
                                            <SelectTrigger id="religion">
                                                <SelectValue placeholder="Pilih agama" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="islam">Islam</SelectItem>
                                                <SelectItem value="kristen">Kristen</SelectItem>
                                                <SelectItem value="katolik">Katolik</SelectItem>
                                                <SelectItem value="hindu">Hindu</SelectItem>
                                                <SelectItem value="buddha">Buddha</SelectItem>
                                                <SelectItem value="konghucu">Konghucu</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="address">Alamat</Label>
                                        <Textarea
                                            id="address"
                                            rows={3}
                                            value={data.address}
                                            onChange={(e) => update('address', e.target.value)}
                                        />
                                    </div>
                                </TabsContent>

                                {/* Tab 2: Kepegawaian */}
                                <TabsContent value="kepegawaian" className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="nip">NIP</Label>
                                            <Input
                                                id="nip"
                                                value={data.nip}
                                                onChange={(e) => update('nip', e.target.value)}
                                                className={errors.nip ? 'border-destructive' : ''}
                                            />
                                            {errors.nip && <p className="text-sm text-destructive">{errors.nip}</p>}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="nuptk">NUPTK</Label>
                                            <Input
                                                id="nuptk"
                                                value={data.nuptk}
                                                onChange={(e) => update('nuptk', e.target.value)}
                                                className={errors.nuptk ? 'border-destructive' : ''}
                                            />
                                            {errors.nuptk && (
                                                <p className="text-sm text-destructive">{errors.nuptk}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="space-y-2">
                                            <Label htmlFor="join_date">Tanggal Masuk</Label>
                                            <DatePicker
                                                id="join_date"
                                                value={data.join_date}
                                                onChange={(value) => update('join_date', value)}
                                                placeholder="Pilih tanggal masuk"
                                                fromYear={1970}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Status Kepegawaian</Label>
                                            <Select
                                                value={data.employment_status}
                                                onValueChange={(v) => update('employment_status', v)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(employmentLabels).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Status Guru</Label>
                                            <Select value={data.status} onValueChange={(v) => update('status', v)}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(statusLabels).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Status Sertifikasi</Label>
                                            <Select
                                                value={data.certification_status}
                                                onValueChange={(v) => update('certification_status', v)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(certificationLabels).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="certification_number">No. Sertifikat</Label>
                                            <Input
                                                id="certification_number"
                                                value={data.certification_number}
                                                onChange={(e) => update('certification_number', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="space-y-2">
                                            <Label>Pendidikan</Label>
                                            <Select
                                                value={
                                                    educationOther
                                                        ? EDUCATION_OTHER
                                                        : data.education_level.toLowerCase()
                                                }
                                                onValueChange={handleEducationLevelChange}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Jenjang" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {educationLevels.map((level) => (
                                                        <SelectItem key={level} value={level}>
                                                            {level.toUpperCase()}
                                                        </SelectItem>
                                                    ))}
                                                    <SelectItem value={EDUCATION_OTHER}>Lainnya</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {educationOther && (
                                            <div className="space-y-2">
                                                <Label htmlFor="education_level_note">
                                                    Keterangan Pendidikan
                                                </Label>
                                                <Input
                                                    id="education_level_note"
                                                    placeholder="Contoh: PESANTREN"
                                                    value={data.education_level}
                                                    onChange={(e) =>
                                                        update('education_level', e.target.value)
                                                    }
                                                    className={
                                                        errors.education_level ? 'border-destructive' : ''
                                                    }
                                                />
                                                {errors.education_level && (
                                                    <p className="text-sm text-destructive">
                                                        {errors.education_level}
                                                    </p>
                                                )}
                                            </div>
                                        )}
                                        <div className="space-y-2">
                                            <Label htmlFor="education_major">Jurusan</Label>
                                            <Input
                                                id="education_major"
                                                value={data.education_major}
                                                onChange={(e) => update('education_major', e.target.value)}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="university">Universitas</Label>
                                            <Input
                                                id="university"
                                                value={data.university}
                                                onChange={(e) => update('university', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2 sm:w-1/3">
                                        <Label htmlFor="teaching_experience_years">
                                            Pengalaman Mengajar (tahun)
                                        </Label>
                                        <Input
                                            id="teaching_experience_years"
                                            type="number"
                                            min={0}
                                            max={60}
                                            value={data.teaching_experience_years}
                                            onChange={(e) => update('teaching_experience_years', e.target.value)}
                                        />
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/teachers">Batal</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <Save className="mr-2 h-4 w-4" />
                            Simpan
                        </Button>
                    </div>
                </form>
            </div>

            {/* Kredensial ditampilkan sekali setelah berhasil dibuat */}
            <AlertDialog open={!!credentials} onOpenChange={() => {}}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle className="flex items-center gap-2">
                            <KeyRound className="h-5 w-5 text-primary" />
                            Guru Berhasil Ditambahkan
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-3 pt-2 text-left">
                                <p>Sampaikan kredensial berikut ke guru yang bersangkutan:</p>
                                <div className="space-y-2 rounded-md border bg-muted/50 p-3 font-mono text-sm">
                                    <div>
                                        Username: <span className="font-semibold">{credentials?.initial_username}</span>
                                    </div>
                                    <div>
                                        Password: <span className="font-semibold">{credentials?.initial_password}</span>
                                    </div>
                                </div>
                                <Alert>
                                    <Info className="h-4 w-4" />
                                    <AlertTitle>Wajib diganti saat login pertama</AlertTitle>
                                    <AlertDescription>
                                        Password ini hanya ditampilkan sekali dan tidak dapat dilihat
                                        kembali. Guru akan diminta mengganti password sendiri saat
                                        pertama kali login.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => router.visit('/teachers')}>
                            Nanti Saja
                        </AlertDialogCancel>
                        <AlertDialogAction
                            // ?tab=dokumen: halaman Edit membuka tab Foto & Dokumen
                            // langsung, bukan tab Akun & Pribadi
                            onClick={() => router.visit(`/teachers/${credentials?.id}/edit?tab=dokumen`)}
                        >
                            <ImagePlus className="mr-2 h-4 w-4" />
                            Unggah Foto &amp; Dokumen
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
