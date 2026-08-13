import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import { Calendar, Save, RefreshCw, CheckCircle2, XCircle, AlertCircle, Clock, Pencil, Send, ScanLine, Search, CheckCheck } from 'lucide-react';
import { cn } from '@/lib/utils';
import { studentAttendanceApi } from '@/services/attendance';
import type { DailyAttendanceRecord, AttendanceStatus } from '@/types/attendance';
import type { ClassRoom } from '@/types';

interface Props {
    classrooms: ClassRoom[];
    initialDate?: string;
    initialClassroom?: string;
}

/**
 * Opsi yang boleh dikirim ke server. "Belum Scan" sengaja TIDAK ada di sini:
 * itu status virtual (AttendanceStatus::storable() menolaknya), hanya dipakai
 * sebagai badge baca-saja untuk siswa yang belum punya baris absensi.
 */
const statusOptions: { value: AttendanceStatus; label: string; color: string }[] = [
    { value: 'hadir', label: 'Hadir', color: 'bg-green-500' },
    { value: 'sakit', label: 'Sakit', color: 'bg-yellow-500' },
    { value: 'izin', label: 'Izin', color: 'bg-blue-500' },
    { value: 'alfa', label: 'Alfa', color: 'bg-red-500' },
    { value: 'tanpa_keterangan', label: 'Tanpa Keterangan', color: 'bg-orange-500' },
];

const summaryCards: {
    key: 'hadir' | 'sakit' | 'izin' | 'alfa' | 'belum_scan';
    label: string;
    icon: React.ElementType;
    iconClass: string;
    boxClass: string;
}[] = [
    { key: 'hadir', label: 'Hadir', icon: CheckCircle2, iconClass: 'text-green-600 dark:text-green-500', boxClass: 'bg-green-500/10' },
    { key: 'sakit', label: 'Sakit', icon: AlertCircle, iconClass: 'text-yellow-600 dark:text-yellow-500', boxClass: 'bg-yellow-500/10' },
    { key: 'izin', label: 'Izin', icon: Clock, iconClass: 'text-blue-600 dark:text-blue-500', boxClass: 'bg-blue-500/10' },
    { key: 'alfa', label: 'Alfa', icon: XCircle, iconClass: 'text-red-600 dark:text-red-500', boxClass: 'bg-red-500/10' },
    { key: 'belum_scan', label: 'Belum Scan', icon: Clock, iconClass: 'text-muted-foreground', boxClass: 'bg-muted' },
];

/** Label kecil di atas judul kartu (pola "kicker" dari mockup). */
const kickerClass = 'text-xs font-semibold uppercase tracking-wider text-muted-foreground';

/**
 * "2026-08-13" → "13 Agustus 2026". Sengaja mem-parse per bagian, bukan
 * `new Date(iso)`, supaya tidak tergeser satu hari oleh interpretasi UTC.
 */
const formatDateLabel = (iso: string) => {
    const [year, month, day] = iso.split('-').map(Number);
    if (!year || !month || !day) return iso;
    return new Date(year, month - 1, day).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
};

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        hadir: 'default',
        sakit: 'secondary',
        izin: 'outline',
        alfa: 'destructive',
        tanpa_keterangan: 'destructive',
        belum_scan: 'outline',
    };
    const labels: Record<string, string> = {
        hadir: 'Hadir',
        sakit: 'Sakit',
        izin: 'Izin',
        alfa: 'Alfa',
        tanpa_keterangan: 'Tanpa Keterangan',
        belum_scan: 'Belum Scan',
    };
    return <Badge variant={variants[status] || 'outline'}>{labels[status] || status}</Badge>;
};

