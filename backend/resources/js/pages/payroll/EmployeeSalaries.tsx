import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { DatePicker } from '@/components/ui/date-picker';
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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs';
import { toast } from 'sonner';
import {
    Plus,
    Pencil,
    Trash2,
    RefreshCw,
    Search,
    Users,
    UserCheck,
    History,
    Settings,
    TrendingUp,
    DollarSign,
    Minus,
} from 'lucide-react';
import { employeeSalariesApi, salaryGradesApi, salaryComponentsApi } from '@/services/api';
import type { SalaryGrade, SalaryComponent, PaginationMeta } from '@/types';

interface EmployeeData {
    id: string;
    nip?: string;
    employee_id?: string;
    name: string;
    email?: string;
    employment_status?: string;
}

interface SalaryComponentItem {
    id: string;
    salary_component_id: string;
    salary_component?: {
        id: string;
        code: string;
        name: string;
        type: string;
        type_label: string;
        calculation_type: string;
    };
    value: number;
    value_formatted: string;
    is_active: boolean;
}

interface EmployeeSalary {
    id: string;
    employee_type: 'teacher' | 'staff';
    employee_type_label: string;
    employee_id: string;
    employee?: EmployeeData;
    salary_grade_id: string;
    salary_grade?: {
        id: string;
        code: string;
        name: string;
        base_salary: number;
        base_salary_formatted: string;
    };
    base_salary: number;
    base_salary_formatted: string;
    ptkp_status: string;
    effective_date: string;
    end_date?: string;
    is_current: boolean;
    notes?: string;
    components?: SalaryComponentItem[];
    total_earnings?: number;
    total_deductions?: number;
    total_earnings_formatted?: string;
    total_deductions_formatted?: string;
    components_count?: number;
    created_at: string;
    updated_at: string;
}

interface AvailableEmployee {
    id: string;
    type: 'teacher' | 'staff';
    type_label: string;
    identifier: string;
    name: string;
    email?: string;
    employment_status?: string;
}

interface SalaryHistoryItem {
    id: string;
    change_type: string;
    change_type_label: string;
    old_grade?: { id: string; code: string; name: string };
    new_grade?: { id: string; code: string; name: string };
    old_base_salary?: number;
    old_base_salary_formatted?: string;
    new_base_salary: number;
    new_base_salary_formatted: string;
    salary_difference: number;
    salary_difference_formatted: string;
    percentage_change: number;
    effective_date: string;
    reason?: string;
    changed_by?: { id: string; name: string };
    created_at: string;
}

interface PtkpStatus {
    code: string;
    label: string;
}

interface SalarySummary {
    total_employees: number;
    total_teachers: number;
    total_staff: number;
    total_base_salary: number;
    total_base_salary_formatted: string;
    average_base_salary: number;
    average_base_salary_formatted: string;
    by_grade: { grade: string; count: number; total: number; total_formatted: string }[];
}

interface SalaryForm {
    employee_type: 'teacher' | 'staff' | '';
    employee_id: string;
    salary_grade_id: string;
    base_salary: string;
    ptkp_status: string;
    effective_date: string;
    end_date: string;
    is_current: boolean;
    notes: string;
    components: { salary_component_id: string; value: string; is_active: boolean }[];
}

interface ComponentForm {
    salary_component_id: string;
    value: string;
    is_active: boolean;
}

