import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import {
    Plus,
    RefreshCw,
    MoreHorizontal,
    Play,
    CheckCircle,
    Lock,
    Eye,
    Trash2,
    Calculator,
    Send,
    DollarSign,
} from 'lucide-react';
import { payrollPeriodsApi } from '@/services/api';
import type { PaginationMeta } from '@/types';

interface PayrollPeriod {
    id: string;
    name: string;
    year: number;
    month: number;
    period_label: string;
    start_date: string;
    end_date: string;
    payment_date?: string;
    status: string;
    status_label: string;
    is_draft: boolean;
    is_editable: boolean;
    is_finalized: boolean;
    can_generate_slips: boolean;
    can_approve: boolean;
    can_finalize: boolean;
    total_gross: number;
    total_gross_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    total_net: number;
    total_net_formatted: string;
    employee_count: number;
    slips_count?: number;
    created_at: string;
}

interface PeriodForm {
    year: string;
    month: string;
    start_date: string;
    end_date: string;
    payment_date: string;
    notes: string;
}

const currentYear = new Date().getFullYear();
const currentMonth = new Date().getMonth() + 1;

const emptyForm: PeriodForm = {
    year: String(currentYear),
    month: String(currentMonth),
    start_date: '',
    end_date: '',
    payment_date: '',
    notes: '',
};

const months = [
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

const years = Array.from({ length: 10 }, (_, i) => currentYear - 2 + i);

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
        const firstFieldError = Object.values(response?.data?.errors ?? {})[0]?.[0];
        if (firstFieldError) return firstFieldError;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

function getStatusColor(status: string): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'draft': return 'secondary';
        case 'processing': return 'default';
        case 'pending_approval': return 'outline';
        case 'approved': return 'default';
        case 'paid': return 'default';
        case 'finalized': return 'secondary';
        default: return 'secondary';
    }
}

