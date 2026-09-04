import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState, useEffect } from 'react';
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
import { Alert, AlertDescription } from '@/components/ui/alert';
import { toast } from 'sonner';
import { ArrowLeft, Loader2, Save, User as UserIcon, Briefcase } from 'lucide-react';
import { staffApi, type Staff, type DepartmentOption, type PositionOption, type StaffFormData } from '@/services/api';

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

interface Props {
    staff: Staff;
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
        return { fieldErrors: {}, message: message ?? 'Gagal menyimpan data staf' };
    }
    return { fieldErrors: {}, message: 'Tidak dapat terhubung ke server' };
}

export default function EditStaff({ staff }: Props) {
    const [data, setData] = useState<StaffFormData>({
        first_name: staff.first_name || '',
        last_name: staff.last_name || '',
        email: staff.email || '',
        contact_email: staff.contact_email || '',
        phone: staff.phone || '',
        gender: staff.gender || '',
        birth_place: staff.birth_place || '',
        birth_date: staff.birth_date || '',
        religion: staff.religion || '',
        address: staff.address || '',
        id_number: staff.id_number || '',
        employee_id: staff.employee_id || '',
        department_id: staff.department_id || '',
        position_id: staff.position_id || '',
        join_date: staff.join_date || '',
        employment_status: staff.employment_status || 'permanent',
        status: staff.status || 'active',
        education_level: staff.education_level || '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState('akun');

    const [departments, setDepartments] = useState<DepartmentOption[]>([]);
    const [positions, setPositions] = useState<PositionOption[]>([]);
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
        setProcessing(true);

        try {
            await staffApi.update(staff.id, data);
            toast.success('Data staf berhasil diperbarui');
            router.visit('/staff');
        } catch (error) {
            const { fieldErrors, message } = getErrors(error);
            setErrors(fieldErrors);

            if (message) {
                setFormError(message);
                toast.error(message);
            } else {
                setFormError('Periksa kembali isian yang ditandai merah.');
                toast.error('Data staf gagal disimpan');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <MainLayout>
            <Head title={`Edit Staf - ${staff.full_name}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/staff">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Edit Staf</h1>
                        <p className="text-muted-foreground">{staff.full_name}</p>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Staf</CardTitle>
                            <CardDescription>
                                Perbarui data staf
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
                                                disabled={staff.email_is_generated}
                                            />
                                            {staff.email_is_generated && (
                                                <p className="text-xs text-muted-foreground">
                                                    Email ini dibuat otomatis oleh sistem
                                                </p>
                                            )}
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
                                                onChange={(e) => update('contact_email', e.target.value)}
                                            />
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
                                            <Label htmlFor="gender">Jenis Kelamin</Label>
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
        </MainLayout>
    );
}
