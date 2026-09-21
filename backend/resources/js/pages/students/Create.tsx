import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import LinkExistingUserField, { type LinkableUser } from '@/components/LinkExistingUserField';
import axios from 'axios';
import { toast } from 'sonner';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ArrowLeft, Loader2, Save } from 'lucide-react';
import { studentsApi } from '@/services/api';

const emptyForm = {
    // User data
    first_name: '',
    last_name: '',
    email: '',
    contact_email: '',
    username: '',
    password: '',

    // Student data
    nis: '',
    nisn: '',
    nik: '',
    gender: '',
    birth_place: '',
    birth_date: '',
    religion: '',
    address: '',
    phone: '',
    previous_school: '',
    entry_year: new Date().getFullYear().toString(),
    entry_class: '',
    entry_semester: '1',
};

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

export default function CreateStudent() {
    const [data, setDataRaw] = useState(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    // null = buat akun baru (perilaku lama); terisi = tautkan ke akun itu.
    const [linkedUser, setLinkedUser] = useState<LinkableUser | null>(null);

    const setData = <K extends keyof typeof emptyForm>(field: K, value: string) => {
        setDataRaw((d) => ({ ...d, [field]: value }));
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setFormError(null);
        try {
            const formData = new FormData();
            Object.entries(data).forEach(([key, value]) => formData.append(key, value));
            // Menautkan ke akun yang sudah ada: backend melewati pembuatan akun
            // dan field identitas di atas tidak lagi wajib.
            if (linkedUser) formData.append('user_id', linkedUser.id);

            const response = await studentsApi.create(formData);
            toast.success('Siswa berhasil ditambahkan');
            router.get(`/students/${response.data.data?.id}`);
        } catch (error) {
            const { fieldErrors, message } = getErrors(error);
            setErrors(fieldErrors);
            if (message) {
                setFormError(message);
                toast.error(message);
            } else {
                toast.error('Data siswa gagal disimpan, periksa isian yang ditandai merah.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title="Tambah Siswa" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/students">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tambah Siswa Baru</h1>
                        <p className="text-muted-foreground">
                            Isi formulir di bawah untuk menambahkan siswa baru
                        </p>
                    </div>
                </div>

                {formError && (
                    <Alert variant="destructive">
                        <AlertDescription>{formError}</AlertDescription>
                    </Alert>
                )}

                <form onSubmit={submit}>
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Data Akun */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Akun</CardTitle>
                                <CardDescription>
                                    Informasi login untuk akses sistem
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <LinkExistingUserField
                                    type="student"
                                    label="siswa"
                                    value={linkedUser}
                                    onChange={setLinkedUser}
                                />

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="first_name">Nama Depan *</Label>
                                        <Input
                                            id="first_name"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
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
                                            onChange={(e) => setData('last_name', e.target.value)}
                                        />
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email Login</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className={errors.email ? 'border-destructive' : ''}
                                        placeholder="Kosongkan untuk dibuatkan otomatis"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Dikosongkan = dibuat otomatis dari nama depan + NIS, memakai
                                        domain di Pengaturan → Umum. Alamat ini hanya untuk masuk
                                        aplikasi, tidak dikirimi surat.
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
                                        value={data.contact_email}
                                        onChange={(e) => setData('contact_email', e.target.value)}
                                        className={errors.contact_email ? 'border-destructive' : ''}
                                        placeholder="Mis. email orang tua"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Alamat sungguhan tujuan OTP &amp; notifikasi. Boleh sama untuk
                                        beberapa anak dari orang tua yang sama.
                                    </p>
                                    {errors.contact_email && (
                                        <p className="text-sm text-destructive">{errors.contact_email}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="username">Username</Label>
                                    <Input
                                        id="username"
                                        value={data.username}
                                        onChange={(e) => setData('username', e.target.value)}
                                        placeholder="Kosongkan untuk generate otomatis"
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="password">Password</Label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Default: password123"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Data Identitas */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Identitas</CardTitle>
                                <CardDescription>
                                    Nomor identitas siswa
                                </CardDescription>
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
                                    {errors.nis && (
                                        <p className="text-sm text-destructive">{errors.nis}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nisn">NISN (Nomor Induk Siswa Nasional)</Label>
                                    <Input
                                        id="nisn"
                                        value={data.nisn}
                                        onChange={(e) => setData('nisn', e.target.value)}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="nik">NIK (Nomor Induk Kependudukan)</Label>
                                    <Input
                                        id="nik"
                                        value={data.nik}
                                        onChange={(e) => setData('nik', e.target.value)}
                                        maxLength={16}
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="phone">No. Telepon</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="08123456789"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Data Pribadi */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Data Pribadi</CardTitle>
                                <CardDescription>
                                    Informasi pribadi siswa
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="gender">Jenis Kelamin *</Label>
                                    <Select
                                        value={data.gender}
                                        onValueChange={(value) => setData('gender', value)}
                                    >
                                        <SelectTrigger className={errors.gender ? 'border-destructive' : ''}>
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
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="religion">Agama</Label>
                                    <Select
                                        value={data.religion}
                                        onValueChange={(value) => setData('religion', value)}
                                    >
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
                                <CardDescription>
                                    Informasi pendidikan siswa
                                </CardDescription>
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

                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="entry_year">Tahun Masuk *</Label>
                                        <Input
                                            id="entry_year"
                                            type="number"
                                            value={data.entry_year}
                                            onChange={(e) => setData('entry_year', e.target.value)}
                                            min={2000}
                                            max={new Date().getFullYear() + 1}
                                            className={errors.entry_year ? 'border-destructive' : ''}
                                        />
                                        {errors.entry_year && (
                                            <p className="text-sm text-destructive">{errors.entry_year}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="entry_semester">Semester Masuk</Label>
                                        <Select
                                            value={data.entry_semester}
                                            onValueChange={(value) => setData('entry_semester', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="1">Semester 1 (Ganjil)</SelectItem>
                                                <SelectItem value="2">Semester 2 (Genap)</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="entry_class">Kelas Masuk</Label>
                                    <Input
                                        id="entry_class"
                                        value={data.entry_class}
                                        onChange={(e) => setData('entry_class', e.target.value)}
                                        placeholder="Contoh: 7A, X IPA 1"
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="mt-6 flex justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/students">Batal</Link>
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
