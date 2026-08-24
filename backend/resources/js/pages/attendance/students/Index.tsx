import { Head, Link } from '@inertiajs/react';
import { useState, useEffect, useMemo } from 'react';
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
import { Calendar, Save, RefreshCw, CheckCircle2, XCircle, AlertCircle, Clock, Pencil, Send, ScanLine, Search, ListChecks } from 'lucide-react';
import { studentAttendanceApi, attendanceSettingsApi } from '@/services/attendance';
import type { DailyAttendanceRecord, AttendanceStatus } from '@/types/attendance';
import type { ClassRoom } from '@/types';
import { usePermissions } from '@/hooks/usePermissions';

interface Props {
    classrooms: ClassRoom[];
    initialDate?: string;
    initialClassroom?: string;
}

const statusOptions: { value: AttendanceStatus; label: string; color: string }[] = [
    { value: 'hadir', label: 'Hadir', color: 'bg-green-500' },
    { value: 'sakit', label: 'Sakit', color: 'bg-yellow-500' },
    { value: 'izin', label: 'Izin', color: 'bg-blue-500' },
    { value: 'alfa', label: 'Alfa', color: 'bg-red-500' },
    { value: 'tanpa_keterangan', label: 'Tanpa Keterangan', color: 'bg-orange-500' },
];

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

/** "HH:mm" atau "HH:mm:ss" -> menit sejak 00:00 */
const timeToMinutes = (time: string): number => {
    const [h, m] = time.split(':').map(Number);
    return h * 60 + m;
};

