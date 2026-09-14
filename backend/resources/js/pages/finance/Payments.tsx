import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
import {
    Plus,
    RefreshCw,
    Search,
    Filter,
    X,
    Eye,
    CheckCircle,
    XCircle,
    FileText,
    CreditCard,
    Banknote,
    Users,
} from 'lucide-react';
import { paymentsApi, studentFeesApi, paymentMethodsApi, studentsApi, classroomsApi } from '@/services/api';
import type { Payment, StudentFee, PaymentMethod, Student, PaginationMeta, Classroom } from '@/types';

interface Summary {
    total_received: number;
    total_pending: number;
    total_received_formatted: string;
    total_pending_formatted: string;
    count_total: number;
    count_completed: number;
    count_pending: number;
    count_needs_verification: number;
}

function getStatusColor(status: string): string {
    switch (status) {
        case 'completed': return 'bg-green-100 text-green-800';
        case 'pending': return 'bg-yellow-100 text-yellow-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'failed': return 'bg-red-100 text-red-800';
        case 'cancelled': return 'bg-gray-100 text-gray-800';
        case 'refunded': return 'bg-purple-100 text-purple-800';
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

export default function Payments() {
    const [payments, setPayments] = useState<Payment[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [summary, setSummary] = useState<Summary | null>(null);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);

    // Filters
    const [search, setSearch] = useState('');
    const [filterStatus, setFilterStatus] = useState('');
    const [filterFromDate, setFilterFromDate] = useState('');
    const [filterToDate, setFilterToDate] = useState('');
    const [showFilters, setShowFilters] = useState(false);

    // Create payment
    const [createOpen, setCreateOpen] = useState(false);
    const [selectedStudent, setSelectedStudent] = useState<Student | null>(null);
    const [studentSearch, setStudentSearch] = useState('');
    const [studentResults, setStudentResults] = useState<Student[]>([]);
    const [searchingStudents, setSearchingStudents] = useState(false);
    const [studentFees, setStudentFees] = useState<StudentFee[]>([]);
    const [loadingFees, setLoadingFees] = useState(false);
    const [selectedFeeIds, setSelectedFeeIds] = useState<string[]>([]);
    const [feeAmounts, setFeeAmounts] = useState<Record<string, number>>({});
    const [selectedPaymentMethod, setSelectedPaymentMethod] = useState('');
    const [paymentNote, setPaymentNote] = useState('');
    const [creating, setCreating] = useState(false);

    // Classroom & Student selection (new dropdown approach)
    const [classrooms, setClassrooms] = useState<Classroom[]>([]);
    const [selectedClassroom, setSelectedClassroom] = useState('');
    const [classroomStudents, setClassroomStudents] = useState<Student[]>([]);
    const [loadingClassrooms, setLoadingClassrooms] = useState(false);
    const [loadingStudents, setLoadingStudents] = useState(false);
    const [selectionMode, setSelectionMode] = useState<'dropdown' | 'search'>('dropdown');

    // View payment details
    const [viewOpen, setViewOpen] = useState(false);
    const [viewingPayment, setViewingPayment] = useState<Payment | null>(null);

    // Complete payment (cash)
    const [completeOpen, setCompleteOpen] = useState(false);
    const [completingPayment, setCompletingPayment] = useState<Payment | null>(null);
    const [transactionId, setTransactionId] = useState('');
    const [completing, setCompleting] = useState(false);

    // Verify payment (transfer)
    const [verifyOpen, setVerifyOpen] = useState(false);
    const [verifyingPayment, setVerifyingPayment] = useState<Payment | null>(null);
    const [verifyApproved, setVerifyApproved] = useState(true);
    const [verifyNotes, setVerifyNotes] = useState('');
    const [verifying, setVerifying] = useState(false);

    // Cancel payment
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelingPayment, setCancelingPayment] = useState<Payment | null>(null);
    const [cancelReason, setCancelReason] = useState('');
    const [canceling, setCanceling] = useState(false);

    // Payment methods
    const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);

    // Load payment methods & classrooms
    useEffect(() => {
        const loadPaymentMethods = async () => {
            try {
                const response = await paymentMethodsApi.list({ per_page: 100, is_active: true });
                setPaymentMethods(response.data.data.data ?? []);
            } catch {
                // ignore
            }
        };
        loadPaymentMethods();
    }, []);

    // Load classrooms for student selection
    useEffect(() => {
        const loadClassrooms = async () => {
            setLoadingClassrooms(true);
            try {
                const response = await classroomsApi.list({ per_page: 100, sort: 'name', direction: 'asc' });
                setClassrooms(response.data.data.data ?? []);
            } catch {
                // ignore
            } finally {
                setLoadingClassrooms(false);
            }
        };
        loadClassrooms();
    }, []);

    // Load students when classroom is selected
    useEffect(() => {
        const loadStudentsByClassroom = async () => {
            if (!selectedClassroom) {
                setClassroomStudents([]);
                return;
            }
            setLoadingStudents(true);
            try {
                // Use dedicated endpoint: GET /academic/classrooms/{id}/students
                const response = await classroomsApi.students(selectedClassroom);
                // response.data = { success, message, data: Student[] }
                const students = response.data.data ?? [];
                setClassroomStudents(students);
            } catch (error) {
                console.error('Error loading students:', error);
                setClassroomStudents([]);
            } finally {
                setLoadingStudents(false);
            }
        };
        loadStudentsByClassroom();
    }, [selectedClassroom]);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterStatus) params.status = filterStatus;
            if (filterFromDate) params.from_date = filterFromDate;
            if (filterToDate) params.to_date = filterToDate;

            const [paymentsRes, summaryRes] = await Promise.all([
                paymentsApi.list(params),
                paymentsApi.summary(params),
            ]);

            const payload = paymentsRes.data.data;
            setPayments(payload.data ?? []);
            setMeta(payload.meta ?? null);
            setSummary(summaryRes.data.data as Summary);
        } catch {
            toast.error('Gagal memuat data pembayaran');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterStatus, filterFromDate, filterToDate]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const clearFilters = () => {
        setSearch('');
        setFilterStatus('');
        setFilterFromDate('');
        setFilterToDate('');
        setPage(1);
    };

    const hasFilters = search || filterStatus || filterFromDate || filterToDate;

    // Search students
    const searchStudents = useCallback(async (query: string) => {
        if (!query.trim()) {
            setStudentResults([]);
            return;
        }
        setSearchingStudents(true);
        try {
            const response = await studentsApi.list({ search: query.trim(), per_page: 10 });
            // `studentsApi.list` mengembalikan PaginatedResponse, jadi
            // `response.data.data` SUDAH berupa array — bukan objek berpaginasi lagi.
            setStudentResults(response.data.data ?? []);
        } catch {
            // ignore
        } finally {
            setSearchingStudents(false);
        }
    }, []);

    useEffect(() => {
        const timer = setTimeout(() => {
            if (studentSearch.trim()) {
                searchStudents(studentSearch);
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [studentSearch, searchStudents]);

    // Load student fees when student is selected
    useEffect(() => {
        const loadStudentFees = async () => {
            if (!selectedStudent) {
                setStudentFees([]);
                return;
            }
            setLoadingFees(true);
            try {
                const response = await studentFeesApi.studentHistory(selectedStudent.id, {
                    per_page: 100,
                });
                // Filter unpaid fees
                const unpaid = (response.data.data.data ?? []).filter(
                    (f: StudentFee) => f.status === 'unpaid' || f.status === 'partial' || f.status === 'overdue'
                );
                setStudentFees(unpaid);
                // Reset selections
                setSelectedFeeIds([]);
                setFeeAmounts({});
            } catch {
                toast.error('Gagal memuat tagihan siswa');
            } finally {
                setLoadingFees(false);
            }
        };
        loadStudentFees();
    }, [selectedStudent]);

    const selectStudent = (student: Student) => {
        setSelectedStudent(student);
        setStudentSearch('');
        setStudentResults([]);
    };

    const toggleFeeSelection = (feeId: string, fee: StudentFee) => {
        if (selectedFeeIds.includes(feeId)) {
            setSelectedFeeIds(selectedFeeIds.filter(id => id !== feeId));
            const newAmounts = { ...feeAmounts };
            delete newAmounts[feeId];
            setFeeAmounts(newAmounts);
        } else {
            setSelectedFeeIds([...selectedFeeIds, feeId]);
            setFeeAmounts({ ...feeAmounts, [feeId]: fee.remaining_amount });
        }
    };

    const updateFeeAmount = (feeId: string, amount: number, maxAmount: number) => {
        const validAmount = Math.min(Math.max(0, amount), maxAmount);
        setFeeAmounts({ ...feeAmounts, [feeId]: validAmount });
    };

    const totalPayment = selectedFeeIds.reduce((sum, id) => sum + (feeAmounts[id] || 0), 0);
    const selectedMethod = paymentMethods.find(m => m.id === selectedPaymentMethod);
    const adminFee = selectedMethod?.admin_fee ?? 0;
    const grandTotal = totalPayment + adminFee;

    const resetCreateForm = () => {
        setSelectedStudent(null);
        setStudentSearch('');
        setStudentResults([]);
        setStudentFees([]);
        setSelectedFeeIds([]);
        setFeeAmounts({});
        setSelectedPaymentMethod('');
        setPaymentNote('');
        setSelectedClassroom('');
        setClassroomStudents([]);
        setSelectionMode('dropdown');
    };

    const handleSelectStudentFromDropdown = (studentId: string) => {
        const student = classroomStudents.find(s => s.id === studentId);
        if (student) {
            setSelectedStudent(student);
        }
    };

    const handleCreate = async () => {
        if (!selectedStudent || selectedFeeIds.length === 0 || !selectedPaymentMethod) {
            toast.error('Pilih siswa, tagihan, dan metode pembayaran');
            return;
        }

        setCreating(true);
        try {
            await paymentsApi.create({
                student_id: selectedStudent.id,
                payment_method_id: selectedPaymentMethod,
                student_fee_ids: selectedFeeIds,
                amounts: selectedFeeIds.map(id => feeAmounts[id] || 0),
                notes: paymentNote || undefined,
            });
            toast.success('Pembayaran berhasil dibuat');
            setCreateOpen(false);
            resetCreateForm();
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membuat pembayaran'));
        } finally {
            setCreating(false);
        }
    };

    const openView = async (payment: Payment) => {
        try {
            const response = await paymentsApi.get(payment.id);
            setViewingPayment(response.data.data);
            setViewOpen(true);
        } catch {
            toast.error('Gagal memuat detail pembayaran');
        }
    };

    const openComplete = (payment: Payment) => {
        setCompletingPayment(payment);
        setTransactionId('');
        setCompleteOpen(true);
    };

    const handleComplete = async () => {
        if (!completingPayment) return;

        setCompleting(true);
        try {
            await paymentsApi.complete(completingPayment.id, {
                transaction_id: transactionId || undefined,
            });
            toast.success('Pembayaran berhasil dicatat');
            setCompleteOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mencatat pembayaran'));
        } finally {
            setCompleting(false);
        }
    };

    const openVerify = (payment: Payment) => {
        setVerifyingPayment(payment);
        setVerifyApproved(true);
        setVerifyNotes('');
        setVerifyOpen(true);
    };

    const handleVerify = async () => {
        if (!verifyingPayment) return;

        setVerifying(true);
        try {
            await paymentsApi.verify(verifyingPayment.id, {
                approved: verifyApproved,
                notes: verifyNotes || undefined,
            });
            toast.success(verifyApproved ? 'Pembayaran berhasil diverifikasi' : 'Pembayaran ditolak');
            setVerifyOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal memverifikasi pembayaran'));
        } finally {
            setVerifying(false);
        }
    };

    const openCancel = (payment: Payment) => {
        setCancelingPayment(payment);
        setCancelReason('');
        setCancelOpen(true);
    };

    const handleCancel = async () => {
        if (!cancelingPayment) return;

        setCanceling(true);
        try {
            await paymentsApi.cancel(cancelingPayment.id, {
                reason: cancelReason || undefined,
            });
            toast.success('Pembayaran berhasil dibatalkan');
            setCancelOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membatalkan pembayaran'));
        } finally {
            setCanceling(false);
        }
    };

    const printReceipt = async (payment: Payment) => {
        try {
            const response = await paymentsApi.receipt(payment.id);
            // For now, just show the data. In a real app, you'd open a print-friendly page
            console.log('Receipt data:', response.data.data);
            toast.success('Fitur cetak kuitansi akan segera tersedia');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal memuat kuitansi'));
        }
    };

    return (
        <MainLayout title="Pembayaran">
            <Head title="Keuangan - Pembayaran" />

            <div className="space-y-4 sm:space-y-6">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4">
                    <div>
                        <h1 className="text-2xl sm:text-3xl font-bold tracking-tight">Pembayaran</h1>
                        <p className="text-sm sm:text-base text-muted-foreground">
                            Kelola transaksi pembayaran biaya pendidikan
                        </p>
                    </div>
                    <Button onClick={() => { resetCreateForm(); setCreateOpen(true); }} className="w-full sm:w-auto">
                        <Plus className="mr-2 h-4 w-4" />
                        Buat Pembayaran
                    </Button>
                </div>

                {/* Summary Cards */}
                {summary && (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Total Diterima</CardDescription>
                                <CardTitle className="text-2xl text-green-600">{summary.total_received_formatted}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{summary.count_completed} transaksi selesai</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Menunggu Pembayaran</CardDescription>
                                <CardTitle className="text-2xl text-yellow-600">{summary.total_pending_formatted}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">{summary.count_pending} transaksi pending</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Perlu Verifikasi</CardDescription>
                                <CardTitle className="text-2xl text-blue-600">{summary.count_needs_verification}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">transaksi transfer menunggu verifikasi</p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="pb-2">
                                <CardDescription>Total Transaksi</CardDescription>
                                <CardTitle className="text-2xl">{summary.count_total}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-xs text-muted-foreground">semua transaksi</p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Pembayaran</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} transaksi terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative flex-1 min-w-[200px] max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari invoice atau nama siswa..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select value={filterStatus} onValueChange={(v) => { setFilterStatus(v); setPage(1); }}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua status</SelectItem>
                                    <SelectItem value="pending">Pending</SelectItem>
                                    <SelectItem value="processing">Diproses</SelectItem>
                                    <SelectItem value="completed">Selesai</SelectItem>
                                    <SelectItem value="failed">Gagal</SelectItem>
                                    <SelectItem value="cancelled">Dibatalkan</SelectItem>
                                </SelectContent>
                            </Select>
                            <Button
                                variant={showFilters ? 'secondary' : 'outline'}
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="mr-2 h-4 w-4" />
                                Filter
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
                            <div className="grid grid-cols-1 gap-4 rounded-lg border p-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Dari Tanggal</Label>
                                    <DatePicker
                                        value={filterFromDate}
                                        onChange={(value) => { setFilterFromDate(value); setPage(1); }}
                                        placeholder="Pilih tanggal"
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Sampai Tanggal</Label>
                                    <DatePicker
                                        value={filterToDate}
                                        onChange={(value) => { setFilterToDate(value); setPage(1); }}
                                        placeholder="Pilih tanggal"
                                    />
                                </div>
                            </div>
                        )}

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : payments.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data pembayaran
                            </div>
                        ) : (
                            <div className="rounded-md border overflow-x-auto">
                                <Table className="min-w-[700px]">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="whitespace-nowrap">Invoice</TableHead>
                                            <TableHead className="whitespace-nowrap">Siswa</TableHead>
                                            <TableHead className="whitespace-nowrap hidden sm:table-cell">Metode</TableHead>
                                            <TableHead className="text-right whitespace-nowrap">Total</TableHead>
                                            <TableHead className="whitespace-nowrap hidden md:table-cell">Tanggal</TableHead>
                                            <TableHead className="whitespace-nowrap">Status</TableHead>
                                            <TableHead className="w-[120px] sm:w-[140px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell className="font-mono text-xs sm:text-sm whitespace-nowrap">
                                                    {payment.invoice_number}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="min-w-0">
                                                        <div className="font-medium text-sm truncate max-w-[120px] sm:max-w-none">{payment.student?.name}</div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {payment.student?.nis}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="hidden sm:table-cell">
                                                    <div className="flex items-center gap-1">
                                                        {payment.payment_method?.type === 'cash' ? (
                                                            <Banknote className="h-4 w-4 text-green-600 shrink-0" />
                                                        ) : (
                                                            <CreditCard className="h-4 w-4 text-blue-600 shrink-0" />
                                                        )}
                                                        <span className="text-sm truncate">{payment.payment_method?.name}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-mono font-medium text-xs sm:text-sm whitespace-nowrap">
                                                    {payment.grand_total_formatted}
                                                </TableCell>
                                                <TableCell className="text-sm hidden md:table-cell whitespace-nowrap">
                                                    {payment.paid_at
                                                        ? new Date(payment.paid_at).toLocaleDateString('id-ID')
                                                        : new Date(payment.created_at).toLocaleDateString('id-ID')}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={`${getStatusColor(payment.status)} text-xs whitespace-nowrap`}>
                                                        {payment.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-0.5 sm:gap-1 flex-wrap">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="h-8 w-8"
                                                            onClick={() => openView(payment)}
                                                            title="Detail"
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {payment.status === 'pending' && payment.payment_method?.type === 'cash' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-green-600"
                                                                onClick={() => openComplete(payment)}
                                                                title="Catat Pembayaran"
                                                            >
                                                                <CheckCircle className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {payment.can_be_verified && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-blue-600"
                                                                onClick={() => openVerify(payment)}
                                                                title="Verifikasi"
                                                            >
                                                                <CheckCircle className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {payment.status === 'completed' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8"
                                                                onClick={() => printReceipt(payment)}
                                                                title="Kuitansi"
                                                            >
                                                                <FileText className="h-4 w-4" />
                                                            </Button>
                                                        )}
                                                        {payment.can_be_cancelled && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-red-600"
                                                                onClick={() => openCancel(payment)}
                                                                title="Batalkan"
                                                            >
                                                                <XCircle className="h-4 w-4" />
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
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p className="text-xs sm:text-sm text-muted-foreground text-center sm:text-left">
                                    Menampilkan {meta.from}-{meta.to} dari {meta.total} data
                                </p>
                                <div className="flex gap-2 justify-center sm:justify-end">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page <= 1 || loading}
                                        onClick={() => setPage((p) => p - 1)}
                                        className="flex-1 sm:flex-none"
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page >= meta.last_page || loading}
                                        onClick={() => setPage((p) => p + 1)}
                                        className="flex-1 sm:flex-none"
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create Payment Dialog */}
            <Dialog open={createOpen} onOpenChange={(open) => { if (!open) resetCreateForm(); setCreateOpen(open); }}>
                <DialogContent className="w-full max-w-2xl max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-lg sm:text-xl">Buat Pembayaran</DialogTitle>
                        <DialogDescription className="text-sm">
                            Pilih siswa dan tagihan yang akan dibayar
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {/* Student Selection */}
                        <div className="space-y-3">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <Label>Siswa *</Label>
                                {!selectedStudent && (
                                    <div className="flex gap-1">
                                        <Button
                                            type="button"
                                            variant={selectionMode === 'dropdown' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setSelectionMode('dropdown')}
                                            className="text-xs h-7"
                                        >
                                            <Users className="h-3 w-3 mr-1" />
                                            Per Kelas
                                        </Button>
                                        <Button
                                            type="button"
                                            variant={selectionMode === 'search' ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => setSelectionMode('search')}
                                            className="text-xs h-7"
                                        >
                                            <Search className="h-3 w-3 mr-1" />
                                            Cari
                                        </Button>
                                    </div>
                                )}
                            </div>

                            {selectedStudent ? (
                                <div className="flex items-center justify-between rounded-md border p-3 bg-muted/50">
                                    <div className="min-w-0 flex-1">
                                        <div className="font-medium truncate">{selectedStudent.full_name}</div>
                                        <div className="text-sm text-muted-foreground truncate">
                                            NIS: {selectedStudent.nis}
                                            {selectedStudent.current_class && ` - ${selectedStudent.current_class.name}`}
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="sm" onClick={() => setSelectedStudent(null)} className="ml-2 shrink-0">
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : selectionMode === 'dropdown' ? (
                                <div className="space-y-3">
                                    {/* Classroom Dropdown */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs text-muted-foreground">Pilih Kelas</Label>
                                            <Select
                                                value={selectedClassroom || undefined}
                                                onValueChange={(value) => {
                                                    setSelectedClassroom(value);
                                                    setSelectedStudent(null);
                                                }}
                                                disabled={loadingClassrooms}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue placeholder={loadingClassrooms ? 'Memuat...' : 'Pilih kelas'} />
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

                                        {/* Student Dropdown */}
                                        <div className="space-y-1.5">
                                            <Label className="text-xs text-muted-foreground">Pilih Siswa</Label>
                                            <Select
                                                value={undefined}
                                                onValueChange={handleSelectStudentFromDropdown}
                                                disabled={!selectedClassroom || loadingStudents}
                                            >
                                                <SelectTrigger className="w-full">
                                                    <SelectValue
                                                        placeholder={
                                                            !selectedClassroom
                                                                ? 'Pilih kelas dulu'
                                                                : loadingStudents
                                                                    ? 'Memuat...'
                                                                    : classroomStudents.length > 0
                                                                        ? `${classroomStudents.length} siswa tersedia`
                                                                        : 'Tidak ada siswa'
                                                        }
                                                    />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {classroomStudents.length === 0 ? (
                                                        <div className="py-2 px-3 text-sm text-muted-foreground text-center">
                                                            {loadingStudents ? 'Memuat siswa...' : 'Tidak ada siswa di kelas ini'}
                                                        </div>
                                                    ) : (
                                                        classroomStudents.map((student) => (
                                                            <SelectItem key={student.id} value={student.id}>
                                                                <div className="flex flex-col">
                                                                    <span>{student.full_name}</span>
                                                                    <span className="text-xs text-muted-foreground">NIS: {student.nis}</span>
                                                                </div>
                                                            </SelectItem>
                                                        ))
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Cari NIS atau nama siswa..."
                                        className="pl-8"
                                        value={studentSearch}
                                        onChange={(e) => setStudentSearch(e.target.value)}
                                    />
                                    {studentResults.length > 0 && (
                                        <div className="absolute z-10 mt-1 w-full rounded-md border bg-background shadow-lg max-h-[200px] overflow-y-auto">
                                            {studentResults.map((student) => (
                                                <button
                                                    key={student.id}
                                                    className="w-full px-3 py-2 text-left hover:bg-accent border-b last:border-b-0"
                                                    onClick={() => selectStudent(student)}
                                                >
                                                    <div className="font-medium">{student.full_name}</div>
                                                    <div className="text-sm text-muted-foreground">
                                                        NIS: {student.nis}
                                                        {student.current_class && ` - ${student.current_class.name}`}
                                                    </div>
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                    {searchingStudents && (
                                        <div className="absolute z-10 mt-1 w-full rounded-md border bg-background p-3 text-center text-muted-foreground">
                                            Mencari...
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        {/* Student Fees */}
                        {selectedStudent && (
                            <div className="space-y-2">
                                <Label>Tagihan Belum Lunas</Label>
                                {loadingFees ? (
                                    <div className="py-4 text-center text-muted-foreground">Memuat tagihan...</div>
                                ) : studentFees.length === 0 ? (
                                    <div className="rounded-md border p-4 text-center text-muted-foreground text-sm">
                                        Tidak ada tagihan yang belum lunas
                                    </div>
                                ) : (
                                    <div className="rounded-md border divide-y max-h-[200px] overflow-y-auto">
                                        {studentFees.map((fee) => (
                                            <div key={fee.id} className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 p-3">
                                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                                    <Checkbox
                                                        id={`fee-${fee.id}`}
                                                        checked={selectedFeeIds.includes(fee.id)}
                                                        onCheckedChange={() => toggleFeeSelection(fee.id, fee)}
                                                    />
                                                    <label
                                                        htmlFor={`fee-${fee.id}`}
                                                        className="flex-1 cursor-pointer min-w-0"
                                                    >
                                                        <div className="font-medium text-sm truncate">
                                                            {fee.fee_structure?.fee_type?.name} - {fee.period_label}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            Sisa: {fee.remaining_amount_formatted}
                                                        </div>
                                                    </label>
                                                </div>
                                                {selectedFeeIds.includes(fee.id) && (
                                                    <div className="w-full sm:w-28 ml-8 sm:ml-0">
                                                        <Input
                                                            type="number"
                                                            min={0}
                                                            max={fee.remaining_amount}
                                                            value={feeAmounts[fee.id] || 0}
                                                            onChange={(e) => updateFeeAmount(fee.id, parseFloat(e.target.value) || 0, fee.remaining_amount)}
                                                            className="text-right text-sm h-9"
                                                        />
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Payment Method */}
                        <div className="space-y-2">
                            <Label>Metode Pembayaran *</Label>
                            <Select value={selectedPaymentMethod} onValueChange={setSelectedPaymentMethod}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih metode pembayaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethods.map((method) => (
                                        <SelectItem key={method.id} value={method.id}>
                                            <div className="flex items-center gap-2">
                                                {method.type === 'cash' ? (
                                                    <Banknote className="h-4 w-4 text-green-600" />
                                                ) : (
                                                    <CreditCard className="h-4 w-4 text-blue-600" />
                                                )}
                                                {method.name}
                                                {method.admin_fee > 0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        (+{method.admin_fee_formatted})
                                                    </span>
                                                )}
                                            </div>
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Notes */}
                        <div className="space-y-2">
                            <Label htmlFor="payment-note">Catatan</Label>
                            <Textarea
                                id="payment-note"
                                rows={2}
                                placeholder="Catatan pembayaran (opsional)"
                                value={paymentNote}
                                onChange={(e) => setPaymentNote(e.target.value)}
                            />
                        </div>

                        {/* Summary */}
                        {selectedFeeIds.length > 0 && (
                            <div className="rounded-md bg-muted p-3 sm:p-4 space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span>Subtotal ({selectedFeeIds.length} tagihan)</span>
                                    <span className="font-mono text-sm">Rp {totalPayment.toLocaleString('id-ID')}</span>
                                </div>
                                {adminFee > 0 && (
                                    <div className="flex justify-between text-sm text-muted-foreground">
                                        <span>Biaya Admin</span>
                                        <span className="font-mono text-sm">Rp {adminFee.toLocaleString('id-ID')}</span>
                                    </div>
                                )}
                                <div className="flex justify-between font-medium border-t pt-2">
                                    <span>Total Bayar</span>
                                    <span className="font-mono">Rp {grandTotal.toLocaleString('id-ID')}</span>
                                </div>
                            </div>
                        )}
                    </div>
                    <DialogFooter className="flex-col-reverse sm:flex-row gap-2 sm:gap-0 mt-4">
                        <Button variant="outline" onClick={() => setCreateOpen(false)} disabled={creating} className="w-full sm:w-auto">
                            Batal
                        </Button>
                        <Button onClick={handleCreate} disabled={creating || selectedFeeIds.length === 0} className="w-full sm:w-auto">
                            {creating && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Buat Pembayaran
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* View Payment Dialog */}
            <Dialog open={viewOpen} onOpenChange={setViewOpen}>
                <DialogContent className="w-full max-w-lg max-h-[90vh] overflow-y-auto p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-lg">Detail Pembayaran</DialogTitle>
                        <DialogDescription className="font-mono text-xs sm:text-sm">{viewingPayment?.invoice_number}</DialogDescription>
                    </DialogHeader>
                    {viewingPayment && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4 text-sm">
                                <div>
                                    <p className="text-muted-foreground text-xs">Siswa</p>
                                    <p className="font-medium">{viewingPayment.student?.name}</p>
                                    <p className="text-muted-foreground text-xs">{viewingPayment.student?.nis}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Status</p>
                                    <Badge className={getStatusColor(viewingPayment.status)}>
                                        {viewingPayment.status_label}
                                    </Badge>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Metode Pembayaran</p>
                                    <p className="font-medium">{viewingPayment.payment_method?.name}</p>
                                </div>
                                <div>
                                    <p className="text-muted-foreground text-xs">Tanggal</p>
                                    <p className="font-medium text-sm">
                                        {viewingPayment.paid_at
                                            ? new Date(viewingPayment.paid_at).toLocaleString('id-ID')
                                            : new Date(viewingPayment.created_at).toLocaleString('id-ID')}
                                    </p>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <p className="text-sm text-muted-foreground">Item Pembayaran</p>
                                <div className="rounded-md border divide-y max-h-[150px] overflow-y-auto">
                                    {viewingPayment.items?.map((item) => (
                                        <div key={item.id} className="flex flex-col sm:flex-row sm:justify-between gap-1 p-3 text-sm">
                                            <span className="truncate">{item.student_fee?.fee_type?.name} - {item.student_fee?.period_label}</span>
                                            <span className="font-mono text-right">{item.amount_formatted}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="rounded-md bg-muted p-3 sm:p-4 space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span>Subtotal</span>
                                    <span className="font-mono">{viewingPayment.total_amount_formatted}</span>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <span>Biaya Admin</span>
                                    <span className="font-mono">{viewingPayment.admin_fee_formatted}</span>
                                </div>
                                <div className="flex justify-between font-medium border-t pt-2">
                                    <span>Total</span>
                                    <span className="font-mono">{viewingPayment.grand_total_formatted}</span>
                                </div>
                            </div>

                            {viewingPayment.notes && (
                                <div>
                                    <p className="text-sm text-muted-foreground">Catatan</p>
                                    <p className="text-sm">{viewingPayment.notes}</p>
                                </div>
                            )}

                            {viewingPayment.received_by_user && (
                                <div>
                                    <p className="text-sm text-muted-foreground">Diterima oleh</p>
                                    <p className="text-sm">{viewingPayment.received_by_user.name}</p>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter className="flex-col-reverse sm:flex-row gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => setViewOpen(false)} className="w-full sm:w-auto">
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Complete Payment Dialog */}
            <Dialog open={completeOpen} onOpenChange={setCompleteOpen}>
                <DialogContent className="w-full max-w-md p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-lg">Catat Pembayaran Tunai</DialogTitle>
                        <DialogDescription className="text-sm">
                            <span className="font-mono">{completingPayment?.invoice_number}</span>
                            <span className="block sm:inline sm:ml-2 font-semibold">{completingPayment?.grand_total_formatted}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="transaction-id">ID Transaksi (Opsional)</Label>
                            <Input
                                id="transaction-id"
                                placeholder="Nomor referensi / ID transaksi"
                                value={transactionId}
                                onChange={(e) => setTransactionId(e.target.value)}
                            />
                        </div>
                    </div>
                    <DialogFooter className="flex-col-reverse sm:flex-row gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => setCompleteOpen(false)} disabled={completing} className="w-full sm:w-auto">
                            Batal
                        </Button>
                        <Button onClick={handleComplete} disabled={completing} className="w-full sm:w-auto">
                            {completing && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Konfirmasi
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Verify Payment Dialog */}
            <Dialog open={verifyOpen} onOpenChange={setVerifyOpen}>
                <DialogContent className="w-full max-w-md p-4 sm:p-6">
                    <DialogHeader>
                        <DialogTitle className="text-lg">Verifikasi Pembayaran Transfer</DialogTitle>
                        <DialogDescription className="text-sm">
                            <span className="font-mono">{verifyingPayment?.invoice_number}</span>
                            <span className="block sm:inline sm:ml-2 font-semibold">{verifyingPayment?.grand_total_formatted}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-2 sm:gap-4">
                            <Button
                                variant={verifyApproved ? 'default' : 'outline'}
                                className="w-full"
                                onClick={() => setVerifyApproved(true)}
                            >
                                <CheckCircle className="mr-1 sm:mr-2 h-4 w-4" />
                                <span className="text-sm">Setujui</span>
                            </Button>
                            <Button
                                variant={!verifyApproved ? 'destructive' : 'outline'}
                                className="w-full"
                                onClick={() => setVerifyApproved(false)}
                            >
                                <XCircle className="mr-1 sm:mr-2 h-4 w-4" />
                                <span className="text-sm">Tolak</span>
                            </Button>
                        </div>
                        {!verifyApproved && (
                            <div className="space-y-2">
                                <Label htmlFor="verify-notes">Alasan Penolakan</Label>
                                <Textarea
                                    id="verify-notes"
                                    rows={3}
                                    placeholder="Masukkan alasan penolakan..."
                                    value={verifyNotes}
                                    onChange={(e) => setVerifyNotes(e.target.value)}
                                />
                            </div>
                        )}
                    </div>
                    <DialogFooter className="flex-col-reverse sm:flex-row gap-2 sm:gap-0">
                        <Button variant="outline" onClick={() => setVerifyOpen(false)} disabled={verifying} className="w-full sm:w-auto">
                            Batal
                        </Button>
                        <Button
                            onClick={handleVerify}
                            disabled={verifying || (!verifyApproved && !verifyNotes.trim())}
                            variant={verifyApproved ? 'default' : 'destructive'}
                            className="w-full sm:w-auto"
                        >
                            {verifying && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {verifyApproved ? 'Setujui' : 'Tolak'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Cancel Payment Dialog */}
            <AlertDialog open={cancelOpen} onOpenChange={(open) => !canceling && setCancelOpen(open)}>
                <AlertDialogContent className="w-[95vw] max-w-md p-4 sm:p-6">
                    <AlertDialogHeader>
                        <AlertDialogTitle className="text-lg">Batalkan Pembayaran</AlertDialogTitle>
                        <AlertDialogDescription className="text-sm">
                            Apakah Anda yakin ingin membatalkan pembayaran{' '}
                            <span className="font-medium font-mono">{cancelingPayment?.invoice_number}</span>?
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-2">
                        <Label htmlFor="cancel-reason">Alasan (Opsional)</Label>
                        <Textarea
                            id="cancel-reason"
                            rows={2}
                            placeholder="Alasan pembatalan..."
                            value={cancelReason}
                            onChange={(e) => setCancelReason(e.target.value)}
                        />
                    </div>
                    <AlertDialogFooter className="flex-col-reverse sm:flex-row gap-2 sm:gap-0">
                        <AlertDialogCancel onClick={() => setCancelOpen(false)} disabled={canceling} className="w-full sm:w-auto">
                            Tidak
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                handleCancel();
                            }}
                            disabled={canceling}
                            className="w-full sm:w-auto bg-red-600 hover:bg-red-700"
                        >
                            {canceling ? 'Membatalkan...' : 'Ya, Batalkan'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
