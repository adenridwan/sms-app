import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
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
import { Progress } from '@/components/ui/progress';
import { toast } from 'sonner';
import { Download, RefreshCw, FileText, FileSpreadsheet, TrendingUp, Users, GraduationCap } from 'lucide-react';
import { attendanceReportApi } from '@/services/attendance';
import type { MonthlyReport, WeeklyTrend } from '@/types/attendance';
import type { ClassRoom } from '@/types';

interface Props {
    classrooms: ClassRoom[];
}

const months = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maret' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Agustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 2 + i);

export default function ReportsIndex({ classrooms }: Props) {
    const [activeTab, setActiveTab] = useState('students');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(currentYear);
    const [classroomId, setClassroomId] = useState('all');
    const [report, setReport] = useState<MonthlyReport | null>(null);
    const [weeklyTrend, setWeeklyTrend] = useState<WeeklyTrend | null>(null);
    const [loading, setLoading] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const [exportingExcel, setExportingExcel] = useState(false);

    const fetchReport = async () => {
        setLoading(true);
        try {
            const params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' } = {
                month,
                year,
                type: activeTab === 'students' ? 'student' : 'teacher',
            };
            if (activeTab === 'students' && classroomId !== 'all') {
                params.classroom_id = classroomId;
            }

            const [reportRes, trendRes] = await Promise.all([
                attendanceReportApi.monthly(params),
                attendanceReportApi.weeklyTrend({
                    type: activeTab === 'students' ? 'student' : 'teacher',
                    classroom_id: activeTab === 'students' && classroomId !== 'all' ? classroomId : undefined,
                }),
            ]);

            if (reportRes.data.data) {
                setReport(reportRes.data.data);
            }
            if (trendRes.data.data) {
                setWeeklyTrend(trendRes.data.data);
            }
        } catch (error) {
            toast.error('Gagal memuat laporan');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchReport();
    }, [month, year, classroomId, activeTab]);

    const handleDownloadPdf = async () => {
        setDownloading(true);
        try {
            const params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' } = {
                month,
                year,
                type: activeTab === 'students' ? 'student' : 'teacher',
            };
            if (activeTab === 'students' && classroomId !== 'all') {
                params.classroom_id = classroomId;
            }

            const response = await attendanceReportApi.downloadPdf(params);
            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `laporan-absensi-${activeTab}-${months.find(m => m.value === month)?.label}-${year}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            toast.success('PDF berhasil diunduh');
        } catch (error) {
            toast.error('Gagal mengunduh PDF');
        } finally {
            setDownloading(false);
        }
    };

    const handleDownloadExcel = async () => {
        setExportingExcel(true);
        try {
            const params: { month: number; year: number; classroom_id?: string; type?: 'student' | 'teacher' } = {
                month,
                year,
                type: activeTab === 'students' ? 'student' : 'teacher',
            };
            if (activeTab === 'students' && classroomId !== 'all') {
                params.classroom_id = classroomId;
            }

            const response = await attendanceReportApi.downloadExcel(params);
            const blob = new Blob([response.data], {
                type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `laporan-absensi-${activeTab}-${months.find(m => m.value === month)?.label}-${year}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            toast.success('Excel berhasil diunduh');
        } catch (error) {
            toast.error('Gagal mengunduh Excel');
        } finally {
            setExportingExcel(false);
        }
    };

    const getAttendanceRate = (rate: number) => {
        if (rate >= 90) return 'text-green-600';
        if (rate >= 75) return 'text-yellow-600';
        return 'text-red-600';
    };

    return (
        <MainLayout title="Laporan Absensi">
            <Head title="Laporan Absensi" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Laporan Absensi</h1>
                        <p className="text-muted-foreground">
                            Lihat statistik dan laporan kehadiran
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={handleDownloadExcel} disabled={exportingExcel || !report}>
                            <FileSpreadsheet className={`mr-2 h-4 w-4 ${exportingExcel ? 'animate-spin' : ''}`} />
                            {exportingExcel ? 'Mengekspor...' : 'Export Excel'}
                        </Button>
                        <Button onClick={handleDownloadPdf} disabled={downloading || !report}>
                            <Download className={`mr-2 h-4 w-4 ${downloading ? 'animate-spin' : ''}`} />
                            {downloading ? 'Mengunduh...' : 'Download PDF'}
                        </Button>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <TabsList>
                        <TabsTrigger value="students" className="gap-2">
                            <Users className="h-4 w-4" />
                            Siswa
                        </TabsTrigger>
                        <TabsTrigger value="teachers" className="gap-2">
                            <GraduationCap className="h-4 w-4" />
                            Guru
                        </TabsTrigger>
                    </TabsList>

                    {/* Filters */}
                    <Card className="mt-4">
                        <CardHeader>
                            <CardTitle>Filter Laporan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-wrap gap-4">
                                <div className="w-[150px]">
                                    <Label>Bulan</Label>
                                    <Select value={String(month)} onValueChange={(v) => setMonth(Number(v))}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {months.map((m) => (
                                                <SelectItem key={m.value} value={String(m.value)}>
                                                    {m.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="w-[100px]">
                                    <Label>Tahun</Label>
                                    <Select value={String(year)} onValueChange={(v) => setYear(Number(v))}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {years.map((y) => (
                                                <SelectItem key={y} value={String(y)}>
                                                    {y}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                {activeTab === 'students' && (
                                    <div className="w-[200px]">
                                        <Label>Kelas</Label>
                                        <Select value={classroomId} onValueChange={setClassroomId}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">Semua Kelas</SelectItem>
                                                {classrooms.map((c) => (
                                                    <SelectItem key={c.id} value={c.id}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                                <div className="flex items-end">
                                    <Button onClick={fetchReport} variant="outline" disabled={loading}>
                                        <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                        Refresh
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Students Content */}
                    <TabsContent value="students" className="space-y-4">
                        {loading ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <RefreshCw className="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Memuat laporan...</p>
                                </CardContent>
                            </Card>
                        ) : !report ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <FileText className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Tidak ada data</p>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                {/* Summary */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Ringkasan - {report.month_name} {report.year}</CardTitle>
                                        <CardDescription>
                                            Total {report.total_working_days} hari kerja
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid gap-4 md:grid-cols-4">
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-green-600">
                                                    {report.students?.reduce((sum, s) => sum + s.stats.hadir, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Hadir</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-yellow-600">
                                                    {report.students?.reduce((sum, s) => sum + s.stats.sakit, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Sakit</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-blue-600">
                                                    {report.students?.reduce((sum, s) => sum + s.stats.izin, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Izin</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-red-600">
                                                    {report.students?.reduce((sum, s) => sum + s.stats.alfa, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Alfa</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Student List */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Detail per Siswa</CardTitle>
                                        <CardDescription>
                                            {report.students?.length || 0} siswa
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="rounded-md border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>No</TableHead>
                                                        <TableHead>NIS</TableHead>
                                                        <TableHead>Nama</TableHead>
                                                        <TableHead className="text-center">Hadir</TableHead>
                                                        <TableHead className="text-center">Sakit</TableHead>
                                                        <TableHead className="text-center">Izin</TableHead>
                                                        <TableHead className="text-center">Alfa</TableHead>
                                                        <TableHead className="text-center">Terlambat</TableHead>
                                                        <TableHead>Kehadiran</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {report.students?.map((student, index) => (
                                                        <TableRow key={student.student_id}>
                                                            <TableCell>{index + 1}</TableCell>
                                                            <TableCell className="font-medium">{student.nis}</TableCell>
                                                            <TableCell>{student.name}</TableCell>
                                                            <TableCell className="text-center">{student.stats.hadir}</TableCell>
                                                            <TableCell className="text-center">{student.stats.sakit}</TableCell>
                                                            <TableCell className="text-center">{student.stats.izin}</TableCell>
                                                            <TableCell className="text-center">{student.stats.alfa}</TableCell>
                                                            <TableCell className="text-center">
                                                                {student.stats.total_late_minutes > 0
                                                                    ? `${student.stats.total_late_minutes} mnt`
                                                                    : '-'}
                                                            </TableCell>
                                                            <TableCell>
                                                                <div className="flex items-center gap-2">
                                                                    <Progress value={student.attendance_rate} className="w-20" />
                                                                    <span className={`text-sm font-medium ${getAttendanceRate(student.attendance_rate)}`}>
                                                                        {student.attendance_rate.toFixed(1)}%
                                                                    </span>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </TabsContent>

                    {/* Teachers Content */}
                    <TabsContent value="teachers" className="space-y-4">
                        {loading ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <RefreshCw className="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Memuat laporan...</p>
                                </CardContent>
                            </Card>
                        ) : !report ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <FileText className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Tidak ada data</p>
                                </CardContent>
                            </Card>
                        ) : (
                            <>
                                {/* Summary */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Ringkasan - {report.month_name} {report.year}</CardTitle>
                                        <CardDescription>
                                            Total {report.total_working_days} hari kerja
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid gap-4 md:grid-cols-4">
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-green-600">
                                                    {report.teachers?.reduce((sum, t) => sum + t.stats.present, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Hadir</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-yellow-600">
                                                    {report.teachers?.reduce((sum, t) => sum + t.stats.sick, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Sakit</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-blue-600">
                                                    {report.teachers?.reduce((sum, t) => sum + t.stats.permitted, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Izin</div>
                                            </div>
                                            <div className="rounded-lg border p-4 text-center">
                                                <div className="text-2xl font-bold text-red-600">
                                                    {report.teachers?.reduce((sum, t) => sum + t.stats.absent, 0) || 0}
                                                </div>
                                                <div className="text-sm text-muted-foreground">Total Tidak Hadir</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Teacher List */}
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Detail per Guru</CardTitle>
                                        <CardDescription>
                                            {report.teachers?.length || 0} guru
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="rounded-md border">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>No</TableHead>
                                                        <TableHead>Nama</TableHead>
                                                        <TableHead className="text-center">Hadir</TableHead>
                                                        <TableHead className="text-center">Sakit</TableHead>
                                                        <TableHead className="text-center">Izin</TableHead>
                                                        <TableHead className="text-center">Tidak Hadir</TableHead>
                                                        <TableHead className="text-center">Terlambat</TableHead>
                                                        <TableHead>Kehadiran</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {report.teachers?.map((teacher, index) => (
                                                        <TableRow key={teacher.user_id}>
                                                            <TableCell>{index + 1}</TableCell>
                                                            <TableCell className="font-medium">{teacher.name}</TableCell>
                                                            <TableCell className="text-center">{teacher.stats.present}</TableCell>
                                                            <TableCell className="text-center">{teacher.stats.sick}</TableCell>
                                                            <TableCell className="text-center">{teacher.stats.permitted}</TableCell>
                                                            <TableCell className="text-center">{teacher.stats.absent}</TableCell>
                                                            <TableCell className="text-center">
                                                                {teacher.stats.total_late_minutes > 0
                                                                    ? `${teacher.stats.total_late_minutes} mnt`
                                                                    : '-'}
                                                            </TableCell>
                                                            <TableCell>
                                                                <div className="flex items-center gap-2">
                                                                    <Progress value={teacher.attendance_rate} className="w-20" />
                                                                    <span className={`text-sm font-medium ${getAttendanceRate(teacher.attendance_rate)}`}>
                                                                        {teacher.attendance_rate.toFixed(1)}%
                                                                    </span>
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        )}
                    </TabsContent>
                </Tabs>

                {/* Weekly Trend */}
                {weeklyTrend && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <TrendingUp className="h-5 w-5" />
                                Tren Mingguan
                            </CardTitle>
                            <CardDescription>
                                {weeklyTrend.start_date} - {weeklyTrend.end_date}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-4 overflow-x-auto pb-4">
                                {weeklyTrend.trend.map((day) => (
                                    <div key={day.date} className="min-w-[100px] rounded-lg border p-3 text-center">
                                        <div className="font-medium">{day.day_name}</div>
                                        <div className="text-xs text-muted-foreground">{day.date}</div>
                                        <div className="mt-2 text-2xl font-bold text-green-600">
                                            {day.hadir ?? day.present ?? 0}
                                        </div>
                                        <div className="text-xs text-muted-foreground">Hadir</div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </MainLayout>
    );
}
