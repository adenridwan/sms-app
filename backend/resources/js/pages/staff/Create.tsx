import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState, useEffect } from 'react';
import LinkExistingUserField, { type LinkableUser } from '@/components/LinkExistingUserField';
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
import { ArrowLeft, Info, KeyRound, Loader2, Save, User as UserIcon, Briefcase } from 'lucide-react';
import { staffApi, type CreateStaffResponse, type DepartmentOption, type PositionOption, type StaffFormData } from '@/services/api';

const emptyForm: StaffFormData = {
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
    employee_id: '',
    department_id: '',
    position_id: '',
    join_date: new Date().toISOString().slice(0, 10),
    employment_status: 'permanent',
    status: 'active',
    education_level: '',
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

const educationLevels = ['sma', 'd3', 'd4', 's1', 's2', 's3'];

const requiredFields: Array<{ field: keyof StaffFormData; label: string; tab: string }> = [
    { field: 'first_name', label: 'Nama Depan', tab: 'akun' },
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
        return { fieldErrors: {}, message: message ?? 'Gagal menyimpan data staf' };
    }
    return { fieldErrors: {}, message: 'Tidak dapat terhubung ke server' };
}

export default function CreateStaff() {
    const [data, setData] = useState<StaffFormData>(emptyForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState('akun');

    const [credentials, setCredentials] = useState<CreateStaffResponse | null>(null);

    const [departments, setDepartments] = useState<DepartmentOption[]>([]);
    const [positions, setPositions] = useState<PositionOption[]>([]);
    // null = buat akun baru (perilaku lama); terisi = tautkan ke akun itu.
    const [linkedUser, setLinkedUser] = useState<LinkableUser | null>(null);
    const [loadingOptions, setLoadingOptions] = useState(true);

    useEffect(() => {
        const loadOptions = async () => {
            try {
                const [deptRes, posRes] = await Promise.all([
                    staffApi.departments(),
                    staffApi.positions(),
                ]);
                setDepartments(deptRes.data.data);
                setPositions(posRes.data.data);
            } catch {
                // Silent fail
            } finally {
                setLoadingOptions(false);
            }
        };
        loadOptions();
    }, []);

    const update = <K extends keyof StaffFormData>(field: K, value: StaffFormData[K]) => {
        setData((d) => ({ ...d, [field]: value }));
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});
        setFormError(null);

        // Menautkan akun yang sudah ada: identitas diambil dari akun itu.
        const missing = linkedUser
            ? []
            : requiredFields.filter(({ field }) => !String(data[field] ?? '').trim());
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
            const response = await staffApi.create(
                linkedUser ? { ...data, user_id: linkedUser.id } : data
            );
            setCredentials(response.data.data);
            toast.success('Staf berhasil ditambahkan');
        } catch (error) {
            const { fieldErrors, message } = getErrors(error);
            setErrors(fieldErrors);

            if (message) {
                setFormError(message);
                toast.error(message);
            } else {
                setFormError('Periksa kembali isian yang ditandai merah.');
                toast.error('Data staf gagal disimpan, periksa isian yang ditandai merah.');
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
            <Head title="Tambah Staf" />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/staff">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tambah Staf Baru</h1>
                        <p className="text-muted-foreground">
                            Akun login dibuat otomatis dari data di bawah
                        </p>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Staf</CardTitle>
                            <CardDescription>
                                Data staf non-teaching (TU, Kebersihan, Keamanan, dll)
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
                                    <LinkExistingUserField
                                        type="staff"
                                        label="staf"
                                        value={linkedUser}
                                        onChange={setLinkedUser}
                                    />

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
                                                Dikosongkan = dibuat otomatis dari nama
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
                                            <Label htmlFor="employee_id">ID Pegawai</Label>
                                            <Input
                                                id="employee_id"
                                                value={data.employee_id}
                                                onChange={(e) => update('employee_id', e.target.value)}
                                                className={errors.employee_id ? 'border-destructive' : ''}
                                            />
                                            {errors.employee_id && <p className="text-sm text-destructive">{errors.employee_id}</p>}
                                        </div>
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
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>Bidang/Unit</Label>
                                            <Select
                                                value={data.department_id}
                                                onValueChange={(v) => update('department_id', v)}
                                                disabled={loadingOptions}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Pilih bidang" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {departments.map((dept) => (
                                                        <SelectItem key={dept.id} value={dept.id}>
                                                            {dept.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.department_id && (
                                                <p className="text-sm text-destructive">{errors.department_id}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Jabatan</Label>
                                            <Select
                                                value={data.position_id}
                                                onValueChange={(v) => update('position_id', v)}
                                                disabled={loadingOptions}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Pilih jabatan" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {positions.map((pos) => (
                                                        <SelectItem key={pos.id} value={pos.id}>
                                                            {pos.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.position_id && (
                                                <p className="text-sm text-destructive">{errors.position_id}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
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
                                            <Label>Status Staf</Label>
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

                                    <div className="space-y-2 sm:w-1/2">
                                        <Label>Pendidikan Terakhir</Label>
                                        <Select
                                            value={data.education_level.toLowerCase()}
                                            onValueChange={(v) => update('education_level', v)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih jenjang" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {educationLevels.map((level) => (
                                                    <SelectItem key={level} value={level}>
                                                        {level.toUpperCase()}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/staff">Batal</Link>
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
                            Staf Berhasil Ditambahkan
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-3 pt-2 text-left">
                                <p>Sampaikan kredensial berikut ke staf yang bersangkutan:</p>
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
                                        kembali. Staf akan diminta mengganti password sendiri saat
                                        pertama kali login.
                                    </AlertDescription>
                                </Alert>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => router.visit('/staff')}>
                            Ke Daftar Staf
                        </AlertDialogCancel>
                        <AlertDialogAction onClick={() => router.visit(`/staff/${credentials?.id}/edit`)}>
                            Edit Data Staf
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