const minutesToTime = (totalMinutes: number): string => {
    const normalized = ((totalMinutes % (24 * 60)) + 24 * 60) % (24 * 60);
    const hh = Math.floor(normalized / 60);
    const mm = normalized % 60;
    return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`;
};

const nowTimeString = (): string => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
};

interface AttendanceTimeSettings {
    checkInStart: string; // jam masuk resmi, "HH:mm"
    checkInDeadline: string; // check_in_end + toleransi, "HH:mm" — lewat ini dianggap terlambat
}

export default function StudentAttendanceIndex({ classrooms, initialDate, initialClassroom }: Props) {
    const { can } = usePermissions();
    const [date, setDate] = useState(initialDate || new Date().toISOString().split('T')[0]);
    const [classroomId, setClassroomId] = useState(initialClassroom || '');
    const [students, setStudents] = useState<DailyAttendanceRecord[]>([]);
    const [summary, setSummary] = useState<Record<string, number>>({});
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [sending, setSending] = useState(false);
    const [search, setSearch] = useState('');
    const [editingStudent, setEditingStudent] = useState<DailyAttendanceRecord | null>(null);
    const [editForm, setEditForm] = useState({ status: '' as AttendanceStatus, notes: '' });
    const [timeSettings, setTimeSettings] = useState<AttendanceTimeSettings | null>(null);

    // Modal isi jam masuk manual — dipicu ketika menandai Hadir tapi sudah
    // lewat batas jam masuk (check_in_end + toleransi keterlambatan).
    const [lateEntryOpen, setLateEntryOpen] = useState(false);
    const [lateEntryStudentId, setLateEntryStudentId] = useState<string | null>(null);
    const [lateEntryTime, setLateEntryTime] = useState('');
    const [lateEntryError, setLateEntryError] = useState('');

    useEffect(() => {
        attendanceSettingsApi
            .get()
            .then((response) => {
                const attendance = response.data.data?.attendance;
                if (!attendance) return;
                const checkInStart = attendance.check_in_start.slice(0, 5);
                const checkInDeadline = minutesToTime(
                    timeToMinutes(attendance.check_in_end.slice(0, 5)) + attendance.late_tolerance_minutes
                );
                setTimeSettings({ checkInStart, checkInDeadline });
            })
            .catch(() => {
                // Pengaturan jam masuk gagal dimuat — auto-isi jam masuk & deteksi
                // terlambat dilewati, status tetap bisa diubah manual seperti biasa.
            });
    }, []);

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

    const applyPresentStatus = (studentId: string, checkInTime: string) => {
        setStudents(prev =>
            prev.map(s =>
                s.student_id === studentId
                    ? { ...s, status: 'hadir' as AttendanceStatus, status_label: 'Hadir', check_in_time: checkInTime, menit_keterlambatan: 0 }
                    : s
            )
        );
    };

    const handleStatusChange = (studentId: string, status: AttendanceStatus) => {
        if (status === 'hadir') {
            if (timeSettings && nowTimeString() > timeSettings.checkInDeadline) {
                // Sudah lewat batas jam masuk — jangan asumsikan tepat waktu,
                // minta admin mengisi jam masuk sebenarnya lewat modal.
                setLateEntryStudentId(studentId);
                setLateEntryTime(nowTimeString());
                setLateEntryError('');
                setLateEntryOpen(true);
                return;
            }

            applyPresentStatus(studentId, timeSettings?.checkInStart ?? nowTimeString());
            return;
        }

        setStudents(prev =>
            prev.map(s =>
                s.student_id === studentId
                    ? { ...s, status, status_label: statusOptions.find(o => o.value === status)?.label || status }
                    : s
            )
        );
    };

    const confirmLateEntry = () => {
        if (!lateEntryStudentId) return;

        if (timeSettings && lateEntryTime < timeSettings.checkInStart) {
            setLateEntryError(`Jam masuk tidak boleh kurang dari jam masuk resmi (${timeSettings.checkInStart}).`);
            return;
        }

        applyPresentStatus(lateEntryStudentId, lateEntryTime);
        setLateEntryOpen(false);
        setLateEntryStudentId(null);
    };

    const cancelLateEntry = () => {
        setLateEntryOpen(false);
        setLateEntryStudentId(null);
        setLateEntryError('');
    };

    const handleMarkAllPresent = () => {
        const checkInStart = timeSettings?.checkInStart ?? nowTimeString();
        setStudents(prev =>
            prev.map(s => ({
                ...s,
                status: 'hadir' as AttendanceStatus,
                status_label: 'Hadir',
                check_in_time: checkInStart,
                menit_keterlambatan: 0,
            }))
        );
        toast.info('Semua siswa ditandai Hadir (jam masuk sesuai jam masuk resmi) — klik "Simpan Semua" untuk menyimpan.');
    };

    const handleSaveAll = async () => {
        if (!classroomId || !date) return;

        setSaving(true);
        try {
            const attendances = students.map(s => ({
                student_id: s.student_id,
                status: s.status,
                check_in_time: s.status === 'hadir' ? (s.check_in_time || undefined) : undefined,
                notes: s.notes || undefined,
            }));

            await studentAttendanceApi.storeBulk({
                classroom_id: classroomId,
                date,
                attendances,
            });

            toast.success('Absensi berhasil disimpan');
            fetchAttendance();
        } catch (error: unknown) {
            const message = (error as { response?: { data?: { message?: string } } })
                ?.response?.data?.message;
            toast.error(message || 'Gagal menyimpan absensi');
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

    const filteredStudents = useMemo(() => {
        const query = search.trim().toLowerCase();
        if (!query) return students;
        return students.filter(
            (s) => s.name.toLowerCase().includes(query) || s.nis.toLowerCase().includes(query)
        );
    }, [students, search]);

    const lateEntryStudent = students.find(s => s.student_id === lateEntryStudentId) || null;

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
                    {can('attendance.scanner-operate') && (
                        <Button asChild variant="outline">
                            <Link href="/scanner">
                                <ScanLine className="mr-2 h-4 w-4" />
                                Buka Scanner
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4">
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
                                <Select value={classroomId} onValueChange={setClassroomId}>
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
                {students.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-5">
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.hadir || 0}</div>
                                        <div className="text-sm text-muted-foreground">Hadir</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="h-5 w-5 text-yellow-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.sakit || 0}</div>
                                        <div className="text-sm text-muted-foreground">Sakit</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-5 w-5 text-blue-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.izin || 0}</div>
                                        <div className="text-sm text-muted-foreground">Izin</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <XCircle className="h-5 w-5 text-red-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.alfa || 0}</div>
                                        <div className="text-sm text-muted-foreground">Alfa</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <Clock className="h-5 w-5 text-gray-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.belum_scan || 0}</div>
                                        <div className="text-sm text-muted-foreground">Belum Scan</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Table */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div>
                                <CardTitle>Daftar Siswa</CardTitle>
                                <CardDescription>
                                    {students.length > 0
                                        ? `${filteredStudents.length} dari ${students.length} siswa di kelas ini`
                                        : 'Pilih kelas untuk melihat daftar siswa'}
                                </CardDescription>
                            </div>
                            {students.length > 0 && (
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="relative w-[220px]">
                                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                        <Input
                                            placeholder="Cari nama atau NIS..."
                                            value={search}
                                            onChange={(e) => setSearch(e.target.value)}
                                            className="pl-8"
                                        />
                                    </div>
                                    <Button onClick={handleMarkAllPresent} variant="outline" size="sm">
                                        <ListChecks className="mr-2 h-4 w-4" />
                                        Ceklis Hadir Semua
                                    </Button>
                                </div>
                            )}
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
                                Tidak ada siswa yang cocok dengan pencarian "{search}"
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
                                            <TableHead>Keterlambatan</TableHead>
                                            <TableHead className="w-[200px]">Ubah Status</TableHead>
                                            <TableHead className="w-[60px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {filteredStudents.map((student, index) => (
                                            <TableRow key={student.student_id}>
                                                <TableCell>{student.student_number_in_class || index + 1}</TableCell>
                                                <TableCell className="font-medium">{student.nis}</TableCell>
                                                <TableCell>{student.name}</TableCell>
                                                <TableCell>{getStatusBadge(student.status)}</TableCell>
                                                <TableCell>{student.check_in_time || '-'}</TableCell>
                                                <TableCell>{student.check_out_time || '-'}</TableCell>
                                                <TableCell>
                                                    {student.menit_keterlambatan > 0 ? (
                                                        <Badge variant="destructive">
                                                            {student.menit_keterlambatan} menit
                                                        </Badge>
                                                    ) : (
                                                        '-'
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

            {/* Modal isi jam masuk (terlambat) */}
            <Dialog open={lateEntryOpen} onOpenChange={(open) => !open && cancelLateEntry()}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Isi Jam Masuk</DialogTitle>
                        <DialogDescription>
                            {lateEntryStudent?.name} sudah melewati batas jam masuk
                            {timeSettings ? ` (${timeSettings.checkInDeadline})` : ''}. Masukkan jam masuk sebenarnya.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label>Jam Masuk</Label>
                        <Input
                            type="time"
                            value={lateEntryTime}
                            min={timeSettings?.checkInStart}
                            onChange={(e) => {
                                setLateEntryTime(e.target.value);
                                setLateEntryError('');
                            }}
                        />
                        {timeSettings && (
                            <p className="text-sm text-muted-foreground">
                                Tidak boleh kurang dari jam masuk resmi ({timeSettings.checkInStart}).
                            </p>
                        )}
                        {lateEntryError && (
                            <p className="text-sm text-destructive">{lateEntryError}</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={cancelLateEntry}>
                            Batal
                        </Button>
                        <Button onClick={confirmLateEntry}>Simpan Jam Masuk</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
