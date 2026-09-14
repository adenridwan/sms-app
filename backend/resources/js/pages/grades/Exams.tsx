import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { toast } from 'sonner';
import {
    examApi,
    examTypeApi,
    academicYearsApi,
    semestersApi,
    subjectsApi,
    classroomsApi,
    type Exam,
    type ExamType,
    type ExamStatistics,
} from '@/services/api';
import {
    Search,
    Plus,
    MoreVertical,
    Pencil,
    Trash2,
    FileText,
    Calendar,
    BookOpen,
    CheckCircle,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700',
    scheduled: 'bg-blue-100 text-blue-700',
    ongoing: 'bg-yellow-100 text-yellow-700',
    completed: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
};

interface SelectOption {
    id: string;
    name: string;
}

export default function Exams() {
    // State
    const [exams, setExams] = useState<Exam[]>([]);
    const [statistics, setStatistics] = useState<ExamStatistics | null>(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Dialog state
    const [formOpen, setFormOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [selectedExam, setSelectedExam] = useState<Exam | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Options for selects
    const [examTypes, setExamTypes] = useState<ExamType[]>([]);
    const [academicYears, setAcademicYears] = useState<SelectOption[]>([]);
    const [semesters, setSemesters] = useState<SelectOption[]>([]);
    const [subjects, setSubjects] = useState<SelectOption[]>([]);
    const [classrooms, setClassrooms] = useState<SelectOption[]>([]);

    // Form state
    const [formData, setFormData] = useState({
        academic_year_id: '',
        semester_id: '',
        subject_id: '',
        exam_type_id: '',
        classroom_id: '',
        name: '',
        description: '',
        exam_date: '',
        start_time: '',
        end_time: '',
        duration_minutes: 60,
        max_score: 100,
        passing_score: 70,
        weight: 100,
        status: 'draft' as Exam['status'],
    });

    // Load exams
    const loadExams = useCallback(async () => {
        try {
            setLoading(true);
            const params: Record<string, unknown> = {
                page: currentPage,
                per_page: 10,
            };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;

            const response = await examApi.list(params);
            setExams(response.data.data || []);
            setTotalPages(response.data.meta?.last_page || 1);
        } catch (error) {
            console.error('Failed to load exams:', error);
            toast.error('Gagal memuat data ujian');
        } finally {
            setLoading(false);
        }
    }, [currentPage, search, statusFilter]);

    // Load statistics
    const loadStatistics = useCallback(async () => {
        try {
            const response = await examApi.statistics();
            setStatistics(response.data.data);
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    }, []);

    // Load select options
    const loadOptions = async () => {
        try {
            const [typesRes, yearsRes, semestersRes, subjectsRes, classroomsRes] = await Promise.all([
                examTypeApi.list({ all: true, is_active: true }),
                academicYearsApi.list({ all: true }),
                semestersApi.list({ all: true }),
                subjectsApi.list({ all: true }),
                classroomsApi.list({ all: true }),
            ]);

            setExamTypes(typesRes.data.data || []);
            setAcademicYears((yearsRes.data.data || []) as unknown as SelectOption[]);
            setSemesters((semestersRes.data.data || []) as unknown as SelectOption[]);
            setSubjects((subjectsRes.data.data || []) as unknown as SelectOption[]);
            setClassrooms((classroomsRes.data.data || []) as unknown as SelectOption[]);
        } catch (error) {
            console.error('Failed to load options:', error);
        }
    };

    useEffect(() => {
        loadExams();
        loadStatistics();
        loadOptions();
    }, [loadExams, loadStatistics]);

    // Open form for create/edit
    const openForm = (exam?: Exam) => {
        if (exam) {
            setSelectedExam(exam);
            setFormData({
                academic_year_id: exam.academic_year?.id || '',
                semester_id: exam.semester?.id || '',
                subject_id: exam.subject?.id || '',
                exam_type_id: exam.exam_type?.id || '',
                classroom_id: exam.classroom?.id || '',
                name: exam.name,
                description: exam.description || '',
                exam_date: exam.exam_date,
                start_time: exam.start_time || '',
                end_time: exam.end_time || '',
                duration_minutes: exam.duration_minutes || 60,
                max_score: exam.max_score,
                passing_score: exam.passing_score,
                weight: exam.weight,
                status: exam.status,
            });
        } else {
            setSelectedExam(null);
            setFormData({
                academic_year_id: '',
                semester_id: '',
                subject_id: '',
                exam_type_id: '',
                classroom_id: '',
                name: '',
                description: '',
                exam_date: '',
                start_time: '',
                end_time: '',
                duration_minutes: 60,
                max_score: 100,
                passing_score: 70,
                weight: 100,
                status: 'draft',
            });
        }
        setFormOpen(true);
    };

    // Handle submit
    const handleSubmit = async () => {
        if (!formData.name || !formData.academic_year_id || !formData.semester_id ||
            !formData.subject_id || !formData.exam_type_id || !formData.classroom_id ||
            !formData.exam_date) {
            toast.error('Lengkapi semua field yang wajib diisi');
            return;
        }

        setSubmitting(true);
        try {
            if (selectedExam) {
                await examApi.update(selectedExam.id, formData);
                toast.success('Ujian berhasil diperbarui');
            } else {
                await examApi.create(formData);
                toast.success('Ujian berhasil ditambahkan');
            }
            setFormOpen(false);
            loadExams();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(selectedExam ? 'Gagal memperbarui ujian' : 'Gagal menambahkan ujian', {
                description: err.response?.data?.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // Handle delete
    const handleDelete = async () => {
        if (!selectedExam) return;

        setSubmitting(true);
        try {
            await examApi.delete(selectedExam.id);
            toast.success('Ujian berhasil dihapus');
            setDeleteOpen(false);
            loadExams();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal menghapus ujian', {
                description: err.response?.data?.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // Format date
    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        try {
            return format(parseISO(dateStr), 'dd MMM yyyy', { locale: localeId });
        } catch {
            return dateStr;
        }
    };

    return (
        <MainLayout title="Manajemen Ujian">
            <Head title="Manajemen Ujian" />

            <div className="space-y-6">
                {/* Statistics */}
                {statistics && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Ujian</CardTitle>
                                <FileText className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.total_exams}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Dijadwalkan</CardTitle>
                                <Calendar className="h-4 w-4 text-yellow-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {statistics.by_status?.scheduled || 0}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Selesai</CardTitle>
                                <CheckCircle className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {statistics.by_status?.completed || 0}
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Rata-rata Nilai</CardTitle>
                                <BookOpen className="h-4 w-4 text-purple-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    {statistics.average_score?.toFixed(1) || '-'}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Main Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Daftar Ujian</CardTitle>
                                <CardDescription>Kelola ujian dan penilaian</CardDescription>
                            </div>
                            <Button onClick={() => openForm()}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Ujian
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex flex-wrap gap-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Cari ujian..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setCurrentPage(1);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select
                                value={statusFilter}
                                onValueChange={(value) => {
                                    setStatusFilter(value);
                                    setCurrentPage(1);
                                }}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Status</SelectItem>
                                    <SelectItem value="draft">Draf</SelectItem>
                                    <SelectItem value="scheduled">Dijadwalkan</SelectItem>
                                    <SelectItem value="ongoing">Berlangsung</SelectItem>
                                    <SelectItem value="completed">Selesai</SelectItem>
                                    <SelectItem value="cancelled">Dibatalkan</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Table */}
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ujian</TableHead>
                                        <TableHead>Mata Pelajaran</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Nilai</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center py-8">
                                                Memuat...
                                            </TableCell>
                                        </TableRow>
                                    ) : exams.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center py-8">
                                                Tidak ada data ujian
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        exams.map((exam) => (
                                            <TableRow key={exam.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{exam.name}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {exam.exam_type?.name}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{exam.subject?.name || '-'}</TableCell>
                                                <TableCell>{exam.classroom?.name || '-'}</TableCell>
                                                <TableCell>{formatDate(exam.exam_date)}</TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        <div>{exam.score_count} siswa</div>
                                                        {exam.average_score && (
                                                            <div className="text-muted-foreground">
                                                                Rata-rata: {exam.average_score.toFixed(1)}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={statusColors[exam.status]}>
                                                        {exam.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            {exam.can_edit && (
                                                                <DropdownMenuItem onClick={() => openForm(exam)}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </DropdownMenuItem>
                                                            )}
                                                            {exam.can_delete && (
                                                                <DropdownMenuItem
                                                                    onClick={() => {
                                                                        setSelectedExam(exam);
                                                                        setDeleteOpen(true);
                                                                    }}
                                                                    className="text-red-600"
                                                                >
                                                                    <Trash2 className="mr-2 h-4 w-4" />
                                                                    Hapus
                                                                </DropdownMenuItem>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    disabled={currentPage === 1}
                                >
                                    Sebelumnya
                                </Button>
                                <span className="flex items-center px-3 text-sm">
                                    Halaman {currentPage} dari {totalPages}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    disabled={currentPage === totalPages}
                                >
                                    Selanjutnya
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedExam ? 'Edit Ujian' : 'Tambah Ujian'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedExam
                                ? 'Perbarui informasi ujian'
                                : 'Buat ujian baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tahun Ajaran *</Label>
                                <Select
                                    value={formData.academic_year_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, academic_year_id: value })
                                    }
                                >
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
                            <div className="space-y-2">
                                <Label>Semester *</Label>
                                <Select
                                    value={formData.semester_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, semester_id: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((sem) => (
                                            <SelectItem key={sem.id} value={sem.id}>
                                                {sem.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Mata Pelajaran *</Label>
                                <Select
                                    value={formData.subject_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, subject_id: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih mata pelajaran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {subjects.map((subj) => (
                                            <SelectItem key={subj.id} value={subj.id}>
                                                {subj.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Kelas *</Label>
                                <Select
                                    value={formData.classroom_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, classroom_id: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classrooms.map((cls) => (
                                            <SelectItem key={cls.id} value={cls.id}>
                                                {cls.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Jenis Ujian *</Label>
                                <Select
                                    value={formData.exam_type_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, exam_type_id: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih jenis ujian" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {examTypes.map((type) => (
                                            <SelectItem key={type.id} value={type.id}>
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="exam_date">Tanggal Ujian *</Label>
                                <Input
                                    id="exam_date"
                                    type="date"
                                    value={formData.exam_date}
                                    onChange={(e) =>
                                        setFormData({ ...formData, exam_date: e.target.value })
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="name">Nama Ujian *</Label>
                            <Input
                                id="name"
                                value={formData.name}
                                onChange={(e) =>
                                    setFormData({ ...formData, name: e.target.value })
                                }
                                placeholder="Contoh: UTS Matematika Semester 1"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) =>
                                    setFormData({ ...formData, description: e.target.value })
                                }
                                placeholder="Deskripsi ujian (opsional)"
                                rows={3}
                            />
                        </div>

                        <div className="grid grid-cols-3 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="max_score">Nilai Maks</Label>
                                <Input
                                    id="max_score"
                                    type="number"
                                    min={1}
                                    max={1000}
                                    value={formData.max_score}
                                    onChange={(e) =>
                                        setFormData({ ...formData, max_score: parseInt(e.target.value) || 100 })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="passing_score">KKM</Label>
                                <Input
                                    id="passing_score"
                                    type="number"
                                    min={0}
                                    max={formData.max_score}
                                    value={formData.passing_score}
                                    onChange={(e) =>
                                        setFormData({ ...formData, passing_score: parseInt(e.target.value) || 70 })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="weight">Bobot (%)</Label>
                                <Input
                                    id="weight"
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={formData.weight}
                                    onChange={(e) =>
                                        setFormData({ ...formData, weight: parseInt(e.target.value) || 100 })
                                    }
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select
                                value={formData.status}
                                onValueChange={(value: Exam['status']) =>
                                    setFormData({ ...formData, status: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="draft">Draf</SelectItem>
                                    <SelectItem value="scheduled">Dijadwalkan</SelectItem>
                                    <SelectItem value="ongoing">Berlangsung</SelectItem>
                                    <SelectItem value="completed">Selesai</SelectItem>
                                    <SelectItem value="cancelled">Dibatalkan</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={submitting}>
                            {submitting ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Ujian</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus ujian "{selectedExam?.name}"?
                            Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={submitting}
                        >
                            {submitting ? 'Menghapus...' : 'Hapus'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