const emptyForm: SalaryForm = {
    employee_type: '',
    employee_id: '',
    salary_grade_id: '',
    base_salary: '',
    ptkp_status: 'TK/0',
    effective_date: new Date().toISOString().split('T')[0],
    end_date: '',
    is_current: true,
    notes: '',
    components: [],
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
    const num = value.replace(/\D/g, '');
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseCurrency(value: string): number {
    return parseFloat(value.replace(/\./g, '')) || 0;
}

export default function EmployeeSalaries() {
    const [items, setItems] = useState<EmployeeSalary[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [filterType, setFilterType] = useState<string>('');
    const [filterGrade, setFilterGrade] = useState<string>('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<EmployeeSalary | null>(null);
    const [form, setForm] = useState<SalaryForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<EmployeeSalary | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [detailItem, setDetailItem] = useState<EmployeeSalary | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [historyItems, setHistoryItems] = useState<SalaryHistoryItem[]>([]);
    const [loadingHistory, setLoadingHistory] = useState(false);

    const [grades, setGrades] = useState<SalaryGrade[]>([]);
    const [components, setComponents] = useState<SalaryComponent[]>([]);
    const [availableEmployees, setAvailableEmployees] = useState<AvailableEmployee[]>([]);
    const [ptkpStatuses, setPtkpStatuses] = useState<PtkpStatus[]>([]);
    const [summary, setSummary] = useState<SalarySummary | null>(null);
    const [, setLoadingSummary] = useState(false);

    const [componentsOpen, setComponentsOpen] = useState(false);
    const [componentsList, setComponentsList] = useState<ComponentForm[]>([]);
    const [savingComponents, setSavingComponents] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterType) params.employee_type = filterType;
            if (filterGrade) params.salary_grade_id = filterGrade;

            const response = await employeeSalariesApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data gaji karyawan');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterType, filterGrade]);

    const fetchGrades = async () => {
        try {
            const response = await salaryGradesApi.list({ per_page: 100, is_active: true });
            setGrades(response.data.data.data ?? []);
        } catch {
            console.error('Failed to load grades');
        }
    };

    const fetchComponents = async () => {
        try {
            const response = await salaryComponentsApi.list({ per_page: 100, is_active: true });
            setComponents(response.data.data.data ?? []);
        } catch {
            console.error('Failed to load components');
        }
    };

    const fetchAvailableEmployees = async (type?: string) => {
        try {
            const params: Record<string, string> = {};
            if (type) params.type = type;
            const response = await employeeSalariesApi.availableEmployees(params);
            setAvailableEmployees(response.data.data.data ?? []);
        } catch {
            console.error('Failed to load available employees');
        }
    };

    const fetchPtkpStatuses = async () => {
        try {
            const response = await employeeSalariesApi.ptkpStatuses();
            setPtkpStatuses(response.data.data.data ?? []);
        } catch {
            console.error('Failed to load PTKP statuses');
        }
    };

    const fetchSummary = async () => {
        setLoadingSummary(true);
        try {
            const response = await employeeSalariesApi.summary();
            setSummary(response.data.data ?? null);
        } catch {
            console.error('Failed to load summary');
        } finally {
            setLoadingSummary(false);
        }
    };

    const fetchHistory = async (salaryId: string) => {
        setLoadingHistory(true);
        try {
            const response = await employeeSalariesApi.history(salaryId);
            setHistoryItems(response.data.data.data ?? []);
        } catch {
            console.error('Failed to load history');
        } finally {
            setLoadingHistory(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    useEffect(() => {
        fetchGrades();
        fetchComponents();
        fetchPtkpStatuses();
        fetchSummary();
    }, []);

    const openCreate = () => {
        setEditingItem(null);
        setForm(emptyForm);
        fetchAvailableEmployees();
        setFormOpen(true);
    };

    const openEdit = (item: EmployeeSalary) => {
        setEditingItem(item);
        setForm({
            employee_type: item.employee_type,
            employee_id: item.employee_id,
            salary_grade_id: item.salary_grade_id,
            base_salary: formatCurrency(String(item.base_salary)),
            ptkp_status: item.ptkp_status,
            effective_date: item.effective_date,
            end_date: item.end_date ?? '',
            is_current: item.is_current,
            notes: item.notes ?? '',
            components: item.components?.map(c => ({
                salary_component_id: c.salary_component_id,
                value: formatCurrency(String(c.value)),
                is_active: c.is_active,
            })) ?? [],
        });
        setFormOpen(true);
    };

    const openDetail = (item: EmployeeSalary) => {
        setDetailItem(item);
        setDetailOpen(true);
        fetchHistory(item.id);
    };

    const openComponents = (item: EmployeeSalary) => {
        setDetailItem(item);
        setComponentsList(item.components?.map(c => ({
            salary_component_id: c.salary_component_id,
            value: formatCurrency(String(c.value)),
            is_active: c.is_active,
        })) ?? []);
        setComponentsOpen(true);
    };

    const handleGradeChange = (gradeId: string) => {
        const grade = grades.find(g => g.id === gradeId);
        setForm(prev => ({
            ...prev,
            salary_grade_id: gradeId,
            base_salary: grade ? formatCurrency(String(grade.base_salary)) : prev.base_salary,
        }));
    };

    const handleEmployeeTypeChange = (type: 'teacher' | 'staff') => {
        setForm(prev => ({ ...prev, employee_type: type, employee_id: '' }));
        fetchAvailableEmployees(type);
    };

    const addComponent = () => {
        setForm(prev => ({
            ...prev,
            components: [...prev.components, { salary_component_id: '', value: '', is_active: true }],
        }));
    };

    const removeComponent = (index: number) => {
        setForm(prev => ({
            ...prev,
            components: prev.components.filter((_, i) => i !== index),
        }));
    };

    const updateComponent = (index: number, field: keyof ComponentForm, value: string | boolean) => {
        setForm(prev => ({
            ...prev,
            components: prev.components.map((c, i) => i === index ? { ...c, [field]: value } : c),
        }));
    };

    const handleSubmit = async () => {
        if (!form.employee_type || !form.employee_id || !form.salary_grade_id || !form.base_salary) {
            toast.error('Karyawan, golongan gaji, dan gaji pokok wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                employee_type: form.employee_type,
                employee_id: form.employee_id,
                salary_grade_id: form.salary_grade_id,
                base_salary: parseCurrency(form.base_salary),
                ptkp_status: form.ptkp_status,
                effective_date: form.effective_date,
                end_date: form.end_date || null,
                is_current: form.is_current,
                notes: form.notes.trim() || null,
                components: form.components
                    .filter(c => c.salary_component_id && c.value)
                    .map(c => ({
                        salary_component_id: c.salary_component_id,
                        value: parseCurrency(c.value),
                        is_active: c.is_active,
                    })),
            };

            if (editingItem) {
                await employeeSalariesApi.update(editingItem.id, payload);
                toast.success('Pengaturan gaji berhasil diperbarui');
            } else {
                await employeeSalariesApi.create(payload);
                toast.success('Pengaturan gaji berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
            fetchSummary();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan pengaturan gaji'));
        } finally {
            setSaving(false);
        }
    };

    const handleSaveComponents = async () => {
        if (!detailItem) return;

        setSavingComponents(true);
        try {
            const payload = {
                components: componentsList
                    .filter(c => c.salary_component_id && c.value)
                    .map(c => ({
                        salary_component_id: c.salary_component_id,
                        value: parseCurrency(c.value),
                        is_active: c.is_active,
                    })),
            };

            await employeeSalariesApi.syncComponents(detailItem.id, payload);
            toast.success('Komponen gaji berhasil diperbarui');
            setComponentsOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan komponen gaji'));
        } finally {
            setSavingComponents(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await employeeSalariesApi.delete(deletingItem.id);
            toast.success('Pengaturan gaji berhasil dihapus');
            setDeletingItem(null);
            fetchData();
            fetchSummary();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus pengaturan gaji'));
        } finally {
            setDeleting(false);
        }
    };

    const addComponentToList = () => {
        setComponentsList(prev => [...prev, { salary_component_id: '', value: '', is_active: true }]);
    };

    const removeComponentFromList = (index: number) => {
        setComponentsList(prev => prev.filter((_, i) => i !== index));
    };

    const updateComponentInList = (index: number, field: keyof ComponentForm, value: string | boolean) => {
        setComponentsList(prev => prev.map((c, i) => i === index ? { ...c, [field]: value } : c));
    };

    const filteredEmployees = form.employee_type
        ? availableEmployees.filter(e => e.type === form.employee_type)
        : availableEmployees;

    return (
        <MainLayout title="Gaji Karyawan">
            <Head title="Payroll - Gaji Karyawan" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Gaji Karyawan</h1>
                        <p className="text-muted-foreground">
                            Kelola pengaturan gaji untuk guru dan staf
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Gaji
                    </Button>
                </div>

                {/* Summary Cards */}
                {summary && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Karyawan</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{summary.total_employees}</div>
                                <p className="text-xs text-muted-foreground">
                                    {summary.total_teachers} guru, {summary.total_staff} staf
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Gaji Pokok</CardTitle>
                                <DollarSign className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{summary.total_base_salary_formatted}</div>
                                <p className="text-xs text-muted-foreground">per bulan</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Rata-rata Gaji</CardTitle>
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{summary.average_base_salary_formatted}</div>
                                <p className="text-xs text-muted-foreground">per karyawan</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Golongan</CardTitle>
                                <UserCheck className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{summary.by_grade.length}</div>
                                <p className="text-xs text-muted-foreground">golongan aktif</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Gaji Karyawan</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} pengaturan gaji terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama atau NIP..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select value={filterType} onValueChange={(v) => { setFilterType(v); setPage(1); }}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Semua Tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Tipe</SelectItem>
                                    <SelectItem value="teacher">Guru</SelectItem>
                                    <SelectItem value="staff">Staf</SelectItem>
                                </SelectContent>
                            </Select>
                            <Select value={filterGrade} onValueChange={(v) => { setFilterGrade(v); setPage(1); }}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Semua Golongan" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Golongan</SelectItem>
                                    {grades.map((g) => (
                                        <SelectItem key={g.id} value={g.id}>{g.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="icon" onClick={fetchData} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : items.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data gaji karyawan
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Karyawan</TableHead>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead>Golongan</TableHead>
                                            <TableHead className="text-right">Gaji Pokok</TableHead>
                                            <TableHead>Status PTKP</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-[140px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{item.employee?.name ?? '-'}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {item.employee?.nip ?? item.employee?.employee_id ?? '-'}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={item.employee_type === 'teacher' ? 'default' : 'secondary'}>
                                                        {item.employee_type_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-mono text-sm">{item.salary_grade?.code}</div>
                                                        <div className="text-sm text-muted-foreground">{item.salary_grade?.name}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {item.base_salary_formatted}
                                                </TableCell>
                                                <TableCell>{item.ptkp_status}</TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_current ? 'default' : 'outline'}>
                                                        {item.is_current ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openDetail(item)} title="Detail & Riwayat">
                                                            <History className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => openComponents(item)} title="Komponen">
                                                            <Settings className="h-4 w-4" />
                                                        </Button>
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(item)} title="Edit">
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingItem(item)}
                                                            title="Hapus"
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
                                    <Button variant="outline" size="sm" disabled={page <= 1 || loading} onClick={() => setPage((p) => p - 1)}>
                                        Sebelumnya
                                    </Button>
                                    <Button variant="outline" size="sm" disabled={page >= meta.last_page || loading} onClick={() => setPage((p) => p + 1)}>
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Gaji Karyawan' : 'Tambah Gaji Karyawan'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui pengaturan gaji karyawan' : 'Tambahkan pengaturan gaji baru untuk karyawan'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        {!editingItem && (
                            <>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Tipe Karyawan *</Label>
                                        <Select value={form.employee_type} onValueChange={(v) => handleEmployeeTypeChange(v as 'teacher' | 'staff')}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih tipe" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="teacher">Guru</SelectItem>
                                                <SelectItem value="staff">Staf</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Karyawan *</Label>
                                        <Select
                                            value={form.employee_id}
                                            onValueChange={(v) => setForm(prev => ({ ...prev, employee_id: v }))}
                                            disabled={!form.employee_type}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih karyawan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {filteredEmployees.map((e) => (
                                                    <SelectItem key={e.id} value={e.id}>
                                                        {e.name} ({e.identifier})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </>
                        )}

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Golongan Gaji *</Label>
                                <Select value={form.salary_grade_id} onValueChange={handleGradeChange}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih golongan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {grades.map((g) => (
                                            <SelectItem key={g.id} value={g.id}>
                                                {g.code} - {g.name} ({g.base_salary_formatted})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Gaji Pokok (Rp) *</Label>
                                <Input
                                    value={form.base_salary}
                                    onChange={(e) => setForm(prev => ({ ...prev, base_salary: formatCurrency(e.target.value) }))}
                                    placeholder="0"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Status PTKP</Label>
                                <Select value={form.ptkp_status} onValueChange={(v) => setForm(prev => ({ ...prev, ptkp_status: v }))}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ptkpStatuses.map((s) => (
                                            <SelectItem key={s.code} value={s.code}>
                                                {s.code} - {s.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Tanggal Efektif *</Label>
                                <DatePicker
                                    value={form.effective_date}
                                    onChange={(value) => setForm(prev => ({ ...prev, effective_date: value }))}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tanggal Berakhir</Label>
                                <DatePicker
                                    value={form.end_date}
                                    onChange={(value) => setForm(prev => ({ ...prev, end_date: value }))}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                            <div className="flex items-center space-x-2 pt-6">
                                <Switch
                                    id="is_current"
                                    checked={form.is_current}
                                    onCheckedChange={(checked) => setForm(prev => ({ ...prev, is_current: checked }))}
                                />
                                <Label htmlFor="is_current">Gaji Aktif</Label>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Catatan</Label>
                            <Textarea
                                value={form.notes}
                                onChange={(e) => setForm(prev => ({ ...prev, notes: e.target.value }))}
                                placeholder="Catatan tambahan (opsional)"
                                rows={2}
                            />
                        </div>

                        {/* Components Section */}
                        <div className="space-y-3 pt-4 border-t">
                            <div className="flex items-center justify-between">
                                <Label className="text-base font-semibold">Komponen Gaji</Label>
                                <Button type="button" variant="outline" size="sm" onClick={addComponent}>
                                    <Plus className="h-4 w-4 mr-1" /> Tambah Komponen
                                </Button>
                            </div>
                            {form.components.length === 0 ? (
                                <p className="text-sm text-muted-foreground">Belum ada komponen gaji. Klik tombol di atas untuk menambahkan.</p>
                            ) : (
                                <div className="space-y-2">
                                    {form.components.map((comp, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            <Select
                                                value={comp.salary_component_id}
                                                onValueChange={(v) => updateComponent(index, 'salary_component_id', v)}
                                            >
                                                <SelectTrigger className="flex-1">
                                                    <SelectValue placeholder="Pilih komponen" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {components.map((c) => (
                                                        <SelectItem key={c.id} value={c.id}>
                                                            {c.name} ({c.type_label})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <Input
                                                className="w-32"
                                                value={comp.value}
                                                onChange={(e) => updateComponent(index, 'value', formatCurrency(e.target.value))}
                                                placeholder="Nilai"
                                            />
                                            <Switch
                                                checked={comp.is_active}
                                                onCheckedChange={(checked) => updateComponent(index, 'is_active', checked)}
                                            />
                                            <Button type="button" variant="ghost" size="icon" onClick={() => removeComponent(index)}>
                                                <Minus className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>Batal</Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving ? 'Menyimpan...' : editingItem ? 'Perbarui' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deletingItem} onOpenChange={() => setDeletingItem(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Pengaturan Gaji?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Pengaturan gaji untuk <strong>{deletingItem?.employee?.name}</strong> akan dihapus.
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} disabled={deleting} className="bg-red-600 hover:bg-red-700">
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Detail & History Sheet */}
            <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
                <SheetContent className="w-full sm:max-w-xl overflow-y-auto">
                    <SheetHeader>
                        <SheetTitle>Detail Gaji Karyawan</SheetTitle>
                        <SheetDescription>
                            {detailItem?.employee?.name} - {detailItem?.employee_type_label}
                        </SheetDescription>
                    </SheetHeader>
                    {detailItem && (
                        <Tabs defaultValue="info" className="mt-6">
                            <TabsList className="grid w-full grid-cols-2">
                                <TabsTrigger value="info">Informasi</TabsTrigger>
                                <TabsTrigger value="history">Riwayat</TabsTrigger>
                            </TabsList>
                            <TabsContent value="info" className="space-y-4 mt-4">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Golongan</p>
                                        <p className="font-medium">{detailItem.salary_grade?.code} - {detailItem.salary_grade?.name}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Gaji Pokok</p>
                                        <p className="font-medium font-mono">{detailItem.base_salary_formatted}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Status PTKP</p>
                                        <p className="font-medium">{detailItem.ptkp_status}</p>
                                    </div>
                                    <div>
                                        <p className="text-sm text-muted-foreground">Tanggal Efektif</p>
                                        <p className="font-medium">{detailItem.effective_date}</p>
                                    </div>
                                </div>
                                {detailItem.components && detailItem.components.length > 0 && (
                                    <div className="space-y-2 pt-4 border-t">
                                        <p className="text-sm font-semibold">Komponen Gaji</p>
                                        {detailItem.components.map((comp) => (
                                            <div key={comp.id} className="flex items-center justify-between text-sm">
                                                <span className={comp.salary_component?.type === 'deduction' ? 'text-red-600' : 'text-green-600'}>
                                                    {comp.salary_component?.name}
                                                </span>
                                                <span className="font-mono">{comp.value_formatted}</span>
                                            </div>
                                        ))}
                                        <div className="flex items-center justify-between text-sm pt-2 border-t">
                                            <span className="font-medium text-green-600">Total Tunjangan</span>
                                            <span className="font-mono">{detailItem.total_earnings_formatted ?? '-'}</span>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium text-red-600">Total Potongan</span>
                                            <span className="font-mono">{detailItem.total_deductions_formatted ?? '-'}</span>
                                        </div>
                                    </div>
                                )}
                            </TabsContent>
                            <TabsContent value="history" className="mt-4">
                                {loadingHistory ? (
                                    <p className="text-center text-muted-foreground py-4">Memuat riwayat...</p>
                                ) : historyItems.length === 0 ? (
                                    <p className="text-center text-muted-foreground py-4">Belum ada riwayat perubahan</p>
                                ) : (
                                    <div className="space-y-4">
                                        {historyItems.map((h) => (
                                            <div key={h.id} className="border rounded-lg p-3 space-y-2">
                                                <div className="flex items-center justify-between">
                                                    <Badge variant={h.change_type === 'promotion' ? 'default' : h.change_type === 'demotion' ? 'destructive' : 'secondary'}>
                                                        {h.change_type_label}
                                                    </Badge>
                                                    <span className="text-sm text-muted-foreground">{new Date(h.created_at).toLocaleDateString('id-ID')}</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-sm">
                                                    <span className="text-muted-foreground">{h.old_base_salary_formatted ?? '-'}</span>
                                                    <span>&rarr;</span>
                                                    <span className="font-medium">{h.new_base_salary_formatted}</span>
                                                    <span className={h.salary_difference >= 0 ? 'text-green-600' : 'text-red-600'}>
                                                        ({h.salary_difference_formatted})
                                                    </span>
                                                </div>
                                                {h.reason && <p className="text-sm text-muted-foreground">{h.reason}</p>}
                                                {h.changed_by && (
                                                    <p className="text-xs text-muted-foreground">Diubah oleh: {h.changed_by.name}</p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </TabsContent>
                        </Tabs>
                    )}
                </SheetContent>
            </Sheet>

            {/* Components Dialog */}
            <Dialog open={componentsOpen} onOpenChange={setComponentsOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Komponen Gaji</DialogTitle>
                        <DialogDescription>
                            Kelola komponen gaji untuk {detailItem?.employee?.name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3 py-4">
                        <div className="flex justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={addComponentToList}>
                                <Plus className="h-4 w-4 mr-1" /> Tambah
                            </Button>
                        </div>
                        {componentsList.length === 0 ? (
                            <p className="text-sm text-muted-foreground text-center py-4">Belum ada komponen</p>
                        ) : (
                            <div className="space-y-2">
                                {componentsList.map((comp, index) => (
                                    <div key={index} className="flex items-center gap-2">
                                        <Select
                                            value={comp.salary_component_id}
                                            onValueChange={(v) => updateComponentInList(index, 'salary_component_id', v)}
                                        >
                                            <SelectTrigger className="flex-1">
                                                <SelectValue placeholder="Pilih komponen" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {components.map((c) => (
                                                    <SelectItem key={c.id} value={c.id}>
                                                        {c.name} ({c.type_label})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Input
                                            className="w-28"
                                            value={comp.value}
                                            onChange={(e) => updateComponentInList(index, 'value', formatCurrency(e.target.value))}
                                            placeholder="Nilai"
                                        />
                                        <Switch
                                            checked={comp.is_active}
                                            onCheckedChange={(checked) => updateComponentInList(index, 'is_active', checked)}
                                        />
                                        <Button type="button" variant="ghost" size="icon" onClick={() => removeComponentFromList(index)}>
                                            <Minus className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setComponentsOpen(false)} disabled={savingComponents}>Batal</Button>
                        <Button onClick={handleSaveComponents} disabled={savingComponents}>
                            {savingComponents ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
