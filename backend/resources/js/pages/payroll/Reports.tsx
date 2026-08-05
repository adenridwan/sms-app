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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { toast } from 'sonner';
import {
    RefreshCw,
    Download,
    Users,
    Wallet,
    Calculator,
    Heart,
    ChevronLeft,
    ChevronRight,
    TrendingUp,
} from 'lucide-react';
import { payrollReportsApi } from '@/services/api';
import type { PaginationMeta } from '@/types';

interface DashboardData {
    summary: {
        year: number;
        total_employees: number;
        total_gross: number;
        total_gross_formatted: string;
        total_deductions: number;
        total_deductions_formatted: string;
        total_net: number;
        total_net_formatted: string;
        total_bpjs: number;
        total_bpjs_formatted: string;
        total_pph21: number;
        total_pph21_formatted: string;
    };
    current_month: {
        id: string;
        name: string;
        status: string;
        status_label: string;
        total_gross: number;
        total_gross_formatted: string;
        total_net: number;
        total_net_formatted: string;
        employee_count: number;
    } | null;
    monthly_trend: Array<{
        month: number;
        month_name: string;
        total_gross: number;
        total_gross_formatted: string;
        total_deductions: number;
        total_deductions_formatted: string;
        total_net: number;
        total_net_formatted: string;
        employee_count: number;
    }>;
}

interface MonthlyRecapItem {
    id: string;
    name: string;
    month: number;
    month_name: string;
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
    finalized_at: string | null;
}

interface Pph21Item {
    id: string;
    period: string;
    employee_name: string;
    employee_identifier: string;
    employee_type: string;
    employee_type_label: string;
    ptkp_status: string;
    gross_salary: number;
    gross_salary_formatted: string;
    pph21: number;
    pph21_formatted: string;
    net_salary: number;
    net_salary_formatted: string;
}

interface BpjsItem {
    id: string;
    period: string;
    employee_name: string;
    employee_identifier: string;
    employee_type: string;
    employee_type_label: string;
    gross_salary: number;
    gross_salary_formatted: string;
    bpjs_kesehatan: number;
    bpjs_kesehatan_formatted: string;
    bpjs_jht: number;
    bpjs_jht_formatted: string;
    bpjs_jp: number;
    bpjs_jp_formatted: string;
    total_bpjs: number;
    total_bpjs_formatted: string;
}

