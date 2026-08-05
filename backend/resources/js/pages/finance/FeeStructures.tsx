import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { DatePicker } from '@/components/ui/date-picker';
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
import { toast } from 'sonner';
import { Plus, Pencil, Trash2, RefreshCw, Filter, X } from 'lucide-react';
import { feeStructuresApi, feeTypesApi, academicYearsApi, gradeLevelsApi, majorsApi } from '@/services/api';
import type { FeeStructure, FeeType, AcademicYear, GradeLevel, Major, PaginationMeta } from '@/types';

interface FeeStructureForm {
    academic_year_id: string;
    fee_type_id: string;
    grade_level_id: string;
    major_id: string;
    amount: string;
    discount_amount: string;
    due_date: string;
    due_day: string;
    is_active: boolean;
}

const emptyForm: FeeStructureForm = {
    academic_year_id: '',
    fee_type_id: '',
    grade_level_id: '',
    major_id: '',
    amount: '',
    discount_amount: '',
    due_date: '',
    due_day: '',
    is_active: true,
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

function formatCurrency(value: string): string {
    const num = parseInt(value.replace(/\D/g, ''), 10);
    if (isNaN(num)) return '';
    return num.toLocaleString('id-ID');
}

function parseCurrency(value: string): number {
    return parseInt(value.replace(/\D/g, ''), 10) || 0;
}

export default function FeeStructures() {
    const [structures, setStructures] = useState<FeeStructure[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);

    // Filters
    const [filterAcademicYear, setFilterAcademicYear] = useState('');
    const [filterFeeType, setFilterFeeType] = useState('');
    const [filterGradeLevel, setFilterGradeLevel] = useState('');
    const [filterMajor, setFilterMajor] = useState('');
    const [showFilters, setShowFilters] = useState(false);

    // Form state
    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<FeeStructure | null>(null);
    const [form, setForm] = useState<FeeStructureForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete state
    const [deletingItem, setDeletingItem] = useState<FeeStructure | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Select options
    const [academicYears, setAcademicYears] = useState<AcademicYear[]>([]);
    const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);
    const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([]);
    const [majors, setMajors] = useState<Major[]>([]);

    // Load select options
    useEffect(() => {
        const loadOptions = async () => {
            try {
                const [ayRes, ftRes, glRes, mjRes] = await Promise.all([
                    academicYearsApi.list({ per_page: 100 }),
                    feeTypesApi.list({ per_page: 100, is_active: true }),
                    gradeLevelsApi.list({ per_page: 100, is_active: true }),
                    majorsApi.list({ per_page: 100, is_active: true }),
                ]);
                setAcademicYears(ayRes.data.data.data ?? []);
                setFeeTypes(ftRes.data.data.data ?? []);
                setGradeLevels(glRes.data.data.data ?? []);
                setMajors(mjRes.data.data.data ?? []);
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
            if (filterAcademicYear) params.academic_year_id = filterAcademicYear;
            if (filterFeeType) params.fee_type_id = filterFeeType;
            if (filterGradeLevel) params.grade_level_id = filterGradeLevel;
            if (filterMajor) params.major_id = filterMajor;

            const response = await feeStructuresApi.list(params);
            const payload = response.data.data;
            setStructures(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data struktur biaya');
        } finally {
            setLoading(false);
        }
    }, [page, filterAcademicYear, filterFeeType, filterGradeLevel, filterMajor]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const clearFilters = () => {
        setFilterAcademicYear('');
        setFilterFeeType('');
        setFilterGradeLevel('');
        setFilterMajor('');
        setPage(1);
    };

    const hasFilters = filterAcademicYear || filterFeeType || filterGradeLevel || filterMajor;

    const openCreate = () => {
        setEditingItem(null);
        // Pre-select active academic year if available
        const activeYear = academicYears.find(ay => ay.is_active);
        setForm({
            ...emptyForm,
            academic_year_id: activeYear?.id ?? '',
        });
        setFormOpen(true);
    };

    const openEdit = (item: FeeStructure) => {
        setEditingItem(item);
        setForm({
            academic_year_id: item.academic_year_id,
            fee_type_id: item.fee_type_id,
            grade_level_id: item.grade_level_id,
            major_id: item.major_id ?? '',
            amount: item.amount.toString(),
            discount_amount: item.discount_amount?.toString() ?? '',
            due_date: item.due_date ?? '',
            due_day: item.due_day?.toString() ?? '',
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.academic_year_id || !form.fee_type_id || !form.grade_level_id || !form.amount) {
            toast.error('Tahun ajaran, jenis biaya, tingkat kelas, dan nominal wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                academic_year_id: form.academic_year_id,
                fee_type_id: form.fee_type_id,
                grade_level_id: form.grade_level_id,
                major_id: form.major_id || null,
                amount: parseCurrency(form.amount),
                discount_amount: form.discount_amount ? parseCurrency(form.discount_amount) : 0,
                due_date: form.due_date || null,
                due_day: form.due_day ? parseInt(form.due_day, 10) : null,
                is_active: form.is_active,
            };

            if (editingItem) {
                await feeStructuresApi.update(editingItem.id, payload);
                toast.success('Struktur biaya berhasil diperbarui');
            } else {
                await feeStructuresApi.create(payload);
                toast.success('Struktur biaya berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan struktur biaya'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await feeStructuresApi.delete(deletingItem.id);
            toast.success('Struktur biaya berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus struktur biaya'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Struktur Biaya">
            <Head title="Keuangan - Struktur Biaya" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Struktur Biaya</h1>
                        <p className="text-muted-foreground">
                            Kelola tarif biaya per tahun ajaran, tingkat kelas, dan jurusan
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Struktur Biaya
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Struktur Biaya</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} struktur biaya terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                variant={showFilters ? 'secondary' : 'outline'}
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="mr-2 h-4 w-4" />
                                Filter
                                {hasFilters && (
                                    <Badge variant="secondary" className="ml-2">
                                        {[filterAcademicYear, filterFeeType, filterGradeLevel, filterMajor].filter(Boolean).length}
                                    </Badge>
                                )}
                            </Button>
                            {hasFilters && (
                                <Button variant="ghost" size="sm" onClick={clearFilters}>
                                    <X className="mr-2 h-4 w-4" />
                                    Reset Filter
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
                                    <Label>Tahun Ajaran</Label>
                                    <Select value={filterAcademicYear} onValueChange={(v) => { setFilterAcademicYear(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua tahun ajaran" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua tahun ajaran</SelectItem>
                                            {academicYears.map((ay) => (
                                                <SelectItem key={ay.id} value={ay.id}>
                                                    {ay.name} {ay.is_active && '(Aktif)'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Jenis Biaya</Label>
                                    <Select value={filterFeeType} onValueChange={(v) => { setFilterFeeType(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua jenis biaya" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua jenis biaya</SelectItem>
                                            {feeTypes.map((ft) => (
                                                <SelectItem key={ft.id} value={ft.id}>
                                                    {ft.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Tingkat Kelas</Label>
                                    <Select value={filterGradeLevel} onValueChange={(v) => { setFilterGradeLevel(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua tingkat" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua tingkat</SelectItem>
                                            {gradeLevels.map((gl) => (
                                                <SelectItem key={gl.id} value={gl.id}>
                                                    {gl.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-2">
                                    <Label>Jurusan</Label>
                                    <Select value={filterMajor} onValueChange={(v) => { setFilterMajor(v); setPage(1); }}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Semua jurusan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">Semua jurusan</SelectItem>
                                            {majors.map((mj) => (
                                                <SelectItem key={mj.id} value={mj.id}>
                                                    {mj.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        )}

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : structures.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data struktur biaya
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tahun Ajaran</TableHead>
                                            <TableHead>Jenis Biaya</TableHead>
                                            <TableHead>Tingkat</TableHead>
                                            <TableHead>Jurusan</TableHead>
                                            <TableHead className="text-right">Nominal</TableHead>
                                            <TableHead className="text-right">Diskon</TableHead>
                                            <TableHead className="text-right">Efektif</TableHead>
                                            <TableHead className="w-[80px]">Status</TableHead>
                                            <TableHead className="w-[80px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {structures.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {item.academic_year?.name}
                                                        {item.academic_year?.is_active && (
                                                            <Badge variant="outline" className="text-xs">Aktif</Badge>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{item.fee_type?.name}</div>
                                                        <div className="text-xs text-muted-foreground">{item.fee_type?.code}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{item.grade_level?.name}</TableCell>
                                                <TableCell>{item.major?.name ?? '-'}</TableCell>
                                                <TableCell className="text-right font-mono">{item.amount_formatted}</TableCell>
                                                <TableCell className="text-right font-mono text-muted-foreground">
                                                    {item.discount_amount > 0 ? item.discount_amount_formatted : '-'}
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-medium">
                                                    {item.effective_amount_formatted}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_active ? 'default' : 'secondary'}>
                                                        {item.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(item)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingItem(item)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
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

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Struktur Biaya' : 'Tambah Struktur Biaya'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data struktur biaya' : 'Isi data struktur biaya baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tahun Ajaran *</Label>
                                <Select
                                    value={form.academic_year_id}
                                    onValueChange={(value) => setForm({ ...form, academic_year_id: value })}
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
                            <div className="space-y-2">
                                <Label>Jenis Biaya *</Label>
                                <Select
                                    value={form.fee_type_id}
                                    onValueChange={(value) => setForm({ ...form, fee_type_id: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih jenis biaya" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {feeTypes.map((ft) => (
                                            <SelectItem key={ft.id} value={ft.id}>
                                                {ft.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tingkat Kelas *</Label>
                                <Select
                                    value={form.grade_level_id}
                                    onValueChange={(value) => setForm({ ...form, grade_level_id: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tingkat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {gradeLevels.map((gl) => (
                                            <SelectItem key={gl.id} value={gl.id}>
                                                {gl.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Jurusan</Label>
                                <Select
                                    value={form.major_id}
                                    onValueChange={(value) => setForm({ ...form, major_id: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua jurusan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua jurusan</SelectItem>
                                        {majors.map((mj) => (
                                            <SelectItem key={mj.id} value={mj.id}>
                                                {mj.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="amount">Nominal *</Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 text-sm text-muted-foreground">Rp</span>
                                    <Input
                                        id="amount"
                                        className="pl-10 text-right"
                                        placeholder="0"
                                        value={formatCurrency(form.amount)}
                                        onChange={(e) => setForm({ ...form, amount: e.target.value.replace(/\D/g, '') })}
                                    />
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="discount">Diskon</Label>
                                <div className="relative">
                                    <span className="absolute left-3 top-2.5 text-sm text-muted-foreground">Rp</span>
                                    <Input
                                        id="discount"
                                        className="pl-10 text-right"
                                        placeholder="0"
                                        value={formatCurrency(form.discount_amount)}
                                        onChange={(e) => setForm({ ...form, discount_amount: e.target.value.replace(/\D/g, '') })}
                                    />
                                </div>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="due_date">Tanggal Jatuh Tempo</Label>
                                <DatePicker
                                    id="due_date"
                                    value={form.due_date}
                                    onChange={(value) => setForm({ ...form, due_date: value })}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="due_day">Tanggal Jatuh Tempo Bulanan (1-31)</Label>
                                <Input
                                    id="due_day"
                                    type="number"
                                    min={1}
                                    max={31}
                                    placeholder="Contoh: 10"
                                    value={form.due_day}
                                    onChange={(e) => setForm({ ...form, due_day: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="struct-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Struktur biaya aktif dapat digunakan untuk generate tagihan
                                </p>
                            </div>
                            <Switch
                                id="struct-active"
                                checked={form.is_active}
                                onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {editingItem ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!deletingItem} onOpenChange={(open) => !open && !deleting && setDeletingItem(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Struktur Biaya</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus struktur biaya{' '}
                            <span className="font-medium">{deletingItem?.fee_type?.name}</span> untuk{' '}
                            <span className="font-medium">{deletingItem?.grade_level?.name}</span>?
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingItem(null)} disabled={deleting}>
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
