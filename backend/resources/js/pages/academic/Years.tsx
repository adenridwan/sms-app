import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, Fragment } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    Plus,
    Pencil,
    Trash2,
    RefreshCw,
    Search,
    ChevronRight,
    CheckCircle2,
    CalendarRange,
    Wand2,
} from 'lucide-react';
import { academicYearsApi, semestersApi } from '@/services/api';
import type { AcademicYear, Semester, PaginationMeta } from '@/types';
import { cn } from '@/lib/utils';

interface YearForm {
    /** Hanya dipakai di mode cepat: satu angka yang menurunkan nama + kedua tanggal. */
    start_year: string;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    create_semesters: boolean;
    /** Buka input tanggal manual untuk sekolah dengan kalender tidak standar. */
    manual: boolean;
}

interface SemesterForm {
    name: string;
    semester_number: '1' | '2';
    start_date: string;
    end_date: string;
}

const currentStartYear = (() => {
    const now = new Date();
    // Tahun ajaran baru mulai Juli: sebelum Juli, tahun ajaran berjalan masih milik tahun sebelumnya.
    return now.getMonth() >= 6 ? now.getFullYear() : now.getFullYear() - 1;
})();

/** Turunkan nama & rentang tanggal baku (1 Juli – 30 Juni) dari tahun mulai. */
function deriveFromStartYear(startYear: number) {
    return {
        name: `${startYear}/${startYear + 1}`,
        start_date: `${startYear}-07-01`,
        end_date: `${startYear + 1}-06-30`,
    };
}

function makeEmptyForm(): YearForm {
    return {
        start_year: String(currentStartYear),
        ...deriveFromStartYear(currentStartYear),
        is_active: false,
        create_semesters: true,
        manual: false,
    };
}

/**
 * Bagi satu tahun ajaran jadi Ganjil & Genap. Mengikuti konvensi Indonesia
 * (Ganjil berakhir 31 Desember); kalau tanggal itu di luar rentang, periode
 * dibagi dua sama panjang. Cerminan createDefaultSemesters di backend.
 */
function splitSemesters(startDate: string, endDate: string) {
    const start = new Date(startDate);
    const end = new Date(endDate);

    let ganjilEnd = new Date(start.getFullYear(), 11, 31);
    if (ganjilEnd <= start || ganjilEnd >= end) {
        ganjilEnd = new Date(start.getTime() + (end.getTime() - start.getTime()) / 2);
    }

    const genapStart = new Date(ganjilEnd.getTime());
    genapStart.setDate(genapStart.getDate() + 1);

    return {
        ganjil: { start_date: startDate, end_date: toDateInput(ganjilEnd) },
        genap: { start_date: toDateInput(genapStart), end_date: endDate },
    };
}