const MONTHS = [
    { value: '', label: 'Semua Bulan' },
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
        case 'draft': return 'bg-gray-100 text-gray-800';
        case 'processing': return 'bg-blue-100 text-blue-800';
        case 'pending_approval': return 'bg-yellow-100 text-yellow-800';
        case 'approved': return 'bg-green-100 text-green-800';
        case 'paid': return 'bg-emerald-100 text-emerald-800';
        case 'finalized': return 'bg-purple-100 text-purple-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

export default function Reports() {
    const [activeTab, setActiveTab] = useState('dashboard');
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);

    // Dashboard
    const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
    const [dashboardYear, setDashboardYear] = useState<string>(currentYear.toString());

    // Monthly Recap
    const [monthlyRecapData, setMonthlyRecapData] = useState<MonthlyRecapItem[]>([]);
    const [monthlyRecapSummary, setMonthlyRecapSummary] = useState<{
        year: number;
        total_periods: number;
        total_gross: number;
        total_gross_formatted: string;
        total_deductions: number;
        total_deductions_formatted: string;
        total_net: number;
        total_net_formatted: string;
    } | null>(null);
    const [recapYear, setRecapYear] = useState<string>(currentYear.toString());

    // PPh 21 Report
    const [pph21Data, setPph21Data] = useState<Pph21Item[]>([]);
    const [pph21Summary, setPph21Summary] = useState<{
        year: number;
        month: number | null;
        month_name: string;
        total_employees: number;
        total_gross: number;
        total_gross_formatted: string;
        total_pph21: number;
        total_pph21_formatted: string;
    } | null>(null);
    const [pph21MonthlyBreakdown, setPph21MonthlyBreakdown] = useState<Array<{
        month: number;
        month_name: string;
        employee_count: number;
        total_pph21: number;
        total_pph21_formatted: string;
    }>>([]);
    const [pph21Meta, setPph21Meta] = useState<PaginationMeta | null>(null);
    const [pph21Filters, setPph21Filters] = useState({
        year: currentYear.toString(),
        month: '',
        page: 1,
    });

    // BPJS Report
    const [bpjsData, setBpjsData] = useState<BpjsItem[]>([]);
    const [bpjsSummary, setBpjsSummary] = useState<{
        year: number;
        month: number | null;
        month_name: string;
        total_employees: number;
        total_bpjs_kesehatan: number;
        total_bpjs_kesehatan_formatted: string;
        total_bpjs_jht: number;
        total_bpjs_jht_formatted: string;
        total_bpjs_jp: number;
        total_bpjs_jp_formatted: string;
        total_bpjs: number;
        total_bpjs_formatted: string;
    } | null>(null);
    const [bpjsMonthlyBreakdown, setBpjsMonthlyBreakdown] = useState<Array<{
        month: number;
        month_name: string;
        employee_count: number;
        total_kesehatan: number;
        total_kesehatan_formatted: string;
        total_jht: number;
        total_jht_formatted: string;
        total_jp: number;
        total_jp_formatted: string;
        total: number;
        total_formatted: string;
    }>>([]);
    const [bpjsMeta, setBpjsMeta] = useState<PaginationMeta | null>(null);
    const [bpjsFilters, setBpjsFilters] = useState({
        year: currentYear.toString(),
        month: '',
        page: 1,
    });

    // Load dashboard data
    const loadDashboard = useCallback(async () => {
        setLoading(true);
        try {
            const response = await payrollReportsApi.dashboard({ year: parseInt(dashboardYear) });
            setDashboardData(response.data.data);
        } catch (error) {
            console.error('Failed to load dashboard:', error);
            toast.error('Gagal memuat dashboard');
        } finally {
            setLoading(false);
        }
    }, [dashboardYear]);

    // Load monthly recap
    const loadMonthlyRecap = useCallback(async () => {
        setLoading(true);
        try {
            const response = await payrollReportsApi.monthlyRecap({ year: parseInt(recapYear) });
            setMonthlyRecapData(response.data.data || []);
            setMonthlyRecapSummary(response.data.summary);
        } catch (error) {
            console.error('Failed to load monthly recap:', error);
            toast.error('Gagal memuat rekap bulanan');
        } finally {
            setLoading(false);
        }
    }, [recapYear]);

    // Load PPh 21 data
    const loadPph21 = useCallback(async () => {
        setLoading(true);
        try {
            const response = await payrollReportsApi.pph21({
                year: parseInt(pph21Filters.year),
                month: pph21Filters.month ? parseInt(pph21Filters.month) : undefined,
                per_page: 15,
            });
            setPph21Data(response.data.data || []);
            setPph21Summary(response.data.summary);
            setPph21MonthlyBreakdown(response.data.monthly_breakdown || []);
            setPph21Meta(response.data.meta);
        } catch (error) {
            console.error('Failed to load PPh 21:', error);
            toast.error('Gagal memuat laporan PPh 21');
        } finally {
            setLoading(false);
        }
    }, [pph21Filters]);

    // Load BPJS data
    const loadBpjs = useCallback(async () => {
        setLoading(true);
        try {
            const response = await payrollReportsApi.bpjs({
                year: parseInt(bpjsFilters.year),
                month: bpjsFilters.month ? parseInt(bpjsFilters.month) : undefined,
                per_page: 15,
            });
            setBpjsData(response.data.data || []);
            setBpjsSummary(response.data.summary);
            setBpjsMonthlyBreakdown(response.data.monthly_breakdown || []);
            setBpjsMeta(response.data.meta);
        } catch (error) {
            console.error('Failed to load BPJS:', error);
            toast.error('Gagal memuat laporan BPJS');
        } finally {
            setLoading(false);
        }
    }, [bpjsFilters]);

    // Load data based on active tab
    useEffect(() => {
        if (activeTab === 'dashboard') {
            loadDashboard();
        } else if (activeTab === 'recap') {
            loadMonthlyRecap();
        } else if (activeTab === 'pph21') {
            loadPph21();
        } else if (activeTab === 'bpjs') {
            loadBpjs();
        }
    }, [activeTab, loadDashboard, loadMonthlyRecap, loadPph21, loadBpjs]);

    // Export handlers
    const handleExportRecap = async () => {
        setExporting(true);
        try {
            const response = await payrollReportsApi.exportMonthlyRecap({ year: parseInt(recapYear) });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `rekap_gaji_${recapYear}.csv`);
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

    const handleExportPph21 = async () => {
        setExporting(true);
        try {
            const response = await payrollReportsApi.exportPph21({
                year: parseInt(pph21Filters.year),
                month: pph21Filters.month ? parseInt(pph21Filters.month) : undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            const monthSuffix = pph21Filters.month ? `_${pph21Filters.month}` : '';
            link.setAttribute('download', `pph21_${pph21Filters.year}${monthSuffix}.csv`);
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

    const handleExportBpjs = async () => {
        setExporting(true);
        try {
            const response = await payrollReportsApi.exportBpjs({
                year: parseInt(bpjsFilters.year),
                month: bpjsFilters.month ? parseInt(bpjsFilters.month) : undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            const monthSuffix = bpjsFilters.month ? `_${bpjsFilters.month}` : '';
            link.setAttribute('download', `bpjs_${bpjsFilters.year}${monthSuffix}.csv`);
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
        <MainLayout>
            <Head title="Laporan Penggajian" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Laporan Penggajian</h1>
                        <p className="text-muted-foreground">Lihat ringkasan dan laporan penggajian karyawan</p>
                    </div>
                </div>

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
                        <TabsTrigger value="recap">Rekap Bulanan</TabsTrigger>
                        <TabsTrigger value="pph21">PPh 21</TabsTrigger>
                        <TabsTrigger value="bpjs">BPJS</TabsTrigger>
                    </TabsList>

                    {/* Dashboard Tab */}
                    <TabsContent value="dashboard" className="space-y-6">
                        <div className="flex items-center gap-4">
                            <Select value={dashboardYear} onValueChange={setDashboardYear}>
                                <SelectTrigger className="w-[120px]">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((year) => (
                                        <SelectItem key={year} value={year}>{year}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" onClick={loadDashboard} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                        </div>

                        {dashboardData && (
                            <>
                                {/* Summary Cards */}
                                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total Karyawan</CardTitle>
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">{dashboardData.summary.total_employees}</div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total Gaji Kotor</CardTitle>
                                            <Wallet className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">{dashboardData.summary.total_gross_formatted}</div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total PPh 21</CardTitle>
                                            <Calculator className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-red-600">{dashboardData.summary.total_pph21_formatted}</div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total BPJS</CardTitle>
                                            <Heart className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-red-600">{dashboardData.summary.total_bpjs_formatted}</div>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    {/* Current Month */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Periode Bulan Ini</CardTitle>
                                            <CardDescription>Status penggajian bulan berjalan</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            {dashboardData.current_month ? (
                                                <div className="space-y-4">
                                                    <div className="flex items-center justify-between">
                                                        <span className="font-medium">{dashboardData.current_month.name}</span>
                                                        <Badge className={getStatusColor(dashboardData.current_month.status)}>
                                                            {dashboardData.current_month.status_label}
                                                        </Badge>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                                        <div>
                                                            <p className="text-muted-foreground">Karyawan</p>
                                                            <p className="font-medium">{dashboardData.current_month.employee_count}</p>
                                                        </div>
                                                        <div>
                                                            <p className="text-muted-foreground">Gaji Bersih</p>
                                                            <p className="font-medium text-green-600">{dashboardData.current_month.total_net_formatted}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            ) : (
                                                <p className="text-sm text-muted-foreground">Belum ada periode bulan ini</p>
                                            )}
                                        </CardContent>
                                    </Card>

                                    {/* Monthly Trend */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Trend Bulanan</CardTitle>
                                            <CardDescription>Gaji bersih per bulan</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {dashboardData.monthly_trend.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">Belum ada data</p>
                                                ) : (
                                                    dashboardData.monthly_trend.map((item) => (
                                                        <div key={item.month} className="flex items-center justify-between">
                                                            <div>
                                                                <p className="text-sm font-medium">{item.month_name}</p>
                                                                <p className="text-xs text-muted-foreground">{item.employee_count} karyawan</p>
                                                            </div>
                                                            <p className="font-medium text-green-600">{item.total_net_formatted}</p>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>
                                </div>
                            </>
                        )}
                    </TabsContent>

                    {/* Monthly Recap Tab */}
                    <TabsContent value="recap" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <Select value={recapYear} onValueChange={setRecapYear}>
                                <SelectTrigger className="w-[120px]">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((year) => (
                                        <SelectItem key={year} value={year}>{year}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" onClick={handleExportRecap} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {monthlyRecapSummary && (
                            <div className="grid gap-4 md:grid-cols-4">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Periode</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{monthlyRecapSummary.total_periods}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Gaji Kotor</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{monthlyRecapSummary.total_gross_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Potongan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-red-600">{monthlyRecapSummary.total_deductions_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Gaji Bersih</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-green-600">{monthlyRecapSummary.total_net_formatted}</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Bulan</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Karyawan</TableHead>
                                            <TableHead className="text-right">Gaji Kotor</TableHead>
                                            <TableHead className="text-right">Potongan</TableHead>
                                            <TableHead className="text-right">Gaji Bersih</TableHead>
                                            <TableHead>Tanggal Bayar</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {monthlyRecapData.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada data untuk tahun {recapYear}
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            monthlyRecapData.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="font-medium">{item.month_name} {item.year}</TableCell>
                                                    <TableCell>
                                                        <Badge className={getStatusColor(item.status)}>
                                                            {item.status_label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">{item.employee_count}</TableCell>
                                                    <TableCell className="text-right">{item.total_gross_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.total_deductions_formatted}</TableCell>
                                                    <TableCell className="text-right text-green-600">{item.total_net_formatted}</TableCell>
                                                    <TableCell>{item.payment_date || '-'}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* PPh 21 Tab */}
                    <TabsContent value="pph21" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Select
                                    value={pph21Filters.year}
                                    onValueChange={(value) => setPph21Filters(prev => ({ ...prev, year: value, page: 1 }))}
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
                                    value={pph21Filters.month}
                                    onValueChange={(value) => setPph21Filters(prev => ({ ...prev, month: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Bulan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MONTHS.map((month) => (
                                            <SelectItem key={month.value} value={month.value}>{month.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button variant="outline" onClick={handleExportPph21} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {pph21Summary && (
                            <div className="grid gap-4 md:grid-cols-3">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Periode</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-xl font-bold">{pph21Summary.month_name} {pph21Summary.year}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Wajib Pajak</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{pph21Summary.total_employees}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total PPh 21</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-red-600">{pph21Summary.total_pph21_formatted}</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Periode</TableHead>
                                            <TableHead>NIK/NIP</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead>PTKP</TableHead>
                                            <TableHead className="text-right">Gaji Kotor</TableHead>
                                            <TableHead className="text-right">PPh 21</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {pph21Data.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada data pajak
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            pph21Data.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell>{item.period}</TableCell>
                                                    <TableCell className="font-mono">{item.employee_identifier}</TableCell>
                                                    <TableCell>{item.employee_name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{item.employee_type_label}</Badge>
                                                    </TableCell>
                                                    <TableCell>{item.ptkp_status}</TableCell>
                                                    <TableCell className="text-right">{item.gross_salary_formatted}</TableCell>
                                                    <TableCell className="text-right font-medium text-red-600">{item.pph21_formatted}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {pph21Meta && pph21Meta.last_page > 1 && (
                            <div className="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPph21Filters(prev => ({ ...prev, page: prev.page - 1 }))}
                                    disabled={pph21Filters.page <= 1}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="text-sm">
                                    Halaman {pph21Meta.current_page} dari {pph21Meta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPph21Filters(prev => ({ ...prev, page: prev.page + 1 }))}
                                    disabled={pph21Filters.page >= pph21Meta.last_page}
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </TabsContent>

                    {/* BPJS Tab */}
                    <TabsContent value="bpjs" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Select
                                    value={bpjsFilters.year}
                                    onValueChange={(value) => setBpjsFilters(prev => ({ ...prev, year: value, page: 1 }))}
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
                                    value={bpjsFilters.month}
                                    onValueChange={(value) => setBpjsFilters(prev => ({ ...prev, month: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Bulan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {MONTHS.map((month) => (
                                            <SelectItem key={month.value} value={month.value}>{month.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button variant="outline" onClick={handleExportBpjs} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {bpjsSummary && (
                            <div className="grid gap-4 md:grid-cols-5">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Peserta</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{bpjsSummary.total_employees}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">BPJS Kesehatan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-xl font-bold text-red-600">{bpjsSummary.total_bpjs_kesehatan_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">BPJS JHT</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-xl font-bold text-red-600">{bpjsSummary.total_bpjs_jht_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">BPJS JP</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-xl font-bold text-red-600">{bpjsSummary.total_bpjs_jp_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total BPJS</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-red-600">{bpjsSummary.total_bpjs_formatted}</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Periode</TableHead>
                                            <TableHead>NIK/NIP</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead className="text-right">Gaji Kotor</TableHead>
                                            <TableHead className="text-right">Kesehatan</TableHead>
                                            <TableHead className="text-right">JHT</TableHead>
                                            <TableHead className="text-right">JP</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {bpjsData.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada data BPJS
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            bpjsData.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell>{item.period}</TableCell>
                                                    <TableCell className="font-mono">{item.employee_identifier}</TableCell>
                                                    <TableCell>{item.employee_name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{item.employee_type_label}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right">{item.gross_salary_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.bpjs_kesehatan_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.bpjs_jht_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.bpjs_jp_formatted}</TableCell>
                                                    <TableCell className="text-right font-medium text-red-600">{item.total_bpjs_formatted}</TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {bpjsMeta && bpjsMeta.last_page > 1 && (
                            <div className="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setBpjsFilters(prev => ({ ...prev, page: prev.page - 1 }))}
                                    disabled={bpjsFilters.page <= 1}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="text-sm">
                                    Halaman {bpjsMeta.current_page} dari {bpjsMeta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setBpjsFilters(prev => ({ ...prev, page: prev.page + 1 }))}
                                    disabled={bpjsFilters.page >= bpjsMeta.last_page}
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}
