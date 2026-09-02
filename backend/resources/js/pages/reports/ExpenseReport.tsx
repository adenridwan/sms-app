import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import { toast } from 'sonner';
import {
    RefreshCw,
    Download,
    TrendingUp,
    TrendingDown,
    Wallet,
    Users,
    ArrowUpRight,
    ArrowDownRight,
    CheckCircle,
    Clock,
    FileText,
} from 'lucide-react';
import { expenseReportsApi } from '@/services/api';

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
const currentMonth = new Date().getMonth() + 1;
const YEARS = Array.from({ length: 5 }, (_, i) => (currentYear - 2 + i).toString());

interface ExpenseSummary {
    total_income: number;
    total_income_formatted: string;
    total_payroll_gross: number;
    total_payroll_gross_formatted: string;
    total_payroll_deductions: number;
    total_payroll_deductions_formatted: string;
    total_payroll_net: number;
    total_payroll_net_formatted: string;
    total_employees: number;
    net_balance: number;
    net_balance_formatted: string;
    expense_ratio: number;
}

interface PayrollPeriodData {
    id: string;
    period: string;
    month: number;
    year: number;
    status: string;
    status_label: string;
    employee_count: number;
    total_gross: number;
    total_gross_formatted: string;
    total_deductions: number;
    total_deductions_formatted: string;
    total_net: number;
    total_net_formatted: string;
    payment_date: string | null;
    approved_at: string | null;
}

interface MonthlyBreakdown {
    month: number;
    month_name: string;
    income: number;
    income_formatted: string;
    payroll_expense: number;
    payroll_expense_formatted: string;
    net_balance: number;
    net_balance_formatted: string;
    has_payroll: boolean;
}

interface ExpenseData {
    period: string;
    summary: ExpenseSummary;
    payroll_periods: PayrollPeriodData[];
    monthly_breakdown: MonthlyBreakdown[];
}

function getStatusBadge(status: string) {
    switch (status) {
        case 'draft':
            return <Badge variant="secondary">Draf</Badge>;
        case 'processing':
            return <Badge variant="default">Diproses</Badge>;
        case 'pending_approval':
            return <Badge variant="outline">Menunggu Persetujuan</Badge>;
        case 'approved':
            return <Badge className="bg-blue-100 text-blue-800">Disetujui</Badge>;
        case 'paid':
            return <Badge className="bg-green-100 text-green-800">Dibayar</Badge>;
        case 'finalized':
            return <Badge variant="secondary">Final</Badge>;
        default:
            return <Badge variant="secondary">{status}</Badge>;
    }
}

