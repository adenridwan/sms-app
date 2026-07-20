import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import axios from 'axios';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { toast } from 'sonner';
import {
    ArrowLeft,
    Pencil,
    KeyRound,
    UserX,
    ShieldAlert,
    School,
    Calendar,
    BookOpen,
    FileText,
    Image as ImageIcon,
    Download,
    Loader2,
} from 'lucide-react';
import { teachersApi, usersApi } from '@/services/api';
import type { PageProps, Teacher, TeacherAssignment, TeacherDocumentCollection } from '@/types';

interface ShowTeacherProps {
    teacher: Teacher;
    assignment: TeacherAssignment;
}

const documentLabels: Record<TeacherDocumentCollection, string> = {
    ijazah: 'Ijazah',
    sertifikat_pendidik: 'Sertifikat Pendidik',
    surat_penugasan: 'Surat Penugasan',
    ktp: 'KTP',
    npwp: 'NPWP',
    lainnya: 'Dokumen Lainnya',
};

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-4 py-1.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="text-right font-medium">{value ?? '-'}</span>
        </div>
    );
}

export default function ShowTeacher({ teacher, assignment }: ShowTeacherProps) {
    const { auth } = usePage<PageProps>().props;
    const isSuperAdmin = auth.user?.user_type === 'super_admin';

    const [deactivating, setDeactivating] = useState(false);
    const [confirmDeactivate, setConfirmDeactivate] = useState(false);

    const [resetOpen, setResetOpen] = useState(false);
    const [newPassword, setNewPassword] = useState('');
    const [resetting, setResetting] = useState(false);

    const documents = teacher.documents;
    const hasAnyDocument = documents
        ? Object.values(documents).some((files) => files.length > 0)
        : false;

    const handleDeactivate = async () => {
        setDeactivating(true);
        try {
            await teachersApi.update(teacher.id, { status: 'inactive' });
            toast.success('Guru berhasil dinonaktifkan');
            router.reload();
        } catch {
            toast.error('Gagal menonaktifkan guru');
        } finally {
            setDeactivating(false);
            setConfirmDeactivate(false);
        }
    };

    const handleResetPassword = async () => {
        if (newPassword.length < 8) {
            toast.error('Password minimal 8 karakter');
            return;
        }
        if (!teacher.user_id) return;

        setResetting(true);
        try {
            await usersApi.resetPassword(teacher.user_id, newPassword);
            toast.success('Password berhasil direset. Guru wajib mengganti saat login berikutnya.');
            setResetOpen(false);
            setNewPassword('');
        } catch (error) {
            const message = axios.isAxiosError(error) ? error.response?.data?.message : null;
            toast.error(message ?? 'Gagal mereset password');
        } finally {
            setResetting(false);
        }
    };

    return (
        <MainLayout>
            <Head title={`Guru — ${teacher.full_name}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/teachers">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-3xl font-bold tracking-tight">{teacher.full_name}</h1>
                        <p className="text-muted-foreground">{teacher.nip ?? 'NIP belum diisi'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => setResetOpen(true)} disabled={!isSuperAdmin}>
                            <KeyRound className="mr-2 h-4 w-4" />
                            Reset Password
                        </Button>
                        {teacher.status === 'active' && (
                            <Button
                                variant="outline"
                                className="text-muted-foreground hover:text-red-600"
                                onClick={() => setConfirmDeactivate(true)}
                            >
                                <UserX className="mr-2 h-4 w-4" />
                                Nonaktifkan
                            </Button>
                        )}
                        <Button asChild>
                            <Link href={`/teachers/${teacher.id}/edit`}>
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Kolom kiri: identitas & kepegawaian */}
                    <div className="space-y-6 lg:col-span-1">
                        <Card>
                            <CardContent className="flex flex-col items-center gap-3 pt-6 text-center">
                                <Avatar className="h-24 w-24">
                                    <AvatarImage src={teacher.avatar_url ?? undefined} />
                                    <AvatarFallback className="text-2xl">
                                        {teacher.full_name?.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>
                                <div>
                                    <div className="font-semibold">{teacher.full_name}</div>
                                    <div className="text-sm text-muted-foreground">{teacher.email}</div>
                                </div>
                                <div className="flex flex-wrap justify-center gap-2">
                                    <Badge variant={teacher.status === 'active' ? 'default' : 'outline'}>
                                        {teacher.status_label}
                                    </Badge>
                                    <Badge variant="secondary">{teacher.employment_status_label}</Badge>
                                    {teacher.must_change_password && (
                                        <Badge variant="outline" className="gap-1 text-amber-600">
                                            <ShieldAlert className="h-3.5 w-3.5" />
                                            Belum ganti password
                                        </Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Data Pribadi</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow label="Jenis Kelamin" value={teacher.gender_label} />
                                <InfoRow label="Tempat, Tgl Lahir" value={
                                    teacher.birth_place || teacher.birth_date
                                        ? `${teacher.birth_place ?? '-'}, ${teacher.birth_date ?? '-'}`
                                        : '-'
                                } />
                                <InfoRow label="Agama" value={teacher.religion} />
                                <InfoRow label="No. HP" value={teacher.phone} />
                                <InfoRow label="NIK" value={teacher.id_number} />
                                <InfoRow label="Alamat" value={teacher.address} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Kepegawaian</CardTitle>
                            </CardHeader>
                            <CardContent className="divide-y">
                                <InfoRow label="NIP" value={teacher.nip} />
                                <InfoRow label="NUPTK" value={teacher.nuptk} />
                                <InfoRow label="Tanggal Masuk" value={teacher.join_date} />
                                <InfoRow label="Sertifikasi" value={teacher.certification_status_label} />
                                {teacher.certification_number && (
                                    <InfoRow label="No. Sertifikat" value={teacher.certification_number} />
                                )}
                                <InfoRow
                                    label="Pendidikan"
                                    value={
                                        [teacher.education_level?.toUpperCase(), teacher.education_major]
                                            .filter(Boolean)
                                            .join(' — ') || '-'
                                    }
                                />
                                <InfoRow label="Universitas" value={teacher.university} />
                                <InfoRow
                                    label="Pengalaman Mengajar"
                                    value={
                                        teacher.teaching_experience_years != null
                                            ? `${teacher.teaching_experience_years} tahun`
                                            : '-'
                                    }
                                />
                            </CardContent>
                        </Card>
                    </div>

                    {/* Kolom kanan: penugasan (read-only) & dokumen */}
                    <div className="space-y-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <School className="h-5 w-5" />
                                    Penugasan
                                </CardTitle>
                                <CardDescription>
                                    {assignment.active_academic_year
                                        ? `Tahun ajaran ${assignment.active_academic_year} — `
                                        : ''}
                                    hanya untuk dilihat. Atur penempatan kelas &amp; wali kelas dari
                                    menu Kelas, dan mapel/jadwal dari menu Mata Pelajaran / Jadwal.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {!assignment.active_academic_year ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada tahun ajaran aktif.
                                    </p>
                                ) : (
                                    <>
                                        <div>
                                            <Label className="mb-2 block text-xs uppercase text-muted-foreground">
                                                Kelas Diampu
                                            </Label>
                                            {assignment.classrooms.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Belum ada kelas yang ditugaskan.
                                                </p>
                                            ) : (
                                                <div className="flex flex-wrap gap-2">
                                                    {assignment.classrooms.map((c) => (
                                                        <Badge key={c.id} variant="secondary">
                                                            {c.name} ({c.students_count} siswa)
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        {assignment.homeroom_classroom && (
                                            <div>
                                                <Label className="mb-2 block text-xs uppercase text-muted-foreground">
                                                    Wali Kelas
                                                </Label>
                                                <Badge>{assignment.homeroom_classroom.name}</Badge>
                                            </div>
                                        )}

                                        <div>
                                            <Label className="mb-2 flex items-center gap-1.5 text-xs uppercase text-muted-foreground">
                                                <BookOpen className="h-3.5 w-3.5" />
                                                Mapel Kompetensi
                                            </Label>
                                            {assignment.subjects.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Belum ada mapel kompetensi tercatat.
                                                </p>
                                            ) : (
                                                <div className="flex flex-wrap gap-2">
                                                    {assignment.subjects.map((s) => (
                                                        <Badge key={s.id} variant={s.is_primary ? 'default' : 'outline'}>
                                                            {s.name}
                                                        </Badge>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        <div>
                                            <Label className="mb-2 flex items-center gap-1.5 text-xs uppercase text-muted-foreground">
                                                <Calendar className="h-3.5 w-3.5" />
                                                Jadwal Mengajar
                                            </Label>
                                            {assignment.schedules.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Belum ada jadwal terinput.
                                                </p>
                                            ) : (
                                                <div className="space-y-1.5">
                                                    {assignment.schedules.map((s, i) => (
                                                        <div
                                                            key={i}
                                                            className="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm"
                                                        >
                                                            <span className="font-medium">{s.day_name}</span>
                                                            <span className="text-muted-foreground">{s.subject}</span>
                                                            <span className="text-muted-foreground">{s.classroom}</span>
                                                            <span>{s.start_time}–{s.end_time}</span>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Dokumen Pemberkasan</CardTitle>
                                <CardDescription>
                                    Untuk mengunggah atau menghapus dokumen, buka halaman Edit.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {!hasAnyDocument ? (
                                    <p className="text-sm text-muted-foreground">
                                        Belum ada dokumen yang diunggah.
                                    </p>
                                ) : (
                                    <div className="space-y-4">
                                        {(Object.keys(documentLabels) as TeacherDocumentCollection[])
                                            .filter((key) => (documents?.[key]?.length ?? 0) > 0)
                                            .map((key) => (
                                                <div key={key}>
                                                    <Label className="mb-2 block text-xs uppercase text-muted-foreground">
                                                        {documentLabels[key]}
                                                    </Label>
                                                    <div className="space-y-1.5">
                                                        {documents![key].map((file) => (
                                                            <a
                                                                key={file.id}
                                                                href={file.url}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted/50"
                                                            >
                                                                <span className="flex min-w-0 items-center gap-2">
                                                                    {file.mime_type === 'application/pdf' ? (
                                                                        <FileText className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                                    ) : (
                                                                        <ImageIcon className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                                    )}
                                                                    <span className="truncate">{file.name}</span>
                                                                </span>
                                                                <Download className="h-4 w-4 shrink-0 text-muted-foreground" />
                                                            </a>
                                                        ))}
                                                    </div>
                                                    <Separator className="mt-4" />
                                                </div>
                                            ))}
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Reset Password */}
            <Dialog open={resetOpen} onOpenChange={(open) => { setResetOpen(open); if (!open) setNewPassword(''); }}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset Password</DialogTitle>
                        <DialogDescription>
                            Atur password baru untuk <span className="font-medium">{teacher.full_name}</span>.
                            Guru akan diminta menggantinya sendiri saat login berikutnya.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="reset-password">Password Baru *</Label>
                        <Input
                            id="reset-password"
                            type="password"
                            placeholder="Minimal 8 karakter"
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                        />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setResetOpen(false)} disabled={resetting}>
                            Batal
                        </Button>
                        <Button onClick={handleResetPassword} disabled={resetting}>
                            {resetting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <KeyRound className="mr-2 h-4 w-4" />
                            Reset Password
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Nonaktifkan */}
            <AlertDialog open={confirmDeactivate} onOpenChange={setConfirmDeactivate}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Nonaktifkan Guru</AlertDialogTitle>
                        <AlertDialogDescription>
                            Status <span className="font-medium">{teacher.full_name}</span> akan
                            diubah menjadi Tidak Aktif. Akun login tidak dihapus dan bisa
                            diaktifkan kembali kapan saja lewat Edit.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deactivating}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDeactivate} disabled={deactivating}>
                            {deactivating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Nonaktifkan
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
