import { Head } from '@inertiajs/react';
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
import { Calendar, RefreshCw, CheckCircle2, XCircle, AlertCircle, Clock } from 'lucide-react';
import { teacherAttendanceApi } from '@/services/attendance';
import type { TeacherDailyRecord, TeacherAttendanceStatus } from '@/types/attendance';

const statusOptions: { value: TeacherAttendanceStatus; label: string }[] = [
    { value: 'present', label: 'Hadir' },
    { value: 'absent', label: 'Tidak Hadir' },
    { value: 'late', label: 'Terlambat' },
    { value: 'sick', label: 'Sakit' },
    { value: 'permitted', label: 'Izin' },
    { value: 'on_duty', label: 'Dinas Luar' },
    { value: 'work_from_home', label: 'WFH' },
];

const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
        present: 'default',
        late: 'secondary',
        sick: 'secondary',
        permitted: 'outline',
        absent: 'destructive',
        on_duty: 'outline',
        work_from_home: 'outline',
        belum_scan: 'outline',
    };
    const labels: Record<string, string> = {
        present: 'Hadir',
        absent: 'Tidak Hadir',
        late: 'Terlambat',
        sick: 'Sakit',
        permitted: 'Izin',
        on_duty: 'Dinas Luar',
        work_from_home: 'WFH',
        belum_scan: 'Belum Scan',
    };
    return <Badge variant={variants[status] || 'outline'}>{labels[status] || status}</Badge>;
};

export default function TeacherAttendanceIndex() {
    const [date, setDate] = useState(new Date().toISOString().split('T')[0]);
    const [teachers, setTeachers] = useState<TeacherDailyRecord[]>([]);
    const [summary, setSummary] = useState<Record<string, number>>({});
    const [loading, setLoading] = useState(false);
    const [editingTeacher, setEditingTeacher] = useState<TeacherDailyRecord | null>(null);
    const [editForm, setEditForm] = useState({ status: '' as TeacherAttendanceStatus, notes: '' });

    const fetchAttendance = async () => {
        if (!date) return;

        setLoading(true);
        try {
            const response = await teacherAttendanceApi.daily(date);
            if (response.data.data) {
                setTeachers(response.data.data.teachers);
                setSummary(response.data.data.summary);
            }
        } catch (error) {
            toast.error('Gagal memuat data absensi guru');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (date) {
            fetchAttendance();
        }
    }, [date]);

    const handleEditSave = async () => {
        if (!editingTeacher || !editingTeacher.attendance_id) {
            toast.error('Tidak dapat mengedit - guru belum memiliki record absensi');
            return;
        }

        try {
            await teacherAttendanceApi.update(editingTeacher.attendance_id, {
                status: editForm.status,
                notes: editForm.notes,
            });
            toast.success('Absensi guru berhasil diperbarui');
            setEditingTeacher(null);
            fetchAttendance();
        } catch (error) {
            toast.error('Gagal memperbarui absensi guru');
        }
    };

    const openEditDialog = (teacher: TeacherDailyRecord) => {
        setEditingTeacher(teacher);
        setEditForm({
            status: teacher.status as TeacherAttendanceStatus,
            notes: teacher.notes || '',
        });
    };

    return (
        <MainLayout title="Absensi Guru">
            <Head title="Absensi Guru" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Absensi Guru</h1>
                        <p className="text-muted-foreground">
                            Kelola kehadiran harian guru dan karyawan
                        </p>
                    </div>
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
                            <div className="flex items-end">
                                <Button onClick={fetchAttendance} variant="outline" disabled={loading}>
                                    <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Summary */}
                {teachers.length > 0 && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.present || 0}</div>
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
                                        <div className="text-2xl font-bold">{(summary.sick || 0) + (summary.permitted || 0)}</div>
                                        <div className="text-sm text-muted-foreground">Sakit/Izin</div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-4">
                                <div className="flex items-center gap-2">
                                    <XCircle className="h-5 w-5 text-red-600" />
                                    <div>
                                        <div className="text-2xl font-bold">{summary.absent || 0}</div>
                                        <div className="text-sm text-muted-foreground">Tidak Hadir</div>
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
                        <CardTitle>Daftar Guru</CardTitle>
                        <CardDescription>
                            {teachers.length > 0
                                ? `${teachers.length} guru/karyawan`
                                : 'Pilih tanggal untuk melihat data absensi'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : teachers.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data guru
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[60px]">No</TableHead>
                                            <TableHead>NIP</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Jam Masuk</TableHead>
                                            <TableHead>Jam Pulang</TableHead>
                                            <TableHead>Keterlambatan</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {teachers.map((teacher, index) => (
                                            <TableRow key={teacher.user_id}>
                                                <TableCell>{index + 1}</TableCell>
                                                <TableCell className="font-medium">{teacher.nip || '-'}</TableCell>
                                                <TableCell>{teacher.name}</TableCell>
                                                <TableCell>{getStatusBadge(teacher.status)}</TableCell>
                                                <TableCell>{teacher.check_in_time || '-'}</TableCell>
                                                <TableCell>{teacher.check_out_time || '-'}</TableCell>
                                                <TableCell>
                                                    {teacher.late_minutes > 0 ? (
                                                        <Badge variant="destructive">
                                                            {teacher.late_minutes} menit
                                                        </Badge>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEditDialog(teacher)}
                                                        disabled={!teacher.attendance_id}
                                                    >
                                                        Edit
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
            <Dialog open={!!editingTeacher} onOpenChange={() => setEditingTeacher(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Absensi Guru</DialogTitle>
                        <DialogDescription>
                            {editingTeacher?.name} - {editingTeacher?.nip || 'Tanpa NIP'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Status</Label>
                            <Select
                                value={editForm.status}
                                onValueChange={(value) => setEditForm(prev => ({ ...prev, status: value as TeacherAttendanceStatus }))}
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
                        <Button variant="outline" onClick={() => setEditingTeacher(null)}>
                            Batal
                        </Button>
                        <Button onClick={handleEditSave}>Simpan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
