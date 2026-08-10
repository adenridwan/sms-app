import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, useMemo } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Checkbox } from '@/components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { TimeInput24 } from '@/components/ui/time-input-24';
import { toast } from 'sonner';
import { Plus, Pencil, Trash2, RefreshCw, Settings, ArrowLeft, Coffee, Download, Copy, ChevronDown, AlertTriangle } from 'lucide-react';
import {
    scheduleApi,
    timeSlotsApi,
    academicYearsApi,
    semestersApi,
    classroomsApi,
    subjectsApi,
    teachersApi,
} from '@/services/api';
import { subjectColorClasses } from '@/lib/subjectColors';
import type { Schedule, TimeSlot, AcademicYear, Semester, Classroom, Subject, Teacher, ScheduleCopyResult } from '@/types';

function downloadBlob(data: Blob, filename: string) {
    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

const DAYS = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
];

interface ScheduleForm {
    subject_id: string;
    teacher_id: string;
    room: string;
    is_active: boolean;
}

const emptyScheduleForm: ScheduleForm = {
    subject_id: '',
    teacher_id: '',
    room: '',
    is_active: true,
};

interface TimeSlotForm {
    name: string;
    start_time: string;
    end_time: string;
    order: string;
    is_break: boolean;
}

const emptyTimeSlotForm: TimeSlotForm = {
    name: '',
    start_time: '',
    end_time: '',
    order: '0',
    is_break: false,
};

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
        const firstFieldError = Object.values(response?.data?.errors ?? {})[0]?.[0];
        if (firstFieldError) return firstFieldError;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

