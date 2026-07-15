import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn, formatCurrency } from '@/lib/utils';
import {
    Users,
    GraduationCap,
    Briefcase,
    DoorOpen,
    TrendingUp,
    TrendingDown,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    FileText,
    ScanLine,
    Calendar,
} from 'lucide-react';
import type { DashboardAttendanceStats, TopLateStudent, ConsecutiveAbsence, WeeklyTrendItem } from '@/types/attendance';

interface DashboardProps {
    stats: {
        total_students: number;
        total_teachers: number;
        total_staff: number;
        total_classrooms: number;
        attendance_today: DashboardAttendanceStats;
        finance_summary: {
            total_billed: number;
            total_collected: number;
            total_outstanding: number;
        };
    };
}

export default function Dashboard({ stats }: DashboardProps) {
    const statCards = [
        {
            title: 'Total Siswa',
            value: stats?.total_students || 540,
            icon: Users,
            change: '+12%',
            trend: 'up',
            color: 'text-blue-600',
            bg: 'bg-blue-50',
        },
        {
            title: 'Total Guru',
            value: stats?.total_teachers || 45,
            icon: GraduationCap,
            change: '+2%',
            trend: 'up',
            color: 'text-green-600',
            bg: 'bg-green-50',
        },
        {
            title: 'Total Staff',
            value: stats?.total_staff || 20,
            icon: Briefcase,
            change: '0%',
            trend: 'neutral',
            color: 'text-purple-600',
            bg: 'bg-purple-50',
        },
        {
            title: 'Total Kelas',
            value: stats?.total_classrooms || 18,
            icon: DoorOpen,
            change: '+6%',
            trend: 'up',
            color: 'text-orange-600',
            bg: 'bg-orange-50',
        },
    ];

    const attendanceStats = stats?.attendance_today || {
        today: new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }),
        summary: {
            hadir: 485,
            sakit: 15,
            izin: 10,
            alfa: 5,
            belum_scan: 25,
        },
        percentage: 89.8,
        top_late: [],
        consecutive_absences: [],
        weekly_trend: [],
    };

    const financeStats = stats?.finance_summary || {
        total_billed: 324000000,
        total_collected: 280000000,
        total_outstanding: 44000000,
    };

    const totalStudents = Object.values(attendanceStats.summary).reduce((a, b) => a + b, 0);
    const attendancePercentage = totalStudents > 0
        ? ((attendanceStats.summary.hadir / totalStudents) * 100).toFixed(1)
        : '0';

    return (
        <MainLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => (
                        <Card key={stat.title}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-muted-foreground">
                                    {stat.title}
                                </CardTitle>
                                <div className={cn('rounded-lg p-2', stat.bg)}>
                                    <stat.icon className={cn('h-4 w-4', stat.color)} />
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stat.value}</div>
                                <div className="flex items-center text-xs text-muted-foreground">
                                    {stat.trend === 'up' ? (
                                        <TrendingUp className="mr-1 h-3 w-3 text-green-500" />
                                    ) : stat.trend === 'down' ? (
                                        <TrendingDown className="mr-1 h-3 w-3 text-red-500" />
                                    ) : null}
                                    <span
                                        className={cn(
                                            stat.trend === 'up' && 'text-green-500',
                                            stat.trend === 'down' && 'text-red-500'
                                        )}
                                    >
                                        {stat.change}
                                    </span>
                                    <span className="ml-1">dari bulan lalu</span>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Attendance Summary - 5 Cards */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <div>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Absensi Hari Ini
                            </CardTitle>
                            <CardDescription>{attendanceStats.today}</CardDescription>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/scanner">
                                <ScanLine className="mr-2 h-4 w-4" />
                                Buka Scanner
                            </Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            {/* Progress Bar */}
                            <div>
                                <div className="mb-2 flex justify-between text-sm">
                                    <span>Tingkat Kehadiran</span>
                                    <span className="font-medium">{attendancePercentage}%</span>
                                </div>
                                <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-green-500 transition-all"
                                        style={{ width: `${attendancePercentage}%` }}
                                    />
                                </div>
                            </div>

                            {/* 5 Attendance Status Cards */}
                            <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                                <div className="rounded-lg bg-green-50 p-4 text-center dark:bg-green-900/20">
                                    <CheckCircle2 className="mx-auto h-6 w-6 text-green-600" />
                                    <div className="mt-2 text-3xl font-bold text-green-600">
                                        {attendanceStats.summary.hadir}
                                    </div>
                                    <div className="text-sm text-muted-foreground">Hadir</div>
                                </div>
                                <div className="rounded-lg bg-yellow-50 p-4 text-center dark:bg-yellow-900/20">
                                    <AlertCircle className="mx-auto h-6 w-6 text-yellow-600" />
                                    <div className="mt-2 text-3xl font-bold text-yellow-600">
                                        {attendanceStats.summary.sakit}
                                    </div>
                                    <div className="text-sm text-muted-foreground">Sakit</div>
                                </div>
                                <div className="rounded-lg bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                                    <FileText className="mx-auto h-6 w-6 text-blue-600" />
                                    <div className="mt-2 text-3xl font-bold text-blue-600">
                                        {attendanceStats.summary.izin}
                                    </div>
                                    <div className="text-sm text-muted-foreground">Izin</div>
                                </div>
                                <div className="rounded-lg bg-red-50 p-4 text-center dark:bg-red-900/20">
                                    <XCircle className="mx-auto h-6 w-6 text-red-600" />
                                    <div className="mt-2 text-3xl font-bold text-red-600">
                                        {attendanceStats.summary.alfa}
                                    </div>
                                    <div className="text-sm text-muted-foreground">Alfa</div>
                                </div>
                                <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900/20">
                                    <Clock className="mx-auto h-6 w-6 text-gray-600" />
                                    <div className="mt-2 text-3xl font-bold text-gray-600">
                                        {attendanceStats.summary.belum_scan}
                                    </div>
                                    <div className="text-sm text-muted-foreground">Belum Scan</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Top Late Students & Consecutive Absences */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Top Late Students */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <AlertCircle className="h-5 w-5 text-yellow-600" />
                                Siswa Sering Terlambat
                            </CardTitle>
                            <CardDescription>5 siswa dengan poin keterlambatan tertinggi</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {attendanceStats.top_late && attendanceStats.top_late.length > 0 ? (
                                <div className="space-y-3">
                                    {attendanceStats.top_late.slice(0, 5).map((student: TopLateStudent, index: number) => (
                                        <div key={student.student_id} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Badge variant="outline" className="w-6 justify-center">
                                                    {index + 1}
                                                </Badge>
                                                <div>
                                                    <div className="font-medium">{student.name}</div>
                                                    <div className="text-sm text-muted-foreground">{student.nis}</div>
                                                </div>
                                            </div>
                                            <Badge variant="destructive">{student.poin_pelanggaran} poin</Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-8 text-center text-muted-foreground">
                                    <CheckCircle2 className="mx-auto h-8 w-8 text-green-500" />
                                    <p className="mt-2">Tidak ada siswa terlambat</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Consecutive Absences */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <XCircle className="h-5 w-5 text-red-600" />
                                Absen Beruntun
                            </CardTitle>
                            <CardDescription>Siswa tidak hadir 3+ hari berturut-turut</CardDescription>
                        </CardHeader>
                        <CardContent>
                            {attendanceStats.consecutive_absences && attendanceStats.consecutive_absences.length > 0 ? (
                                <div className="space-y-3">
                                    {attendanceStats.consecutive_absences.slice(0, 5).map((student: ConsecutiveAbsence) => (
                                        <div key={student.student_id} className="flex items-center justify-between">
                                            <div>
                                                <div className="font-medium">{student.name}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {student.classroom} - {student.nis}
                                                </div>
                                            </div>
                                            <Badge variant="destructive">{student.consecutive_days} hari</Badge>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-8 text-center text-muted-foreground">
                                    <CheckCircle2 className="mx-auto h-8 w-8 text-green-500" />
                                    <p className="mt-2">Tidak ada siswa absen beruntun</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Weekly Trend */}
                {attendanceStats.weekly_trend && attendanceStats.weekly_trend.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Calendar className="h-5 w-5" />
                                Tren Kehadiran 7 Hari Terakhir
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-2 overflow-x-auto pb-2">
                                {attendanceStats.weekly_trend.map((day: WeeklyTrendItem) => {
                                    const total = (day.hadir || 0) + (day.sakit || 0) + (day.izin || 0) + (day.alfa || 0);
                                    const percentage = total > 0 ? ((day.hadir || 0) / total * 100).toFixed(0) : 0;
                                    return (
                                        <div
                                            key={day.date}
                                            className="min-w-[100px] flex-1 rounded-lg border p-3 text-center"
                                        >
                                            <div className="text-sm font-medium">{day.day_name}</div>
                                            <div className="text-xs text-muted-foreground">{day.date}</div>
                                            <div className="mt-2">
                                                <div className="h-16 w-full rounded-full bg-muted">
                                                    <div
                                                        className="flex h-full items-end justify-center rounded-full"
                                                        style={{ height: `${percentage}%` }}
                                                    >
                                                        <div
                                                            className="w-full rounded-full bg-green-500"
                                                            style={{ height: `${percentage}%`, minHeight: '4px' }}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="mt-2 text-lg font-bold text-green-600">{day.hadir || 0}</div>
                                            <div className="text-xs text-muted-foreground">Hadir</div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Finance Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <TrendingUp className="h-5 w-5" />
                            Ringkasan Keuangan
                        </CardTitle>
                        <CardDescription>Bulan ini</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-3">
                            <div className="rounded-lg bg-muted/50 p-4">
                                <div className="text-sm text-muted-foreground">Total Tagihan</div>
                                <div className="mt-1 text-2xl font-bold">
                                    {formatCurrency(financeStats.total_billed)}
                                </div>
                            </div>

                            <div className="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">Sudah Terbayar</div>
                                    <span className="text-sm font-medium text-green-600">
                                        {((financeStats.total_collected / financeStats.total_billed) * 100).toFixed(1)}%
                                    </span>
                                </div>
                                <div className="mt-1 text-2xl font-bold text-green-600">
                                    {formatCurrency(financeStats.total_collected)}
                                </div>
                            </div>

                            <div className="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">Belum Terbayar</div>
                                    <span className="text-sm font-medium text-red-600">
                                        {((financeStats.total_outstanding / financeStats.total_billed) * 100).toFixed(1)}%
                                    </span>
                                </div>
                                <div className="mt-1 text-2xl font-bold text-red-600">
                                    {formatCurrency(financeStats.total_outstanding)}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Recent Activity & Quick Actions */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Recent Activity */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Aktivitas Terbaru</CardTitle>
                            <CardDescription>Log aktivitas sistem</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {[
                                    { action: 'Siswa baru ditambahkan', user: 'Admin', time: '5 menit lalu' },
                                    { action: 'Pembayaran SPP diterima', user: 'Bendahara', time: '15 menit lalu' },
                                    { action: 'Nilai ujian diinput', user: 'Guru Matematika', time: '1 jam lalu' },
                                    { action: 'Jadwal pelajaran diupdate', user: 'Admin', time: '2 jam lalu' },
                                    { action: 'Buku baru ditambahkan', user: 'Pustakawan', time: '3 jam lalu' },
                                ].map((activity, index) => (
                                    <div key={index} className="flex items-center justify-between border-b pb-2 last:border-0">
                                        <div>
                                            <div className="text-sm font-medium">{activity.action}</div>
                                            <div className="text-xs text-muted-foreground">oleh {activity.user}</div>
                                        </div>
                                        <div className="text-xs text-muted-foreground">{activity.time}</div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Quick Actions */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Aksi Cepat</CardTitle>
                            <CardDescription>Pintasan ke fitur utama</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3">
                                <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                                    <Link href="/attendance">
                                        <Clock className="h-5 w-5" />
                                        <span>Absensi</span>
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                                    <Link href="/scanner">
                                        <ScanLine className="h-5 w-5" />
                                        <span>Scanner</span>
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                                    <Link href="/attendance/permissions">
                                        <FileText className="h-5 w-5" />
                                        <span>Perizinan</span>
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                                    <Link href="/attendance/reports">
                                        <TrendingUp className="h-5 w-5" />
                                        <span>Laporan</span>
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
