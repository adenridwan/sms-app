import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { toast } from 'sonner';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import {
    RefreshCw,
    Search,
    Eye,
    ArrowLeft,
    Plus,
    Trash2,
    Users,
    DollarSign,
    TrendingDown,
    Wallet,
    Pencil,
    History,
    FileDown,
    Printer,
    MessageCircle,
    Send,
    Phone,
} from 'lucide-react';
import { payrollPeriodsApi, payrollSlipsApi } from '@/services/api';
import type { PaginationMeta } from '@/types';

interface PayrollSlip {
    id: string;
    payroll_period_id: string;
    employee_type: 'teacher' | 'staff';
    employee_type_label: string;
    employee_id: string;
    employee_name: string;
    employee_identifier?: string;
    salary_grade_code?: string;
    ptkp_status: string;
    base_salary: number;
    base_salary_formatted: string;
    total_allowances: number;
    total_allowances_formatted: string;
    gross_salary: number;
    gross_salary_formatted: string;
    bpjs_kesehatan: number;
    bpjs_kesehatan_formatted: string;
    bpjs_jht: number;
    bpjs_jht_formatted: string;
    bpjs_jp: number;
    bpjs_jp_formatted: string;
    pph21: number;
    pph21_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    net_salary: number;
    net_salary_formatted: string;
    status: string;
    status_label: string;
    is_editable: boolean;
    // Attendance data
    working_days: number;
    days_present: number;
    days_absent: number;
    days_late: number;
    days_leave: number;
    items?: SlipItem[];
    earnings?: { id: string; code: string; name: string; amount: number; amount_formatted: string }[];
    deductions?: { id: string; code: string; name: string; amount: number; amount_formatted: string }[];
}

interface SlipItem {
    id: string;
    salary_component_id?: string;
    component_code: string;
    component_name: string;
    type: 'earning' | 'deduction';
    type_label: string;
    category: string;
    category_label: string;
    amount: number;
    amount_formatted: string;
    quantity: number;
    rate?: number;
    rate_formatted?: string;
    is_taxable: boolean;
    is_auto_calculated: boolean;
    notes?: string;
}

interface AuditLog {
    id: string;
    item: { id: string; component_code: string; component_name: string } | null;
    field: string;
    field_label: string;
    old_value: string | null;
    new_value: string | null;
    old_value_formatted: string;
    new_value_formatted: string;
    reason: string;
    changed_by: { id: string; name: string } | null;
    changed_at: string;
}

interface SalaryComponent {
    id: string;
    code: string;
    name: string;
    type: 'earning' | 'deduction';
    type_label: string;
    calculation_type: string;
    calculation_type_label: string;
    default_value: number;
    default_value_formatted: string;
    is_taxable: boolean;
    is_active: boolean;
}

interface PayrollPeriod {
    id: string;
    name: string;
    period_label: string;
    status: string;
    status_label: string;
    is_editable: boolean;
    total_gross: number;
    total_gross_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    total_net: number;
    total_net_formatted: string;
    employee_count: number;
}

interface Props {
    periodId: string;
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

export default function PayrollSlips({ periodId }: Props) {
    const [period, setPeriod] = useState<PayrollPeriod | null>(null);
    const [items, setItems] = useState<PayrollSlip[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [filterType, setFilterType] = useState<string>('');
    const [page, setPage] = useState(1);

    const [detailSlip, setDetailSlip] = useState<PayrollSlip | null>(null);
    const [detailOpen, setDetailOpen] = useState(false);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const [addItemOpen, setAddItemOpen] = useState(false);
    const [addItemMode, setAddItemMode] = useState<'select' | 'manual'>('select');
    const [components, setComponents] = useState<SalaryComponent[]>([]);
    const [loadingComponents, setLoadingComponents] = useState(false);
    const [selectedComponentId, setSelectedComponentId] = useState<string>('');
    const [selectedComponent, setSelectedComponent] = useState<SalaryComponent | null>(null);
    const [addItemForm, setAddItemForm] = useState({
        component_code: '',
        component_name: '',
        type: 'earning' as 'earning' | 'deduction',
        category: 'other' as 'basic' | 'allowance' | 'attendance' | 'statutory' | 'tax' | 'other',
        quantity: '',
        rate: '',
        amount: '',
        notes: '',
    });
    const [savingItem, setSavingItem] = useState(false);

    // Edit item state
    const [editItemOpen, setEditItemOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<SlipItem | null>(null);
    const [editItemForm, setEditItemForm] = useState({
        quantity: '',
        rate: '',
        amount: '',
        reason: '',
    });
    const [savingEdit, setSavingEdit] = useState(false);

    // Audit log state
    const [audits, setAudits] = useState<AuditLog[]>([]);
    const [loadingAudits, setLoadingAudits] = useState(false);

    // WhatsApp state
    const [whatsAppOpen, setWhatsAppOpen] = useState(false);
    const [whatsAppPhone, setWhatsAppPhone] = useState('');
    const [loadingPhone, setLoadingPhone] = useState(false);
    const [sendingWhatsApp, setSendingWhatsApp] = useState(false);

    const fetchPeriod = async () => {
        try {
            const response = await payrollPeriodsApi.get(periodId);
            setPeriod(response.data.data);
        } catch {
            toast.error('Gagal memuat data periode');
        }
    };

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterType) params.employee_type = filterType;

            const response = await payrollSlipsApi.list(periodId, params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data slip gaji');
        } finally {
            setLoading(false);
        }
    }, [periodId, page, search, filterType]);