function toDateInput(date: Date): string {
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${date.getFullYear()}-${month}-${day}`;
}

function formatShortDate(value?: string | null): string {
    if (!value) return '-';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function formatPeriod(start?: string | null, end?: string | null): string {
    if (!start || !end) return '-';
    return `${formatShortDate(start)} – ${formatShortDate(end)}`;
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const data = (
            error as {
                response?: { data?: { message?: string; errors?: Record<string, string[]> } };
            }
        ).response?.data;

        // Laravel membalas 422 bawaan dengan message generik "Validation failed" —
        // pesan yang berguna ada di errors[field][0].
        const firstFieldError = Object.values(data?.errors ?? {})[0]?.[0];
        if (firstFieldError) return firstFieldError;

        if (data?.message) return data.message;
    }
    return fallback;
}

export default function AcademicYears() {
    const [years, setYears] = useState<AcademicYear[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [expanded, setExpanded] = useState<Set<string>>(new Set());

    // Create/Edit tahun ajaran
    const [formOpen, setFormOpen] = useState(false);
    const [editingYear, setEditingYear] = useState<AcademicYear | null>(null);
    const [form, setForm] = useState<YearForm>(makeEmptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingYear, setDeletingYear] = useState<AcademicYear | null>(null);
    const [activatingYear, setActivatingYear] = useState<AcademicYear | null>(null);

    // Create/Edit semester (di dalam baris tahun ajaran yang dibuka)
    const [semesterFormOpen, setSemesterFormOpen] = useState(false);
    const [semesterYear, setSemesterYear] = useState<AcademicYear | null>(null);
    const [editingSemester, setEditingSemester] = useState<Semester | null>(null);
    const [semesterForm, setSemesterForm] = useState<SemesterForm>({
        name: '',
        semester_number: '1',
        start_date: '',
        end_date: '',
    });
    const [savingSemester, setSavingSemester] = useState(false);
    const [deletingSemester, setDeletingSemester] = useState<Semester | null>(null);

    const fetchYears = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await academicYearsApi.list(params);
            const payload = response.data.data;
            setYears(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data tahun ajaran');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    useEffect(() => {
        fetchYears();
    }, [fetchYears]);

    const toggleExpanded = (id: string) => {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    // --- Tahun ajaran -------------------------------------------------------

    const openCreate = () => {
        setEditingYear(null);
        setForm(makeEmptyForm());
        setFormOpen(true);
    };

    const openEdit = (year: AcademicYear) => {
        setEditingYear(year);
        setForm({
            start_year: String(new Date(year.start_date).getFullYear()),
            name: year.name,
            start_date: year.start_date,
            end_date: year.end_date,
            is_active: year.is_active,
            create_semesters: false,
            // Saat edit selalu tampilkan tanggal apa adanya — jangan diturunkan ulang.
            manual: true,
        });
        setFormOpen(true);
    };

    const handleStartYearChange = (value: string) => {
        const parsed = Number(value);
        if (!value || Number.isNaN(parsed)) {
            setForm((prev) => ({ ...prev, start_year: value }));
            return;
        }

        setForm((prev) => ({ ...prev, start_year: value, ...deriveFromStartYear(parsed) }));
    };

    const handleSubmit = async () => {
        if (!form.name.trim()) {
            toast.error('Nama tahun ajaran wajib diisi');
            return;
        }
        if (!form.start_date || !form.end_date) {
            toast.error('Tanggal mulai dan selesai wajib diisi');
            return;
        }
        if (form.end_date <= form.start_date) {
            toast.error('Tanggal selesai harus setelah tanggal mulai');
            return;
        }

        setSaving(true);
        try {
            if (editingYear) {
                await academicYearsApi.update(editingYear.id, {
                    name: form.name.trim(),
                    start_date: form.start_date,
                    end_date: form.end_date,
                });
                toast.success('Tahun ajaran berhasil diperbarui');
            } else {
                await academicYearsApi.create({
                    name: form.name.trim(),
                    start_date: form.start_date,
                    end_date: form.end_date,
                    is_active: form.is_active,
                    create_semesters: form.create_semesters,
                });
                toast.success('Tahun ajaran berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan tahun ajaran'));
        } finally {
            setSaving(false);
        }
    };

    const handleActivate = async () => {
        if (!activatingYear) return;
        try {
            await academicYearsApi.activate(activatingYear.id);
            toast.success(`Tahun ajaran ${activatingYear.name} berhasil diaktifkan`);
            setActivatingYear(null);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengaktifkan tahun ajaran'));
            setActivatingYear(null);
        }
    };

    const handleDelete = async () => {
        if (!deletingYear) return;
        try {
            await academicYearsApi.delete(deletingYear.id);
            toast.success('Tahun ajaran berhasil dihapus');
            setDeletingYear(null);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus tahun ajaran'));
            setDeletingYear(null);
        }
    };

    // --- Semester -----------------------------------------------------------

    const openCreateSemester = (year: AcademicYear) => {
        const used = (year.semesters ?? []).map((s) => s.semester_number);
        const nextNumber: '1' | '2' = used.includes(1) && !used.includes(2) ? '2' : '1';
        const split = splitSemesters(year.start_date, year.end_date);
        const range = nextNumber === '1' ? split.ganjil : split.genap;

        setSemesterYear(year);
        setEditingSemester(null);
        setSemesterForm({
            name: nextNumber === '1' ? 'Semester Ganjil' : 'Semester Genap',
            semester_number: nextNumber,
            ...range,
        });
        setSemesterFormOpen(true);
    };

    const openEditSemester = (year: AcademicYear, semester: Semester) => {
        setSemesterYear(year);
        setEditingSemester(semester);
        setSemesterForm({
            name: semester.name,
            semester_number: String(semester.semester_number) as '1' | '2',
            start_date: semester.start_date,
            end_date: semester.end_date,
        });
        setSemesterFormOpen(true);
    };

    const handleSubmitSemester = async () => {
        if (!semesterYear) return;
        if (!semesterForm.name.trim()) {
            toast.error('Nama semester wajib diisi');
            return;
        }
        if (semesterForm.end_date <= semesterForm.start_date) {
            toast.error('Tanggal selesai harus setelah tanggal mulai');
            return;
        }

        setSavingSemester(true);
        try {
            const payload = {
                academic_year_id: semesterYear.id,
                name: semesterForm.name.trim(),
                semester_number: Number(semesterForm.semester_number) as 1 | 2,
                start_date: semesterForm.start_date,
                end_date: semesterForm.end_date,
            };

            if (editingSemester) {
                await semestersApi.update(editingSemester.id, payload);
                toast.success('Semester berhasil diperbarui');
            } else {
                await semestersApi.create(payload);
                toast.success('Semester berhasil ditambahkan');
            }
            setSemesterFormOpen(false);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan semester'));
        } finally {
            setSavingSemester(false);
        }
    };

    /** Buat Ganjil & Genap sekaligus untuk tahun ajaran yang belum punya semester. */
    const handleGenerateSemesters = async (year: AcademicYear) => {
        const split = splitSemesters(year.start_date, year.end_date);
        setSavingSemester(true);
        try {
            await semestersApi.create({
                academic_year_id: year.id,
                name: 'Semester Ganjil',
                semester_number: 1,
                ...split.ganjil,
            });
            await semestersApi.create({
                academic_year_id: year.id,
                name: 'Semester Genap',
                semester_number: 2,
                ...split.genap,
            });
            toast.success('Semester Ganjil & Genap berhasil dibuat');
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membuat semester otomatis'));
            fetchYears();
        } finally {
            setSavingSemester(false);
        }
    };

    const handleActivateSemester = async (semester: Semester) => {
        try {
            await semestersApi.activate(semester.id);
            toast.success(`${semester.name} berhasil diaktifkan`);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengaktifkan semester'));
        }
    };

    const handleDeleteSemester = async () => {
        if (!deletingSemester) return;
        try {
            await semestersApi.delete(deletingSemester.id);
            toast.success('Semester berhasil dihapus');
            setDeletingSemester(null);
            fetchYears();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus semester'));
            setDeletingSemester(null);
        }
    };

    const previewName = form.name.trim() || '—';

    return (
        <MainLayout title="Tahun Ajaran">
            <Head title="Akademik - Tahun Ajaran" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tahun Ajaran</h1>
                        <p className="text-muted-foreground">
                            Kelola tahun ajaran beserta semester Ganjil & Genap
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Tahun Ajaran
                    </Button>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Tahun Ajaran</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} tahun ajaran terdaftar` : 'Memuat data tahun ajaran'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari tahun ajaran..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchYears} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : years.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data tahun ajaran
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[48px]" />
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead>Semester</TableHead>
                                            <TableHead className="w-[90px]">Kelas</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[130px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {years.map((year) => {
                                            const isOpen = expanded.has(year.id);
                                            const semesters = year.semesters ?? [];

                                            return (
                                                <Fragment key={year.id}>
                                                    <TableRow>
                                                        <TableCell>
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                onClick={() => toggleExpanded(year.id)}
                                                                title="Lihat semester"
                                                            >
                                                                <ChevronRight
                                                                    className={cn(
                                                                        'h-4 w-4 transition-transform',
                                                                        isOpen && 'rotate-90'
                                                                    )}
                                                                />
                                                            </Button>
                                                        </TableCell>
                                                        <TableCell className="font-medium">{year.name}</TableCell>
                                                        <TableCell className="text-muted-foreground">
                                                            {formatPeriod(year.start_date, year.end_date)}
                                                        </TableCell>
                                                        <TableCell>
                                                            {semesters.length === 0 ? (
                                                                <span className="text-sm text-muted-foreground">
                                                                    Belum ada
                                                                </span>
                                                            ) : (
                                                                <div className="flex flex-wrap gap-1">
                                                                    {semesters.map((semester) => (
                                                                        <Badge
                                                                            key={semester.id}
                                                                            variant={
                                                                                semester.is_active
                                                                                    ? 'default'
                                                                                    : 'outline'
                                                                            }
                                                                        >
                                                                            {semester.name}
                                                                        </Badge>
                                                                    ))}
                                                                </div>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>{year.classes_count ?? 0}</TableCell>
                                                        <TableCell>
                                                            <Badge
                                                                variant={year.is_active ? 'default' : 'secondary'}
                                                            >
                                                                {year.is_active ? 'Aktif' : 'Nonaktif'}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-1">
                                                                {!year.is_active && (
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        title="Aktifkan tahun ajaran"
                                                                        className="text-muted-foreground hover:text-green-600"
                                                                        onClick={() => setActivatingYear(year)}
                                                                    >
                                                                        <CheckCircle2 className="h-4 w-4" />
                                                                    </Button>
                                                                )}
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title="Edit"
                                                                    onClick={() => openEdit(year)}
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    title="Hapus"
                                                                    className="text-muted-foreground hover:text-red-600"
                                                                    onClick={() => setDeletingYear(year)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </div>
                                                        </TableCell>
                                                    </TableRow>

                                                    {isOpen && (
                                                        <TableRow className="hover:bg-transparent">
                                                            <TableCell colSpan={7} className="bg-muted/40 p-4">
                                                                <div className="space-y-3">
                                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                                        <p className="text-sm font-medium">
                                                                            Semester pada {year.name}
                                                                        </p>
                                                                        <div className="flex gap-2">
                                                                            {semesters.length === 0 && (
                                                                                <Button
                                                                                    variant="outline"
                                                                                    size="sm"
                                                                                    disabled={savingSemester}
                                                                                    onClick={() =>
                                                                                        handleGenerateSemesters(year)
                                                                                    }
                                                                                >
                                                                                    <Wand2 className="mr-2 h-4 w-4" />
                                                                                    Buat Ganjil &amp; Genap
                                                                                </Button>
                                                                            )}
                                                                            {semesters.length < 2 && (
                                                                                <Button
                                                                                    variant="outline"
                                                                                    size="sm"
                                                                                    onClick={() =>
                                                                                        openCreateSemester(year)
                                                                                    }
                                                                                >
                                                                                    <Plus className="mr-2 h-4 w-4" />
                                                                                    Tambah Semester
                                                                                </Button>
                                                                            )}
                                                                        </div>
                                                                    </div>

                                                                    {semesters.length === 0 ? (
                                                                        <p className="text-sm text-muted-foreground">
                                                                            Belum ada semester. Tanpa semester aktif,
                                                                            data absensi tidak terhubung ke periode
                                                                            mana pun.
                                                                        </p>
                                                                    ) : (
                                                                        <div className="space-y-2">
                                                                            {semesters.map((semester) => (
                                                                                <div
                                                                                    key={semester.id}
                                                                                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border bg-background p-3"
                                                                                >
                                                                                    <div className="flex items-center gap-3">
                                                                                        <CalendarRange className="h-4 w-4 text-muted-foreground" />
                                                                                        <div>
                                                                                            <p className="text-sm font-medium">
                                                                                                {semester.name}
                                                                                            </p>
                                                                                            <p className="text-xs text-muted-foreground">
                                                                                                {formatPeriod(
                                                                                                    semester.start_date,
                                                                                                    semester.end_date
                                                                                                )}
                                                                                            </p>
                                                                                        </div>
                                                                                        <Badge
                                                                                            variant={
                                                                                                semester.is_active
                                                                                                    ? 'default'
                                                                                                    : 'secondary'
                                                                                            }
                                                                                        >
                                                                                            {semester.is_active
                                                                                                ? 'Aktif'
                                                                                                : 'Nonaktif'}
                                                                                        </Badge>
                                                                                    </div>
                                                                                    <div className="flex items-center gap-1">
                                                                                        {!semester.is_active && (
                                                                                            <Button
                                                                                                variant="ghost"
                                                                                                size="sm"
                                                                                                onClick={() =>
                                                                                                    handleActivateSemester(
                                                                                                        semester
                                                                                                    )
                                                                                                }
                                                                                            >
                                                                                                Aktifkan
                                                                                            </Button>
                                                                                        )}
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            title="Edit semester"
                                                                                            onClick={() =>
                                                                                                openEditSemester(
                                                                                                    year,
                                                                                                    semester
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Pencil className="h-4 w-4" />
                                                                                        </Button>
                                                                                        <Button
                                                                                            variant="ghost"
                                                                                            size="icon"
                                                                                            title="Hapus semester"
                                                                                            className="text-muted-foreground hover:text-red-600"
                                                                                            onClick={() =>
                                                                                                setDeletingSemester(
                                                                                                    semester
                                                                                                )
                                                                                            }
                                                                                        >
                                                                                            <Trash2 className="h-4 w-4" />
                                                                                        </Button>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </Fragment>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {/* Pagination */}
                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {meta.from}-{meta.to} dari {meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page <= 1 || loading}
                                        onClick={() => setPage((p) => p - 1)}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page >= meta.last_page || loading}
                                        onClick={() => setPage((p) => p + 1)}
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Tahun Ajaran */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {editingYear ? 'Edit Tahun Ajaran' : 'Tambah Tahun Ajaran'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingYear
                                ? 'Perbarui nama dan periode tahun ajaran'
                                : 'Cukup isi tahun mulai — nama dan periode terisi otomatis'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {!editingYear && (
                            <div className="space-y-2">
                                <Label htmlFor="year-start-year">Tahun Mulai *</Label>
                                <Input
                                    id="year-start-year"
                                    type="number"
                                    min={2000}
                                    max={2100}
                                    placeholder="Contoh: 2025"
                                    value={form.start_year}
                                    onChange={(e) => handleStartYearChange(e.target.value)}
                                />
                                <div className="rounded-md border bg-muted/40 p-3 text-sm">
                                    <p>
                                        Akan dibuat:{' '}
                                        <span className="font-medium">{previewName}</span>
                                    </p>
                                    <p className="text-muted-foreground">
                                        {formatPeriod(form.start_date, form.end_date)}
                                    </p>
                                </div>
                            </div>
                        )}

                        {(editingYear || form.manual) && (
                            <>
                                <div className="space-y-2">
                                    <Label htmlFor="year-name">Nama *</Label>
                                    <Input
                                        id="year-name"
                                        placeholder="Contoh: 2025/2026"
                                        value={form.name}
                                        onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="year-start">Mulai *</Label>
                                        <Input
                                            id="year-start"
                                            type="date"
                                            value={form.start_date}
                                            onChange={(e) =>
                                                setForm({ ...form, start_date: e.target.value })
                                            }
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="year-end">Selesai *</Label>
                                        <Input
                                            id="year-end"
                                            type="date"
                                            value={form.end_date}
                                            onChange={(e) =>
                                                setForm({ ...form, end_date: e.target.value })
                                            }
                                        />
                                    </div>
                                </div>
                            </>
                        )}

                        {!editingYear && !form.manual && (
                            <Button
                                type="button"
                                variant="link"
                                className="h-auto p-0 text-sm"
                                onClick={() => setForm({ ...form, manual: true })}
                            >
                                Atur nama &amp; tanggal manual
                            </Button>
                        )}

                        {!editingYear && (
                            <>
                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div className="pr-4">
                                        <Label htmlFor="year-create-semesters">
                                            Buat semester otomatis
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Semester Ganjil &amp; Genap dibuat sekaligus dari periode di atas
                                        </p>
                                    </div>
                                    <Switch
                                        id="year-create-semesters"
                                        checked={form.create_semesters}
                                        onCheckedChange={(checked) =>
                                            setForm({ ...form, create_semesters: checked })
                                        }
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-md border p-3">
                                    <div className="pr-4">
                                        <Label htmlFor="year-active">Jadikan tahun ajaran aktif</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Tahun ajaran aktif sebelumnya akan dinonaktifkan
                                        </p>
                                    </div>
                                    <Switch
                                        id="year-active"
                                        checked={form.is_active}
                                        onCheckedChange={(checked) =>
                                            setForm({ ...form, is_active: checked })
                                        }
                                    />
                                </div>
                            </>
                        )}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {editingYear ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Create/Edit Semester */}
            <Dialog open={semesterFormOpen} onOpenChange={setSemesterFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingSemester ? 'Edit Semester' : 'Tambah Semester'}</DialogTitle>
                        <DialogDescription>
                            {semesterYear ? `Tahun ajaran ${semesterYear.name}` : ''}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="semester-number">Semester Ke *</Label>
                                <Select
                                    value={semesterForm.semester_number}
                                    onValueChange={(value) =>
                                        setSemesterForm({
                                            ...semesterForm,
                                            semester_number: value as '1' | '2',
                                            name:
                                                value === '1' ? 'Semester Ganjil' : 'Semester Genap',
                                        })
                                    }
                                >
                                    <SelectTrigger id="semester-number">
                                        <SelectValue placeholder="Pilih" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="1">1 (Ganjil)</SelectItem>
                                        <SelectItem value="2">2 (Genap)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="semester-name">Nama *</Label>
                                <Input
                                    id="semester-name"
                                    value={semesterForm.name}
                                    onChange={(e) =>
                                        setSemesterForm({ ...semesterForm, name: e.target.value })
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="semester-start">Mulai *</Label>
                                <Input
                                    id="semester-start"
                                    type="date"
                                    value={semesterForm.start_date}
                                    onChange={(e) =>
                                        setSemesterForm({ ...semesterForm, start_date: e.target.value })
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="semester-end">Selesai *</Label>
                                <Input
                                    id="semester-end"
                                    type="date"
                                    value={semesterForm.end_date}
                                    onChange={(e) =>
                                        setSemesterForm({ ...semesterForm, end_date: e.target.value })
                                    }
                                />
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setSemesterFormOpen(false)}
                            disabled={savingSemester}
                        >
                            Batal
                        </Button>
                        <Button onClick={handleSubmitSemester} disabled={savingSemester}>
                            {savingSemester && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {editingSemester ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Konfirmasi Aktivasi */}
            <AlertDialog open={!!activatingYear} onOpenChange={() => setActivatingYear(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Aktifkan Tahun Ajaran</AlertDialogTitle>
                        <AlertDialogDescription>
                            Jadikan <span className="font-medium">{activatingYear?.name}</span> sebagai
                            tahun ajaran aktif? Tahun ajaran aktif saat ini akan dinonaktifkan, dan
                            semester pertama tahun ini ikut diaktifkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setActivatingYear(null)}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleActivate}>Aktifkan</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Hapus Tahun Ajaran */}
            <AlertDialog open={!!deletingYear} onOpenChange={() => setDeletingYear(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Tahun Ajaran</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus{' '}
                            <span className="font-medium">{deletingYear?.name}</span> beserta seluruh
                            semesternya? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingYear(null)}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Hapus Semester */}
            <AlertDialog open={!!deletingSemester} onOpenChange={() => setDeletingSemester(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Semester</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus{' '}
                            <span className="font-medium">{deletingSemester?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingSemester(null)}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDeleteSemester}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