export default function AcademicSchedules() {
    const [academicYears, setAcademicYears] = useState<AcademicYear[]>([]);
    const [semesters, setSemesters] = useState<Semester[]>([]);
    const [classrooms, setClassrooms] = useState<Classroom[]>([]);
    const [subjects, setSubjects] = useState<Subject[]>([]);
    const [teachers, setTeachers] = useState<Teacher[]>([]);
    const [timeSlots, setTimeSlots] = useState<TimeSlot[]>([]);

    const [yearId, setYearId] = useState('');
    const [semesterId, setSemesterId] = useState('');
    const [classroomId, setClassroomId] = useState('');

    const [schedules, setSchedules] = useState<Schedule[]>([]);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);

    // day_of_week 1-6 = Senin-Sabtu, sama seperti JS Date#getDay() (0=Minggu
    // tidak pernah cocok karena sekolah tidak masuk Minggu — tidak perlu
    // ditangani khusus).
    const todayDayOfWeek = new Date().getDay();

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingSchedule, setEditingSchedule] = useState<Schedule | null>(null);
    const [formContext, setFormContext] = useState<{ dayOfWeek: number; timeSlot: TimeSlot } | null>(null);
    const [form, setForm] = useState<ScheduleForm>(emptyScheduleForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingSchedule, setDeletingSchedule] = useState<Schedule | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Kelola Jam Pelajaran
    const [tsManagerOpen, setTsManagerOpen] = useState(false);
    const [tsView, setTsView] = useState<'list' | 'form'>('list');
    const [tsEditing, setTsEditing] = useState<TimeSlot | null>(null);
    const [tsForm, setTsForm] = useState<TimeSlotForm>(emptyTimeSlotForm);
    const [tsSaving, setTsSaving] = useState(false);
    const [tsDeleting, setTsDeleting] = useState<TimeSlot | null>(null);

    // Salin dari Kelas Lain
    const [copyClassroomOpen, setCopyClassroomOpen] = useState(false);
    const [copySourceYearId, setCopySourceYearId] = useState('');
    const [copySourceSemesters, setCopySourceSemesters] = useState<Semester[]>([]);
    const [copySourceSemesterId, setCopySourceSemesterId] = useState('');
    const [copySourceClassrooms, setCopySourceClassrooms] = useState<Classroom[]>([]);
    const [copySourceClassroomId, setCopySourceClassroomId] = useState('');
    const [copyClassroomOverwrite, setCopyClassroomOverwrite] = useState(false);
    const [copyingClassroom, setCopyingClassroom] = useState(false);
    const [copyClassroomResult, setCopyClassroomResult] = useState<ScheduleCopyResult | null>(null);

    // Salin dari Hari Lain
    const [copyDayOpen, setCopyDayOpen] = useState(false);
    const [copyDaySource, setCopyDaySource] = useState<number | null>(null);
    const [copyDayTargets, setCopyDayTargets] = useState<number[]>([]);
    const [copyDayOverwrite, setCopyDayOverwrite] = useState(false);
    const [copyingDay, setCopyingDay] = useState(false);
    const [copyDayResult, setCopyDayResult] = useState<ScheduleCopyResult | null>(null);

    // ---------- reference data ----------

    const fetchTimeSlots = useCallback(async () => {
        try {
            const response = await timeSlotsApi.list({ per_page: 100 });
            setTimeSlots(response.data.data.data ?? []);
        } catch {
            toast.error('Gagal memuat jam pelajaran');
        }
    }, []);

    useEffect(() => {
        (async () => {
            try {
                const [yearsRes, subjectsRes, teachersRes] = await Promise.all([
                    academicYearsApi.list({ per_page: 100 }),
                    subjectsApi.list({ per_page: 100, is_active: true }),
                    teachersApi.list({ per_page: 200, status: 'active' }),
                ]);
                const years = yearsRes.data.data.data ?? [];
                setAcademicYears(years);
                setSubjects(subjectsRes.data.data.data ?? []);
                setTeachers(teachersRes.data.data.data ?? []);

                const activeYear = years.find((y) => y.is_active) ?? years[0];
                if (activeYear) setYearId(activeYear.id);
            } catch {
                toast.error('Gagal memuat data referensi');
            }
        })();
        fetchTimeSlots();
    }, [fetchTimeSlots]);

    useEffect(() => {
        if (!yearId) {
            setSemesters([]);
            setClassrooms([]);
            return;
        }
        (async () => {
            try {
                const [semestersRes, classroomsRes] = await Promise.all([
                    semestersApi.list({ academic_year_id: yearId, per_page: 100 }),
                    classroomsApi.list({ academic_year_id: yearId, per_page: 100, is_active: true }),
                ]);
                const semesterList = semestersRes.data.data.data ?? [];
                setSemesters(semesterList);
                setClassrooms(classroomsRes.data.data.data ?? []);

                const activeSemester = semesterList.find((s) => s.is_active) ?? semesterList[0];
                setSemesterId(activeSemester?.id ?? '');
                setClassroomId('');
            } catch {
                toast.error('Gagal memuat semester/kelas');
            }
        })();
    }, [yearId]);

    const fetchSchedules = useCallback(async () => {
        if (!classroomId || !semesterId) {
            setSchedules([]);
            return;
        }
        setLoading(true);
        try {
            const response = await scheduleApi.list({ classroom_id: classroomId, semester_id: semesterId, per_page: 200 });
            setSchedules(response.data.data.data ?? []);
        } catch {
            toast.error('Gagal memuat jadwal');
        } finally {
            setLoading(false);
        }
    }, [classroomId, semesterId]);

    useEffect(() => {
        fetchSchedules();
    }, [fetchSchedules]);

    const scheduleMap = useMemo(() => {
        const map = new Map<string, Schedule>();
        schedules.forEach((s) => map.set(`${s.day_of_week}-${s.time_slot_id}`, s));
        return map;
    }, [schedules]);

    // ---------- create/edit jadwal ----------

    const openCreate = (dayOfWeek: number, timeSlot: TimeSlot) => {
        setEditingSchedule(null);
        setFormContext({ dayOfWeek, timeSlot });
        setForm(emptyScheduleForm);
        setFormOpen(true);
    };

    const openEdit = (schedule: Schedule) => {
        const timeSlot = timeSlots.find((t) => t.id === schedule.time_slot_id) ?? schedule.time_slot as TimeSlot;
        setEditingSchedule(schedule);
        setFormContext({ dayOfWeek: schedule.day_of_week, timeSlot });
        setForm({
            subject_id: schedule.subject_id,
            teacher_id: schedule.teacher_id,
            room: schedule.room ?? '',
            is_active: schedule.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!formContext) return;
        if (!form.subject_id || !form.teacher_id) {
            toast.error('Mata pelajaran dan guru wajib diisi');
            return;
        }

        setSaving(true);
        try {
            if (editingSchedule) {
                await scheduleApi.update(editingSchedule.id, {
                    subject_id: form.subject_id,
                    teacher_id: form.teacher_id,
                    room: form.room.trim() || null,
                    is_active: form.is_active,
                });
                toast.success('Jadwal berhasil diperbarui');
            } else {
                await scheduleApi.create({
                    academic_year_id: yearId,
                    semester_id: semesterId,
                    classroom_id: classroomId,
                    subject_id: form.subject_id,
                    teacher_id: form.teacher_id,
                    time_slot_id: formContext.timeSlot.id,
                    day_of_week: formContext.dayOfWeek,
                    room: form.room.trim() || null,
                    is_active: form.is_active,
                });
                toast.success('Jadwal berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchSchedules();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan jadwal'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingSchedule || deleting) return;
        setDeleting(true);
        try {
            await scheduleApi.delete(deletingSchedule.id);
            toast.success('Jadwal berhasil dihapus');
            setDeletingSchedule(null);
            fetchSchedules();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus jadwal'));
        } finally {
            setDeleting(false);
        }
    };

    const handleExportPdf = async () => {
        if (!classroomId || !semesterId) return;
        setExporting(true);
        try {
            const response = await scheduleApi.exportPdf({ classroom_id: classroomId, semester_id: semesterId });
            const filename = `jadwal-${(selectedClassroom?.name ?? 'kelas').toLowerCase().replace(/\s+/g, '-')}.pdf`;
            downloadBlob(response.data, filename);
        } catch {
            toast.error('Gagal membuat PDF jadwal');
        } finally {
            setExporting(false);
        }
    };

    // ---------- salin dari kelas lain ----------

    const openCopyClassroom = () => {
        setCopySourceYearId(yearId);
        setCopySourceSemesterId('');
        setCopySourceClassroomId('');
        setCopySourceSemesters(semesters);
        setCopySourceClassrooms(classrooms.filter((c) => c.id !== classroomId));
        setCopyClassroomOverwrite(false);
        setCopyClassroomResult(null);
        setCopyClassroomOpen(true);
    };

    useEffect(() => {
        if (!copyClassroomOpen || !copySourceYearId) return;
        setCopySourceSemesterId('');
        setCopySourceClassroomId('');
        (async () => {
            try {
                const [semRes, classRes] = await Promise.all([
                    semestersApi.list({ academic_year_id: copySourceYearId, per_page: 100 }),
                    classroomsApi.list({ academic_year_id: copySourceYearId, per_page: 100, is_active: true }),
                ]);
                setCopySourceSemesters(semRes.data.data.data ?? []);
                setCopySourceClassrooms((classRes.data.data.data ?? []).filter((c) => c.id !== classroomId));
            } catch {
                toast.error('Gagal memuat semester/kelas sumber');
            }
        })();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [copyClassroomOpen, copySourceYearId]);

    const handleCopyFromClassroom = async () => {
        if (!copySourceClassroomId || !copySourceSemesterId) {
            toast.error('Pilih kelas & semester sumber');
            return;
        }
        setCopyingClassroom(true);
        setCopyClassroomResult(null);
        try {
            const response = await scheduleApi.copyFromClassroom({
                source_classroom_id: copySourceClassroomId,
                source_semester_id: copySourceSemesterId,
                target_classroom_id: classroomId,
                target_semester_id: semesterId,
                target_academic_year_id: yearId,
                overwrite: copyClassroomOverwrite,
            });
            setCopyClassroomResult(response.data.data);
            fetchSchedules();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyalin jadwal'));
        } finally {
            setCopyingClassroom(false);
        }
    };

    // ---------- salin dari hari lain ----------

    const openCopyDay = () => {
        setCopyDaySource(null);
        setCopyDayTargets([]);
        setCopyDayOverwrite(false);
        setCopyDayResult(null);
        setCopyDayOpen(true);
    };

    const daysWithSchedule = useMemo(
        () => new Set(schedules.map((s) => s.day_of_week)),
        [schedules],
    );

    const toggleCopyDayTarget = (day: number) => {
        setCopyDayTargets((prev) => (prev.includes(day) ? prev.filter((d) => d !== day) : [...prev, day]));
    };

    const handleCopyFromDay = async () => {
        if (!copyDaySource || copyDayTargets.length === 0) {
            toast.error('Pilih hari sumber dan minimal satu hari tujuan');
            return;
        }
        setCopyingDay(true);
        setCopyDayResult(null);
        try {
            const response = await scheduleApi.copyFromDay({
                academic_year_id: yearId,
                classroom_id: classroomId,
                semester_id: semesterId,
                source_day_of_week: copyDaySource,
                target_days: copyDayTargets,
                overwrite: copyDayOverwrite,
            });
            setCopyDayResult(response.data.data);
            fetchSchedules();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyalin jadwal'));
        } finally {
            setCopyingDay(false);
        }
    };

    // ---------- kelola jam pelajaran ----------

    const openTsManager = () => {
        setTsView('list');
        setTsManagerOpen(true);
    };

    const openTsCreate = () => {
        setTsEditing(null);
        setTsForm({ ...emptyTimeSlotForm, order: String(timeSlots.length + 1) });
        setTsView('form');
    };

    const openTsEdit = (timeSlot: TimeSlot) => {
        setTsEditing(timeSlot);
        setTsForm({
            name: timeSlot.name,
            start_time: timeSlot.start_time,
            end_time: timeSlot.end_time,
            order: String(timeSlot.order),
            is_break: timeSlot.is_break,
        });
        setTsView('form');
    };

    const handleTsSubmit = async () => {
        if (!tsForm.name.trim() || !tsForm.start_time || !tsForm.end_time) {
            toast.error('Nama, jam mulai, dan jam selesai wajib diisi');
            return;
        }

        setTsSaving(true);
        try {
            const payload = {
                name: tsForm.name.trim(),
                start_time: tsForm.start_time,
                end_time: tsForm.end_time,
                order: Number(tsForm.order) || 0,
                is_break: tsForm.is_break,
            };

            if (tsEditing) {
                await timeSlotsApi.update(tsEditing.id, payload);
                toast.success('Jam pelajaran berhasil diperbarui');
            } else {
                await timeSlotsApi.create(payload);
                toast.success('Jam pelajaran berhasil ditambahkan');
            }
            setTsView('list');
            fetchTimeSlots();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan jam pelajaran'));
        } finally {
            setTsSaving(false);
        }
    };

    const handleTsDelete = async () => {
        if (!tsDeleting) return;
        try {
            await timeSlotsApi.delete(tsDeleting.id);
            toast.success('Jam pelajaran berhasil dihapus');
            setTsDeleting(null);
            fetchTimeSlots();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus jam pelajaran'));
            setTsDeleting(null);
        }
    };

    const selectedClassroom = classrooms.find((c) => c.id === classroomId);

    return (
        <MainLayout title="Jadwal">
            <Head title="Akademik - Jadwal" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Jadwal Pelajaran</h1>
                        <p className="text-muted-foreground">Kelola jadwal pelajaran per kelas per semester</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={handleExportPdf}
                            disabled={!classroomId || !semesterId || exporting}
                        >
                            {exporting ? (
                                <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                            ) : (
                                <Download className="mr-2 h-4 w-4" />
                            )}
                            Export PDF
                        </Button>
                        <Button variant="outline" onClick={openTsManager}>
                            <Settings className="mr-2 h-4 w-4" />
                            Kelola Jam Pelajaran
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button disabled={!classroomId || !semesterId}>
                                    <Copy className="mr-2 h-4 w-4" />
                                    Salin Jadwal
                                    <ChevronDown className="ml-2 h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem onClick={openCopyClassroom}>
                                    Salin dari Kelas Lain...
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={openCopyDay} disabled={!schedules.length}>
                                    Salin dari Hari Lain...
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Filter */}
                <Card>
                    <CardContent className="flex flex-wrap gap-4 pt-6">
                        <div className="w-full max-w-[220px] space-y-2">
                            <Label>Tahun Ajaran</Label>
                            <Select value={yearId} onValueChange={setYearId}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>
                                            {year.name}{year.is_active ? ' (Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="w-full max-w-[220px] space-y-2">
                            <Label>Semester</Label>
                            <Select value={semesterId} onValueChange={setSemesterId} disabled={!semesters.length}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((semester) => (
                                        <SelectItem key={semester.id} value={semester.id}>
                                            {semester.name}{semester.is_active ? ' (Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="w-full max-w-[220px] space-y-2">
                            <Label>Kelas</Label>
                            <Select value={classroomId} onValueChange={setClassroomId} disabled={!classrooms.length}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    {classrooms.map((classroom) => (
                                        <SelectItem key={classroom.id} value={classroom.id}>
                                            {classroom.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button variant="outline" size="icon" className="mt-auto" onClick={fetchSchedules} disabled={loading || !classroomId}>
                            <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                        </Button>
                    </CardContent>
                </Card>

                {/* Grid */}
                <Card>
                    <CardHeader>
                        <CardTitle>{selectedClassroom ? `Jadwal Kelas ${selectedClassroom.name}` : 'Jadwal'}</CardTitle>
                        <CardDescription>
                            {classroomId ? 'Klik sel kosong untuk menambah, klik sel terisi untuk mengubah' : 'Pilih kelas untuk menampilkan jadwal'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {!classroomId ? (
                            <div className="py-12 text-center text-muted-foreground">Pilih kelas terlebih dahulu</div>
                        ) : !timeSlots.length ? (
                            <div className="py-12 text-center text-muted-foreground">
                                Belum ada jam pelajaran. Klik "Kelola Jam Pelajaran" untuk menambahkan.
                            </div>
                        ) : loading ? (
                            <div className="py-12 text-center text-muted-foreground">Memuat...</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[140px]">Jam</TableHead>
                                            {DAYS.map((day) => (
                                                <TableHead
                                                    key={day.value}
                                                    className={`min-w-[140px] text-center ${
                                                        day.value === todayDayOfWeek
                                                            ? 'bg-primary/10 font-semibold text-primary'
                                                            : ''
                                                    }`}
                                                >
                                                    {day.label}
                                                </TableHead>
                                            ))}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {timeSlots.map((slot) =>
                                            slot.is_break ? (
                                                <TableRow key={slot.id} className="bg-amber-50 dark:bg-amber-950/30">
                                                    <TableCell
                                                        colSpan={DAYS.length + 1}
                                                        className="text-center text-sm text-amber-800 dark:text-amber-300"
                                                    >
                                                        <span className="inline-flex items-center gap-1.5">
                                                            <Coffee className="h-3.5 w-3.5" />
                                                            {slot.start_time}-{slot.end_time} · {slot.name}
                                                        </span>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                <TableRow key={slot.id}>
                                                    <TableCell className="align-top">
                                                        <div className="font-medium">{slot.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {slot.start_time}-{slot.end_time}
                                                        </div>
                                                    </TableCell>
                                                    {DAYS.map((day) => {
                                                        const schedule = scheduleMap.get(`${day.value}-${slot.id}`);
                                                        const isToday = day.value === todayDayOfWeek;
                                                        return (
                                                            <TableCell
                                                                key={day.value}
                                                                className={`group p-1 align-top ${isToday ? 'bg-primary/5' : ''}`}
                                                            >
                                                                {schedule ? (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openEdit(schedule)}
                                                                        className={`w-full rounded-md border p-2 text-left transition-colors hover:border-primary ${subjectColorClasses(schedule.subject_id)}`}
                                                                    >
                                                                        <div className="text-sm font-medium">{schedule.subject?.name}</div>
                                                                        <div className="truncate text-xs opacity-80">
                                                                            {schedule.teacher?.full_name}
                                                                        </div>
                                                                        {!schedule.is_active && (
                                                                            <Badge variant="secondary" className="mt-1">Nonaktif</Badge>
                                                                        )}
                                                                    </button>
                                                                ) : (
                                                                    <button
                                                                        type="button"
                                                                        onClick={() => openCreate(day.value, slot)}
                                                                        className="flex h-full min-h-[52px] w-full items-center justify-center rounded-md border border-dashed opacity-0 transition-opacity hover:border-primary group-hover:opacity-100"
                                                                    >
                                                                        <Plus className="h-4 w-4 text-muted-foreground" />
                                                                    </button>
                                                                )}
                                                            </TableCell>
                                                        );
                                                    })}
                                                </TableRow>
                                            ),
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Jadwal Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingSchedule ? 'Edit Jadwal' : 'Tambah Jadwal'}</DialogTitle>
                        <DialogDescription>
                            {formContext && (
                                <>
                                    {DAYS.find((d) => d.value === formContext.dayOfWeek)?.label}, {formContext.timeSlot.start_time}-{formContext.timeSlot.end_time}
                                    {selectedClassroom ? ` · Kelas ${selectedClassroom.name}` : ''}
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Mata Pelajaran *</Label>
                            <Select value={form.subject_id} onValueChange={(value) => setForm({ ...form, subject_id: value })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih mata pelajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {subjects.map((subject) => (
                                        <SelectItem key={subject.id} value={subject.id}>
                                            {subject.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Guru *</Label>
                            <Select value={form.teacher_id} onValueChange={(value) => setForm({ ...form, teacher_id: value })}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih guru" />
                                </SelectTrigger>
                                <SelectContent>
                                    {teachers.filter((t) => t.user_id).map((teacher) => (
                                        <SelectItem key={teacher.user_id} value={teacher.user_id as string}>
                                            {teacher.full_name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="schedule-room">Ruangan</Label>
                            <Input
                                id="schedule-room"
                                placeholder={selectedClassroom?.room || 'Ikuti ruangan kelas'}
                                value={form.room}
                                onChange={(e) => setForm({ ...form, room: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="schedule-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">Jadwal aktif dipakai untuk absensi per jam</p>
                            </div>
                            <Switch
                                id="schedule-active"
                                checked={form.is_active}
                                onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                            />
                        </div>
                    </div>
                    <DialogFooter className="flex items-center sm:justify-between">
                        {editingSchedule ? (
                            <Button
                                variant="ghost"
                                className="text-red-600 hover:bg-red-50 hover:text-red-700"
                                onClick={() => {
                                    setFormOpen(false);
                                    setDeletingSchedule(editingSchedule);
                                }}
                            >
                                <Trash2 className="mr-2 h-4 w-4" />
                                Hapus
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                                Batal
                            </Button>
                            <Button onClick={handleSubmit} disabled={saving}>
                                {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                Simpan
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Jadwal Dialog */}
            <AlertDialog open={!!deletingSchedule} onOpenChange={(open) => !open && !deleting && setDeletingSchedule(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Jadwal</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus jadwal{' '}
                            <span className="font-medium">{deletingSchedule?.subject?.name}</span> ini? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingSchedule(null)} disabled={deleting}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                handleDelete();
                            }}
                            disabled={deleting}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Kelola Jam Pelajaran */}
            <Dialog open={tsManagerOpen} onOpenChange={setTsManagerOpen}>
                <DialogContent className="max-w-lg">
                    {tsView === 'list' ? (
                        <>
                            <DialogHeader>
                                <DialogTitle>Kelola Jam Pelajaran</DialogTitle>
                                <DialogDescription>Berlaku untuk semua kelas dan semester</DialogDescription>
                            </DialogHeader>
                            <div className="max-h-96 space-y-2 overflow-y-auto">
                                {timeSlots.map((slot) => (
                                    <div key={slot.id} className="flex items-center justify-between rounded-md border p-2">
                                        <div>
                                            <div className="font-medium">
                                                {slot.name} {slot.is_break && <Badge variant="secondary" className="ml-1">Istirahat</Badge>}
                                            </div>
                                            <div className="text-xs text-muted-foreground">{slot.start_time}-{slot.end_time} · urutan {slot.order}</div>
                                        </div>
                                        <div className="flex items-center gap-1">
                                            <Button variant="ghost" size="icon" onClick={() => openTsEdit(slot)}>
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="text-muted-foreground hover:text-red-600"
                                                onClick={() => setTsDeleting(slot)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                {!timeSlots.length && (
                                    <div className="py-6 text-center text-muted-foreground">Belum ada jam pelajaran</div>
                                )}
                            </div>
                            <DialogFooter>
                                <Button onClick={openTsCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah Jam Pelajaran
                                </Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <DialogHeader>
                                <DialogTitle>{tsEditing ? 'Edit Jam Pelajaran' : 'Tambah Jam Pelajaran'}</DialogTitle>
                            </DialogHeader>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="ts-name">Nama *</Label>
                                    <Input
                                        id="ts-name"
                                        placeholder="Contoh: Jam 1"
                                        value={tsForm.name}
                                        onChange={(e) => setTsForm({ ...tsForm, name: e.target.value })}
                                        maxLength={150}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Jam Mulai *</Label>
                                        <TimeInput24
                                            value={tsForm.start_time}
                                            onChange={(value) => setTsForm({ ...tsForm, start_time: value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Jam Selesai *</Label>
                                        <TimeInput24
                                            value={tsForm.end_time}
                                            onChange={(value) => setTsForm({ ...tsForm, end_time: value })}
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="ts-order">Urutan</Label>
                                    <Input
                                        id="ts-order"
                                        type="number"
                                        min={0}
                                        value={tsForm.order}
                                        onChange={(e) => setTsForm({ ...tsForm, order: e.target.value })}
                                    />
                                </div>
                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div>
                                        <Label htmlFor="ts-break">Jam Istirahat</Label>
                                        <p className="text-sm text-muted-foreground">Ditampilkan sebagai baris penuh di grid jadwal</p>
                                    </div>
                                    <Switch
                                        id="ts-break"
                                        checked={tsForm.is_break}
                                        onCheckedChange={(checked) => setTsForm({ ...tsForm, is_break: checked })}
                                    />
                                </div>
                            </div>
                            <DialogFooter className="flex items-center sm:justify-between">
                                <Button variant="ghost" onClick={() => setTsView('list')}>
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Kembali
                                </Button>
                                <Button onClick={handleTsSubmit} disabled={tsSaving}>
                                    {tsSaving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                    Simpan
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Delete Jam Pelajaran */}
            <AlertDialog open={!!tsDeleting} onOpenChange={(open) => !open && setTsDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Jam Pelajaran</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus{' '}
                            <span className="font-medium">{tsDeleting?.name}</span>? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setTsDeleting(null)}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleTsDelete} className="bg-red-600 hover:bg-red-700">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Salin dari Kelas Lain */}
            <Dialog open={copyClassroomOpen} onOpenChange={(open) => !copyingClassroom && setCopyClassroomOpen(open)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Salin dari Kelas Lain</DialogTitle>
                        <DialogDescription>
                            Menyalin semua jadwal dari kelas sumber ke{' '}
                            <strong>{selectedClassroom?.name ?? 'kelas ini'}</strong>
                            {semesters.find((s) => s.id === semesterId) ? ` · ${semesters.find((s) => s.id === semesterId)?.name}` : ''}.
                        </DialogDescription>
                    </DialogHeader>

                    {!copyClassroomResult ? (
                        <>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Tahun Ajaran Sumber</Label>
                                    <Select value={copySourceYearId} onValueChange={setCopySourceYearId}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih tahun ajaran" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {academicYears.map((year) => (
                                                <SelectItem key={year.id} value={year.id}>
                                                    {year.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Semester Sumber</Label>
                                        <Select
                                            value={copySourceSemesterId}
                                            onValueChange={setCopySourceSemesterId}
                                            disabled={!copySourceSemesters.length}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih semester" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {copySourceSemesters.map((semester) => (
                                                    <SelectItem key={semester.id} value={semester.id}>
                                                        {semester.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Kelas Sumber</Label>
                                        <Select
                                            value={copySourceClassroomId}
                                            onValueChange={setCopySourceClassroomId}
                                            disabled={!copySourceClassrooms.length}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih kelas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {copySourceClassrooms.map((classroom) => (
                                                    <SelectItem key={classroom.id} value={classroom.id}>
                                                        {classroom.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={copyClassroomOverwrite}
                                        onCheckedChange={(checked) => setCopyClassroomOverwrite(checked === true)}
                                    />
                                    Timpa jadwal yang sudah ada di kelas tujuan
                                </label>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setCopyClassroomOpen(false)} disabled={copyingClassroom}>
                                    Batal
                                </Button>
                                <Button onClick={handleCopyFromClassroom} disabled={copyingClassroom}>
                                    {copyingClassroom && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                    Salin
                                </Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <div className="space-y-3">
                                <Alert className="border-green-300 dark:border-green-800">
                                    <Copy className="h-4 w-4" />
                                    <AlertTitle>{copyClassroomResult.copied} jadwal berhasil disalin</AlertTitle>
                                </Alert>
                                {copyClassroomResult.skipped.length > 0 && (
                                    <Alert className="border-amber-300 dark:border-amber-800">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>{copyClassroomResult.skipped.length} dilewati</AlertTitle>
                                        <AlertDescription>
                                            <ul className="max-h-40 list-disc space-y-1 overflow-y-auto pl-4">
                                                {copyClassroomResult.skipped.map((reason, i) => (
                                                    <li key={i}>{reason}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                            <DialogFooter>
                                <Button onClick={() => setCopyClassroomOpen(false)}>Tutup</Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            {/* Salin dari Hari Lain */}
            <Dialog open={copyDayOpen} onOpenChange={(open) => !copyingDay && setCopyDayOpen(open)}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Salin dari Hari Lain</DialogTitle>
                        <DialogDescription>
                            Menyalin jadwal satu hari ke hari lain dalam <strong>{selectedClassroom?.name ?? 'kelas ini'}</strong>.
                        </DialogDescription>
                    </DialogHeader>

                    {!copyDayResult ? (
                        <>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label>Hari Sumber</Label>
                                    <Select
                                        value={copyDaySource ? String(copyDaySource) : undefined}
                                        onValueChange={(value) => {
                                            const day = Number(value);
                                            setCopyDaySource(day);
                                            setCopyDayTargets((prev) => prev.filter((d) => d !== day));
                                        }}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih hari yang sudah ada jadwalnya" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {DAYS.filter((day) => daysWithSchedule.has(day.value)).map((day) => (
                                                <SelectItem key={day.value} value={String(day.value)}>
                                                    {day.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Hari Tujuan</Label>
                                    <div className="grid grid-cols-2 gap-2 rounded-md border p-3">
                                        {DAYS.filter((day) => day.value !== copyDaySource).map((day) => (
                                            <label key={day.value} className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={copyDayTargets.includes(day.value)}
                                                    onCheckedChange={() => toggleCopyDayTarget(day.value)}
                                                />
                                                {day.label}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={copyDayOverwrite}
                                        onCheckedChange={(checked) => setCopyDayOverwrite(checked === true)}
                                    />
                                    Timpa jadwal yang sudah ada di hari tujuan
                                </label>
                            </div>
                            <DialogFooter>
                                <Button variant="outline" onClick={() => setCopyDayOpen(false)} disabled={copyingDay}>
                                    Batal
                                </Button>
                                <Button onClick={handleCopyFromDay} disabled={copyingDay}>
                                    {copyingDay && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                    Salin
                                </Button>
                            </DialogFooter>
                        </>
                    ) : (
                        <>
                            <div className="space-y-3">
                                <Alert className="border-green-300 dark:border-green-800">
                                    <Copy className="h-4 w-4" />
                                    <AlertTitle>{copyDayResult.copied} jadwal berhasil disalin</AlertTitle>
                                </Alert>
                                {copyDayResult.skipped.length > 0 && (
                                    <Alert className="border-amber-300 dark:border-amber-800">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>{copyDayResult.skipped.length} dilewati</AlertTitle>
                                        <AlertDescription>
                                            <ul className="max-h-40 list-disc space-y-1 overflow-y-auto pl-4">
                                                {copyDayResult.skipped.map((reason, i) => (
                                                    <li key={i}>{reason}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                            <DialogFooter>
                                <Button onClick={() => setCopyDayOpen(false)}>Tutup</Button>
                            </DialogFooter>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