    useEffect(() => {
        fetchPeriod();
    }, [periodId]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const openDetail = async (slip: PayrollSlip) => {
        setDetailSlip(slip);
        setDetailOpen(true);
        setLoadingDetail(true);
        try {
            const response = await payrollSlipsApi.get(slip.id);
            setDetailSlip(response.data.data);
        } catch {
            toast.error('Gagal memuat detail slip');
        } finally {
            setLoadingDetail(false);
        }
    };

    const fetchComponents = async () => {
        setLoadingComponents(true);
        try {
            const response = await payrollSlipsApi.getComponents();
            setComponents(response.data.data);
        } catch {
            toast.error('Gagal memuat komponen gaji');
        } finally {
            setLoadingComponents(false);
        }
    };

    const openAddItem = () => {
        if (!detailSlip) return;
        setAddItemMode('select');
        setSelectedComponentId('');
        setAddItemForm({
            component_code: '',
            component_name: '',
            type: 'earning',
            category: 'other',
            quantity: '',
            rate: '',
            amount: '',
            notes: '',
        });
        setAddItemOpen(true);
        fetchComponents();
    };

    const handleSelectComponent = (componentId: string) => {
        setSelectedComponentId(componentId);
        if (componentId) {
            const comp = components.find(c => c.id === componentId);
            if (comp) {
                setSelectedComponent(comp);

                // Pre-fill rate dari default_value komponen
                const rate = comp.default_value > 0 ? formatCurrency(comp.default_value.toString()) : '';

                // Untuk fixed: langsung pakai default_value sebagai amount
                // Untuk per_day atau lainnya: biarkan kosong, user isi qty untuk hitung
                let calculatedAmount = '';
                if (comp.calculation_type === 'fixed' && comp.default_value > 0) {
                    calculatedAmount = formatCurrency(comp.default_value.toString());
                }

                setAddItemForm(prev => ({
                    ...prev,
                    component_code: comp.code,
                    component_name: comp.name,
                    type: comp.type,
                    rate: rate,
                    amount: calculatedAmount,
                    quantity: '',
                }));
            }
        } else {
            setSelectedComponent(null);
            setAddItemForm(prev => ({
                ...prev,
                component_code: '',
                component_name: '',
                type: 'earning',
                rate: '',
                amount: '',
                quantity: '',
            }));
        }
    };

    const handleAddItem = async () => {
        if (!detailSlip) return;

        // Validasi berdasarkan mode
        if (addItemMode === 'select') {
            if (!selectedComponentId || !selectedComponent) {
                toast.error('Pilih komponen dari daftar');
                return;
            }
        } else {
            if (!addItemForm.component_code || !addItemForm.component_name) {
                toast.error('Kode dan nama wajib diisi');
                return;
            }
        }

        // Hitung amount: gunakan qty × rate jika keduanya diisi, atau amount langsung
        const qty = parseFloat(addItemForm.quantity) || 0;
        const rate = parseFloat(addItemForm.rate.replace(/\./g, '')) || 0;
        const directAmount = parseFloat(addItemForm.amount.replace(/\./g, '')) || 0;
        const calculatedAmount = qty > 0 && rate > 0 ? qty * rate : directAmount;

        if (calculatedAmount <= 0) {
            toast.error('Jumlah harus lebih dari 0');
            return;
        }

        setSavingItem(true);
        try {
            const payload: {
                salary_component_id?: string;
                component_code?: string;
                component_name?: string;
                type?: 'earning' | 'deduction';
                category?: string;
                amount: number;
                quantity?: number;
                rate?: number;
                notes?: string;
            } = {
                amount: calculatedAmount,
            };

            if (addItemMode === 'select') {
                // Mode select: kirim salary_component_id
                payload.salary_component_id = selectedComponentId;
            } else {
                // Mode manual: kirim data komponen baru
                payload.component_code = addItemForm.component_code.toUpperCase();
                payload.component_name = addItemForm.component_name;
                payload.type = addItemForm.type;
                payload.category = addItemForm.category;
            }

            // Kirim quantity dan rate jika diisi
            if (qty > 0) payload.quantity = qty;
            if (rate > 0) payload.rate = rate;
            if (addItemForm.notes) payload.notes = addItemForm.notes;

            const response = await payrollSlipsApi.addItem(detailSlip.id, payload);
            setDetailSlip(response.data.data);
            setAddItemOpen(false);
            toast.success('Item berhasil ditambahkan');
            fetchData();
            fetchPeriod();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menambahkan item'));
        } finally {
            setSavingItem(false);
        }
    };

    const handleRemoveItem = async (itemId: string) => {
        if (!detailSlip) return;

        try {
            const response = await payrollSlipsApi.removeItem(detailSlip.id, itemId);
            setDetailSlip(response.data.data);
            toast.success('Item berhasil dihapus');
            fetchData();
            fetchPeriod();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus item'));
        }
    };

    const openEditItem = (item: SlipItem) => {
        setEditingItem(item);
        setEditItemForm({
            quantity: item.quantity?.toString() || '',
            rate: item.rate?.toString() || '',
            amount: item.amount?.toString() || '',
            reason: '',
        });
        setEditItemOpen(true);
    };

    const handleEditItem = async () => {
        if (!detailSlip || !editingItem) return;

        if (!editItemForm.reason.trim()) {
            toast.error('Alasan perubahan wajib diisi');
            return;
        }

        setSavingEdit(true);
        try {
            const payload: {
                quantity?: number;
                rate?: number;
                amount?: number;
                reason: string;
            } = {
                reason: editItemForm.reason.trim(),
            };

            if (editItemForm.quantity) {
                payload.quantity = parseFloat(editItemForm.quantity);
            }
            if (editItemForm.rate) {
                payload.rate = parseFloat(editItemForm.rate.replace(/\./g, ''));
            }
            if (editItemForm.amount) {
                payload.amount = parseFloat(editItemForm.amount.replace(/\./g, ''));
            }

            const response = await payrollSlipsApi.updateItem(detailSlip.id, editingItem.id, payload);
            setDetailSlip(response.data.data);
            setEditItemOpen(false);
            toast.success('Item berhasil diperbarui');
            fetchData();
            fetchPeriod();
            loadAudits();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal memperbarui item'));
        } finally {
            setSavingEdit(false);
        }
    };

    const loadAudits = async () => {
        if (!detailSlip) return;

        setLoadingAudits(true);
        try {
            const response = await payrollSlipsApi.getAudits(detailSlip.id);
            setAudits(response.data.data.audits);
        } catch {
            // Silent fail - audit log is optional
        } finally {
            setLoadingAudits(false);
        }
    };

    const openWhatsAppDialog = async () => {
        if (!detailSlip) return;

        setLoadingPhone(true);
        setWhatsAppOpen(true);

        try {
            const response = await payrollSlipsApi.getEmployeePhone(detailSlip.id);
            const data = response.data.data;
            setWhatsAppPhone(data.phone ?? '');
        } catch {
            toast.error('Gagal memuat nomor telepon');
        } finally {
            setLoadingPhone(false);
        }
    };

    const handleSendWhatsApp = async () => {
        if (!detailSlip) return;

        if (!whatsAppPhone) {
            toast.error('Nomor telepon tidak tersedia');
            return;
        }

        setSendingWhatsApp(true);
        try {
            const response = await payrollSlipsApi.sendWhatsApp(detailSlip.id, whatsAppPhone);
            toast.success(response.data.message || 'Slip gaji berhasil dikirim');
            setWhatsAppOpen(false);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengirim slip gaji'));
        } finally {
            setSendingWhatsApp(false);
        }
    };

    const formatCurrency = (value: string): string => {
        const num = value.replace(/\D/g, '');
        return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    };

    const goBack = () => {
        router.visit('/payroll/periods');
    };

    return (
        <MainLayout title="Slip Gaji">
            <Head title="Payroll - Slip Gaji" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="icon" onClick={goBack}>
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Slip Gaji</h1>
                            <p className="text-muted-foreground">
                                {period?.period_label ?? 'Memuat...'} - {period?.status_label}
                            </p>
                        </div>
                    </div>
                </div>

                {/* Summary Cards */}
                {period && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Karyawan</CardTitle>
                                <Users className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{period.employee_count}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Bruto</CardTitle>
                                <DollarSign className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{period.total_gross_formatted}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Potongan</CardTitle>
                                <TrendingDown className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{period.total_deductions_formatted}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Bersih</CardTitle>
                                <Wallet className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-600">{period.total_net_formatted}</div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Slip Gaji</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} slip gaji` : 'Memuat data'}
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
                            <Button variant="outline" size="icon" onClick={fetchData} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : items.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data slip gaji. Generate slip dari halaman periode.
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Karyawan</TableHead>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead className="text-right">Gaji Pokok</TableHead>
                                            <TableHead className="text-right">Bruto</TableHead>
                                            <TableHead className="text-right">Potongan</TableHead>
                                            <TableHead className="text-right">Bersih</TableHead>
                                            <TableHead className="w-[80px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{item.employee_name}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {item.employee_identifier} | {item.salary_grade_code}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={item.employee_type === 'teacher' ? 'default' : 'secondary'}>
                                                        {item.employee_type_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {item.base_salary_formatted}
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {item.gross_salary_formatted}
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-red-600">
                                                    {item.total_deductions_formatted}
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-semibold text-green-600">
                                                    {item.net_salary_formatted}
                                                </TableCell>
                                                <TableCell>
                                                    <Button variant="ghost" size="icon" onClick={() => openDetail(item)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
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

            {/* Detail Sheet */}
            <Sheet open={detailOpen} onOpenChange={setDetailOpen}>
                <SheetContent className="w-full sm:max-w-xl overflow-y-auto">
                    <SheetHeader>
                        <SheetTitle>Detail Slip Gaji</SheetTitle>
                        <SheetDescription>
                            {detailSlip?.employee_name} - {detailSlip?.employee_type_label}
                        </SheetDescription>
                    </SheetHeader>
                    {loadingDetail ? (
                        <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                    ) : detailSlip && (
                        <div className="mt-6 space-y-6">
                            {/* Employee Info */}
                            <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground">NIP/ID</p>
                                    <p className="font-medium">{detailSlip.employee_identifier ?? '-'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Golongan</p>
                                    <p className="font-medium">{detailSlip.salary_grade_code ?? '-'}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Status PTKP</p>
                                    <p className="font-medium">{detailSlip.ptkp_status}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground">Status</p>
                                    <Badge>{detailSlip.status_label}</Badge>
                                </div>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => window.open(payrollSlipsApi.getPreviewPdfUrl(detailSlip.id), '_blank')}
                                >
                                    <Printer className="h-4 w-4 mr-2" />
                                    Preview PDF
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    asChild
                                >
                                    <a href={payrollSlipsApi.getPdfUrl(detailSlip.id)} download>
                                        <FileDown className="h-4 w-4 mr-2" />
                                        Download PDF
                                    </a>
                                </Button>
                                <Button
                                    variant="default"
                                    size="sm"
                                    onClick={openWhatsAppDialog}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    <MessageCircle className="h-4 w-4 mr-2" />
                                    Kirim WA
                                </Button>
                            </div>

                            {/* Attendance Summary */}
                            {detailSlip.working_days > 0 && (
                                <div className="space-y-2">
                                    <h4 className="text-sm font-medium text-muted-foreground">Kehadiran</h4>
                                    <div className="grid grid-cols-5 gap-2 text-center text-sm">
                                        <div className="rounded-md border p-2">
                                            <p className="text-xs text-muted-foreground">Hari Kerja</p>
                                            <p className="text-lg font-semibold">{detailSlip.working_days}</p>
                                        </div>
                                        <div className="rounded-md border p-2 bg-green-50">
                                            <p className="text-xs text-muted-foreground">Hadir</p>
                                            <p className="text-lg font-semibold text-green-600">{detailSlip.days_present}</p>
                                        </div>
                                        <div className="rounded-md border p-2 bg-red-50">
                                            <p className="text-xs text-muted-foreground">Absen</p>
                                            <p className="text-lg font-semibold text-red-600">{detailSlip.days_absent}</p>
                                        </div>
                                        <div className="rounded-md border p-2 bg-yellow-50">
                                            <p className="text-xs text-muted-foreground">Telat</p>
                                            <p className="text-lg font-semibold text-yellow-600">{detailSlip.days_late}</p>
                                        </div>
                                        <div className="rounded-md border p-2 bg-blue-50">
                                            <p className="text-xs text-muted-foreground">Cuti</p>
                                            <p className="text-lg font-semibold text-blue-600">{detailSlip.days_leave}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            <Tabs defaultValue="items" onValueChange={(v) => v === 'history' && loadAudits()}>
                                <TabsList className="grid w-full grid-cols-2">
                                    <TabsTrigger value="items">Item Gaji</TabsTrigger>
                                    <TabsTrigger value="history">
                                        <History className="h-4 w-4 mr-1" /> Riwayat
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="items" className="space-y-4 mt-4">
                                    {/* Earnings */}
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <h4 className="font-semibold text-green-600">Pendapatan</h4>
                                            {detailSlip.is_editable && (
                                                <Button variant="outline" size="sm" onClick={openAddItem}>
                                                    <Plus className="h-4 w-4 mr-1" /> Tambah
                                                </Button>
                                            )}
                                        </div>
                                        <div className="space-y-1 rounded-md border p-3">
                                            {detailSlip.items?.filter(i => i.type === 'earning').map((item) => (
                                                <div key={item.id} className="flex items-center justify-between text-sm py-1">
                                                    <div>
                                                        <span>{item.component_name}</span>
                                                        {item.quantity > 0 && item.rate && (
                                                            <span className="text-xs text-muted-foreground ml-2">
                                                                ({item.quantity} × {item.rate_formatted})
                                                            </span>
                                                        )}
                                                        {!item.is_auto_calculated && (
                                                            <Badge variant="outline" className="ml-2 text-xs">Manual</Badge>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <span className="font-mono">{item.amount_formatted}</span>
                                                        {detailSlip.is_editable && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-6 w-6 text-muted-foreground hover:text-blue-600"
                                                                    onClick={() => openEditItem(item)}
                                                                >
                                                                    <Pencil className="h-3 w-3" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-6 w-6 text-muted-foreground hover:text-red-600"
                                                                    onClick={() => handleRemoveItem(item.id)}
                                                                >
                                                                    <Trash2 className="h-3 w-3" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                            <div className="flex items-center justify-between text-sm font-semibold pt-2 border-t">
                                                <span>Total Pendapatan</span>
                                                <span className="font-mono text-green-600">{detailSlip.gross_salary_formatted}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Deductions */}
                                    <div className="space-y-2">
                                        <h4 className="font-semibold text-red-600">Potongan</h4>
                                        <div className="space-y-1 rounded-md border p-3">
                                            {detailSlip.items?.filter(i => i.type === 'deduction').map((item) => (
                                                <div key={item.id} className="flex items-center justify-between text-sm py-1">
                                                    <div>
                                                        <span>{item.component_name}</span>
                                                        {!item.is_auto_calculated && (
                                                            <Badge variant="outline" className="ml-2 text-xs">Manual</Badge>
                                                        )}
                                                    </div>
                                                    <div className="flex items-center gap-1">
                                                        <span className="font-mono">{item.amount_formatted}</span>
                                                        {detailSlip.is_editable && !['BPJS_KES', 'BPJS_JHT', 'BPJS_JP', 'PPH21'].includes(item.component_code) && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-6 w-6 text-muted-foreground hover:text-blue-600"
                                                                    onClick={() => openEditItem(item)}
                                                                >
                                                                    <Pencil className="h-3 w-3" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-6 w-6 text-muted-foreground hover:text-red-600"
                                                                    onClick={() => handleRemoveItem(item.id)}
                                                                >
                                                                    <Trash2 className="h-3 w-3" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                            <div className="flex items-center justify-between text-sm font-semibold pt-2 border-t">
                                                <span>Total Potongan</span>
                                                <span className="font-mono text-red-600">{detailSlip.total_deductions_formatted}</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Net Salary */}
                                    <div className="rounded-md border bg-muted/50 p-4">
                                        <div className="flex items-center justify-between">
                                            <span className="font-semibold">Gaji Bersih (Take Home Pay)</span>
                                            <span className="text-xl font-bold text-green-600">{detailSlip.net_salary_formatted}</span>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="history" className="mt-4">
                                    {loadingAudits ? (
                                        <div className="py-8 text-center text-muted-foreground">Memuat riwayat...</div>
                                    ) : audits.length === 0 ? (
                                        <div className="py-8 text-center text-muted-foreground">
                                            Belum ada riwayat perubahan
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {audits.map((audit) => (
                                                <div key={audit.id} className="rounded-md border p-3 text-sm">
                                                    <div className="flex items-start justify-between">
                                                        <div>
                                                            <p className="font-medium">
                                                                {audit.item?.component_name ?? 'Item dihapus'}
                                                            </p>
                                                            <p className="text-muted-foreground">
                                                                {audit.field_label}: {audit.old_value_formatted} → {audit.new_value_formatted}
                                                            </p>
                                                        </div>
                                                        <span className="text-xs text-muted-foreground">{audit.changed_at}</span>
                                                    </div>
                                                    <p className="mt-2 text-muted-foreground italic">"{audit.reason}"</p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        oleh {audit.changed_by?.name ?? 'Unknown'}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </TabsContent>
                            </Tabs>
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            {/* Add Item Dialog */}
            <Dialog open={addItemOpen} onOpenChange={(open) => {
                setAddItemOpen(open);
                if (!open) {
                    // Reset state saat dialog ditutup
                    setSelectedComponentId('');
                    setSelectedComponent(null);
                    setAddItemMode('select');
                    setAddItemForm({
                        component_code: '',
                        component_name: '',
                        type: 'earning',
                        category: 'other',
                        quantity: '',
                        rate: '',
                        amount: '',
                        notes: '',
                    });
                }
            }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Tambah Item</DialogTitle>
                        <DialogDescription>
                            Pilih dari komponen yang ada atau buat baru
                        </DialogDescription>
                    </DialogHeader>
                    <Tabs value={addItemMode} onValueChange={(v) => {
                        setAddItemMode(v as 'select' | 'manual');
                        setSelectedComponentId('');
                        setSelectedComponent(null);
                        setAddItemForm(prev => ({
                            ...prev,
                            component_code: '',
                            component_name: '',
                            rate: '',
                            amount: '',
                            quantity: '',
                        }));
                    }}>
                        <TabsList className="grid w-full grid-cols-2">
                            <TabsTrigger value="select">Pilih Komponen</TabsTrigger>
                            <TabsTrigger value="manual">Buat Baru</TabsTrigger>
                        </TabsList>

                        <TabsContent value="select" className="space-y-4 mt-4">
                            {loadingComponents ? (
                                <div className="py-4 text-center text-muted-foreground">Memuat komponen...</div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="space-y-2">
                                        <Label>Pilih Komponen *</Label>
                                        <Select value={selectedComponentId} onValueChange={handleSelectComponent}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih komponen gaji..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {components.length === 0 ? (
                                                    <div className="py-4 text-center text-sm text-muted-foreground">
                                                        Belum ada komponen gaji. Buat di menu Komponen Gaji atau gunakan tab "Buat Baru".
                                                    </div>
                                                ) : (
                                                    <>
                                                        {components.filter(c => c.type === 'earning').length > 0 && (
                                                            <>
                                                                <div className="px-2 py-1.5 text-sm font-semibold text-green-600">
                                                                    Pendapatan
                                                                </div>
                                                                {components.filter(c => c.type === 'earning').map(comp => (
                                                                    <SelectItem key={comp.id} value={comp.id}>
                                                                        {comp.name} ({comp.code}) - {comp.calculation_type_label}
                                                                    </SelectItem>
                                                                ))}
                                                            </>
                                                        )}
                                                        {components.filter(c => c.type === 'deduction').length > 0 && (
                                                            <>
                                                                <div className="px-2 py-1.5 text-sm font-semibold text-red-600 border-t mt-1 pt-1">
                                                                    Potongan
                                                                </div>
                                                                {components.filter(c => c.type === 'deduction').map(comp => (
                                                                    <SelectItem key={comp.id} value={comp.id}>
                                                                        {comp.name} ({comp.code}) - {comp.calculation_type_label}
                                                                    </SelectItem>
                                                                ))}
                                                            </>
                                                        )}
                                                    </>
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    {selectedComponentId && selectedComponent && (
                                        <>
                                            {/* Info komponen yang dipilih */}
                                            <div className="rounded-md border p-3 bg-muted/30">
                                                <p className="text-sm font-medium">{selectedComponent.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    Kode: {selectedComponent.code} | {selectedComponent.type_label} | {selectedComponent.calculation_type_label}
                                                </p>
                                                {selectedComponent.default_value > 0 && (
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        Nilai default: Rp {selectedComponent.default_value_formatted}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Hitung per hari/unit (opsional) */}
                                            <div className="rounded-md border p-3 space-y-3 bg-muted/30">
                                                <p className="text-xs text-muted-foreground font-medium">Hitung per hari/unit (opsional)</p>
                                                <div className="grid grid-cols-2 gap-4">
                                                    <div className="space-y-2">
                                                        <Label>Qty (hari/unit)</Label>
                                                        <Input
                                                            type="number"
                                                            step="1"
                                                            min="0"
                                                            value={addItemForm.quantity}
                                                            onChange={(e) => {
                                                                const qty = e.target.value;
                                                                setAddItemForm(prev => {
                                                                    const rate = parseFloat(prev.rate.replace(/\./g, '')) || 0;
                                                                    const newQty = parseFloat(qty) || 0;
                                                                    const calculated = newQty > 0 && rate > 0 ? newQty * rate : 0;
                                                                    return {
                                                                        ...prev,
                                                                        quantity: qty,
                                                                        amount: calculated > 0 ? formatCurrency(calculated.toString()) : prev.amount,
                                                                    };
                                                                });
                                                            }}
                                                            placeholder={detailSlip?.days_present?.toString() || '0'}
                                                        />
                                                    </div>
                                                    <div className="space-y-2">
                                                        <Label>Tarif (Rp)</Label>
                                                        <Input
                                                            value={addItemForm.rate}
                                                            onChange={(e) => {
                                                                const rate = formatCurrency(e.target.value);
                                                                setAddItemForm(prev => {
                                                                    const qty = parseFloat(prev.quantity) || 0;
                                                                    const newRate = parseFloat(rate.replace(/\./g, '')) || 0;
                                                                    const calculated = qty > 0 && newRate > 0 ? qty * newRate : 0;
                                                                    return {
                                                                        ...prev,
                                                                        rate,
                                                                        amount: calculated > 0 ? formatCurrency(calculated.toString()) : prev.amount,
                                                                    };
                                                                });
                                                            }}
                                                            placeholder={selectedComponent.default_value_formatted || '0'}
                                                        />
                                                    </div>
                                                </div>
                                                {detailSlip && detailSlip.working_days > 0 && (
                                                    <p className="text-xs text-muted-foreground">
                                                        Kehadiran: {detailSlip.days_present} hari hadir dari {detailSlip.working_days} hari kerja
                                                    </p>
                                                )}
                                            </div>

                                            {/* Jumlah (hasil atau input langsung) */}
                                            <div className="space-y-2">
                                                <Label>Jumlah (Rp) *</Label>
                                                <Input
                                                    value={addItemForm.amount}
                                                    onChange={(e) => setAddItemForm(prev => ({ ...prev, amount: formatCurrency(e.target.value) }))}
                                                    placeholder="0"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    {addItemForm.quantity && addItemForm.rate && parseFloat(addItemForm.quantity) > 0 && parseFloat(addItemForm.rate.replace(/\./g, '')) > 0
                                                        ? `Dihitung dari: ${addItemForm.quantity} × Rp ${addItemForm.rate}`
                                                        : 'Isi langsung atau gunakan Qty × Tarif di atas'}
                                                </p>
                                            </div>

                                            {/* Field catatan (opsional) */}
                                            <div className="space-y-2">
                                                <Label>Catatan</Label>
                                                <Input
                                                    value={addItemForm.notes}
                                                    onChange={(e) => setAddItemForm(prev => ({ ...prev, notes: e.target.value }))}
                                                    placeholder="Catatan (opsional)"
                                                />
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}
                        </TabsContent>

                        <TabsContent value="manual" className="space-y-4 mt-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Kode *</Label>
                                    <Input
                                        value={addItemForm.component_code}
                                        onChange={(e) => setAddItemForm(prev => ({ ...prev, component_code: e.target.value.toUpperCase() }))}
                                        placeholder="UANG_MAKAN"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Tipe *</Label>
                                    <Select
                                        value={addItemForm.type}
                                        onValueChange={(v) => setAddItemForm(prev => ({ ...prev, type: v as 'earning' | 'deduction' }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="earning">Pendapatan</SelectItem>
                                            <SelectItem value="deduction">Potongan</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label>Nama Komponen *</Label>
                                    <Input
                                        value={addItemForm.component_name}
                                        onChange={(e) => setAddItemForm(prev => ({ ...prev, component_name: e.target.value }))}
                                        placeholder="Uang Makan"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Kategori</Label>
                                    <Select
                                        value={addItemForm.category}
                                        onValueChange={(v) => setAddItemForm(prev => ({ ...prev, category: v as typeof addItemForm.category }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="basic">Gaji Pokok</SelectItem>
                                            <SelectItem value="allowance">Tunjangan</SelectItem>
                                            <SelectItem value="attendance">Kehadiran</SelectItem>
                                            <SelectItem value="statutory">Wajib</SelectItem>
                                            <SelectItem value="tax">Pajak</SelectItem>
                                            <SelectItem value="other">Lainnya</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            {/* Quantity x Rate */}
                            <div className="rounded-md border p-3 space-y-3 bg-muted/30">
                                <p className="text-xs text-muted-foreground font-medium">Hitung per hari/unit (opsional)</p>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label>Qty (hari/unit)</Label>
                                        <Input
                                            type="number"
                                            step="1"
                                            min="0"
                                            value={addItemForm.quantity}
                                            onChange={(e) => {
                                                const qty = e.target.value;
                                                setAddItemForm(prev => {
                                                    const rate = parseFloat(prev.rate.replace(/\./g, '')) || 0;
                                                    const newQty = parseFloat(qty) || 0;
                                                    const calculated = newQty > 0 && rate > 0 ? newQty * rate : 0;
                                                    return {
                                                        ...prev,
                                                        quantity: qty,
                                                        amount: calculated > 0 ? formatCurrency(calculated.toString()) : prev.amount,
                                                    };
                                                });
                                            }}
                                            placeholder={detailSlip?.days_present?.toString() || '0'}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Tarif (Rp)</Label>
                                        <Input
                                            value={addItemForm.rate}
                                            onChange={(e) => {
                                                const rate = formatCurrency(e.target.value);
                                                setAddItemForm(prev => {
                                                    const qty = parseFloat(prev.quantity) || 0;
                                                    const newRate = parseFloat(rate.replace(/\./g, '')) || 0;
                                                    const calculated = qty > 0 && newRate > 0 ? qty * newRate : 0;
                                                    return {
                                                        ...prev,
                                                        rate,
                                                        amount: calculated > 0 ? formatCurrency(calculated.toString()) : prev.amount,
                                                    };
                                                });
                                            }}
                                            placeholder="50.000"
                                        />
                                    </div>
                                </div>
                                {detailSlip && detailSlip.working_days > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        Kehadiran: {detailSlip.days_present} hari hadir dari {detailSlip.working_days} hari kerja
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Jumlah (Rp) *</Label>
                                <Input
                                    value={addItemForm.amount}
                                    onChange={(e) => setAddItemForm(prev => ({ ...prev, amount: formatCurrency(e.target.value) }))}
                                    placeholder="0"
                                />
                                <p className="text-xs text-muted-foreground">
                                    {addItemForm.quantity && addItemForm.rate && parseFloat(addItemForm.quantity) > 0 && parseFloat(addItemForm.rate.replace(/\./g, '')) > 0
                                        ? `Dihitung dari: ${addItemForm.quantity} × Rp ${addItemForm.rate}`
                                        : 'Isi langsung atau gunakan Qty × Tarif di atas'}
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label>Catatan</Label>
                                <Input
                                    value={addItemForm.notes}
                                    onChange={(e) => setAddItemForm(prev => ({ ...prev, notes: e.target.value }))}
                                    placeholder="Catatan (opsional)"
                                />
                            </div>

                            <p className="text-xs text-muted-foreground bg-blue-50 p-2 rounded">
                                Komponen baru akan otomatis tersimpan di menu Komponen Gaji
                            </p>
                        </TabsContent>
                    </Tabs>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAddItemOpen(false)} disabled={savingItem}>Batal</Button>
                        <Button onClick={handleAddItem} disabled={savingItem || (addItemMode === 'select' && !selectedComponentId)}>
                            {savingItem ? 'Menyimpan...' : 'Tambah'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Item Dialog */}
            <Dialog open={editItemOpen} onOpenChange={setEditItemOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Edit Item</DialogTitle>
                        <DialogDescription>
                            {editingItem?.component_name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Jumlah/Hari</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={editItemForm.quantity}
                                    onChange={(e) => setEditItemForm(prev => ({ ...prev, quantity: e.target.value }))}
                                    placeholder={editingItem?.quantity?.toString() || '0'}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tarif (Rp)</Label>
                                <Input
                                    value={editItemForm.rate}
                                    onChange={(e) => setEditItemForm(prev => ({ ...prev, rate: formatCurrency(e.target.value) }))}
                                    placeholder={editingItem?.rate?.toString() || '0'}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Nominal (Rp)</Label>
                            <Input
                                value={editItemForm.amount}
                                onChange={(e) => setEditItemForm(prev => ({ ...prev, amount: formatCurrency(e.target.value) }))}
                                placeholder={editingItem?.amount?.toString() || '0'}
                            />
                            <p className="text-xs text-muted-foreground">
                                Jika Jumlah dan Tarif diisi, nominal akan dihitung otomatis
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label>Alasan Perubahan *</Label>
                            <Textarea
                                value={editItemForm.reason}
                                onChange={(e) => setEditItemForm(prev => ({ ...prev, reason: e.target.value }))}
                                placeholder="Contoh: Koreksi data kehadiran dari 20 hari menjadi 22 hari"
                                rows={3}
                            />
                            <p className="text-xs text-muted-foreground">
                                Alasan wajib diisi dan akan dicatat di riwayat perubahan
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditItemOpen(false)} disabled={savingEdit}>Batal</Button>
                        <Button onClick={handleEditItem} disabled={savingEdit || !editItemForm.reason.trim()}>
                            {savingEdit ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* WhatsApp Dialog */}
            <Dialog open={whatsAppOpen} onOpenChange={setWhatsAppOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <MessageCircle className="h-5 w-5 text-green-600" />
                            Kirim Slip via WhatsApp
                        </DialogTitle>
                        <DialogDescription>
                            Kirim slip gaji ke WhatsApp karyawan
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        {loadingPhone ? (
                            <div className="py-4 text-center text-muted-foreground">
                                Memuat nomor telepon...
                            </div>
                        ) : (
                            <>
                                <div className="rounded-md border p-3 bg-muted/30">
                                    <p className="text-sm font-medium">{detailSlip?.employee_name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {detailSlip?.employee_type_label} - {detailSlip?.employee_identifier}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="wa-phone" className="flex items-center gap-2">
                                        <Phone className="h-4 w-4" />
                                        Nomor Telepon
                                    </Label>
                                    <Input
                                        id="wa-phone"
                                        value={whatsAppPhone}
                                        onChange={(e) => setWhatsAppPhone(e.target.value)}
                                        placeholder="08xxxxxxxxxx"
                                    />
                                    {!whatsAppPhone && (
                                        <p className="text-xs text-yellow-600 dark:text-yellow-400">
                                            Nomor tidak ditemukan di profil. Masukkan nomor secara manual.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-md bg-green-50 p-3 dark:bg-green-950">
                                    <p className="text-sm font-medium text-green-700 dark:text-green-300">
                                        Gaji Bersih: {detailSlip?.net_salary_formatted}
                                    </p>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Slip gaji akan dikirim dalam format teks ke WhatsApp. Pastikan nomor telepon sudah terdaftar di WhatsApp.
                                </p>
                            </>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setWhatsAppOpen(false)} disabled={sendingWhatsApp}>
                            Batal
                        </Button>
                        <Button
                            onClick={handleSendWhatsApp}
                            disabled={sendingWhatsApp || loadingPhone || !whatsAppPhone}
                            className="bg-green-600 hover:bg-green-700"
                        >
                            {sendingWhatsApp ? (
                                'Mengirim...'
                            ) : (
                                <>
                                    <Send className="h-4 w-4 mr-2" />
                                    Kirim
                                </>
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
