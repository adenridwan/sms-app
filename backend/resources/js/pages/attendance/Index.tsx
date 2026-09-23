import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent } from '@/components/ui/card';
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
    TrendingUp,
    ArrowRight,
    Sparkles,
    UserCheck,
    AlertTriangle,
} from 'lucide-react';
import type { DashboardAttendanceStats } from '@/types/attendance';
import { usePermissions } from '@/hooks/usePermissions';

interface Props {
    stats: DashboardAttendanceStats;
}

export default function AttendanceIndex({ stats }: Props) {
    const { can, canAny } = usePermissions();
    const canOperateScanner = canAny('attendance.scan-students', 'attendance.scan-staff');
    const canManageBackOffice = can('settings.attendance');

    const summary = stats?.summary || {
        hadir: 0,
        sakit: 0,
        izin: 0,
        alfa: 0,
        belum_scan: 0,
    };

    const total = Object.values(summary).reduce((a, b) => a + b, 0);
    const percentage = total > 0 ? ((summary.hadir / total) * 100).toFixed(1) : 0;

    // Main feature cards
    const mainFeatures = [
        {
            title: 'Absensi Siswa',
            description: 'Kelola kehadiran harian siswa per kelas',
            icon: Users,
            href: '/attendance/students',
            gradient: 'from-blue-500 to-blue-600',
            iconBg: 'bg-blue-400/20',
            stats: summary.hadir > 0 ? `${summary.hadir} hadir hari ini` : null,
        },
        {
            title: 'Absensi Guru',
            description: 'Kelola kehadiran guru dan karyawan',
            icon: GraduationCap,
            href: '/attendance/teachers',
            gradient: 'from-emerald-500 to-emerald-600',
            iconBg: 'bg-emerald-400/20',
        },
        {
            title: 'Perizinan',
            description: 'Kelola surat izin dan sakit',
            icon: FileText,
            href: '/attendance/permissions',
            gradient: 'from-violet-500 to-violet-600',
            iconBg: 'bg-violet-400/20',
        },
        {
            title: 'Laporan',
            description: 'Statistik dan rekap kehadiran',
            icon: BarChart3,
            href: '/attendance/reports',
            gradient: 'from-cyan-500 to-cyan-600',
            iconBg: 'bg-cyan-400/20',
        },
    ];

    // Quick action cards
    const quickActions = [
        ...(canManageBackOffice ? [{
            title: 'Hari Libur',
            icon: Calendar,
            href: '/attendance/holidays',
            color: 'text-orange-600 dark:text-orange-400',
            bg: 'bg-orange-50 dark:bg-orange-900/20',
            hoverBg: 'hover:bg-orange-100 dark:hover:bg-orange-900/30',
        }] : []),
        ...(canManageBackOffice ? [{
            title: 'QR Code',
            icon: QrCode,
            href: '/attendance/qr-codes',
            color: 'text-indigo-600 dark:text-indigo-400',
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            hoverBg: 'hover:bg-indigo-100 dark:hover:bg-indigo-900/30',
        }] : []),
        ...(canManageBackOffice ? [{
            title: 'Template Kartu',
            icon: LayoutTemplate,
            href: '/attendance/card-templates',
            color: 'text-pink-600 dark:text-pink-400',
            bg: 'bg-pink-50 dark:bg-pink-900/20',
            hoverBg: 'hover:bg-pink-100 dark:hover:bg-pink-900/30',
        }] : []),
        ...(canManageBackOffice ? [{
            title: 'Pengaturan',
            icon: Settings,
            href: '/attendance/settings',
            color: 'text-slate-600 dark:text-slate-400',
            bg: 'bg-slate-50 dark:bg-slate-800/50',
            hoverBg: 'hover:bg-slate-100 dark:hover:bg-slate-800/70',
        }] : []),
    ];

    const statusCards = [
        {
            label: 'Hadir',
            value: summary.hadir,
            icon: CheckCircle2,
            color: 'text-emerald-600 dark:text-emerald-400',
            bg: 'bg-emerald-50 dark:bg-emerald-900/20',
            ring: 'ring-emerald-200 dark:ring-emerald-800',
        },
        {
            label: 'Sakit',
            value: summary.sakit,
            icon: AlertCircle,
            color: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-50 dark:bg-amber-900/20',
            ring: 'ring-amber-200 dark:ring-amber-800',
        },
        {
            label: 'Izin',
            value: summary.izin,
            icon: FileText,
            color: 'text-blue-600 dark:text-blue-400',
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            ring: 'ring-blue-200 dark:ring-blue-800',
        },
        {
            label: 'Alfa',
            value: summary.alfa,
            icon: XCircle,
            color: 'text-rose-600 dark:text-rose-400',
            bg: 'bg-rose-50 dark:bg-rose-900/20',
            ring: 'ring-rose-200 dark:ring-rose-800',
        },
        {
            label: 'Belum Scan',
            value: summary.belum_scan,
            icon: Clock,
            color: 'text-slate-600 dark:text-slate-400',
            bg: 'bg-slate-50 dark:bg-slate-800/50',
            ring: 'ring-slate-200 dark:ring-slate-700',
        },
    ];

    return (
        <MainLayout title="Absensi">
            <Head title="Absensi" />

            <div className="space-y-8">
                {/* Hero Header */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary/90 via-primary to-primary/80 p-6 text-primary-foreground shadow-lg md:p-8">
                    <div className="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5" />
                    <div className="absolute -bottom-12 -left-12 h-48 w-48 rounded-full bg-white/5" />
                    <div className="absolute right-1/4 top-1/2 h-24 w-24 rounded-full bg-white/5" />

                    <div className="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5" />
                                <span className="text-sm font-medium opacity-90">Modul Absensi</span>
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                                Kelola Kehadiran
                            </h1>
                            <p className="max-w-md text-sm opacity-80 md:text-base">
                                {stats?.today || new Date().toLocaleDateString('id-ID', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Button asChild variant="secondary" size="lg" className="shadow-md">
                                <Link href="/attendance/me">
                                    <UserCheck className="mr-2 h-4 w-4" />
                                    Absensi Saya
                                </Link>
                            </Button>
                            {canOperateScanner && (
                                <Button asChild size="lg" variant="outline" className="border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                                    <Link href="/scanner">
                                        <ScanLine className="mr-2 h-4 w-4" />
                                        Buka Scanner
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </div>

                {/* Today's Stats Row */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Ringkasan Hari Ini</h2>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                            <span>Tingkat kehadiran: <strong className="text-foreground">{percentage}%</strong></span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                        {statusCards.map((item) => (
                            <div
                                key={item.label}
                                className={`group relative overflow-hidden rounded-xl ${item.bg} p-4 ring-1 ${item.ring} transition-all hover:scale-[1.02] hover:shadow-md`}
                            >
                                <div className="flex items-start justify-between">
                                    <div>
                                        <p className="text-xs font-medium text-muted-foreground">{item.label}</p>
                                        <p className={`mt-1 text-2xl font-bold ${item.color}`}>{item.value}</p>
                                    </div>
                                    <div className={`rounded-lg ${item.bg} p-2`}>
                                        <item.icon className={`h-5 w-5 ${item.color}`} />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Progress Bar */}
                    <div className="rounded-xl bg-muted/50 p-4">
                        <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
                                style={{ width: `${percentage}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* Main Feature Cards */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Menu Utama</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {mainFeatures.map((item) => (
                            <Link key={item.href} href={item.href} className="group">
                                <div className={`relative h-full overflow-hidden rounded-2xl bg-gradient-to-br ${item.gradient} p-5 text-white shadow-lg transition-all duration-300 hover:-translate-y-1 hover:shadow-xl`}>
                                    <div className="absolute -right-4 -top-4 h-20 w-20 rounded-full bg-white/10" />
                                    <div className="absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-white/10" />

                                    <div className="relative z-10">
                                        <div className={`inline-flex rounded-xl ${item.iconBg} p-3`}>
                                            <item.icon className="h-6 w-6" />
                                        </div>
                                        <h3 className="mt-4 text-lg font-semibold">{item.title}</h3>
                                        <p className="mt-1 text-sm opacity-90">{item.description}</p>
                                        {item.stats && (
                                            <p className="mt-2 text-xs font-medium opacity-75">{item.stats}</p>
                                        )}
                                        <div className="mt-4 flex items-center gap-1 text-sm font-medium opacity-0 transition-opacity group-hover:opacity-100">
                                            <span>Buka</span>
                                            <ArrowRight className="h-4 w-4" />
                                        </div>
                                    </div>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Quick Actions */}
                {quickActions.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Aksi Cepat</h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {quickActions.map((item) => (
                                <Link key={item.href} href={item.href}>
                                    <div className={`flex items-center gap-3 rounded-xl ${item.bg} ${item.hoverBg} p-4 ring-1 ring-inset ring-black/5 transition-all hover:shadow-md dark:ring-white/5`}>
                                        <div className={`rounded-lg p-2 ${item.bg}`}>
                                            <item.icon className={`h-5 w-5 ${item.color}`} />
                                        </div>
                                        <span className="text-sm font-medium">{item.title}</span>
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </div>
                )}

                {/* Alerts Section */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Top Late Students */}
                    {stats?.top_late && stats.top_late.length > 0 && (
                        <Card className="overflow-hidden border-0 shadow-md">
                            <div className="border-b bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 dark:from-amber-900/20 dark:to-orange-900/20">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-amber-100 p-2 dark:bg-amber-800/30">
                                        <Clock className="h-5 w-5 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">Siswa Sering Terlambat</h3>
                                        <p className="text-xs text-muted-foreground">5 siswa dengan poin tertinggi</p>
                                    </div>
                                </div>
                            </div>
                            <CardContent className="p-0">
                                <div className="divide-y">
                                    {stats.top_late.map((student, index) => (
                                        <div key={student.student_id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-muted/50">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                                    {index + 1}
                                                </div>
                                                <div>
                                                    <div className="font-medium">{student.name}</div>
                                                    <div className="text-xs text-muted-foreground">{student.nis}</div>
                                                </div>
                                            </div>
                                            <Badge variant="destructive" className="font-semibold">
                                                {student.poin_pelanggaran} poin
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Consecutive Absences */}
                    {stats?.consecutive_absences && stats.consecutive_absences.length > 0 && (
                        <Card className="overflow-hidden border-0 shadow-md">
                            <div className="border-b bg-gradient-to-r from-rose-50 to-red-50 px-5 py-4 dark:from-rose-900/20 dark:to-red-900/20">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-rose-100 p-2 dark:bg-rose-800/30">
                                        <AlertTriangle className="h-5 w-5 text-rose-600 dark:text-rose-400" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">Absen Beruntun</h3>
                                        <p className="text-xs text-muted-foreground">Tidak hadir 3+ hari berturut-turut</p>
                                    </div>
                                </div>
                            </div>
                            <CardContent className="p-0">
                                <div className="divide-y">
                                    {stats.consecutive_absences.map((student) => (
                                        <div key={student.student_id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-muted/50">
                                            <div>
                                                <div className="font-medium">{student.name}</div>
                                                <div className="text-xs text-muted-foreground">
                                                    {student.classroom} • {student.nis}
                                                </div>
                                            </div>
                                            <Badge variant="destructive" className="font-semibold">
                                                {student.consecutive_days} hari
                                            </Badge>
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