export default function ExpenseReport() {
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);

    const [filters, setFilters] = useState({
        month: '', // Empty means full year
        year: currentYear.toString(),
    });

    const [data, setData] = useState<ExpenseData | null>(null);

    const loadData = useCallback(async () => {
        setLoading(true);
        try {
            const params: { year: number; month?: number } = {
                year: parseInt(filters.year),
            };
            if (filters.month) {
                params.month = parseInt(filters.month);
            }
            const response = await expenseReportsApi.monthly(params);
            setData(response.data.data as unknown as ExpenseData);
        } catch (error) {
            console.error('Failed to load expense report:', error);
            toast.error('Gagal memuat laporan pengeluaran');
        } finally {
            setLoading(false);
        }
    }, [filters]);

    useEffect(() => {
        loadData();
    }, [loadData]);

    const handleExport = async () => {
        setExporting(true);
        try {
            const response = await expenseReportsApi.exportMonthly({
                year: parseInt(filters.year),
                month: filters.month ? parseInt(filters.month) : undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `laporan_pengeluaran_${filters.year}${filters.month ? '_' + filters.month : ''}.csv`);
            document.body.appendChild(link);
            link.click();
            link.remove();
            toast.success('Export berhasil');
        } catch (error) {
            console.error('Export failed:', error);
            toast.error('Export gagal');
        } finally {
            setExporting(false);
        }
    };

    return (
        <MainLayout title="Laporan Pengeluaran">
            <Head title="Laporan Pengeluaran" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Laporan Pengeluaran</h1>
                        <p className="text-muted-foreground">
                            Ringkasan pengeluaran gaji dan pemasukan SPP
                        </p>
                    </div>
                </div>

                {/* Filters */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Select
                            value={filters.year}
                            onValueChange={(value) => setFilters(prev => ({ ...prev, year: value }))}
                        >
                            <SelectTrigger className="w-[120px]">
                                <SelectValue placeholder="Tahun" />
                            </SelectTrigger>
                            <SelectContent>
                                {YEARS.map((year) => (
                                    <SelectItem key={year} value={year}>{year}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.month}
                            onValueChange={(value) => setFilters(prev => ({ ...prev, month: value }))}
                        >
                            <SelectTrigger className="w-[150px]">
                                <SelectValue placeholder="Semua Bulan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="">Semua Bulan</SelectItem>
                                {MONTHS.map((month) => (
                                    <SelectItem key={month.value} value={month.value}>{month.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button variant="outline" onClick={loadData} disabled={loading}>
                            <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                    </div>
                    <Button variant="outline" onClick={handleExport} disabled={exporting}>
                        <Download className="h-4 w-4 mr-2" />
                        Export CSV
                    </Button>
                </div>

                {data && (
                    <>
                        {/* Summary Cards */}
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Pemasukan SPP</CardTitle>
                                    <ArrowUpRight className="h-4 w-4 text-green-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-green-600">
                                        {data.summary.total_income_formatted}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {data.period}
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Pengeluaran Gaji</CardTitle>
                                    <ArrowDownRight className="h-4 w-4 text-red-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold text-red-600">
                                        {data.summary.total_payroll_net_formatted}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {data.summary.expense_ratio}% dari pemasukan
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Saldo Bersih</CardTitle>
                                    {data.summary.net_balance >= 0 ? (
                                        <TrendingUp className="h-4 w-4 text-green-600" />
                                    ) : (
                                        <TrendingDown className="h-4 w-4 text-red-600" />
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className={`text-2xl font-bold ${data.summary.net_balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                        {data.summary.net_balance_formatted}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Pemasukan - Pengeluaran
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Karyawan Digaji</CardTitle>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        {data.summary.total_employees}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Total periode
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Payroll Summary Card */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Wallet className="h-5 w-5" />
                                    Ringkasan Gaji
                                </CardTitle>
                                <CardDescription>
                                    Detail pengeluaran gaji {data.period}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="rounded-lg border p-4">
                                        <p className="text-sm text-muted-foreground">Total Gaji Kotor</p>
                                        <p className="text-xl font-bold">{data.summary.total_payroll_gross_formatted}</p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="text-sm text-muted-foreground">Potongan (BPJS + Pajak)</p>
                                        <p className="text-xl font-bold text-red-600">-{data.summary.total_payroll_deductions_formatted}</p>
                                    </div>
                                    <div className="rounded-lg border p-4">
                                        <p className="text-sm text-muted-foreground">Gaji Bersih (THP)</p>
                                        <p className="text-xl font-bold text-green-600">{data.summary.total_payroll_net_formatted}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Monthly Breakdown Table (if viewing full year) */}
                        {data.monthly_breakdown && data.monthly_breakdown.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Rincian Per Bulan
                                    </CardTitle>
                                    <CardDescription>
                                        Perbandingan pemasukan dan pengeluaran per bulan
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Bulan</TableHead>
                                                <TableHead className="text-right">Pemasukan SPP</TableHead>
                                                <TableHead className="text-right">Pengeluaran Gaji</TableHead>
                                                <TableHead className="text-right">Saldo</TableHead>
                                                <TableHead className="text-center">Status Gaji</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {data.monthly_breakdown.map((item) => (
                                                <TableRow key={item.month}>
                                                    <TableCell className="font-medium">{item.month_name}</TableCell>
                                                    <TableCell className="text-right font-mono text-green-600">
                                                        {item.income_formatted}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-red-600">
                                                        {item.payroll_expense > 0 ? `-${item.payroll_expense_formatted}` : '-'}
                                                    </TableCell>
                                                    <TableCell className={`text-right font-mono ${item.net_balance >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                                        {item.net_balance_formatted}
                                                    </TableCell>
                                                    <TableCell className="text-center">
                                                        {item.has_payroll ? (
                                                            <Badge className="bg-green-100 text-green-800">
                                                                <CheckCircle className="h-3 w-3 mr-1" />
                                                                Sudah Proses
                                                            </Badge>
                                                        ) : (
                                                            <Badge variant="outline">
                                                                <Clock className="h-3 w-3 mr-1" />
                                                                Belum Proses
                                                            </Badge>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}

                        {/* Payroll Periods Table (if viewing specific month) */}
                        {data.payroll_periods && data.payroll_periods.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Wallet className="h-5 w-5" />
                                        Periode Gaji
                                    </CardTitle>
                                    <CardDescription>
                                        Detail periode penggajian yang sudah diproses
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Periode</TableHead>
                                                <TableHead className="text-center">Karyawan</TableHead>
                                                <TableHead className="text-right">Gaji Kotor</TableHead>
                                                <TableHead className="text-right">Potongan</TableHead>
                                                <TableHead className="text-right">Gaji Bersih</TableHead>
                                                <TableHead>Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {data.payroll_periods.map((period) => (
                                                <TableRow key={period.id}>
                                                    <TableCell className="font-medium">{period.period}</TableCell>
                                                    <TableCell className="text-center">{period.employee_count}</TableCell>
                                                    <TableCell className="text-right font-mono">
                                                        {period.total_gross_formatted}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono text-red-600">
                                                        -{period.total_deductions_formatted}
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono font-semibold">
                                                        {period.total_net_formatted}
                                                    </TableCell>
                                                    <TableCell>
                                                        {getStatusBadge(period.status)}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                {!data && !loading && (
                    <Card>
                        <CardContent className="py-12">
                            <div className="text-center text-muted-foreground">
                                <Wallet className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                <p>Tidak ada data untuk periode ini</p>
                                <p className="text-sm mt-2">Pilih tahun yang berbeda atau buat periode gaji terlebih dahulu</p>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}