export default function StudentAttendanceIndex({ classrooms, initialDate, initialClassroom }: Props) {
    const [date, setDate] = useState(initialDate || new Date().toISOString().split('T')[0]);
    const [classroomId, setClassroomId] = useState(initialClassroom || '');
    const [students, setStudents] = useState<DailyAttendanceRecord[]>([]);
    const [summary, setSummary] = useState<Record<string, number>>({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [sending, setSending] = useState(false);
    const [editingStudent, setEditingStudent] = useState<DailyAttendanceRecord | null>(null);
    const [editForm, setEditForm] = useState({ status: '' as AttendanceStatus, notes: '' });
    const [search, setSearch] = useState('');

    const classroomLabel = classrooms.find((c) => c.id === classroomId)?.name ?? '';

    // Nomor urut dihitung dari daftar utuh supaya tidak ikut bergeser saat dicari.
    const query = search.trim().toLowerCase();
    const filteredStudents = students
        .map((student, index) => ({
            ...student,
            rowNumber: student.student_number_in_class || String(index + 1),
        }))
        .filter((student) => !query || (student.name ?? '').toLowerCase().includes(query));

    const fetchAttendance = async () => {
        if (!classroomId || !date) return;

        setLoading(true);
        try {
            const response = await studentAttendanceApi.daily(classroomId, date);
            if (response.data.data) {
                setStudents(response.data.data.students);
                setSummary(response.data.data.summary);
            }
        } catch (error) {
            toast.error('Gagal memuat data absensi');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (classroomId && date) {
            fetchAttendance();
        }
    }, [classroomId, date]);

    const handleStatusChange = (studentId: string, status: AttendanceStatus) => {
        setStudents(prev =>
            prev.map(s =>
                s.student_id === studentId
                    ? { ...s, status, status_label: statusOptions.find(o => o.value === status)?.label || status }
                    : s
            )
        );
    };

    /**
     * Menandai Hadir hanya untuk baris yang sedang tampil DAN masih "Belum
     * Scan" — status yang sudah dicatat (Sakit/Izin/Alfa) tidak ditimpa.
     * Perubahan hanya di layar; baru tersimpan lewat "Simpan Semua".
     */
    const handleMarkAllPresent = () => {
        const targetIds = new Set(
            filteredStudents.filter((s) => s.status === 'belum_scan').map((s) => s.student_id)
        );

        if (targetIds.size === 0) {
            toast.info('Tidak ada siswa berstatus "Belum Scan" pada daftar yang tampil');
            return;
        }

        setStudents((prev) =>
            prev.map((s) =>
                targetIds.has(s.student_id) ? { ...s, status: 'hadir' as AttendanceStatus, status_label: 'Hadir' } : s
            )
        );

        const skipped = filteredStudents.length - targetIds.size;
        toast.success(
            `${targetIds.size} siswa ditandai Hadir` +
                (skipped > 0 ? ` · ${skipped} dilewati karena sudah punya status` : '') +
                '. Klik "Simpan Semua" untuk menyimpan.'
        );
    };

    const handleSaveAll = async () => {
        if (!classroomId || !date) return;

        setSaving(true);
        try {
            const attendances = students.map(s => ({
                student_id: s.student_id,
                status: s.status,
                notes: s.notes || undefined,
            }));

            await studentAttendanceApi.storeBulk({
                classroom_id: classroomId,
                date,
                attendances,
            });

            toast.success('Absensi berhasil disimpan');
            fetchAttendance();
        } catch (error) {
            toast.error('Gagal menyimpan absensi');
        } finally {
            setSaving(false);
        }
    };

    const handleNotifyDaily = async () => {
        if (!classroomId || !date) return;

        if (!confirm('Kirim notifikasi rekap absensi hari ini ke wali murid satu kelas?')) {
            return;
        }

        setSending(true);
        try {
            const response = await studentAttendanceApi.notifyDaily({
                classroom_id: classroomId,
                date,
            });
            toast.success(response.data.message || 'Notifikasi rekap sedang dikirim');
        } catch (error: unknown) {
            const message = (error as { response?: { data?: { message?: string } } })
                ?.response?.data?.message;
            toast.error(message || 'Gagal mengirim notifikasi rekap');
        } finally {
            setSending(false);
        }
    };

    const handleEditSave = async () => {
        if (!editingStudent || !editingStudent.attendance_id) {
            toast.error('Tidak dapat mengedit - siswa belum memiliki record absensi');
            return;
        }

        try {
            await studentAttendanceApi.update(editingStudent.attendance_id, {
                status: editForm.status,
                notes: editForm.notes,
            });
            toast.success('Absensi berhasil diperbarui');
            setEditingStudent(null);
            fetchAttendance();
        } catch (error) {
            toast.error('Gagal memperbarui absensi');
        }
    };

    const openEditDialog = (student: DailyAttendanceRecord) => {
        setEditingStudent(student);
        setEditForm({
            status: student.status as AttendanceStatus,
            notes: student.notes || '',
        });
    };

    return (
        <MainLayout title="Absensi Siswa">
            <Head title="Absensi Siswa" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Absensi Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola kehadiran harian siswa per kelas
                        </p>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/scanner">
                            <ScanLine className="mr-2 h-4 w-4" />
                            Buka Scanner
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className={kickerClass}>Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="w-[200px]">
                                <Label>Tanggal</Label>
                                <div className="relative">
                                    <Calendar className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="date"
                                        value={date}
                                        onChange={(e) => setDate(e.target.value)}
                                        className="pl-8"
                                    />
                                </div>
                            </div>
                            <div className="w-[250px]">
                                <Label>Kelas</Label>
                                <Select
                                    value={classroomId}
                                    onValueChange={(value) => {
                                        setClassroomId(value);
                                        // Pencarian dari kelas sebelumnya tidak relevan lagi —
                                        // kalau dibiarkan, daftar kelas baru bisa tampak kosong.
                                        setSearch('');
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classrooms.map((c) => (
                                            <SelectItem key={c.id} value={c.id}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={fetchAttendance} variant="outline" disabled={loading}>
                                    <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                                <Button onClick={handleSaveAll} disabled={saving || students.length === 0}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {saving ? 'Menyimpan...' : 'Simpan Semua'}
                                </Button>
                                <Button
                                    onClick={handleNotifyDaily}
                                    variant="outline"
                                    disabled={sending || !classroomId || students.length === 0}
                                >
                                    <Send className="mr-2 h-4 w-4" />
                                    {sending ? 'Mengirim...' : 'Kirim Notifikasi'}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    {summaryCards.map((card) => (
                        <Card key={card.key}>
                            <CardContent className="flex items-center justify-between gap-3 p-4">
                                <div className="min-w-0">
                                    <div className={kickerClass}>{card.label}</div>
                                    <div className="mt-1.5 text-3xl font-bold leading-none">
                                        {summary[card.key] || 0}
                                    </div>
                                </div>
                                <div
                                    className={cn(
                                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg',
                                        card.boxClass
                                    )}
                                >
                                    <card.icon className={cn('h-5 w-5', card.iconClass)} />
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Table */}
                <Card>
                    <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between sm:space-y-0">
                        <div className="space-y-1">
                            <div className={kickerClass}>Daftar Siswa</div>
                            <CardTitle className="text-lg">
                                {classroomLabel ? `Kelas ${classroomLabel}` : 'Belum ada kelas dipilih'}
                            </CardTitle>
                            <CardDescription>
                                {students.length > 0
                                    ? `${filteredStudents.length} dari ${students.length} siswa · ${formatDateLabel(date)}`
                                    : 'Pilih kelas untuk melihat daftar siswa'}
                            </CardDescription>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <Search className="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Cari nama siswa..."
                                    disabled={students.length === 0}
                                    className="w-full pl-8 sm:w-[220px]"
                                />
                            </div>
                            <Button
                                variant="outline"
                                onClick={handleMarkAllPresent}
                                disabled={students.length === 0}
                            >
                                <CheckCheck className="mr-2 h-4 w-4" />
                                Ceklis Hadir Semua
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : students.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                {classroomId ? 'Tidak ada siswa di kelas ini' : 'Pilih kelas terlebih dahulu'}
                            </div>
                        ) : filteredStudents.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada siswa yang cocok dengan pencarian "{search.trim()}"
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[60px]">No</TableHead>
                                            <TableHead>NIS</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Jam Masuk</TableHead>
                                            <TableHead>Jam Pulang</TableHead>
                                            <TableHead>Terlambat</TableHead>
                                            <TableHead className="w-[200px]">Ubah Status</TableHead>
                                            <TableHead className="w-[60px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredStudents.map((student) => (
                                            <TableRow key={student.student_id}>
                                                <TableCell>{student.rowNumber}</TableCell>
                                                <TableCell>{student.nis}</TableCell>
                                                <TableCell className="font-medium">{student.name}</TableCell>
                                                <TableCell>{getStatusBadge(student.status)}</TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {student.check_in_time || '—'}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {student.check_out_time || '—'}
                                                </TableCell>
                                                <TableCell>
                                                    {student.menit_keterlambatan > 0 ? (
                                                        <Badge variant="destructive">
                                                            {student.menit_keterlambatan} menit
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Select
                                                        value={student.status}
                                                        onValueChange={(value) =>
                                                            handleStatusChange(student.student_id, value as AttendanceStatus)
                                                        }
                                                    >
                                                        <SelectTrigger className="w-[150px]">
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {statusOptions.map((option) => (
                                                                <SelectItem key={option.value} value={option.value}>
                                                                    {option.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => openEditDialog(student)}
                                                        title="Edit absensi & catatan"
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Edit Dialog */}
            <Dialog open={!!editingStudent} onOpenChange={() => setEditingStudent(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Absensi</DialogTitle>
                        <DialogDescription>
                            {editingStudent?.name} - {editingStudent?.nis}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Status</Label>
                            <Select
                                value={editForm.status}
                                onValueChange={(value) => setEditForm(prev => ({ ...prev, status: value as AttendanceStatus }))}
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((option) => (
                                        <SelectItem key={option.value} value={option.value}>
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Catatan</Label>
                            <Textarea
                                value={editForm.notes}
                                onChange={(e) => setEditForm(prev => ({ ...prev, notes: e.target.value }))}
                                placeholder="Tambahkan catatan..."
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditingStudent(null)}>
                            Batal
                        </Button>
                        <Button onClick={handleEditSave}>Simpan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
