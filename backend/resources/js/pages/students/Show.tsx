import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { qrCodeApi } from '@/services/attendance';
import { printAttendanceCardsWithTemplate } from '@/lib/attendanceCardPrint';
import type { PageProps, Student } from '@/types';
import { ArrowLeft, Pencil, Printer, ScanLine } from 'lucide-react';

interface ShowStudentProps {
    student: Student;
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-1.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '-'}</span>
        </div>
    );
}

export default function ShowStudent({ student }: ShowStudentProps) {
    const { tenant } = usePage<PageProps>().props;
    const [printing, setPrinting] = useState(false);

    const handlePrintCard = async () => {
        setPrinting(true);
        try {
            const response = await qrCodeApi.student(student.id);
            const qr = response.data.data;
            if (!qr) {
                toast.error('Data QR siswa tidak ditemukan');
                return;
            }

            const opened = await printAttendanceCardsWithTemplate('student', [
                { ...qr, classroom: qr.classroom ?? student.current_class?.name ?? null },
            ], {
                title: `Kartu Siswa - ${qr.name}`,
                schoolName: tenant?.name,
                schoolLogo: tenant?.logo,
            });

            if (!opened) {
                toast.error('Gagal menyiapkan halaman cetak. Coba lagi.');
            }
        } catch {
            toast.error('Gagal memuat kartu siswa');
        } finally {
            setPrinting(false);
        }
    };

    return (
        <MainLayout>
            <Head title={`Siswa — ${student.full_name}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/students">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-3xl font-bold tracking-tight">{student.full_name}</h1>
                        <p className="text-muted-foreground">{student.nis}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={handlePrintCard} disabled={printing}>
                            <Printer className="mr-2 h-4 w-4" />
                            {printing ? 'Memuat...' : 'Cetak Kartu'}
                        </Button>
                        <Button asChild>
                            <Link href={`/students/${student.id}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom kiri: identitas */}
                    <div className="space-y-6 lg:col-span-1">
                        <Card>
                            <CardContent className="flex flex-col items-center gap-3 pt-6 text-center">
                                <Avatar className="h-24 w-24">
                                    <AvatarImage src={student.photo_url ?? undefined} />
                                    <AvatarFallback className="text-2xl">
                                        {student.full_name?.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-semibold">{student.full_name}</div>
                                    <div className="text-sm text-muted-foreground">{student.email}</div>
                                </div>
                                <div className="flex flex-wrap justify-center gap-2">
                                    <Badge variant={student.status === 'active' ? 'default' : 'outline'}>
                                        {student.status_label}
                                    </Badge>
                                    {student.current_class && (
                                        <Badge variant="secondary">{student.current_class.name}</Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Data Pribadi</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow label="Jenis Kelamin" value={student.gender_label} />
                                <InfoRow label="Tempat, Tgl Lahir" value={
                                    student.birth_place || student.birth_date
                                        ? `${student.birth_place ?? '-'}, ${student.birth_date ?? '-'}`
                                        : '-'
                                } />
                                <InfoRow label="Agama" value={student.religion} />
                                <InfoRow label="No. Telepon" value={student.phone} />
                                <InfoRow label="NIK" value={student.nik} />
                                <InfoRow label="Alamat" value={student.address} />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Kolom kanan: pendidikan, kredensial presensi, orang tua/wali */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Data Pendidikan</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow label="NIS" value={student.nis} />
                                <InfoRow label="NISN" value={student.nisn} />
                                <InfoRow label="Kelas Saat Ini" value={student.current_class?.name} />
                                <InfoRow label="Sekolah Asal" value={student.previous_school} />
                                <InfoRow label="Tanggal Masuk" value={student.entry_date} />
                                <InfoRow label="Jenis Masuk" value={student.entry_type} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <ScanLine className="h-5 w-5" />
                                    Kredensial Presensi
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow label="Kode QR" value={<code className="text-xs">{student.unique_code}</code>} />
                                <InfoRow
                                    label="Kode Kartu RFID"
                                    value={
                                        student.rfid_code
                                            ? <code className="text-xs">{student.rfid_code}</code>
                                            : <span className="text-muted-foreground">Belum diatur — atur di halaman Edit</span>
                                    }
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Orang Tua / Wali</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {!student.parents || student.parents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada data orang tua/wali tertaut.
                                    </p>
                                ) : (
                                    <div className="space-y-3">
                                        {student.parents.map((parent) => (
                                            <div
                                                key={parent.id}
                                                className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                            >
                                                <div>
                                                    <div className="font-medium">{parent.full_name}</div>
                                                    <div className="text-muted-foreground">{parent.phone ?? '-'}</div>
                                                </div>
                                                <Badge variant="outline">{parent.relationship_label}</Badge>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </MainLayout>
    );
}