export default function PayrollPeriods() {
    const [items, setItems] = useState<PayrollPeriod[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [filterYear, setFilterYear] = useState<string>('');
    const [filterStatus, setFilterStatus] = useState<string>('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [form, setForm] = useState<PeriodForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<PayrollPeriod | null>(null);
    const [deleting, setDeleting] = useState(false);

    const [actionItem, setActionItem] = useState<PayrollPeriod | null>(null);
    const [actionType, setActionType] = useState<string>('');
    const [actionLoading, setActionLoading] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (filterYear) params.year = filterYear;
            if (filterStatus) params.status = filterStatus;

            const response = await payrollPeriodsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data periode gaji');
        } finally {
            setLoading(false);
        }
    }, [page, filterYear, filterStatus]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    useEffect(() => {
        // Auto-fill dates when year/month changes
        if (form.year && form.month) {
            const year = parseInt(form.year);
            const month = parseInt(form.month);
            const startDate = new Date(year, month - 1, 1);
            const endDate = new Date(year, month, 0); // Last day of month

            setForm(prev => ({
                ...prev,
                start_date: startDate.toISOString().split('T')[0],
                end_date: endDate.toISOString().split('T')[0],
            }));
        }
    }, [form.year, form.month]);

    const openCreate = () => {
        setForm(emptyForm);
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.year || !form.month || !form.start_date || !form.end_date) {
            toast.error('Tahun, bulan, dan tanggal periode wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                year: parseInt(form.year),
                month: parseInt(form.month),
                start_date: form.start_date,
                end_date: form.end_date,
                payment_date: form.payment_date || null,
                notes: form.notes.trim() || null,
            };

            await payrollPeriodsApi.create(payload);
            toast.success('Periode gaji berhasil dibuat');
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membuat periode gaji'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await payrollPeriodsApi.delete(deletingItem.id);
            toast.success('Periode gaji berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus periode gaji'));
        } finally {
            setDeleting(false);
        }
    };

    const handleAction = async () => {
        if (!actionItem || !actionType || actionLoading) return;

        setActionLoading(true);
        try {
            switch (actionType) {
                case 'generate':
                    await payrollPeriodsApi.generateSlips(actionItem.id);
                    toast.success('Slip gaji berhasil di-generate');
                    break;
                case 'calculate':
                    await payrollPeriodsApi.calculate(actionItem.id);
                    toast.success('Perhitungan gaji berhasil diperbarui');
                    break;
                case 'submit':
                    await payrollPeriodsApi.submitForApproval(actionItem.id);
                    toast.success('Periode berhasil diajukan untuk persetujuan');
                    break;
                case 'approve':
                    await payrollPeriodsApi.approve(actionItem.id);
                    toast.success('Periode gaji berhasil disetujui');
                    break;
                case 'paid':
                    await payrollPeriodsApi.markAsPaid(actionItem.id);
                    toast.success('Periode gaji ditandai sebagai dibayar');
                    break;
                case 'finalize':
                    await payrollPeriodsApi.finalize(actionItem.id);
                    toast.success('Periode gaji berhasil difinalisasi');
                    break;
            }
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal melakukan aksi'));
        } finally {
            setActionLoading(false);
            setActionItem(null);
            setActionType('');
        }
    };

    const confirmAction = (item: PayrollPeriod, type: string) => {
        setActionItem(item);
        setActionType(type);
    };

    const getActionLabel = () => {
        switch (actionType) {
            case 'generate': return 'Generate Slip Gaji';
            case 'calculate': return 'Hitung Ulang';
            case 'submit': return 'Ajukan Persetujuan';
            case 'approve': return 'Setujui';
            case 'paid': return 'Tandai Dibayar';
            case 'finalize': return 'Finalisasi';
            default: return 'Konfirmasi';
        }
    };

    const getActionDescription = () => {
        switch (actionType) {
            case 'generate': return 'Ini akan membuat slip gaji untuk semua karyawan dengan pengaturan gaji aktif.';
            case 'calculate': return 'Ini akan menghitung ulang semua slip gaji termasuk BPJS dan PPh 21.';
            case 'submit': return 'Periode akan diajukan untuk persetujuan atasan.';
            case 'approve': return 'Anda yakin ingin menyetujui periode gaji ini?';
            case 'paid': return 'Ini menandakan bahwa gaji sudah dibayarkan ke semua karyawan.';
            case 'finalize': return 'Periode yang sudah final tidak dapat diubah lagi.';
            default: return 'Apakah Anda yakin?';
        }
    };

    const viewSlips = (periodId: string) => {
        router.visit(`/payroll/slips/${periodId}`);
    };

    return (
        <MainLayout title="Proses Penggajian">
            <Head title="Payroll - Proses Penggajian" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Proses Penggajian</h1>
                        <p className="text-muted-foreground">
                            Kelola periode dan proses penggajian bulanan
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Buat Periode
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Periode Gaji</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} periode terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={filterYear} onValueChange={(v) => { setFilterYear(v); setPage(1); }}>
                                <SelectTrigger className="w-[120px]">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Tahun</SelectItem>
                                    {years.map((y) => (
                                        <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filterStatus} onValueChange={(v) => { setFilterStatus(v); setPage(1); }}>
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Status</SelectItem>
                                    <SelectItem value="draft">Draf</SelectItem>
                                    <SelectItem value="processing">Sedang Diproses</SelectItem>
                                    <SelectItem value="pending_approval">Menunggu Persetujuan</SelectItem>
                                    <SelectItem value="approved">Disetujui</SelectItem>
                                    <SelectItem value="paid">Dibayar</SelectItem>
                                    <SelectItem value="finalized">Final</SelectItem>
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
                                Tidak ada data periode gaji
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Periode</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-center">Karyawan</TableHead>
                                            <TableHead className="text-right">Total Gaji Bersih</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{item.period_label}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {item.start_date} s/d {item.end_date}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={getStatusColor(item.status)}>
                                                        {item.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {item.employee_count}
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {item.total_net_formatted}
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onClick={() => viewSlips(item.id)}>
                                                                <Eye className="mr-2 h-4 w-4" /> Lihat Slip
                                                            </DropdownMenuItem>
                                                            {item.can_generate_slips && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'generate')}>
                                                                    <Play className="mr-2 h-4 w-4" /> Generate Slip
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.is_editable && item.employee_count > 0 && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'calculate')}>
                                                                    <Calculator className="mr-2 h-4 w-4" /> Hitung Ulang
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.status === 'processing' && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'submit')}>
                                                                    <Send className="mr-2 h-4 w-4" /> Ajukan Persetujuan
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.can_approve && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'approve')}>
                                                                    <CheckCircle className="mr-2 h-4 w-4" /> Setujui
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.status === 'approved' && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'paid')}>
                                                                    <DollarSign className="mr-2 h-4 w-4" /> Tandai Dibayar
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.can_finalize && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'finalize')}>
                                                                    <Lock className="mr-2 h-4 w-4" /> Finalisasi
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.is_draft && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        className="text-red-600"
                                                                        onClick={() => setDeletingItem(item)}
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" /> Hapus
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
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

            {/* Create Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Buat Periode Gaji</DialogTitle>
                        <DialogDescription>
                            Buat periode gaji baru untuk memulai proses penggajian
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tahun *</Label>
                                <Select value={form.year} onValueChange={(v) => setForm(prev => ({ ...prev, year: v }))}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {years.map((y) => (
                                            <SelectItem key={y} value={String(y)}>{y}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Bulan *</Label>
                                <Select value={form.month} onValueChange={(v) => setForm(prev => ({ ...prev, month: v }))}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {months.map((m) => (
                                            <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tanggal Mulai *</Label>
                                <DatePicker
                                    value={form.start_date}
                                    onChange={(value) => setForm(prev => ({ ...prev, start_date: value }))}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tanggal Selesai *</Label>
                                <DatePicker
                                    value={form.end_date}
                                    onChange={(value) => setForm(prev => ({ ...prev, end_date: value }))}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Tanggal Pembayaran</Label>
                            <DatePicker
                                value={form.payment_date}
                                onChange={(value) => setForm(prev => ({ ...prev, payment_date: value }))}
                                placeholder="Pilih tanggal"
                            />
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
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>Batal</Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving ? 'Menyimpan...' : 'Buat Periode'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deletingItem} onOpenChange={() => setDeletingItem(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Periode Gaji?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Periode <strong>{deletingItem?.period_label}</strong> akan dihapus.
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

            {/* Action Confirmation */}
            <AlertDialog open={!!actionItem && !!actionType} onOpenChange={() => { setActionItem(null); setActionType(''); }}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{getActionLabel()}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {getActionDescription()}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={actionLoading}>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleAction} disabled={actionLoading}>
                            {actionLoading ? 'Memproses...' : getActionLabel()}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
