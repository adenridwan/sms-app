import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
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
    LockOpen,
    Eye,
    Trash2,
    Calculator,
    Send,
    DollarSign,
    CalendarCheck,
    Users,
    UserPlus,
    FileDown,
    MessageCircle,
    Loader2,
} from 'lucide-react';
import { payrollPeriodsApi } from '@/services/api';
import { usePermissions } from '@/hooks/usePermissions';
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
    const { can } = usePermissions();

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

    // Bulk WhatsApp state
    const [bulkWhatsAppItem, setBulkWhatsAppItem] = useState<PayrollPeriod | null>(null);
    const [bulkWhatsAppPreview, setBulkWhatsAppPreview] = useState<{
        recipients: Array<{ id: string; name: string; type_label: string; phone: string; net_salary_formatted: string }>;
        no_phone: Array<{ id: string; name: string; type_label: string }>;
        total: number;
        total_no_phone: number;
    } | null>(null);
    const [loadingWhatsAppPreview, setLoadingWhatsAppPreview] = useState(false);
    const [sendingBulkWhatsApp, setSendingBulkWhatsApp] = useState(false);

    // Progress tracking state
    const [progressOpen, setProgressOpen] = useState(false);
    const [progressPeriod, setProgressPeriod] = useState<PayrollPeriod | null>(null);
    const [progress, setProgress] = useState<{
        status: 'idle' | 'processing' | 'completed' | 'error';
        current: number;
        total: number;
        percentage: number;
        message: string;
        result?: {
            slips_created: number;
            attendance_processed: number;
            attendance_errors?: Array<{ slip_id: string; error: string }>;
        };
    } | null>(null);
    const progressIntervalRef = useRef<NodeJS.Timeout | null>(null);

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

    // Start polling for progress
    const startProgressPolling = (periodId: string) => {
        // Clear existing interval
        if (progressIntervalRef.current) {
            clearInterval(progressIntervalRef.current);
        }

        // Poll every 500ms
        progressIntervalRef.current = setInterval(async () => {
            try {
                const response = await payrollPeriodsApi.getGenerateProgress(periodId);
                const data = response.data.data;
                setProgress(data);

                // Stop polling when completed or error
                if (data.status === 'completed' || data.status === 'error') {
                    if (progressIntervalRef.current) {
                        clearInterval(progressIntervalRef.current);
                        progressIntervalRef.current = null;
                    }

                    if (data.status === 'completed') {
                        toast.success(data.message || 'Slip gaji berhasil di-generate');
                        fetchData();
                    } else {
                        toast.error(data.message || 'Gagal generate slip gaji');
                    }
                }
            } catch {
                // Ignore polling errors
            }
        }, 500);
    };

    // Stop polling
    const stopProgressPolling = () => {
        if (progressIntervalRef.current) {
            clearInterval(progressIntervalRef.current);
            progressIntervalRef.current = null;
        }
    };

    // Cleanup on unmount
    useEffect(() => {
        return () => stopProgressPolling();
    }, []);

    const handleAction = async () => {
        if (!actionItem || !actionType || actionLoading) return;

        // For generate actions, use progress dialog
        if (actionType === 'generate' || actionType === 'generate_with_attendance' || actionType === 'calculate_attendance') {
            setActionItem(null);
            setActionType('');
            setProgressPeriod(actionItem);
            setProgress({
                status: 'processing',
                current: 0,
                total: 0,
                percentage: 0,
                message: 'Memulai proses...',
            });
            setProgressOpen(true);

            try {
                // Start the operation
                if (actionType === 'generate' || actionType === 'generate_with_attendance') {
                    // Start polling before the API call
                    startProgressPolling(actionItem.id);
                    await payrollPeriodsApi.generateSlips(actionItem.id);
                } else {
                    startProgressPolling(actionItem.id);
                    await payrollPeriodsApi.calculateAttendance(actionItem.id);
                }
            } catch (error) {
                stopProgressPolling();
                setProgress({
                    status: 'error',
                    current: 0,
                    total: 0,
                    percentage: 0,
                    message: getErrorMessage(error, 'Gagal memproses'),
                });
            }
            return;
        }

        setActionLoading(true);
        try {
            switch (actionType) {
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
                case 'unfinalize':
                    await payrollPeriodsApi.unfinalize(actionItem.id);
                    toast.success('Finalisasi periode berhasil dibatalkan');
                    break;
                case 'sync':
                    const syncResult = await payrollPeriodsApi.syncEmployees(actionItem.id);
                    const added = syncResult.data.data?.added ?? 0;
                    if (added > 0) {
                        toast.success(`Berhasil menambahkan ${added} slip karyawan baru`);
                    } else {
                        toast.info('Tidak ada karyawan baru yang perlu ditambahkan');
                    }
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

    const openBulkWhatsApp = async (item: PayrollPeriod) => {
        setBulkWhatsAppItem(item);
        setLoadingWhatsAppPreview(true);
        setBulkWhatsAppPreview(null);

        try {
            const response = await payrollPeriodsApi.previewWhatsAppRecipients(item.id);
            setBulkWhatsAppPreview(response.data.data);
        } catch {
            toast.error('Gagal memuat preview penerima');
            setBulkWhatsAppItem(null);
        } finally {
            setLoadingWhatsAppPreview(false);
        }
    };

    const handleBulkWhatsApp = async () => {
        if (!bulkWhatsAppItem) return;

        setSendingBulkWhatsApp(true);
        try {
            const response = await payrollPeriodsApi.sendWhatsAppBulk(bulkWhatsAppItem.id);
            const data = response.data.data;

            if (data.sent > 0) {
                toast.success(`Berhasil mengirim ${data.sent} slip gaji`);
            }
            if (data.failed > 0) {
                toast.warning(`${data.failed} gagal dikirim`);
            }

            setBulkWhatsAppItem(null);
            setBulkWhatsAppPreview(null);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengirim slip gaji'));
        } finally {
            setSendingBulkWhatsApp(false);
        }
    };

    const confirmAction = (item: PayrollPeriod, type: string) => {
        setActionItem(item);
        setActionType(type);
    };

    const getActionLabel = () => {
        switch (actionType) {
            case 'generate': return 'Generate Slip Gaji';
            case 'generate_with_attendance': return 'Generate + Kehadiran';
            case 'calculate': return 'Hitung Ulang';
            case 'calculate_attendance': return 'Hitung Kehadiran';
            case 'submit': return 'Ajukan Persetujuan';
            case 'approve': return 'Setujui';
            case 'paid': return 'Tandai Dibayar';
            case 'finalize': return 'Finalisasi';
            case 'unfinalize': return 'Batal Final';
            case 'sync': return 'Sinkronisasi Karyawan';
            default: return 'Konfirmasi';
        }
    };

    const getActionDescription = () => {
        switch (actionType) {
            case 'generate': return 'Ini akan membuat slip gaji untuk semua karyawan dengan pengaturan gaji aktif.';
            case 'generate_with_attendance': return 'Ini akan membuat slip gaji DAN menghitung tunjangan/potongan kehadiran berdasarkan data absensi dalam periode.';
            case 'calculate': return 'Ini akan menghitung ulang semua slip gaji termasuk BPJS dan PPh 21.';
            case 'calculate_attendance': return 'Ini akan menghitung data kehadiran (hadir, absen, telat) dari modul absensi dan menerapkan tunjangan/potongan kehadiran.';
            case 'submit': return 'Periode akan diajukan untuk persetujuan atasan.';
            case 'approve': return 'Anda yakin ingin menyetujui periode gaji ini?';
            case 'paid': return 'Ini menandakan bahwa gaji sudah dibayarkan ke semua karyawan.';
            case 'finalize': return 'Periode yang sudah final tidak dapat diubah lagi.';
            case 'unfinalize': return 'Ini akan membatalkan finalisasi dan mengembalikan status ke "Dibayar". Slip gaji dapat diedit kembali.';
            case 'sync': return 'Ini akan menambahkan slip gaji untuk karyawan baru yang belum ada dalam periode ini. Slip yang sudah ada tidak akan terpengaruh.';
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
                    {can('payroll.manage') && (
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Buat Periode
                        </Button>
                    )}
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
                                                    <div className="flex items-center justify-end gap-1">
                                                        {/* Sync button - hanya muncul jika sudah ada slip dan belum final */}
                                                        {!item.is_finalized && item.employee_count > 0 && can('payroll.process') && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                title="Sinkronisasi karyawan baru"
                                                                onClick={() => confirmAction(item, 'sync')}
                                                            >
                                                                <UserPlus className="h-4 w-4" />
                                                            </Button>
                                                        )}
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
                                                            {item.employee_count > 0 && (
                                                                <>
                                                                    <DropdownMenuItem asChild>
                                                                        <a href={payrollPeriodsApi.getExportPdfUrl(item.id)} download>
                                                                            <FileDown className="mr-2 h-4 w-4" /> Export Semua PDF
                                                                        </a>
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => openBulkWhatsApp(item)}>
                                                                        <MessageCircle className="mr-2 h-4 w-4 text-green-600" /> Kirim Semua ke WA
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                            {item.can_generate_slips && can('payroll.process') && (
                                                                <>
                                                                    <DropdownMenuItem onClick={() => confirmAction(item, 'generate')}>
                                                                        <Play className="mr-2 h-4 w-4" /> Generate Slip
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => confirmAction(item, 'generate_with_attendance')}>
                                                                        <CalendarCheck className="mr-2 h-4 w-4" /> Generate + Kehadiran
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                            {item.is_editable && item.employee_count > 0 && can('payroll.process') && (
                                                                <>
                                                                    <DropdownMenuItem onClick={() => confirmAction(item, 'calculate')}>
                                                                        <Calculator className="mr-2 h-4 w-4" /> Hitung Ulang
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem onClick={() => confirmAction(item, 'calculate_attendance')}>
                                                                        <Users className="mr-2 h-4 w-4" /> Hitung Kehadiran
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                            {item.status === 'processing' && can('payroll.process') && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'submit')}>
                                                                    <Send className="mr-2 h-4 w-4" /> Ajukan Persetujuan
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.can_approve && can('payroll.approve') && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'approve')}>
                                                                    <CheckCircle className="mr-2 h-4 w-4" /> Setujui
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.status === 'approved' && can('payroll.approve') && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'paid')}>
                                                                    <DollarSign className="mr-2 h-4 w-4" /> Tandai Dibayar
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.can_finalize && can('payroll.approve') && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'finalize')}>
                                                                    <Lock className="mr-2 h-4 w-4" /> Finalisasi
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.is_finalized && can('payroll.approve') && (
                                                                <DropdownMenuItem onClick={() => confirmAction(item, 'unfinalize')}>
                                                                    <LockOpen className="mr-2 h-4 w-4" /> Batal Final
                                                                </DropdownMenuItem>
                                                            )}
                                                            {item.is_draft && can('payroll.manage') && (
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

            {/* Bulk WhatsApp Dialog */}
            <Dialog open={!!bulkWhatsAppItem} onOpenChange={() => { setBulkWhatsAppItem(null); setBulkWhatsAppPreview(null); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <MessageCircle className="h-5 w-5 text-green-600" />
                            Kirim Slip Gaji via WhatsApp
                        </DialogTitle>
                        <DialogDescription>
                            Periode: {bulkWhatsAppItem?.period_label}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        {loadingWhatsAppPreview ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Memuat preview penerima...
                            </div>
                        ) : bulkWhatsAppPreview ? (
                            <>
                                <div className="grid grid-cols-2 gap-4 text-center">
                                    <div className="rounded-md border p-3 bg-green-50">
                                        <p className="text-2xl font-bold text-green-600">{bulkWhatsAppPreview.total}</p>
                                        <p className="text-xs text-muted-foreground">Siap dikirim</p>
                                    </div>
                                    <div className="rounded-md border p-3 bg-yellow-50">
                                        <p className="text-2xl font-bold text-yellow-600">{bulkWhatsAppPreview.total_no_phone}</p>
                                        <p className="text-xs text-muted-foreground">Tanpa nomor HP</p>
                                    </div>
                                </div>

                                {bulkWhatsAppPreview.total > 0 && (
                                    <div className="rounded-md border max-h-48 overflow-y-auto">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Nama</TableHead>
                                                    <TableHead>Tipe</TableHead>
                                                    <TableHead className="text-right">Gaji Bersih</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {bulkWhatsAppPreview.recipients.slice(0, 10).map((r) => (
                                                    <TableRow key={r.id}>
                                                        <TableCell className="font-medium">{r.name}</TableCell>
                                                        <TableCell>{r.type_label}</TableCell>
                                                        <TableCell className="text-right">{r.net_salary_formatted}</TableCell>
                                                    </TableRow>
                                                ))}
                                                {bulkWhatsAppPreview.recipients.length > 10 && (
                                                    <TableRow>
                                                        <TableCell colSpan={3} className="text-center text-muted-foreground">
                                                            ... dan {bulkWhatsAppPreview.recipients.length - 10} lainnya
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}

                                {bulkWhatsAppPreview.total_no_phone > 0 && (
                                    <div className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200">
                                        <p className="font-medium">Tidak akan menerima ({bulkWhatsAppPreview.total_no_phone}):</p>
                                        <p className="text-xs mt-1">
                                            {bulkWhatsAppPreview.no_phone.slice(0, 5).map(p => p.name).join(', ')}
                                            {bulkWhatsAppPreview.no_phone.length > 5 && ` dan ${bulkWhatsAppPreview.no_phone.length - 5} lainnya`}
                                        </p>
                                    </div>
                                )}

                                <p className="text-xs text-muted-foreground">
                                    Slip gaji akan dikirim dalam format teks ke WhatsApp masing-masing karyawan.
                                </p>
                            </>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => { setBulkWhatsAppItem(null); setBulkWhatsAppPreview(null); }}
                            disabled={sendingBulkWhatsApp}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={handleBulkWhatsApp}
                            disabled={sendingBulkWhatsApp || loadingWhatsAppPreview || !bulkWhatsAppPreview || bulkWhatsAppPreview.total === 0}
                            className="bg-green-600 hover:bg-green-700"
                        >
                            {sendingBulkWhatsApp ? 'Mengirim...' : `Kirim ke ${bulkWhatsAppPreview?.total ?? 0} Penerima`}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Progress Dialog */}
            <Dialog open={progressOpen} onOpenChange={(open) => {
                if (!open && progress?.status !== 'processing') {
                    setProgressOpen(false);
                    setProgressPeriod(null);
                    setProgress(null);
                    stopProgressPolling();
                }
            }}>
                <DialogContent className="max-w-md" onPointerDownOutside={(e) => {
                    if (progress?.status === 'processing') e.preventDefault();
                }}>
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {progress?.status === 'processing' && <Loader2 className="h-5 w-5 animate-spin text-blue-600" />}
                            {progress?.status === 'completed' && <CheckCircle className="h-5 w-5 text-green-600" />}
                            {progress?.status === 'error' && <span className="h-5 w-5 text-red-600">!</span>}
                            Generate Slip Gaji
                        </DialogTitle>
                        <DialogDescription>
                            Periode: {progressPeriod?.period_label}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        {/* Progress Bar */}
                        <div className="space-y-2">
                            <Progress value={progress?.percentage ?? 0} className="h-3" />
                            <div className="flex justify-between text-sm text-muted-foreground">
                                <span>{progress?.message ?? 'Memulai...'}</span>
                                <span>{progress?.percentage ?? 0}%</span>
                            </div>
                        </div>

                        {/* Result Summary */}
                        {progress?.status === 'completed' && progress.result && (
                            <div className="grid grid-cols-2 gap-4 text-center">
                                <div className="rounded-md border p-3 bg-green-50">
                                    <p className="text-2xl font-bold text-green-600">{progress.result.slips_created}</p>
                                    <p className="text-xs text-muted-foreground">Slip dibuat</p>
                                </div>
                                <div className="rounded-md border p-3 bg-blue-50">
                                    <p className="text-2xl font-bold text-blue-600">{progress.result.attendance_processed}</p>
                                    <p className="text-xs text-muted-foreground">Kehadiran dihitung</p>
                                </div>
                            </div>
                        )}

                        {/* Errors */}
                        {progress?.status === 'completed' && progress.result?.attendance_errors && progress.result.attendance_errors.length > 0 && (
                            <div className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200">
                                <p className="font-medium">{progress.result.attendance_errors.length} error saat menghitung kehadiran</p>
                            </div>
                        )}

                        {/* Error Message */}
                        {progress?.status === 'error' && (
                            <div className="rounded-md bg-red-50 p-3 text-sm text-red-800 dark:bg-red-950 dark:text-red-200">
                                <p>{progress.message}</p>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            onClick={() => {
                                setProgressOpen(false);
                                setProgressPeriod(null);
                                setProgress(null);
                                stopProgressPolling();
                            }}
                            disabled={progress?.status === 'processing'}
                        >
                            {progress?.status === 'processing' ? 'Memproses...' : 'Tutup'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
