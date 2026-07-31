import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Loader2, Save, Camera, Trash2, ScanLine } from 'lucide-react';
import { studentsApi } from '@/services/api';
import type { Student } from '@/types';

interface EditStudentProps {
    student: Student;
}

function toFormData(student: Student) {
    const [firstName, ...rest] = (student.full_name ?? '').split(' ');
    return {
        first_name: firstName ?? '',
        last_name: rest.join(' '),
        email: student.email ?? '',
        nis: student.nis ?? '',
        nisn: student.nisn ?? '',
        nik: student.nik ?? '',
        gender: student.gender ?? '',
        birth_place: student.birth_place ?? '',
        birth_date: student.birth_date ?? '',
        religion: student.religion ?? '',
        address: student.address ?? '',
        phone: student.phone ?? '',
        previous_school: student.previous_school ?? '',
        status: student.status ?? 'active',
    };
}

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
        return { fieldErrors: {}, message: message ?? 'Gagal menyimpan data siswa' };
    }
    return { fieldErrors: {}, message: 'Tidak dapat terhubung ke server' };
}

export default function EditStudent({ student }: EditStudentProps) {
    const [data, setDataRaw] = useState(() => toFormData(student));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const setData = <K extends keyof ReturnType<typeof toFormData>>(field: K, value: string) => {
        setDataRaw((d) => ({ ...d, [field]: value }));
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setFormError(null);
        try {
            await studentsApi.update(student.id, data);
            toast.success('Data siswa berhasil diperbarui');
            router.get(`/students/${student.id}`);
        } catch (error) {
            const { fieldErrors, message } = getErrors(error);
            setErrors(fieldErrors);
            if (message) {
                setFormError(message);
                toast.error(message);
            }
        } finally {
            setProcessing(false);
        }
    };

    // Foto profil — diunggah langsung saat dipilih, terpisah dari form utama
    const [avatarUrl, setAvatarUrl] = useState(student.photo_url ?? null);
    const [photoUploading, setPhotoUploading] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);

    const handlePhotoSelect = async (file: File) => {
        setPhotoUploading(true);
        try {
            const response = await studentsApi.uploadPhoto(student.id, file);
            setAvatarUrl(response.data.data?.photo_url ?? null);
            toast.success('Foto berhasil diperbarui');
        } catch {
            toast.error('Gagal mengunggah foto');
        } finally {
            setPhotoUploading(false);
        }
    };

    const handlePhotoDelete = async () => {
        setPhotoUploading(true);
        try {
            await studentsApi.deletePhoto(student.id);
            setAvatarUrl(null);
            toast.success('Foto berhasil dihapus');
        } catch {
            toast.error('Gagal menghapus foto');
        } finally {
            setPhotoUploading(false);
        }
    };

    // Kode kartu RFID — endpoint terpisah (RfidController, Fase 2)
    const [rfidCode, setRfidCode] = useState(student.rfid_code ?? '');
    const [rfidSaving, setRfidSaving] = useState(false);
    const [rfidError, setRfidError] = useState<string | null>(null);

    const handleSaveRfid = async () => {
        setRfidSaving(true);
        setRfidError(null);
        try {
            const response = await studentsApi.updateRfid(student.id, rfidCode.trim() || null);
            setRfidCode(response.data.data?.rfid_code ?? '');
            toast.success('Kode RFID berhasil disimpan');
        } catch (error: unknown) {
            const message = (error as { response?: { data?: { errors?: { rfid_code?: string[] } } } })
                ?.response?.data?.errors?.rfid_code?.[0];
            setRfidError(message ?? 'Gagal menyimpan kode RFID');
        } finally {
            setRfidSaving(false);
        }
    };

    return (
        <MainLayout>
            <Head title={`Edit — ${student.full_name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href={`/students/${student.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Edit Siswa</h1>
                        <p className="text-muted-foreground">{student.full_name} — {student.nis}</p>
                    </div>
                </div>

                {formError && (
                    <Alert variant="destructive">
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                {/* Foto & RFID — tersimpan langsung, terpisah dari form utama */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Foto Profil</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center gap-4">
                                <Avatar className="h-16 w-16">
                                    <AvatarImage src={avatarUrl ?? undefined} />
                                    <AvatarFallback className="text-lg">
                                        {student.full_name?.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <input
                                    ref={photoInputRef}
                                    type="file"
                                    accept="image/png,image/jpeg,image/jpg"
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
                                        {photoUploading
                                            ? <Loader2 className="mr-2 h-3.5 w-3.5 animate-spin" />
                                            : <Camera className="mr-2 h-3.5 w-3.5" />}
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
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ScanLine className="h-4 w-4" />
                                Kode Kartu RFID
                            </CardTitle>
                            <CardDescription>
                                Nomor dari kartu RFID fisik siswa (dicocokkan saat proses encoding kartu).
                                Harus unik lintas siswa &amp; guru.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2">
                                <Input
                                    value={rfidCode}
                                    onChange={(e) => setRfidCode(e.target.value)}
                                    placeholder="Contoh: 04A2B9C1"
                                    className={rfidError ? 'border-destructive' : ''}
                                />
                                <Button type="button" onClick={handleSaveRfid} disabled={rfidSaving}>
                                    {rfidSaving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Simpan
                                </Button>
                            </div>
                            {rfidError && <p className="mt-2 text-sm text-destructive">{rfidError}</p>}
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Data Akun */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Akun</CardTitle>
                                <CardDescription>Informasi login untuk akses sistem</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="first_name">Nama Depan *</Label>
                                        <Input
                                            id="first_name"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                            className={errors.first_name ? 'border-destructive' : ''}
                                        />
                                        {errors.first_name && <p className="text-sm text-destructive">{errors.first_name}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="last_name">Nama Belakang</Label>
                                        <Input
                                            id="last_name"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email *</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className={errors.email ? 'border-destructive' : ''}
                                    />
                                    {errors.email && <p className="text-sm text-destructive">{errors.email}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">Aktif</SelectItem>
                                            <SelectItem value="graduated">Lulus</SelectItem>
                                            <SelectItem value="transferred">Pindah</SelectItem>
                                            <SelectItem value="dropped">Keluar</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Data Identitas */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Identitas</CardTitle>
                                <CardDescription>Nomor identitas siswa</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="nis">NIS (Nomor Induk Siswa) *</Label>
                                    <Input
                                        id="nis"
                                        value={data.nis}
                                        onChange={(e) => setData('nis', e.target.value)}
                                        className={errors.nis ? 'border-destructive' : ''}
                                    />
                                    {errors.nis && <p className="text-sm text-destructive">{errors.nis}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nisn">NISN (Nomor Induk Siswa Nasional)</Label>
                                    <Input id="nisn" value={data.nisn} onChange={(e) => setData('nisn', e.target.value)} />
                                    {errors.nisn && <p className="text-sm text-destructive">{errors.nisn}</p>}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nik">NIK (Nomor Induk Kependudukan)</Label>
                                    <Input id="nik" value={data.nik} onChange={(e) => setData('nik', e.target.value)} maxLength={16} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone">No. Telepon</Label>
                                    <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Data Pribadi */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Pribadi</CardTitle>
                                <CardDescription>Informasi pribadi siswa</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="gender">Jenis Kelamin</Label>
                                    <Select value={data.gender} onValueChange={(value) => setData('gender', value)}>
                                        <SelectTrigger className={errors.gender ? 'border-destructive' : ''}>
                                            <SelectValue placeholder="Pilih jenis kelamin" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="male">Laki-laki</SelectItem>
                                            <SelectItem value="female">Perempuan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.gender && <p className="text-sm text-destructive">{errors.gender}</p>}
                                </div>

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="birth_place">Tempat Lahir</Label>
                                        <Input
                                            id="birth_place"
                                            value={data.birth_place}
                                            onChange={(e) => setData('birth_place', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="birth_date">Tanggal Lahir</Label>
                                        <Input
                                            id="birth_date"
                                            type="date"
                                            value={data.birth_date}
                                            onChange={(e) => setData('birth_date', e.target.value)}
                                        />
                                        {errors.birth_date && <p className="text-sm text-destructive">{errors.birth_date}</p>}
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="religion">Agama</Label>
                                    <Select value={data.religion} onValueChange={(value) => setData('religion', value)}>
                                        <SelectTrigger>
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
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        rows={3}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Data Pendidikan */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Pendidikan</CardTitle>
                                <CardDescription>Sekolah asal siswa</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="previous_school">Sekolah Asal</Label>
                                    <Input
                                        id="previous_school"
                                        value={data.previous_school}
                                        onChange={(e) => setData('previous_school', e.target.value)}
                                    />
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Kelas &amp; tahun ajaran diatur dari menu Pendaftaran/Kelas, bukan di sini.
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 flex justify-end gap-4">
                        <Button type="button" variant="outline" onClick={() => router.get(`/students/${student.id}`)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <Save className="mr-2 h-4 w-4" />
                            Simpan
                        </Button>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}
