import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { DatePicker } from '@/components/ui/date-picker';
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
import { toast } from 'sonner';
import { Plus, Pencil, Trash2, RefreshCw, Search, Filter, X, FileText, AlertTriangle } from 'lucide-react';
import { studentFeesApi, academicYearsApi, gradeLevelsApi, classroomsApi, feeTypesApi } from '@/services/api';
import type { StudentFee, AcademicYear, GradeLevel, Classroom, FeeType, PaginationMeta } from '@/types';

interface GenerateForm {
    academic_year_id: string;
    grade_level_id: string;
    classroom_id: string;
    fee_type_id: string;
    month: string;
    year: string;
    due_date: string;
    apply_discounts: boolean;
}

interface EditForm {
    amount: string;
    discount: string;
    fine: string;
    due_date: string;
    notes: string;
}

interface Summary {
    total_billed: number;
    total_paid: number;
    total_remaining: number;
    total_billed_formatted: string;
    total_paid_formatted: string;
    total_remaining_formatted: string;
    count_total: number;
    count_unpaid: number;
    count_partial: number;
    count_paid: number;
    count_overdue: number;
}

const MONTHS = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: 5 }, (_, i) => (currentYear - 2 + i).toString());

function getStatusColor(status: string): string {
    switch (status) {
        case 'paid': return 'bg-green-100 text-green-800';
        case 'partial': return 'bg-yellow-100 text-yellow-800';
        case 'unpaid': return 'bg-gray-100 text-gray-800';
        case 'overdue': return 'bg-red-100 text-red-800';
        case 'waived': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
        const firstFieldError = Object.values(response?.data?.errors ?? {})[0]?.[0];
        if (firstFieldError) return firstFieldError;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

function formatCurrency(value: string): string {
    const num = parseInt(value.replace(/\D/g, ''), 10);
    if (isNaN(num)) return '';
    return num.toLocaleString('id-ID');
}

function parseCurrency(value: string): number {
    return parseInt(value.replace(/\D/g, ''), 10) || 0;
}

export default function StudentFees() {
    const [fees, setFees] = useState<StudentFee[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [summary, setSummary] = useState<Summary | null>(null);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);

    // Filters
    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [filterAcademicYear, setFilterAcademicYear] = useState('');
    const [filterMonth, setFilterMonth] = useState('');
    const [filterYear, setFilterYear] = useState('');
    const [filterClassroom, setFilterClassroom] = useState('');
    const [showFilters, setShowFilters] = useState(false);

    // Generate dialog
    const [generateOpen, setGenerateOpen] = useState(false);
    const [generateForm, setGenerateForm] = useState<GenerateForm>({
        academic_year_id: '',
        grade_level_id: '',
        classroom_id: '',
        fee_type_id: '',
        month: (new Date().getMonth() + 1).toString(),
        year: currentYear.toString(),
        due_date: '',
        apply_discounts: true,
    });
    const [generating, setGenerating] = useState(false);

    // Edit dialog
    const [editOpen, setEditOpen] = useState(false);
    const [editingFee, setEditingFee] = useState<StudentFee | null>(null);
    const [editForm, setEditForm] = useState<EditForm>({
        amount: '',
        discount: '',
        fine: '',
        due_date: '',
        notes: '',
    });
    const [saving, setSaving] = useState(false);

    // Waive dialog
    const [waiveOpen, setWaiveOpen] = useState(false);
    const [waivingFee, setWaivingFee] = useState<StudentFee | null>(null);
    const [waiveReason, setWaiveReason] = useState('');
    const [waiving, setWaiving] = useState(false);

    // Delete dialog
    const [deletingFee, setDeletingFee] = useState<StudentFee | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Select options
    const [academicYears, setAcademicYears] = useState<AcademicYear[]>([]);
    const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([]);
    const [classrooms, setClassrooms] = useState<Classroom[]>([]);
    const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);

    // Load select options
    useEffect(() => {
        const loadOptions = async () => {
            try {
                const [ayRes, glRes, crRes, ftRes] = await Promise.all([
                    academicYearsApi.list({ per_page: 100 }),
                    gradeLevelsApi.list({ per_page: 100, is_active: true }),
                    classroomsApi.list({ per_page: 100, is_active: true }),
                    feeTypesApi.list({ per_page: 100, is_active: true }),
                ]);
                const years = ayRes.data.data.data ?? [];
                setAcademicYears(years);
                setGradeLevels(glRes.data.data.data ?? []);
                setClassrooms(crRes.data.data.data ?? []);
                setFeeTypes(ftRes.data.data.data ?? []);

                // Pre-select active academic year
                const activeYear = years.find((ay: AcademicYear) => ay.is_active);
                if (activeYear) {
                    setFilterAcademicYear(activeYear.id);
                    setGenerateForm(f => ({ ...f, academic_year_id: activeYear.id }));
                }
            } catch {
                toast.error('Gagal memuat data referensi');
            }
        };
        loadOptions();
    }, []);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterStatus) params.status = filterStatus;
            if (filterAcademicYear) params.academic_year_id = filterAcademicYear;
            if (filterMonth) params.month = filterMonth;
            if (filterYear) params.year = filterYear;
            if (filterClassroom) params.classroom_id = filterClassroom;

            const [feesRes, summaryRes] = await Promise.all([
                studentFeesApi.list(params),
                studentFeesApi.summary(params),
            ]);

            const payload = feesRes.data.data;
            setFees(payload.data ?? []);
            setMeta(payload.meta ?? null);
            setSummary(summaryRes.data.data as Summary);
        } catch {
            toast.error('Gagal memuat data tagihan');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterStatus, filterAcademicYear, filterMonth, filterYear, filterClassroom]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const clearFilters = () => {
        setSearch('');
        setFilterStatus('');
        setFilterMonth('');
        setFilterYear('');
        setFilterClassroom('');
        setPage(1);
    };

    const hasFilters = search || filterStatus || filterMonth || filterYear || filterClassroom;

    const handleGenerate = async () => {
        if (!generateForm.academic_year_id || !generateForm.month || !generateForm.year || !generateForm.due_date) {
            toast.error('Tahun ajaran, bulan, tahun, dan tanggal jatuh tempo wajib diisi');
            return;
        }

        setGenerating(true);
        try {
            const response = await studentFeesApi.generate({
                academic_year_id: generateForm.academic_year_id,
                grade_level_id: generateForm.grade_level_id || undefined,
                classroom_id: generateForm.classroom_id || undefined,
                fee_type_id: generateForm.fee_type_id || undefined,
                month: parseInt(generateForm.month, 10),
                year: parseInt(generateForm.year, 10),
                due_date: generateForm.due_date,
                apply_discounts: generateForm.apply_discounts,
            });
            const result = response.data.data;
            toast.success(`${result.created} tagihan berhasil dibuat${result.skipped > 0 ? `, ${result.skipped} dilewati` : ''}`);
            setGenerateOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal generate tagihan'));
        } finally {
            setGenerating(false);
        }
    };

    const openEdit = (fee: StudentFee) => {
        setEditingFee(fee);
        setEditForm({
            amount: fee.amount.toString(),
            discount: fee.discount.toString(),
            fine: fee.fine.toString(),
            due_date: fee.due_date ?? '',
            notes: fee.notes ?? '',
        });
        setEditOpen(true);
    };

    const handleSave = async () => {
        if (!editingFee) return;

        setSaving(true);
        try {
            await studentFeesApi.update(editingFee.id, {
                amount: parseCurrency(editForm.amount),
                discount: parseCurrency(editForm.discount),
                fine: parseCurrency(editForm.fine),
                due_date: editForm.due_date || null,
                notes: editForm.notes || null,
            });
            toast.success('Tagihan berhasil diperbarui');
            setEditOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal memperbarui tagihan'));
        } finally {
            setSaving(false);
        }
    };

    const openWaive = (fee: StudentFee) => {
        setWaivingFee(fee);
        setWaiveReason('');
        setWaiveOpen(true);
    };

    const handleWaive = async () => {
        if (!waivingFee || !waiveReason.trim()) {
            toast.error('Alasan pembebasan wajib diisi');
            return;
        }

        setWaiving(true);
        try {
            await studentFeesApi.waive(waivingFee.id, { reason: waiveReason.trim() });
            toast.success('Tagihan berhasil dibebaskan');
            setWaiveOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membebaskan tagihan'));
        } finally {
            setWaiving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingFee || deleting) return;

        setDeleting(true);
        try {
            await studentFeesApi.delete(deletingFee.id);
            toast.success('Tagihan berhasil dihapus');
            setDeletingFee(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus tagihan'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Tagihan Siswa">
            <Head title="Keuangan - Tagihan Siswa" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tagihan Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola tagihan biaya pendidikan siswa
                        </p>
                    </div>
                    <Button onClick={() => setGenerateOpen(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Generate Tagihan
                    </Button>
                </div>

                {/* Summary Cards */}
                {summary && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Total Tagihan</CardDescription>
                                <CardTitle className="text-2xl">{summary.total_billed_formatted}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{summary.count_total} tagihan</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Sudah Dibayar</CardDescription>
                                <CardTitle className="text-2xl text-green-600">{summary.total_paid_formatted}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{summary.count_paid} lunas</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Belum Dibayar</CardDescription>
                                <CardTitle className="text-2xl text-yellow-600">{summary.total_remaining_formatted}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{summary.count_unpaid + summary.count_partial} belum lunas</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Jatuh Tempo</CardDescription>
                                <CardTitle className="text-2xl text-red-600">{summary.count_overdue}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">tagihan melewati batas waktu</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Tagihan</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} tagihan terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative flex-1 min-w-[200px] max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari NIS atau nama siswa..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select value={filterAcademicYear} onValueChange={(v) => { setFilterAcademicYear(v); setPage(1); }}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua tahun</SelectItem>
                                    {academicYears.map((ay) => (
                                        <SelectItem key={ay.id} value={ay.id}>
                                            {ay.name} {ay.is_active && '(Aktif)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                variant={showFilters ? 'secondary' : 'outline'}
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="mr-2 h-4 w-4" />
                                Filter
                                {hasFilters && (
                                    <Badge variant="secondary" className="ml-2">
                                        {[search, filterStatus, filterMonth, filterYear, filterClassroom].filter(Boolean).length}
                                    </Badge>
                                )}
                            </Button>
                            {hasFilters && (
                                <Button variant="ghost" size="sm" onClick={clearFilters}>
                                    <X className="mr-2 h-4 w-4" />
                                    Reset
                                </Button>
                            )}
                            <div className="ml-auto">
                                <Button variant="outline" size="icon" onClick={fetchData} disabled={loading}>
                                    <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                </Button>
                            </div>
                        </div>

                        {showFilters && (
                            <div className="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div className="space-y-2">
                                    <Label>Status</Label>
                                    <Select value={filterStatus} onValueChange={(v) => { setFilterStatus(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua status</SelectItem>
                                            <SelectItem value="unpaid">Belum Dibayar</SelectItem>
                                            <SelectItem value="partial">Dibayar Sebagian</SelectItem>
                                            <SelectItem value="paid">Lunas</SelectItem>
                                            <SelectItem value="overdue">Jatuh Tempo</SelectItem>
                                            <SelectItem value="waived">Dibebaskan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Bulan</Label>
                                    <Select value={filterMonth} onValueChange={(v) => { setFilterMonth(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua bulan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua bulan</SelectItem>
                                            {MONTHS.map((m) => (
                                                <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Tahun</Label>
                                    <Select value={filterYear} onValueChange={(v) => { setFilterYear(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua tahun" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua tahun</SelectItem>
                                            {YEARS.map((y) => (
                                                <SelectItem key={y} value={y}>{y}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Kelas</Label>
                                    <Select value={filterClassroom} onValueChange={(v) => { setFilterClassroom(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua kelas" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua kelas</SelectItem>
                                            {classrooms.map((c) => (
                                                <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : fees.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data tagihan
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Siswa</TableHead>
                                            <TableHead>Jenis Biaya</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                            <TableHead className="text-right">Dibayar</TableHead>
                                            <TableHead className="text-right">Sisa</TableHead>
                                            <TableHead>Jatuh Tempo</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {fees.map((fee) => (
                                            <TableRow key={fee.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{fee.student?.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {fee.student?.nis}
                                                            {fee.student?.classroom && ` - ${fee.student.classroom.name}`}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {fee.fee_structure?.fee_type?.name ?? '-'}
                                                </TableCell>
                                                <TableCell>{fee.period_label}</TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {fee.total_amount_formatted}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-green-600">
                                                    {fee.paid_amount > 0 ? fee.paid_amount_formatted : '-'}
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-medium">
                                                    {fee.remaining_amount_formatted}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        {fee.due_date ?? '-'}
                                                        {fee.is_overdue && (
                                                            <AlertTriangle className="h-4 w-4 text-red-500" />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={getStatusColor(fee.status)}>
                                                        {fee.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEdit(fee)}
                                                            disabled={fee.status === 'paid' || fee.status === 'waived'}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        {fee.status !== 'paid' && fee.status !== 'waived' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-blue-600"
                                                                onClick={() => openWaive(fee)}
                                                                title="Bebaskan"
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {fee.paid_amount === 0 && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="text-muted-foreground hover:text-red-600"
                                                                onClick={() => setDeletingFee(fee)}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

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

            {/* Generate Dialog */}
            <Dialog open={generateOpen} onOpenChange={setGenerateOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Generate Tagihan</DialogTitle>
                        <DialogDescription>
                            Generate tagihan untuk siswa berdasarkan struktur biaya
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Tahun Ajaran *</Label>
                            <Select
                                value={generateForm.academic_year_id}
                                onValueChange={(v) => setGenerateForm({ ...generateForm, academic_year_id: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((ay) => (
                                        <SelectItem key={ay.id} value={ay.id}>
                                            {ay.name} {ay.is_active && '(Aktif)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tingkat Kelas</Label>
                                <Select
                                    value={generateForm.grade_level_id}
                                    onValueChange={(v) => setGenerateForm({ ...generateForm, grade_level_id: v, classroom_id: '' })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua tingkat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua tingkat</SelectItem>
                                        {gradeLevels.map((gl) => (
                                            <SelectItem key={gl.id} value={gl.id}>{gl.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Kelas</Label>
                                <Select
                                    value={generateForm.classroom_id}
                                    onValueChange={(v) => setGenerateForm({ ...generateForm, classroom_id: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua kelas</SelectItem>
                                        {classrooms
                                            .filter(c => !generateForm.grade_level_id || c.grade_level_id === generateForm.grade_level_id)
                                            .map((c) => (
                                                <SelectItem key={c.id} value={c.id}>{c.name}</SelectItem>
                                            ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Jenis Biaya</Label>
                            <Select
                                value={generateForm.fee_type_id}
                                onValueChange={(v) => setGenerateForm({ ...generateForm, fee_type_id: v })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Semua jenis biaya" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua jenis biaya</SelectItem>
                                    {feeTypes.map((ft) => (
                                        <SelectItem key={ft.id} value={ft.id}>{ft.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Bulan *</Label>
                                <Select
                                    value={generateForm.month}
                                    onValueChange={(v) => setGenerateForm({ ...generateForm, month: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih bulan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MONTHS.map((m) => (
                                            <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Tahun *</Label>
                                <Select
                                    value={generateForm.year}
                                    onValueChange={(v) => setGenerateForm({ ...generateForm, year: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tahun" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {YEARS.map((y) => (
                                            <SelectItem key={y} value={y}>{y}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="gen-due-date">Tanggal Jatuh Tempo *</Label>
                            <DatePicker
                                id="gen-due-date"
                                value={generateForm.due_date}
                                onChange={(value) => setGenerateForm({ ...generateForm, due_date: value })}
                                placeholder="Pilih tanggal"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                id="apply-discounts"
                                checked={generateForm.apply_discounts}
                                onChange={(e) => setGenerateForm({ ...generateForm, apply_discounts: e.target.checked })}
                                className="h-4 w-4"
                            />
                            <Label htmlFor="apply-discounts" className="text-sm font-normal">
                                Terapkan potongan/beasiswa siswa
                            </Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setGenerateOpen(false)} disabled={generating}>
                            Batal
                        </Button>
                        <Button onClick={handleGenerate} disabled={generating}>
                            {generating && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Generate
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={editOpen} onOpenChange={setEditOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Tagihan</DialogTitle>
                        <DialogDescription>
                            {editingFee?.student?.name} - {editingFee?.period_label}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-amount">Nominal</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-2.5 text-sm text-muted-foreground">Rp</span>
                                <Input
                                    id="edit-amount"
                                    className="pl-10 text-right"
                                    value={formatCurrency(editForm.amount)}
                                    onChange={(e) => setEditForm({ ...editForm, amount: e.target.value.replace(/\D/g, '') })}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="edit-discount">Diskon</Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 text-sm text-muted-foreground">Rp</span>
                                    <Input
                                        id="edit-discount"
                                        className="pl-10 text-right"
                                        value={formatCurrency(editForm.discount)}
                                        onChange={(e) => setEditForm({ ...editForm, discount: e.target.value.replace(/\D/g, '') })}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edit-fine">Denda</Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 text-sm text-muted-foreground">Rp</span>
                                    <Input
                                        id="edit-fine"
                                        className="pl-10 text-right"
                                        value={formatCurrency(editForm.fine)}
                                        onChange={(e) => setEditForm({ ...editForm, fine: e.target.value.replace(/\D/g, '') })}
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-due-date">Tanggal Jatuh Tempo</Label>
                            <DatePicker
                                id="edit-due-date"
                                value={editForm.due_date}
                                onChange={(value) => setEditForm({ ...editForm, due_date: value })}
                                placeholder="Pilih tanggal"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="edit-notes">Catatan</Label>
                            <Textarea
                                id="edit-notes"
                                rows={2}
                                value={editForm.notes}
                                onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSave} disabled={saving}>
                            {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Waive Dialog */}
            <Dialog open={waiveOpen} onOpenChange={setWaiveOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Bebaskan Tagihan</DialogTitle>
                        <DialogDescription>
                            {waivingFee?.student?.name} - {waivingFee?.period_label}
                            <br />
                            <span className="font-medium">{waivingFee?.total_amount_formatted}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="waive-reason">Alasan Pembebasan *</Label>
                            <Textarea
                                id="waive-reason"
                                rows={3}
                                placeholder="Masukkan alasan pembebasan tagihan..."
                                value={waiveReason}
                                onChange={(e) => setWaiveReason(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setWaiveOpen(false)} disabled={waiving}>
                            Batal
                        </Button>
                        <Button onClick={handleWaive} disabled={waiving}>
                            {waiving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Bebaskan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingFee} onOpenChange={(open) => !open && !deleting && setDeletingFee(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Tagihan</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus tagihan{' '}
                            <span className="font-medium">{deletingFee?.student?.name}</span> periode{' '}
                            <span className="font-medium">{deletingFee?.period_label}</span>?
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingFee(null)} disabled={deleting}>
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
        </MainLayout>
    );
}
