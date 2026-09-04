import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowLeft, Pencil, User, Briefcase, Mail, Phone, MapPin, Calendar } from 'lucide-react';
import type { Staff } from '@/services/api';

interface Props {
    staff: Staff;
}

export default function ShowStaff({ staff }: Props) {
    const getStatusVariant = (status: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            inactive: 'destructive',
            on_leave: 'outline',
            retired: 'secondary',
            terminated: 'destructive',
        };
        return variants[status] || 'default';
    };

    return (
        <MainLayout>
            <Head title={`Detail Staf - ${staff.full_name}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/staff">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">{staff.full_name}</h1>
                            <p className="text-muted-foreground">
                                {staff.position_name || 'Staf'} {staff.department_name ? `- ${staff.department_name}` : ''}
                            </p>
                        </div>
                    </div>
                    <Button asChild>
                        <Link href={`/staff/${staff.id}/edit`}>
                            <Pencil className="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Data Pribadi */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <User className="h-5 w-5" />
                                Data Pribadi
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Nama Lengkap</p>
                                    <p className="font-medium">{staff.full_name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Jenis Kelamin</p>
                                    <p className="font-medium">{staff.gender_label}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Tempat Lahir</p>
                                    <p className="font-medium">{staff.birth_place || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Tanggal Lahir</p>
                                    <p className="font-medium">{staff.birth_date || '-'}</p>
                                </div>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Agama</p>
                                <p className="font-medium capitalize">{staff.religion || '-'}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">NIK</p>
                                <p className="font-medium">{staff.id_number || '-'}</p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Kontak */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-5 w-5" />
                                Kontak
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-start gap-3">
                                <Mail className="h-4 w-4 mt-1 text-muted-foreground" />
                                <div>
                                    <p className="text-sm text-muted-foreground">Email Login</p>
                                    <p className="font-medium">{staff.email}</p>
                                    {staff.email_is_generated && (
                                        <p className="text-xs text-muted-foreground">(dibuat otomatis)</p>
                                    )}
                                </div>
                            </div>
                            {staff.contact_email && (
                                <div className="flex items-start gap-3">
                                    <Mail className="h-4 w-4 mt-1 text-muted-foreground" />
                                    <div>
                                        <p className="text-sm text-muted-foreground">Email Kontak</p>
                                        <p className="font-medium">{staff.contact_email}</p>
                                    </div>
                                </div>
                            )}
                            <div className="flex items-start gap-3">
                                <Phone className="h-4 w-4 mt-1 text-muted-foreground" />
                                <div>
                                    <p className="text-sm text-muted-foreground">No. HP</p>
                                    <p className="font-medium">{staff.phone || '-'}</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <MapPin className="h-4 w-4 mt-1 text-muted-foreground" />
                                <div>
                                    <p className="text-sm text-muted-foreground">Alamat</p>
                                    <p className="font-medium">{staff.address || '-'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Kepegawaian */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Briefcase className="h-5 w-5" />
                                Kepegawaian
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">ID Pegawai</p>
                                    <p className="font-medium">{staff.employee_id || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Status</p>
                                    <Badge variant={getStatusVariant(staff.status)}>
                                        {staff.status_label}
                                    </Badge>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Bidang/Unit</p>
                                    <p className="font-medium">{staff.department_name || '-'}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Jabatan</p>
                                    <p className="font-medium">{staff.position_name || '-'}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Status Kepegawaian</p>
                                    <p className="font-medium">{staff.employment_status_label}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Pendidikan</p>
                                    <p className="font-medium">{staff.education_level?.toUpperCase() || '-'}</p>
                                </div>
                            </div>
                            <div className="flex items-start gap-3">
                                <Calendar className="h-4 w-4 mt-1 text-muted-foreground" />
                                <div>
                                    <p className="text-sm text-muted-foreground">Tanggal Masuk</p>
                                    <p className="font-medium">{staff.join_date || '-'}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Akun */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Akun</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Username</p>
                                    <p className="font-medium">{staff.username}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Status Akun</p>
                                    <Badge variant={staff.account_is_active ? 'default' : 'destructive'}>
                                        {staff.account_is_active ? 'Aktif' : 'Tidak Aktif'}
                                    </Badge>
                                </div>
                            </div>
                            {staff.must_change_password && (
                                <div className="rounded-md bg-yellow-50 p-3 dark:bg-yellow-950">
                                    <p className="text-sm text-yellow-700 dark:text-yellow-300">
                                        Staf ini harus mengganti password saat login berikutnya.
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
