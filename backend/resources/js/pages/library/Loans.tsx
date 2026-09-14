import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
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
import { bookLoanApi, type BookLoan, type LoanStatistics } from '@/services/api';
import {
    Search,
    MoreVertical,
    RotateCcw,
    Clock,
    CheckCircle,
    AlertTriangle,
    BookOpen,
    ArrowLeftRight,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';

const statusOptions = [
    { value: 'borrowed', label: 'Dipinjam', color: 'bg-blue-100 text-blue-700' },
    { value: 'returned', label: 'Dikembalikan', color: 'bg-green-100 text-green-700' },
    { value: 'overdue', label: 'Terlambat', color: 'bg-red-100 text-red-700' },
    { value: 'lost', label: 'Hilang', color: 'bg-gray-100 text-gray-700' },
];

export default function Loans() {
    // State
    const [loans, setLoans] = useState<BookLoan[]>([]);
    const [statistics, setStatistics] = useState<LoanStatistics | null>(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Dialog state
    const [returnOpen, setReturnOpen] = useState(false);
    const [selectedLoan, setSelectedLoan] = useState<BookLoan | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Load loans
    const loadLoans = useCallback(async () => {
        try {
            setLoading(true);
            const params: Record<string, unknown> = {
                page: currentPage,
                per_page: 10,
            };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;

            const response = await bookLoanApi.list(params);
            setLoans(response.data.data || []);
            setTotalPages(response.data.meta?.last_page || 1);
        } catch (error) {
            console.error('Failed to load loans:', error);
            toast.error('Gagal memuat data peminjaman');
        } finally {
            setLoading(false);
        }
    }, [currentPage, search, statusFilter]);

    // Load statistics
    const loadStatistics = useCallback(async () => {
        try {
            const response = await bookLoanApi.statistics();
            setStatistics(response.data.data);
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    }, []);

    useEffect(() => {
        loadLoans();
        loadStatistics();
    }, [loadLoans, loadStatistics]);

    // Handle return
    const handleReturn = async () => {
        if (!selectedLoan) return;

        setSubmitting(true);
        try {
            await bookLoanApi.returnBook(selectedLoan.id);
            toast.success('Buku berhasil dikembalikan');
            setReturnOpen(false);
            loadLoans();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal mengembalikan buku', {
                description: err.response?.data?.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // Handle extend
    const handleExtend = async (loan: BookLoan) => {
        try {
            await bookLoanApi.extend(loan.id);
            toast.success('Peminjaman berhasil diperpanjang');
            loadLoans();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal memperpanjang peminjaman', {
                description: err.response?.data?.message,
            });
        }
    };

    // Get status badge
    const getStatusBadge = (status: string, isOverdue: boolean) => {
        if (status === 'borrowed' && isOverdue) {
            return <Badge className="bg-red-100 text-red-700">Terlambat</Badge>;
        }
        const option = statusOptions.find((s) => s.value === status);
        return option ? (
            <Badge className={option.color}>{option.label}</Badge>
        ) : (
            <Badge variant="secondary">{status}</Badge>
        );
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
        <MainLayout title="Peminjaman Buku">
            <Head title="Peminjaman Buku" />

            <div className="space-y-6">
                {/* Statistics */}
                {statistics && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Sedang Dipinjam</CardTitle>
                                <BookOpen className="h-4 w-4 text-blue-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.active_loans}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Terlambat</CardTitle>
                                <AlertTriangle className="h-4 w-4 text-red-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-red-600">{statistics.overdue_loans}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Dikembalikan Hari Ini</CardTitle>
                                <CheckCircle className="h-4 w-4 text-green-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.returned_today}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Denda Belum Dibayar</CardTitle>
                                <Clock className="h-4 w-4 text-orange-500" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">
                                    Rp {statistics.unpaid_fines.toLocaleString('id-ID')}
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
                                <CardTitle>Daftar Peminjaman</CardTitle>
                                <CardDescription>Kelola peminjaman buku perpustakaan</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex flex-wrap gap-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Cari peminjaman..."
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
                                    {statusOptions.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Table */}
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Anggota</TableHead>
                                        <TableHead>Buku</TableHead>
                                        <TableHead>Tgl Pinjam</TableHead>
                                        <TableHead>Jatuh Tempo</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Memuat...
                                            </TableCell>
                                        </TableRow>
                                    ) : loans.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Tidak ada data peminjaman
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        loans.map((loan) => (
                                            <TableRow key={loan.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {loan.member?.user?.full_name || '-'}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {loan.member?.member_number}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {loan.book_copy?.book?.title || '-'}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {loan.book_copy?.copy_number}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{formatDate(loan.borrow_date)}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div>{formatDate(loan.due_date)}</div>
                                                        {loan.is_overdue && loan.status === 'borrowed' && (
                                                            <div className="text-sm text-red-600">
                                                                {loan.days_overdue} hari terlambat
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getStatusBadge(loan.status, loan.is_overdue)}
                                                </TableCell>
                                                <TableCell>
                                                    {loan.status === 'borrowed' && (
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="icon">
                                                                    <MoreVertical className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem
                                                                    onClick={() => {
                                                                        setSelectedLoan(loan);
                                                                        setReturnOpen(true);
                                                                    }}
                                                                >
                                                                    <RotateCcw className="mr-2 h-4 w-4" />
                                                                    Kembalikan
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem
                                                                    onClick={() => handleExtend(loan)}
                                                                    disabled={loan.is_overdue}
                                                                >
                                                                    <ArrowLeftRight className="mr-2 h-4 w-4" />
                                                                    Perpanjang
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    )}
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

            {/* Return Confirmation Dialog */}
            <Dialog open={returnOpen} onOpenChange={setReturnOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Kembalikan Buku</DialogTitle>
                        <DialogDescription>
                            Konfirmasi pengembalian buku "{selectedLoan?.book_copy?.book?.title}"
                            oleh {selectedLoan?.member?.user?.full_name}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedLoan?.is_overdue && (
                        <div className="bg-red-50 p-4 rounded-lg">
                            <p className="text-red-800 font-medium">
                                Peminjaman terlambat {selectedLoan.days_overdue} hari
                            </p>
                            <p className="text-red-600 text-sm">
                                Denda akan dihitung otomatis berdasarkan pengaturan perpustakaan
                            </p>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setReturnOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleReturn} disabled={submitting}>
                            {submitting ? 'Memproses...' : 'Konfirmasi Pengembalian'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
