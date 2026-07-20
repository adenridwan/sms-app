import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Users,
    GraduationCap,
    FileText,
    Calendar,
    QrCode,
    BarChart3,
    Settings,
    ScanLine,
    LayoutTemplate,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
} from 'lucide-react';
import type { DashboardAttendanceStats } from '@/types/attendance';

interface Props {
    stats: DashboardAttendanceStats;
}

export default function AttendanceIndex({ stats }: Props) {
    const menuItems = [
        {
            title: 'Absensi Siswa',
            description: 'Kelola kehadiran harian siswa',
            icon: Users,
            href: '/attendance/students',
            color: 'text-blue-600',
            bg: 'bg-blue-50',
        },
        {
            title: 'Absensi Guru',
            description: 'Kelola kehadiran guru dan karyawan',
            icon: GraduationCap,
            href: '/attendance/teachers',
            color: 'text-green-600',
            bg: 'bg-green-50',
        },
        {
            title: 'Perizinan',
            description: 'Kelola izin sakit dan perizinan',
            icon: FileText,
            href: '/attendance/permissions',
            color: 'text-purple-600',
            bg: 'bg-purple-50',
        },
        {
            title: 'Hari Libur',
            description: 'Kelola kalender hari libur',
            icon: Calendar,
            href: '/attendance/holidays',
            color: 'text-orange-600',
            bg: 'bg-orange-50',
        },
        {
            title: 'QR Code',
            description: 'Generate QR code untuk siswa dan guru',
            icon: QrCode,
            href: '/attendance/qr-codes',
            color: 'text-indigo-600',
            bg: 'bg-indigo-50',
        },
        {
            title: 'Laporan',
            description: 'Lihat laporan dan statistik',
            icon: BarChart3,
            href: '/attendance/reports',
            color: 'text-cyan-600',
            bg: 'bg-cyan-50',
        },
        {
            title: 'Template Kartu',
            description: 'Atur tata letak kartu ID drag-and-drop',
            icon: LayoutTemplate,
            href: '/attendance/card-templates',
            color: 'text-pink-600',
            bg: 'bg-pink-50',
        },
        {
            title: 'Pengaturan',
            description: 'Konfigurasi absensi dan notifikasi',
            icon: Settings,
            href: '/attendance/settings',
            color: 'text-gray-600',
            bg: 'bg-gray-50',
        },
        {
            title: 'Scanner',
            description: 'Buka aplikasi scanner QR/RFID',
            icon: ScanLine,
            href: '/scanner',
            color: 'text-red-600',
            bg: 'bg-red-50',
        },
    ];

    const summary = stats?.summary || {
        hadir: 0,
        sakit: 0,
        izin: 0,
        alfa: 0,
        belum_scan: 0,
    };

    const total = Object.values(summary).reduce((a, b) => a + b, 0);
    const percentage = total > 0 ? ((summary.hadir / total) * 100).toFixed(1) : 0;

    return (
        <MainLayout title="Absensi">
            <Head title="Absensi" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Modul Absensi</h1>
                        <p className="text-muted-foreground">
                            Kelola kehadiran siswa dan guru
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/scanner">
                            <ScanLine className="mr-2 h-4 w-4" />
                            Buka Scanner
                        </Link>
                    </Button>
                </div>

                {/* Today's Summary */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Clock className="h-5 w-5" />
                            Ringkasan Hari Ini
                        </CardTitle>
                        <CardDescription>
                            {stats?.today || new Date().toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-5">
                            <div className="rounded-lg bg-green-50 p-4 text-center dark:bg-green-900/20">
                                <CheckCircle2 className="mx-auto h-6 w-6 text-green-600" />
                                <div className="mt-2 text-3xl font-bold text-green-600">{summary.hadir}</div>
                                <div className="text-sm text-muted-foreground">Hadir</div>
                            </div>
                            <div className="rounded-lg bg-yellow-50 p-4 text-center dark:bg-yellow-900/20">
                                <AlertCircle className="mx-auto h-6 w-6 text-yellow-600" />
                                <div className="mt-2 text-3xl font-bold text-yellow-600">{summary.sakit}</div>
                                <div className="text-sm text-muted-foreground">Sakit</div>
                            </div>
                            <div className="rounded-lg bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                                <FileText className="mx-auto h-6 w-6 text-blue-600" />
                                <div className="mt-2 text-3xl font-bold text-blue-600">{summary.izin}</div>
                                <div className="text-sm text-muted-foreground">Izin</div>
                            </div>
                            <div className="rounded-lg bg-red-50 p-4 text-center dark:bg-red-900/20">
                                <XCircle className="mx-auto h-6 w-6 text-red-600" />
                                <div className="mt-2 text-3xl font-bold text-red-600">{summary.alfa}</div>
                                <div className="text-sm text-muted-foreground">Alfa</div>
                            </div>
                            <div className="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900/20">
                                <Clock className="mx-auto h-6 w-6 text-gray-600" />
                                <div className="mt-2 text-3xl font-bold text-gray-600">{summary.belum_scan}</div>
                                <div className="text-sm text-muted-foreground">Belum Scan</div>
                            </div>
                        </div>

                        {/* Progress Bar */}
                        <div className="mt-6">
                            <div className="mb-2 flex justify-between text-sm">
                                <span>Tingkat Kehadiran</span>
                                <span className="font-medium">{percentage}%</span>
                            </div>
                            <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-green-500 transition-all"
                                    style={{ width: `${percentage}%` }}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Menu Grid */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {menuItems.map((item) => (
                        <Link key={item.href} href={item.href}>
                            <Card className="h-full cursor-pointer transition-shadow hover:shadow-md">
                                <CardHeader className="flex flex-row items-center space-y-0 pb-2">
                                    <div className={`rounded-lg p-2 ${item.bg}`}>
                                        <item.icon className={`h-5 w-5 ${item.color}`} />
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <CardTitle className="text-lg">{item.title}</CardTitle>
                                    <CardDescription className="mt-1">{item.description}</CardDescription>
                                </CardContent>
                            </Card>
                        </Link>
                    ))}
                </div>

                {/* Additional Info */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Top Late Students */}
                    {stats?.top_late && stats.top_late.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Siswa Sering Terlambat</CardTitle>
                                <CardDescription>5 siswa dengan poin keterlambatan tertinggi</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {stats.top_late.map((student, index) => (
                                        <div key={student.student_id} className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Badge variant="outline">{index + 1}</Badge>
                                                <div>
                                                    <div className="font-medium">{student.name}</div>
                                                    <div className="text-sm text-muted-foreground">{student.nis}</div>
                                                </div>
                                            </div>
                                            <Badge variant="destructive">{student.poin_pelanggaran} poin</Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Consecutive Absences */}
                    {stats?.consecutive_absences && stats.consecutive_absences.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Absen Beruntun</CardTitle>
                                <CardDescription>Siswa tidak hadir 3+ hari berturut-turut</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    {stats.consecutive_absences.map((student) => (
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
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
