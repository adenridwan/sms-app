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
    const [addItemForm, setAddItemForm] = useState({
        component_code: '',
        component_name: '',
        type: 'earning' as 'earning' | 'deduction',
        category: 'other',
        amount: '',
        notes: '',
    });
    const [savingItem, setSavingItem] = useState(false);

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

    const openAddItem = () => {
        if (!detailSlip) return;
        setAddItemForm({
            component_code: '',
            component_name: '',
            type: 'earning',
            category: 'other',
            amount: '',
            notes: '',
        });
        setAddItemOpen(true);
    };

    const handleAddItem = async () => {
        if (!detailSlip || !addItemForm.component_code || !addItemForm.component_name || !addItemForm.amount) {
            toast.error('Kode, nama, dan jumlah wajib diisi');
            return;
        }

        setSavingItem(true);
        try {
            const payload = {
                component_code: addItemForm.component_code.toUpperCase(),
                component_name: addItemForm.component_name,
                type: addItemForm.type,
                category: addItemForm.category,
                amount: parseFloat(addItemForm.amount.replace(/\./g, '')) || 0,
                // API menerima `notes?: string` — kirim undefined (bukan null)
                // supaya field-nya dihilangkan saat kosong.
                notes: addItemForm.notes || undefined,
            };

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

                            {/* Earnings */}
                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <h4 className="font-semibold text-green-600">Pendapatan</h4>
                                    {detailSlip.is_editable && period?.is_editable && (
                                        <Button variant="outline" size="sm" onClick={openAddItem}>
                                            <Plus className="h-4 w-4 mr-1" /> Tambah
                                        </Button>
                                    )}
                                </div>
                                <div className="space-y-1 rounded-md border p-3">
                                    {detailSlip.earnings?.map((item) => (
                                        <div key={item.id} className="flex items-center justify-between text-sm">
                                            <span>{item.name}</span>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono">{item.amount_formatted}</span>
                                                {detailSlip.is_editable && period?.is_editable && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-6 w-6 text-muted-foreground hover:text-red-600"
                                                        onClick={() => handleRemoveItem(item.id)}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
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
                                    {detailSlip.deductions?.map((item) => (
                                        <div key={item.id} className="flex items-center justify-between text-sm">
                                            <span>{item.name}</span>
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono">{item.amount_formatted}</span>
                                                {detailSlip.is_editable && period?.is_editable && item.code !== 'BPJS_KES' && item.code !== 'BPJS_JHT' && item.code !== 'BPJS_JP' && item.code !== 'PPH21' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-6 w-6 text-muted-foreground hover:text-red-600"
                                                        onClick={() => handleRemoveItem(item.id)}
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
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
                        </div>
                    )}
                </SheetContent>
            </Sheet>

            {/* Add Item Dialog */}
            <Dialog open={addItemOpen} onOpenChange={setAddItemOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Tambah Item</DialogTitle>
                        <DialogDescription>
                            Tambahkan komponen pendapatan atau potongan manual
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Kode *</Label>
                                <Input
                                    value={addItemForm.component_code}
                                    onChange={(e) => setAddItemForm(prev => ({ ...prev, component_code: e.target.value.toUpperCase() }))}
                                    placeholder="BONUS_THR"
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

                        <div className="space-y-2">
                            <Label>Nama Komponen *</Label>
                            <Input
                                value={addItemForm.component_name}
                                onChange={(e) => setAddItemForm(prev => ({ ...prev, component_name: e.target.value }))}
                                placeholder="Bonus THR"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Jumlah (Rp) *</Label>
                            <Input
                                value={addItemForm.amount}
                                onChange={(e) => setAddItemForm(prev => ({ ...prev, amount: formatCurrency(e.target.value) }))}
                                placeholder="0"
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Catatan</Label>
                            <Input
                                value={addItemForm.notes}
                                onChange={(e) => setAddItemForm(prev => ({ ...prev, notes: e.target.value }))}
                                placeholder="Catatan (opsional)"
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAddItemOpen(false)} disabled={savingItem}>Batal</Button>
                        <Button onClick={handleAddItem} disabled={savingItem}>
                            {savingItem ? 'Menyimpan...' : 'Tambah'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
