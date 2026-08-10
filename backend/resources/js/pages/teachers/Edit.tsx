import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import axios from 'axios';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { toast } from 'sonner';
import {
    ArrowLeft,
    Loader2,
    Save,
    User as UserIcon,
    Briefcase,
    ShieldAlert,
    Camera,
    Trash2,
    Upload,
    FileText,
    Image as ImageIcon,
    Download,
    FolderOpen,
    ScanLine,
    Sparkles,
} from 'lucide-react';
import { teachersApi } from '@/services/api';
import type { Teacher, TeacherFormData, TeacherDocumentCollection, TeacherDocuments } from '@/types';

const documentCollections: Array<{
    key: TeacherDocumentCollection;
    label: string;
    singleFile: boolean;
}> = [
    { key: 'ijazah', label: 'Ijazah', singleFile: false },
    { key: 'sertifikat_pendidik', label: 'Sertifikat Pendidik', singleFile: false },
    { key: 'surat_penugasan', label: 'Surat Penugasan', singleFile: false },
    { key: 'ktp', label: 'KTP', singleFile: true },
    { key: 'npwp', label: 'NPWP', singleFile: true },
    { key: 'lainnya', label: 'Dokumen Lainnya', singleFile: false },
];

const emptyDocuments: TeacherDocuments = {
    ijazah: [],
    ktp: [],
    npwp: [],
    sertifikat_pendidik: [],
    surat_penugasan: [],
    lainnya: [],
};

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

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
 * disimpan apa adanya sebagai keterangan pada kolom yang sama. Perbandingan
 * huruf kecil sekaligus menampung data lama yang tersimpan "S1" (kapital).
 */
const educationLevels = ['d3', 'd4', 's1', 's2', 's3'];

const EDUCATION_OTHER = 'other';

/** Nilai tersimpan yang tidak cocok dengan daftar di atas = jenjang "Lainnya". */
function isCustomEducation(value: string): boolean {
    const normalized = value.trim().toLowerCase();

    return normalized !== '' && !educationLevels.includes(normalized);
}

