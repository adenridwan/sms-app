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
    TrendingUp,
    TrendingDown,
    Users,
    Wallet,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { financeReportsApi, academicYearsApi, classroomsApi } from '@/services/api';
import type { AcademicYear, Classroom, PaginationMeta } from '@/types';

interface DashboardData {
    summary: {
        total_students: number;
        total_fees: number;
        total_fees_formatted: string;
        total_paid: number;
        total_paid_formatted: string;
        total_outstanding: number;
        total_outstanding_formatted: string;
        monthly_payments: number;
        monthly_payments_formatted: string;
        overdue_students: number;
        collection_rate: number;
    };
    status_breakdown: Record<string, { count: number; amount: number; amount_formatted: string }>;
    recent_payments: Array<{
        id: string;
        invoice_number: string;
        student_name: string;
        amount: number;
        amount_formatted: string;
        paid_at: string;
    }>;
    monthly_trend: Array<{
        year: number;
        month: number;
        month_name: string;
        total: number;
        total_formatted: string;
    }>;
}

interface OutstandingItem {
    id: string;
    student: {
        id: string;
        name: string;
        nis: string;
        classroom: string;
    };
    fee_type: string;
    period: string;
    total_amount: number;
    total_amount_formatted: string;
    paid_amount: number;
    paid_amount_formatted: string;
    remaining_amount: number;
    remaining_amount_formatted: string;
    due_date: string;
    status: string;
    status_label: string;
    days_overdue: number;
}

interface ClassroomReport {
    id: string;
    name: string;
    grade_level: string;
    major: string;
    student_count: number;
    unpaid_count: number;
    total_fees: number;
    total_fees_formatted: string;
    total_paid: number;
    total_paid_formatted: string;
    total_outstanding: number;
    total_outstanding_formatted: string;
    collection_rate: number;
}

interface MonthlyItem {
    id: string;
    student: {
        id: string;
        name: string;
        nis: string;
        classroom: string;
    };
    fee_type: string;
    total_amount: number;
    total_amount_formatted: string;
    paid_amount: number;
    paid_amount_formatted: string;
    remaining_amount: number;
    remaining_amount_formatted: string;
    due_date: string;
    status: string;
    status_label: string;
}

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