function DocumentCollectionCard({
    label,
    singleFile,
    files,
    uploading,
    onUpload,
    onDelete,
}: {
    label: string;
    singleFile: boolean;
    files: TeacherDocuments[TeacherDocumentCollection];
    uploading: boolean;
    onUpload: (file: File) => void;
    onDelete: (mediaId: number) => void;
}) {
    const inputRef = useRef<HTMLInputElement>(null);
    const hasFile = files.length > 0;
    const showUploader = !singleFile || !hasFile;

    return (
        <div className="rounded-md border p-3">
            <div className="mb-2 flex items-center justify-between">
                <span className="text-sm font-medium">{label}</span>
                {!singleFile && files.length > 0 && (
                    <Badge variant="secondary">{files.length} file</Badge>
                )}
            </div>

            {hasFile && (
                <div className="mb-2 space-y-1.5">
                    {files.map((file) => (
                        <div
                            key={file.id}
                            className="flex items-center justify-between gap-2 rounded-md bg-muted/50 px-2 py-1.5 text-sm"
                        >
                            <div className="flex min-w-0 items-center gap-2">
                                {file.mime_type === 'application/pdf' ? (
                                    <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                ) : (
                                    <ImageIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                )}
                                <a
                                    href={file.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="truncate hover:underline"
                                    title={file.name}
                                >
                                    {file.name}
                                </a>
                                <span className="shrink-0 text-xs text-muted-foreground">
                                    {formatFileSize(file.size)}
                                </span>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <Button variant="ghost" size="icon" className="h-7 w-7" asChild>
                                    <a href={file.url} target="_blank" rel="noopener noreferrer">
                                        <Download className="h-3.5 w-3.5" />
                                    </a>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7 text-muted-foreground hover:text-red-600"
                                    onClick={() => onDelete(file.id)}
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showUploader && (
                <>
                    <input
                        ref={inputRef}
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        className="hidden"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) onUpload(file);
                            e.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={uploading}
                        onClick={() => inputRef.current?.click()}
                    >
                        {uploading ? (
                            <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                        ) : (
                            <Upload className="mr-2 h-3.5 w-3.5" />
                        )}
                        {singleFile ? 'Unggah File' : 'Tambah File'}
                    </Button>
                </>
            )}
        </div>
    );
}

function getSingleErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError(error) && error.response) {
        const { message, errors } = error.response.data ?? {};
        if (errors) {
            const first = Object.values(errors as Record<string, string[]>)[0];
            if (first?.[0]) return first[0];
        }
        return message ?? fallback;
    }
    return fallback;
}

/**
 * Isian yang tidak boleh dikosongkan saat mengubah data guru. Dicek di sisi
 * klien lebih dulu supaya notifikasinya seragam dengan halaman lain (toast +
 * ringkasan di atas form), bukan hanya border merah.
 */
const requiredFields: Array<{ field: keyof TeacherFormData; label: string; tab: string }> = [
    { field: 'first_name', label: 'Nama Depan', tab: 'akun' },
    // `email` tidak lagi wajib — lihat docs/EMAIL-OTOMATIS-AKUN.md.
    { field: 'gender', label: 'Jenis Kelamin', tab: 'akun' },
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

const TAB_VALUES = ['akun', 'kepegawaian', 'dokumen'];

/**
 * Tab awal dibaca dari query `?tab=` supaya tautan dari luar bisa membuka
 * tab tertentu — dipakai tombol "Unggah Foto & Dokumen" pada popup
 * kredensial setelah guru baru dibuat (halaman Tambah Guru).
 */
function initialTab(): string {
    if (typeof window === 'undefined') return 'akun';

    const requested = new URLSearchParams(window.location.search).get('tab');

    return requested && TAB_VALUES.includes(requested) ? requested : 'akun';
}

function toFormData(teacher: Teacher): TeacherFormData {
    return {
        first_name: teacher.first_name ?? '',
        last_name: teacher.last_name ?? '',
        email: teacher.email ?? '',
        contact_email: teacher.contact_email ?? '',
        phone: teacher.phone ?? '',
        gender: teacher.gender ?? '',
        birth_place: teacher.birth_place ?? '',
        birth_date: teacher.birth_date ?? '',
        religion: teacher.religion ?? '',
        address: teacher.address ?? '',
        id_number: teacher.id_number ?? '',
        nip: teacher.nip ?? '',
        nuptk: teacher.nuptk ?? '',
        join_date: teacher.join_date ?? '',
        employment_status: teacher.employment_status ?? 'permanent',
        status: teacher.status ?? 'active',
        certification_status: teacher.certification_status ?? 'not_certified',
        certification_number: teacher.certification_number ?? '',
        education_level: teacher.education_level ?? '',
        education_major: teacher.education_major ?? '',
        university: teacher.university ?? '',
        teaching_experience_years: String(teacher.teaching_experience_years ?? 0),
    };
}

interface EditTeacherProps {
    teacher: Teacher;
}

export default function EditTeacher({ teacher }: EditTeacherProps) {
    const [data, setData] = useState<TeacherFormData>(() => toFormData(teacher));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState(initialTab);

    // Tab 3: foto & dokumen — diunggah langsung saat dipilih, terpisah dari
    // form utama (bukan bagian payload PUT tab 1/2)
    const [avatarUrl, setAvatarUrl] = useState(teacher.avatar_url ?? null);
    const [photoUploading, setPhotoUploading] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);
    const [documents, setDocuments] = useState<TeacherDocuments>(teacher.documents ?? emptyDocuments);
    const [uploadingCollection, setUploadingCollection] = useState<TeacherDocumentCollection | null>(null);

    // Jenjang "Lainnya" hanya status tampilan; nilainya sendiri (mis.
    // "PESANTREN") tetap disimpan di data.education_level.
    const [educationOther, setEducationOther] = useState(() =>
        isCustomEducation(teacher.education_level ?? '')
    );

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

    const handlePhotoSelect = async (file: File) => {
        setPhotoUploading(true);
        try {
            const response = await teachersApi.uploadPhoto(teacher.id, file);
            setAvatarUrl(response.data.data.avatar_url);
            toast.success('Foto berhasil diperbarui');
        } catch (error) {
            toast.error(getSingleErrorMessage(error, 'Gagal mengunggah foto'));
        } finally {
            setPhotoUploading(false);
        }
    };

    const handlePhotoDelete = async () => {
        setPhotoUploading(true);
        try {
            await teachersApi.deletePhoto(teacher.id);
            setAvatarUrl(null);
            toast.success('Foto berhasil dihapus');
        } catch {
            toast.error('Gagal menghapus foto');
        } finally {
            setPhotoUploading(false);
        }
    };

    // Kode kartu RFID — endpoint terpisah (RfidController, Fase 2)
    const [rfidCode, setRfidCode] = useState(teacher.rfid_code ?? '');
    const [rfidSaving, setRfidSaving] = useState(false);
    const [rfidError, setRfidError] = useState<string | null>(null);
    // Mode baca kartu: reader RFID USB umumnya berperilaku seperti keyboard —
    // mengetikkan UID kartu lalu menekan Enter. Jadi cukup fokuskan kursor ke
    // input, lalu simpan begitu Enter diterima.
    const [readingCard, setReadingCard] = useState(false);
    const [rfidGenerating, setRfidGenerating] = useState(false);
    const [confirmRegenerate, setConfirmRegenerate] = useState(false);
    const rfidInputRef = useRef<HTMLInputElement>(null);

    const handleSaveRfid = async (codeOverride?: string) => {
        const code = (codeOverride ?? rfidCode).trim();

        setRfidSaving(true);
        setRfidError(null);
        try {
            const response = await teachersApi.updateRfid(teacher.id, code || null);
            setRfidCode(response.data.data?.rfid_code ?? '');
            toast.success('Kode RFID berhasil disimpan');
        } catch (error) {
            setRfidError(getSingleErrorMessage(error, 'Gagal menyimpan kode RFID'));
        } finally {
            setRfidSaving(false);
        }
    };

    const startReadingCard = () => {
        setRfidError(null);
        setRfidCode('');
        setReadingCard(true);
        // Fokus setelah render supaya input sudah dalam keadaan kosong
        window.setTimeout(() => rfidInputRef.current?.focus(), 0);
    };

    const handleRfidKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key !== 'Enter') return;

        // Reader mengirim Enter sebagai penutup; jangan sampai form utama
        // ikut ter-submit.
        event.preventDefault();
        setReadingCard(false);
        // Ambil dari elemen, bukan state: karakter terakhir dari reader bisa
        // saja belum sempat ter-render saat Enter tiba.
        handleSaveRfid(event.currentTarget.value);
    };

    const handleGenerateRfid = async () => {
        setConfirmRegenerate(false);
        setRfidGenerating(true);
        setRfidError(null);
        setReadingCard(false);
        try {
            const response = await teachersApi.generateRfid(teacher.id);
            setRfidCode(response.data.data.rfid_code);
            toast.success('Kode RFID baru dibuat dan disimpan');
        } catch (error) {
            setRfidError(getSingleErrorMessage(error, 'Gagal membuat kode RFID'));
        } finally {
            setRfidGenerating(false);
        }
    };

    const handleDocumentUpload = async (collection: TeacherDocumentCollection, file: File) => {
        setUploadingCollection(collection);
        try {
            const response = await teachersApi.uploadDocument(teacher.id, collection, file);
            setDocuments(response.data.data);
            toast.success('Dokumen berhasil diunggah');
        } catch (error) {
            toast.error(getSingleErrorMessage(error, 'Gagal mengunggah dokumen'));
        } finally {
            setUploadingCollection(null);
        }
    };

    const handleDocumentDelete = async (mediaId: number) => {
        try {
            const response = await teachersApi.deleteDocument(teacher.id, mediaId);
            setDocuments(response.data.data);
            toast.success('Dokumen berhasil dihapus');
        } catch {
            toast.error('Gagal menghapus dokumen');
        }
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
            await teachersApi.update(teacher.id, data);
            toast.success('Data guru berhasil diperbarui');
            router.visit('/teachers');
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
            <Head title={`Edit Guru — ${teacher.full_name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/teachers">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-3xl font-bold tracking-tight">Edit Guru</h1>
                        <p className="text-muted-foreground">{teacher.full_name}</p>
                    </div>
                    {teacher.must_change_password && (
                        <Badge variant="outline" className="gap-1 text-amber-600">
                            <ShieldAlert className="h-3.5 w-3.5" />
                            Belum ganti password awal
                        </Badge>
                    )}
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Guru</CardTitle>
                            <CardDescription>
                                Untuk mengganti password, gunakan Reset Password di menu Pengguna.
                                Penempatan kelas &amp; mata pelajaran dikelola dari menu Kelas /
                                Mata Pelajaran.
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
                                    <TabsTrigger value="dokumen">
                                        <FolderOpen className="mr-2 h-4 w-4" />
                                        Foto &amp; Dokumen
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
                                                value={data.email}
                                                onChange={(e) => update('email', e.target.value)}
                                                className={errors.email ? 'border-destructive' : ''}
                                            />
                                            <p className="text-xs text-muted-foreground">
                                                Alamat untuk masuk aplikasi — mengubahnya mengubah cara
                                                guru login.
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
                                                value={data.phone}
                                                onChange={(e) => update('phone', e.target.value)}
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="gender">Jenis Kelamin *</Label>
                                            <Select value={data.gender} onValueChange={(v) => update('gender', v)}>
                                                <SelectTrigger id="gender">
                                                    <SelectValue placeholder="Pilih jenis kelamin" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="male">Laki-laki</SelectItem>
                                                    <SelectItem value="female">Perempuan</SelectItem>
                                                </SelectContent>
                                            </Select>
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
                                            <Label htmlFor="birth_date">Tanggal Lahir</Label>
                                            <DatePicker
                                                id="birth_date"
                                                value={data.birth_date}
                                                onChange={(value) => update('birth_date', value)}
                                                placeholder="Pilih tanggal lahir"
                                                invalid={!!errors.birth_date}
                                                toYear={new Date().getFullYear()}
                                            />
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

                                {/* Tab 3: Foto & Dokumen (semua opsional) */}
                                <TabsContent value="dokumen" className="space-y-6">
                                    <div>
                                        <Label className="mb-2 block">Foto Profil</Label>
                                        <div className="flex items-center gap-4">
                                            <Avatar className="h-16 w-16">
                                                <AvatarImage src={avatarUrl ?? undefined} />
                                                <AvatarFallback className="text-lg">
                                                    {teacher.full_name?.slice(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <input
                                                ref={photoInputRef}
                                                type="file"
                                                accept=".jpg,.jpeg,.png"
                                                className="hidden"
                                                onChange={(e) => {
                                                    const file = e.target.files?.[0];
                                                    if (file) handlePhotoSelect(file);
                                                    e.target.value = '';
                                                }}
                                            />
                                            <div className="flex gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={photoUploading}
                                                    onClick={() => photoInputRef.current?.click()}
                                                >
                                                    {photoUploading ? (
                                                        <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                                                    ) : (
                                                        <Camera className="mr-2 h-3.5 w-3.5" />
                                                    )}
                                                    Ganti Foto
                                                </Button>
                                                {avatarUrl && (
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        disabled={photoUploading}
                                                        onClick={handlePhotoDelete}
                                                        className="text-muted-foreground hover:text-red-600"
                                                    >
                                                        <Trash2 className="mr-2 h-3.5 w-3.5" />
                                                        Hapus
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <Label className="mb-2 flex items-center gap-1.5">
                                            <ScanLine className="h-4 w-4" />
                                            Kode Kartu RFID
                                        </Label>
                                        <p className="mb-2 text-xs text-muted-foreground">
                                            Isi dari UID kartu fisik (pakai tombol Baca Kartu) atau
                                            terbitkan kode baru bila sekolah membuat kartunya sendiri.
                                            Harus unik lintas guru &amp; siswa.
                                        </p>
                                        <div className="flex max-w-md gap-2">
                                            <Input
                                                ref={rfidInputRef}
                                                value={rfidCode}
                                                onChange={(e) => setRfidCode(e.target.value)}
                                                onKeyDown={handleRfidKeyDown}
                                                placeholder={
                                                    readingCard
                                                        ? 'Tempelkan kartu ke reader...'
                                                        : 'Contoh: 04A2B9C1'
                                                }
                                                className={
                                                    rfidError
                                                        ? 'border-destructive'
                                                        : readingCard
                                                          ? 'border-primary ring-2 ring-ring ring-offset-2'
                                                          : ''
                                                }
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                onClick={() => handleSaveRfid()}
                                                disabled={rfidSaving || rfidGenerating}
                                            >
                                                {rfidSaving && <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />}
                                                Simpan
                                            </Button>
                                        </div>
                                        <div className="mt-2 flex flex-wrap items-center gap-2">
                                            <Button
                                                type="button"
                                                variant={readingCard ? 'secondary' : 'outline'}
                                                size="sm"
                                                onClick={() =>
                                                    readingCard ? setReadingCard(false) : startReadingCard()
                                                }
                                                disabled={rfidSaving || rfidGenerating}
                                            >
                                                <ScanLine className="mr-2 h-3.5 w-3.5" />
                                                {readingCard ? 'Batal Baca' : 'Baca Kartu'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    rfidCode.trim()
                                                        ? setConfirmRegenerate(true)
                                                        : handleGenerateRfid()
                                                }
                                                disabled={rfidSaving || rfidGenerating}
                                            >
                                                {rfidGenerating ? (
                                                    <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                                                ) : (
                                                    <Sparkles className="mr-2 h-3.5 w-3.5" />
                                                )}
                                                Generate Kode
                                            </Button>
                                            {readingCard && (
                                                <span className="text-xs text-muted-foreground">
                                                    Kursor siap — kode tersimpan otomatis setelah kartu terbaca.
                                                </span>
                                            )}
                                        </div>
                                        {rfidError && <p className="mt-1 text-sm text-destructive">{rfidError}</p>}
                                    </div>

                                    <div>
                                        <Label className="mb-2 block">
                                            Dokumen Pemberkasan
                                        </Label>
                                        <p className="mb-3 text-xs text-muted-foreground">
                                            Semua dokumen opsional. Format PDF/JPG/PNG, maksimal 5MB
                                            per file. Diunggah langsung saat file dipilih.
                                        </p>
                                        <div className="grid gap-3 sm:grid-cols-2">
                                            {documentCollections.map((col) => (
                                                <DocumentCollectionCard
                                                    key={col.key}
                                                    label={col.label}
                                                    singleFile={col.singleFile}
                                                    files={documents[col.key]}
                                                    uploading={uploadingCollection === col.key}
                                                    onUpload={(file) => handleDocumentUpload(col.key, file)}
                                                    onDelete={handleDocumentDelete}
                                                />
                                            ))}
                                        </div>
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
                            Simpan Perubahan
                        </Button>
                    </div>
                </form>
            </div>

            {/* Konfirmasi terbitkan ulang kode RFID */}
            <AlertDialog open={confirmRegenerate} onOpenChange={setConfirmRegenerate}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Terbitkan Kode RFID Baru?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Kode saat ini <span className="font-medium">{rfidCode}</span> akan
                            digantikan dan tidak berlaku lagi. Kartu lama guru ini tidak akan
                            terbaca di mesin absensi setelah kode baru disimpan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(event) => {
                                event.preventDefault();
                                handleGenerateRfid();
                            }}
                        >
                            Terbitkan Kode Baru
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