function getStatusColor(status: string): string {
    switch (status) {
        case 'paid': return 'bg-green-100 text-green-800';
        case 'partial': return 'bg-yellow-100 text-yellow-800';
        case 'unpaid': return 'bg-gray-100 text-gray-800';
        case 'overdue': return 'bg-red-100 text-red-800';
        case 'waived': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

export default function Reports() {
    const [activeTab, setActiveTab] = useState('dashboard');
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);

    // Lookup data
    const [academicYears, setAcademicYears] = useState<AcademicYear[]>([]);
    const [classrooms, setClassrooms] = useState<Classroom[]>([]);

    // Dashboard
    const [dashboardData, setDashboardData] = useState<DashboardData | null>(null);
    const [dashboardYearId, setDashboardYearId] = useState<string>('');

    // Outstanding Report
    const [outstandingData, setOutstandingData] = useState<OutstandingItem[]>([]);
    const [outstandingSummary, setOutstandingSummary] = useState<{
        total_outstanding: number;
        total_outstanding_formatted: string;
        total_students: number;
        total_fees: number;
    } | null>(null);
    const [outstandingMeta, setOutstandingMeta] = useState<PaginationMeta | null>(null);
    const [outstandingFilters, setOutstandingFilters] = useState({
        academic_year_id: '',
        classroom_id: '',
        status: '',
        page: 1,
    });

    // Classroom Report
    const [classroomData, setClassroomData] = useState<ClassroomReport[]>([]);
    const [classroomSummary, setClassroomSummary] = useState<{
        total_classrooms: number;
        total_students: number;
        total_fees: number;
        total_fees_formatted: string;
        total_paid: number;
        total_paid_formatted: string;
        total_outstanding: number;
        total_outstanding_formatted: string;
        collection_rate: number;
    } | null>(null);
    const [classroomYearId, setClassroomYearId] = useState<string>('');

    // Monthly Report
    const [monthlyData, setMonthlyData] = useState<MonthlyItem[]>([]);
    const [monthlySummary, setMonthlySummary] = useState<{
        period: string;
        total_students: number;
        total_fees: number;
        total_fees_formatted: string;
        total_paid: number;
        total_paid_formatted: string;
        total_outstanding: number;
        total_outstanding_formatted: string;
        paid_count: number;
        unpaid_count: number;
    } | null>(null);
    const [monthlyMeta, setMonthlyMeta] = useState<PaginationMeta | null>(null);
    const [monthlyFilters, setMonthlyFilters] = useState({
        month: currentMonth.toString(),
        year: currentYear.toString(),
        classroom_id: '',
        page: 1,
    });

    // Load lookup data
    useEffect(() => {
        const loadLookups = async () => {
            try {
                const [yearsRes, classroomsRes] = await Promise.all([
                    academicYearsApi.list({ per_page: 100 }),
                    classroomsApi.list({ per_page: 100 }),
                ]);
                setAcademicYears(yearsRes.data.data?.data || []);
                setClassrooms(classroomsRes.data.data?.data || []);
            } catch (error) {
                console.error('Failed to load lookups:', error);
            }
        };
        loadLookups();
    }, []);

    // Load dashboard data
    const loadDashboard = useCallback(async () => {
        setLoading(true);
        try {
            const response = await financeReportsApi.dashboard<DashboardData>({
                academic_year_id: dashboardYearId || undefined,
            });
            setDashboardData(response.data.data);
        } catch (error) {
            console.error('Failed to load dashboard:', error);
            toast.error('Gagal memuat dashboard');
        } finally {
            setLoading(false);
        }
    }, [dashboardYearId]);

    // Load outstanding data
    const loadOutstanding = useCallback(async () => {
        setLoading(true);
        try {
            const response = await financeReportsApi.outstanding<OutstandingItem>({
                ...outstandingFilters,
                academic_year_id: outstandingFilters.academic_year_id || undefined,
                classroom_id: outstandingFilters.classroom_id || undefined,
                status: outstandingFilters.status || undefined,
                per_page: 15,
            });
            setOutstandingData(response.data.data || []);
            setOutstandingSummary((response.data.summary as typeof outstandingSummary) ?? null);
            setOutstandingMeta(response.data.meta ?? null);
        } catch (error) {
            console.error('Failed to load outstanding:', error);
            toast.error('Gagal memuat data tunggakan');
        } finally {
            setLoading(false);
        }
    }, [outstandingFilters]);

    // Load classroom data
    const loadClassroomReport = useCallback(async () => {
        setLoading(true);
        try {
            const response = await financeReportsApi.byClassroom<ClassroomReport>({
                academic_year_id: classroomYearId || undefined,
            });
            setClassroomData(response.data.data || []);
            setClassroomSummary((response.data.summary as typeof classroomSummary) ?? null);
        } catch (error) {
            console.error('Failed to load classroom report:', error);
            toast.error('Gagal memuat laporan per kelas');
        } finally {
            setLoading(false);
        }
    }, [classroomYearId]);

    // Load monthly data
    const loadMonthly = useCallback(async () => {
        setLoading(true);
        try {
            const response = await financeReportsApi.monthly<MonthlyItem>({
                month: parseInt(monthlyFilters.month),
                year: parseInt(monthlyFilters.year),
                classroom_id: monthlyFilters.classroom_id || undefined,
                per_page: 15,
            });
            setMonthlyData(response.data.data || []);
            setMonthlySummary((response.data.summary as typeof monthlySummary) ?? null);
            setMonthlyMeta(response.data.meta ?? null);
        } catch (error) {
            console.error('Failed to load monthly:', error);
            toast.error('Gagal memuat laporan bulanan');
        } finally {
            setLoading(false);
        }
    }, [monthlyFilters]);

    // Load data based on active tab
    useEffect(() => {
        if (activeTab === 'dashboard') {
            loadDashboard();
        } else if (activeTab === 'outstanding') {
            loadOutstanding();
        } else if (activeTab === 'classroom') {
            loadClassroomReport();
        } else if (activeTab === 'monthly') {
            loadMonthly();
        }
    }, [activeTab, loadDashboard, loadOutstanding, loadClassroomReport, loadMonthly]);

    // Export handlers
    const handleExportOutstanding = async () => {
        setExporting(true);
        try {
            const response = await financeReportsApi.exportOutstanding({
                academic_year_id: outstandingFilters.academic_year_id || undefined,
                classroom_id: outstandingFilters.classroom_id || undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `tunggakan_${new Date().toISOString().slice(0, 10)}.csv`);
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

    const handleExportClassroom = async () => {
        setExporting(true);
        try {
            const response = await financeReportsApi.exportByClassroom({
                academic_year_id: classroomYearId || undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `laporan_per_kelas_${new Date().toISOString().slice(0, 10)}.csv`);
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

    const handleExportMonthly = async () => {
        setExporting(true);
        try {
            const response = await financeReportsApi.exportMonthly({
                month: parseInt(monthlyFilters.month),
                year: parseInt(monthlyFilters.year),
                classroom_id: monthlyFilters.classroom_id || undefined,
            });
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = document.createElement('a');
            link.href = url;
            link.setAttribute('download', `tagihan_bulanan_${monthlyFilters.month}_${monthlyFilters.year}.csv`);
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
            <Head title="Laporan Keuangan" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Laporan Keuangan</h1>
                        <p className="text-muted-foreground">Lihat ringkasan dan laporan keuangan siswa</p>
                    </div>
                </div>

                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="dashboard">Dashboard</TabsTrigger>
                        <TabsTrigger value="outstanding">Tunggakan</TabsTrigger>
                        <TabsTrigger value="classroom">Per Kelas</TabsTrigger>
                        <TabsTrigger value="monthly">Bulanan</TabsTrigger>
                    </TabsList>

                    {/* Dashboard Tab */}
                    <TabsContent value="dashboard" className="space-y-6">
                        <div className="flex items-center gap-4">
                            <Select value={dashboardYearId} onValueChange={setDashboardYearId}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Semua Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Tahun Ajaran</SelectItem>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>{year.name}</SelectItem>
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
                                            <CardTitle className="text-sm font-medium">Total Siswa</CardTitle>
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">{dashboardData.summary.total_students}</div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total Tagihan</CardTitle>
                                            <Wallet className="h-4 w-4 text-muted-foreground" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold">{dashboardData.summary.total_fees_formatted}</div>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total Terbayar</CardTitle>
                                            <TrendingUp className="h-4 w-4 text-green-600" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-green-600">{dashboardData.summary.total_paid_formatted}</div>
                                            <p className="text-xs text-muted-foreground">{dashboardData.summary.collection_rate}% terkumpul</p>
                                        </CardContent>
                                    </Card>
                                    <Card>
                                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                            <CardTitle className="text-sm font-medium">Total Tunggakan</CardTitle>
                                            <TrendingDown className="h-4 w-4 text-red-600" />
                                        </CardHeader>
                                        <CardContent>
                                            <div className="text-2xl font-bold text-red-600">{dashboardData.summary.total_outstanding_formatted}</div>
                                            <p className="text-xs text-muted-foreground">{dashboardData.summary.overdue_students} siswa menunggak</p>
                                        </CardContent>
                                    </Card>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    {/* Recent Payments */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Pembayaran Terbaru</CardTitle>
                                            <CardDescription>5 pembayaran terakhir</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {dashboardData.recent_payments.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">Belum ada pembayaran</p>
                                                ) : (
                                                    dashboardData.recent_payments.map((payment) => (
                                                        <div key={payment.id} className="flex items-center justify-between">
                                                            <div>
                                                                <p className="font-medium">{payment.student_name}</p>
                                                                <p className="text-sm text-muted-foreground">{payment.invoice_number}</p>
                                                            </div>
                                                            <div className="text-right">
                                                                <p className="font-medium text-green-600">{payment.amount_formatted}</p>
                                                                <p className="text-xs text-muted-foreground">{payment.paid_at}</p>
                                                            </div>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {/* Monthly Trend */}
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Trend Bulanan</CardTitle>
                                            <CardDescription>Penerimaan 6 bulan terakhir</CardDescription>
                                        </CardHeader>
                                        <CardContent>
                                            <div className="space-y-4">
                                                {dashboardData.monthly_trend.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">Belum ada data</p>
                                                ) : (
                                                    dashboardData.monthly_trend.map((item) => (
                                                        <div key={`${item.year}-${item.month}`} className="flex items-center justify-between">
                                                            <p className="text-sm">{item.month_name} {item.year}</p>
                                                            <p className="font-medium">{item.total_formatted}</p>
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

                    {/* Outstanding Tab */}
                    <TabsContent value="outstanding" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Select
                                    value={outstandingFilters.academic_year_id}
                                    onValueChange={(value) => setOutstandingFilters(prev => ({ ...prev, academic_year_id: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Tahun Ajaran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua</SelectItem>
                                        {academicYears.map((year) => (
                                            <SelectItem key={year.id} value={year.id}>{year.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={outstandingFilters.classroom_id}
                                    onValueChange={(value) => setOutstandingFilters(prev => ({ ...prev, classroom_id: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua Kelas</SelectItem>
                                        {classrooms.map((classroom) => (
                                            <SelectItem key={classroom.id} value={classroom.id}>{classroom.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select
                                    value={outstandingFilters.status}
                                    onValueChange={(value) => setOutstandingFilters(prev => ({ ...prev, status: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua</SelectItem>
                                        <SelectItem value="unpaid">Belum Dibayar</SelectItem>
                                        <SelectItem value="partial">Sebagian</SelectItem>
                                        <SelectItem value="overdue">Jatuh Tempo</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button variant="outline" onClick={handleExportOutstanding} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {outstandingSummary && (
                            <div className="grid gap-4 md:grid-cols-3">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Tunggakan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-red-600">{outstandingSummary.total_outstanding_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Siswa</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{outstandingSummary.total_students}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Tagihan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{outstandingSummary.total_fees}</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>NIS</TableHead>
                                            <TableHead>Nama Siswa</TableHead>
                                            <TableHead>Kelas</TableHead>
                                            <TableHead>Jenis Tagihan</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead className="text-right">Sisa</TableHead>
                                            <TableHead>Jatuh Tempo</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {outstandingData.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={8} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada tunggakan
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            outstandingData.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="font-mono">{item.student.nis}</TableCell>
                                                    <TableCell>{item.student.name}</TableCell>
                                                    <TableCell>{item.student.classroom}</TableCell>
                                                    <TableCell>{item.fee_type}</TableCell>
                                                    <TableCell>{item.period}</TableCell>
                                                    <TableCell className="text-right font-medium text-red-600">
                                                        {item.remaining_amount_formatted}
                                                    </TableCell>
                                                    <TableCell>{item.due_date}</TableCell>
                                                    <TableCell>
                                                        <Badge className={getStatusColor(item.status)}>
                                                            {item.status_label}
                                                        </Badge>
                                                        {item.days_overdue > 0 && (
                                                            <span className="ml-2 text-xs text-red-600">
                                                                +{item.days_overdue} hari
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {outstandingMeta && outstandingMeta.last_page > 1 && (
                            <div className="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setOutstandingFilters(prev => ({ ...prev, page: prev.page - 1 }))}
                                    disabled={outstandingFilters.page <= 1}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="text-sm">
                                    Halaman {outstandingMeta.current_page} dari {outstandingMeta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setOutstandingFilters(prev => ({ ...prev, page: prev.page + 1 }))}
                                    disabled={outstandingFilters.page >= outstandingMeta.last_page}
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        )}
                    </TabsContent>

                    {/* Classroom Tab */}
                    <TabsContent value="classroom" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <Select value={classroomYearId} onValueChange={setClassroomYearId}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Semua Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Tahun Ajaran</SelectItem>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>{year.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" onClick={handleExportClassroom} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {classroomSummary && (
                            <div className="grid gap-4 md:grid-cols-4">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Jumlah Kelas</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{classroomSummary.total_classrooms}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Tagihan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{classroomSummary.total_fees_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Terbayar</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-green-600">{classroomSummary.total_paid_formatted}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">% Terkumpul</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{classroomSummary.collection_rate}%</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Kelas</TableHead>
                                            <TableHead>Tingkat</TableHead>
                                            <TableHead>Jurusan</TableHead>
                                            <TableHead className="text-right">Siswa</TableHead>
                                            <TableHead className="text-right">Belum Bayar</TableHead>
                                            <TableHead className="text-right">Total Tagihan</TableHead>
                                            <TableHead className="text-right">Terbayar</TableHead>
                                            <TableHead className="text-right">Tunggakan</TableHead>
                                            <TableHead className="text-right">%</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {classroomData.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada data
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            classroomData.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="font-medium">{item.name}</TableCell>
                                                    <TableCell>{item.grade_level}</TableCell>
                                                    <TableCell>{item.major}</TableCell>
                                                    <TableCell className="text-right">{item.student_count}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.unpaid_count}</TableCell>
                                                    <TableCell className="text-right">{item.total_fees_formatted}</TableCell>
                                                    <TableCell className="text-right text-green-600">{item.total_paid_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.total_outstanding_formatted}</TableCell>
                                                    <TableCell className="text-right">
                                                        <Badge variant={item.collection_rate >= 80 ? 'default' : item.collection_rate >= 50 ? 'secondary' : 'destructive'}>
                                                            {item.collection_rate}%
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Monthly Tab */}
                    <TabsContent value="monthly" className="space-y-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <Select
                                    value={monthlyFilters.month}
                                    onValueChange={(value) => setMonthlyFilters(prev => ({ ...prev, month: value, page: 1 }))}
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
                                <Select
                                    value={monthlyFilters.year}
                                    onValueChange={(value) => setMonthlyFilters(prev => ({ ...prev, year: value, page: 1 }))}
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
                                    value={monthlyFilters.classroom_id}
                                    onValueChange={(value) => setMonthlyFilters(prev => ({ ...prev, classroom_id: value, page: 1 }))}
                                >
                                    <SelectTrigger className="w-[180px]">
                                        <SelectValue placeholder="Kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua Kelas</SelectItem>
                                        {classrooms.map((classroom) => (
                                            <SelectItem key={classroom.id} value={classroom.id}>{classroom.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <Button variant="outline" onClick={handleExportMonthly} disabled={exporting}>
                                <Download className="h-4 w-4 mr-2" />
                                Export CSV
                            </Button>
                        </div>

                        {monthlySummary && (
                            <div className="grid gap-4 md:grid-cols-4">
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Periode</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-xl font-bold">{monthlySummary.period}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Total Tagihan</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold">{monthlySummary.total_fees_formatted}</div>
                                        <p className="text-xs text-muted-foreground">{monthlySummary.total_students} siswa</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Lunas</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-green-600">{monthlySummary.paid_count}</div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm font-medium">Belum Lunas</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="text-2xl font-bold text-red-600">{monthlySummary.unpaid_count}</div>
                                    </CardContent>
                                </Card>
                            </div>
                        )}

                        <Card>
                            <CardContent className="p-0">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>NIS</TableHead>
                                            <TableHead>Nama Siswa</TableHead>
                                            <TableHead>Kelas</TableHead>
                                            <TableHead>Jenis Tagihan</TableHead>
                                            <TableHead className="text-right">Total</TableHead>
                                            <TableHead className="text-right">Terbayar</TableHead>
                                            <TableHead className="text-right">Sisa</TableHead>
                                            <TableHead>Jatuh Tempo</TableHead>
                                            <TableHead>Status</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {monthlyData.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                                                    Tidak ada tagihan untuk periode ini
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            monthlyData.map((item) => (
                                                <TableRow key={item.id}>
                                                    <TableCell className="font-mono">{item.student.nis}</TableCell>
                                                    <TableCell>{item.student.name}</TableCell>
                                                    <TableCell>{item.student.classroom}</TableCell>
                                                    <TableCell>{item.fee_type}</TableCell>
                                                    <TableCell className="text-right">{item.total_amount_formatted}</TableCell>
                                                    <TableCell className="text-right text-green-600">{item.paid_amount_formatted}</TableCell>
                                                    <TableCell className="text-right text-red-600">{item.remaining_amount_formatted}</TableCell>
                                                    <TableCell>{item.due_date}</TableCell>
                                                    <TableCell>
                                                        <Badge className={getStatusColor(item.status)}>
                                                            {item.status_label}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        )}
                                    </TableBody>
                                </Table>
                            </CardContent>
                        </Card>

                        {monthlyMeta && monthlyMeta.last_page > 1 && (
                            <div className="flex items-center justify-end gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setMonthlyFilters(prev => ({ ...prev, page: prev.page - 1 }))}
                                    disabled={monthlyFilters.page <= 1}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </Button>
                                <span className="text-sm">
                                    Halaman {monthlyMeta.current_page} dari {monthlyMeta.last_page}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setMonthlyFilters(prev => ({ ...prev, page: prev.page + 1 }))}
                                    disabled={monthlyFilters.page >= monthlyMeta.last_page}
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
