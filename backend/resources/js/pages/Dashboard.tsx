import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboardApi } from '@/services/api';
import { cn, formatCurrency } from '@/lib/utils';
import { usePermissions } from '@/hooks/usePermissions';
import {
    Users,
    GraduationCap,
    Briefcase,
    DoorOpen,
    TrendingUp,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    FileText,
    ScanLine,
    School,
    Wallet,
    CalendarCheck,
} from 'lucide-react';

interface AttendanceToday {
    date: string;
    summary: {
        hadir: number;
        sakit: number;
        izin: number;
        alfa: number;
        belum_scan: number;
    };
    percentage: number;
}

interface FinanceSummary {
    total_billed: number;
    total_collected: number;
    total_outstanding: number;
}

interface MyClass {
    id: string;
    name: string;
    students_count: number;
}

interface ChildStat {
    id: string;
    name: string | null;
    nis: string;
    class_name: string | null;
    attendance_month?: { hadir: number; sakit: number; izin: number; alfa: number };
    attendance_percentage: number | null;
    today_status?: string | null;
    unpaid_fees: number;
}

interface ClassSchedule {
    day_of_week: number;
    day_name: string;
    subject: string;
    start_time: string;
    end_time: string;
}

interface TopAbsentStudent {
    student_id: string;
    nis: string;
    name: string;
    absent_days: number;
}

interface ClassStats {
    classroom_id: string;
    students_count: number;
    attendance_today: AttendanceToday;
    my_schedules: ClassSchedule[];
    top_absent: TopAbsentStudent[];
}

interface RecentPayment {
    id: string;
    invoice_number: string;
    grand_total: number;
    paid_at: string | null;
    student_name: string;
}

interface DashboardStats {
    package: 'admin' | 'principal' | 'finance' | 'operational' | 'teacher' | 'student' | 'parent' | 'none';
    // paket sekolah
    total_students?: number;
    total_teachers?: number;
    total_staff?: number;
    total_classrooms?: number;
    active_academic_year?: string | null;
    attendance_today?: AttendanceToday;
    finance_summary?: FinanceSummary;
    recent_payments?: RecentPayment[];
    // paket guru
    linked?: boolean;
    my_classes?: MyClass[];
    total_classes?: number;
    homeroom_classroom_id?: string | null;
    // paket siswa
    class_name?: string | null;
    attendance_month?: { hadir: number; sakit: number; izin: number; alfa: number };
    attendance_percentage?: number | null;
    today_status?: string | null;
    unpaid_fees?: number;
    // paket orang tua
    children?: ChildStat[];
}

interface DashboardProps {
    stats: DashboardStats;
}

const todayLabel = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
});

const statusLabels: Record<string, string> = {
    present: 'Hadir',
    late: 'Terlambat',
    sick: 'Sakit',
    permitted: 'Izin',
    absent: 'Alfa',
    alpha: 'Alfa',
};

function StatCard({
    title,
    value,
    icon: Icon,
    color,
    bg,
}: {
    title: string;
    value: string | number;
    icon: React.ElementType;
    color: string;
    bg: string;
}) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">{title}</CardTitle>
                <div className={cn('rounded-lg p-2', bg)}>
                    <Icon className={cn('h-4 w-4', color)} />
                </div>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
            </CardContent>
        </Card>
    );
}

function AttendanceTodayCard({ attendance, title }: { attendance: AttendanceToday; title: string }) {
    const { can } = usePermissions();
    const s = attendance.summary;
    const boxes = [
        { label: 'Hadir', value: s.hadir, icon: CheckCircle2, color: 'text-green-600', bg: 'bg-green-50 dark:bg-green-900/20' },
        { label: 'Sakit', value: s.sakit, icon: AlertCircle, color: 'text-yellow-600', bg: 'bg-yellow-50 dark:bg-yellow-900/20' },
        { label: 'Izin', value: s.izin, icon: FileText, color: 'text-blue-600', bg: 'bg-blue-50 dark:bg-blue-900/20' },
        { label: 'Alfa', value: s.alfa, icon: XCircle, color: 'text-red-600', bg: 'bg-red-50 dark:bg-red-900/20' },
        { label: 'Belum Scan', value: s.belum_scan, icon: Clock, color: 'text-gray-600', bg: 'bg-gray-50 dark:bg-gray-900/20' },
    ];

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <div>
                    <CardTitle className="flex items-center gap-2">
                        <Clock className="h-5 w-5" />
                        {title}
                    </CardTitle>
                    <CardDescription>{todayLabel}</CardDescription>
                </div>
                {can('attendance.scanner-operate') && (
                    <Button asChild variant="outline" size="sm">
                        <Link href="/scanner">
                            <ScanLine className="mr-2 h-4 w-4" />
                            Buka Scanner
                        </Link>
                    </Button>
                )}
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    <div>
                        <div className="mb-2 flex justify-between text-sm">
                            <span>Tingkat Kehadiran</span>
                            <span className="font-medium">{attendance.percentage}%</span>
                        </div>
                        <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-green-500 transition-all"
                                style={{ width: `${attendance.percentage}%` }}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                        {boxes.map((b) => (
                            <div key={b.label} className={cn('rounded-lg p-4 text-center', b.bg)}>
                                <b.icon className={cn('mx-auto h-6 w-6', b.color)} />
                                <div className={cn('mt-2 text-3xl font-bold', b.color)}>{b.value}</div>
                                <div className="text-sm text-muted-foreground">{b.label}</div>
                            </div>
                        ))}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function FinanceSummaryCard({ finance }: { finance: FinanceSummary }) {
    const pctCollected = finance.total_billed > 0
        ? ((finance.total_collected / finance.total_billed) * 100).toFixed(1)
        : '0';
    const pctOutstanding = finance.total_billed > 0
        ? ((finance.total_outstanding / finance.total_billed) * 100).toFixed(1)
        : '0';

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <TrendingUp className="h-5 w-5" />
                    Ringkasan Keuangan
                </CardTitle>
                <CardDescription>Akumulasi tagihan siswa</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-lg bg-muted/50 p-4">
                        <div className="text-sm text-muted-foreground">Total Tagihan</div>
                        <div className="mt-1 text-2xl font-bold">{formatCurrency(finance.total_billed)}</div>
                    </div>
                    <div className="rounded-lg bg-green-50 p-4 dark:bg-green-900/20">
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-muted-foreground">Sudah Terbayar</div>
                            <span className="text-sm font-medium text-green-600">{pctCollected}%</span>
                        </div>
                        <div className="mt-1 text-2xl font-bold text-green-600">
                            {formatCurrency(finance.total_collected)}
                        </div>
                    </div>
                    <div className="rounded-lg bg-red-50 p-4 dark:bg-red-900/20">
                        <div className="flex items-center justify-between">
                            <div className="text-sm text-muted-foreground">Belum Terbayar</div>
                            <span className="text-sm font-medium text-red-600">{pctOutstanding}%</span>
                        </div>
                        <div className="mt-1 text-2xl font-bold text-red-600">
                            {formatCurrency(finance.total_outstanding)}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function QuickActions() {
    const { can } = usePermissions();

    return (
        <Card>
            <CardHeader>
                <CardTitle>Aksi Cepat</CardTitle>
                <CardDescription>Pintasan ke fitur utama</CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                        <Link href="/attendance">
                            <Clock className="h-5 w-5" />
                            <span>Absensi</span>
                        </Link>
                    </Button>
                    {can('attendance.scanner-operate') && (
                        <Button asChild variant="outline" className="h-auto flex-col gap-2 py-4">
                            <Link href="/scanner">
                                <ScanLine className="h-5 w-5" />
                                <span>Scanner</span>
                            </Link>
                        </Button>
                    )}
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
    );
}

function EmptyState({ title, message }: { title: string; message: string }) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center gap-3 py-14 text-center">
                <School className="h-12 w-12 text-muted-foreground/50" />
                <h2 className="text-lg font-semibold">{title}</h2>
                <p className="max-w-md text-sm text-muted-foreground">{message}</p>
            </CardContent>
        </Card>
    );
}

/**
 * Paket admin / kepala sekolah / operasional.
 *
 * Kartu ringkasan sekolah (Total Siswa/Guru/Staff/Kelas) bersifat statistik
 * setingan-umum sekolah — hanya untuk 'admin' (admin & super_admin).
 * 'principal' (kepala sekolah/wakil) dan 'operational' (TU/pustakawan)
 * cukup lihat ringkasan absensi hari ini, bukan angka sekolah menyeluruh.
 */
function SchoolDashboard({ stats }: { stats: DashboardStats }) {
    return (
        <>
            <QuickActions />

            {stats.package === 'admin' && (
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <StatCard title="Total Siswa" value={stats.total_students ?? 0} icon={Users} color="text-blue-600" bg="bg-blue-50 dark:bg-blue-900/20" />
                    <StatCard title="Total Guru" value={stats.total_teachers ?? 0} icon={GraduationCap} color="text-green-600" bg="bg-green-50 dark:bg-green-900/20" />
                    <StatCard title="Total Staff" value={stats.total_staff ?? 0} icon={Briefcase} color="text-purple-600" bg="bg-purple-50 dark:bg-purple-900/20" />
                    <StatCard title="Total Kelas" value={stats.total_classrooms ?? 0} icon={DoorOpen} color="text-orange-600" bg="bg-orange-50 dark:bg-orange-900/20" />
                </div>
            )}

            {stats.attendance_today && (
                <AttendanceTodayCard attendance={stats.attendance_today} title="Absensi Hari Ini" />
            )}

            {stats.finance_summary && <FinanceSummaryCard finance={stats.finance_summary} />}
        </>
    );
}

/** Paket bendahara */
function FinanceDashboard({ stats }: { stats: DashboardStats }) {
    return (
        <>
            <div className="grid gap-4 md:grid-cols-2">
                <StatCard title="Total Siswa" value={stats.total_students ?? 0} icon={Users} color="text-blue-600" bg="bg-blue-50 dark:bg-blue-900/20" />
                <StatCard
                    title="Tunggakan"
                    value={formatCurrency(stats.finance_summary?.total_outstanding ?? 0)}
                    icon={Wallet}
                    color="text-red-600"
                    bg="bg-red-50 dark:bg-red-900/20"
                />
            </div>

            {stats.finance_summary && <FinanceSummaryCard finance={stats.finance_summary} />}

            {stats.recent_payments && stats.recent_payments.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle>Pembayaran Terbaru</CardTitle>
                        <CardDescription>5 transaksi terakhir yang selesai</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {stats.recent_payments.map((p) => (
                                <div key={p.id} className="flex items-center justify-between border-b pb-2 last:border-0">
                                    <div>
                                        <div className="text-sm font-medium">{p.student_name || p.invoice_number}</div>
                                        <div className="text-xs text-muted-foreground">{p.invoice_number}</div>
                                    </div>
                                    <div className="text-sm font-semibold text-green-600">
                                        {formatCurrency(p.grand_total)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            )}
        </>
    );
}

/** Paket guru / wali kelas — dengan dropdown pemilih kelas diampu (Fase 3) */
function TeacherDashboard({ stats }: { stats: DashboardStats }) {
    const classes = stats.my_classes ?? [];
    const [selectedId, setSelectedId] = useState<string>(
        stats.homeroom_classroom_id ?? classes[0]?.id ?? ''
    );
    const [classStats, setClassStats] = useState<ClassStats | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!selectedId) return;
        let cancelled = false;
        setLoading(true);
        dashboardApi
            .classStats(selectedId)
            .then((response) => {
                if (!cancelled) setClassStats(response.data.data as unknown as ClassStats);
            })
            .catch(() => {
                if (!cancelled) setClassStats(null);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });
        return () => {
            cancelled = true;
        };
    }, [selectedId]);

    if (!stats.linked) {
        return (
            <EmptyState
                title="Belum Ada Kelas yang Ditugaskan"
                message="Anda belum terhubung dengan kelas mana pun pada tahun ajaran aktif. Hubungi admin sekolah untuk mengatur penugasan kelas, jadwal mengajar, atau perwalian."
            />
        );
    }

    const selectedClass = classes.find((c) => c.id === selectedId);

    return (
        <>
            <QuickActions />

            <div className="grid gap-4 md:grid-cols-2">
                <StatCard title="Kelas Diampu" value={stats.total_classes ?? 0} icon={DoorOpen} color="text-blue-600" bg="bg-blue-50 dark:bg-blue-900/20" />
                <StatCard title="Total Siswa Diampu" value={stats.total_students ?? 0} icon={Users} color="text-green-600" bg="bg-green-50 dark:bg-green-900/20" />
            </div>

            {/* Pemilih kelas */}
            <Card>
                <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle className="flex items-center gap-2">
                            <School className="h-5 w-5" />
                            Ringkasan Kelas
                        </CardTitle>
                        <CardDescription>
                            {selectedClass
                                ? `${selectedClass.name} — ${classStats?.students_count ?? selectedClass.students_count} siswa`
                                : 'Pilih kelas yang diampu'}
                            {stats.homeroom_classroom_id === selectedId && selectedId && (
                                <Badge variant="secondary" className="ml-2">Kelas Perwalian</Badge>
                            )}
                        </CardDescription>
                    </div>
                    <Select value={selectedId} onValueChange={setSelectedId}>
                        <SelectTrigger className="w-[220px]">
                            <SelectValue placeholder="Pilih kelas" />
                        </SelectTrigger>
                        <SelectContent>
                            {classes.map((c) => (
                                <SelectItem key={c.id} value={c.id}>
                                    {c.name} ({c.students_count} siswa)
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </CardHeader>
                <CardContent>
                    {loading ? (
                        <div className="py-8 text-center text-muted-foreground">Memuat data kelas...</div>
                    ) : !classStats ? (
                        <div className="py-8 text-center text-muted-foreground">
                            Data kelas tidak tersedia
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Absensi hari ini untuk kelas terpilih */}
                            <div>
                                <div className="mb-2 flex justify-between text-sm">
                                    <span>Kehadiran Hari Ini</span>
                                    <span className="font-medium">{classStats.attendance_today.percentage}%</span>
                                </div>
                                <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        className="h-full bg-green-500 transition-all"
                                        style={{ width: `${classStats.attendance_today.percentage}%` }}
                                    />
                                </div>
                                <div className="mt-4 grid grid-cols-2 gap-4 md:grid-cols-5">
                                    {(
                                        [
                                            ['Hadir', classStats.attendance_today.summary.hadir, 'text-green-600', 'bg-green-50 dark:bg-green-900/20'],
                                            ['Sakit', classStats.attendance_today.summary.sakit, 'text-yellow-600', 'bg-yellow-50 dark:bg-yellow-900/20'],
                                            ['Izin', classStats.attendance_today.summary.izin, 'text-blue-600', 'bg-blue-50 dark:bg-blue-900/20'],
                                            ['Alfa', classStats.attendance_today.summary.alfa, 'text-red-600', 'bg-red-50 dark:bg-red-900/20'],
                                            ['Belum Scan', classStats.attendance_today.summary.belum_scan, 'text-gray-600', 'bg-gray-50 dark:bg-gray-900/20'],
                                        ] as const
                                    ).map(([label, value, color, bg]) => (
                                        <div key={label} className={cn('rounded-lg p-3 text-center', bg)}>
                                            <div className={cn('text-2xl font-bold', color)}>{value}</div>
                                            <div className="text-sm text-muted-foreground">{label}</div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                {/* Jadwal mengajar di kelas ini */}
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                                        <CalendarCheck className="h-4 w-4" />
                                        Jadwal Anda di Kelas Ini
                                    </h3>
                                    {classStats.my_schedules.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            Belum ada jadwal terinput
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {classStats.my_schedules.map((s, i) => (
                                                <div
                                                    key={i}
                                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                                >
                                                    <span className="font-medium">{s.day_name}</span>
                                                    <span className="text-muted-foreground">{s.subject}</span>
                                                    <span>
                                                        {s.start_time}–{s.end_time}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* Siswa sering alfa bulan ini */}
                                <div>
                                    <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                                        <XCircle className="h-4 w-4 text-red-600" />
                                        Sering Alfa Bulan Ini
                                    </h3>
                                    {classStats.top_absent.length === 0 ? (
                                        <p className="py-4 text-center text-sm text-muted-foreground">
                                            Tidak ada siswa alfa bulan ini
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {classStats.top_absent.map((s) => (
                                                <div
                                                    key={s.student_id}
                                                    className="flex items-center justify-between rounded-md border px-3 py-2 text-sm"
                                                >
                                                    <div>
                                                        <div className="font-medium">{s.name || s.nis}</div>
                                                        <div className="text-xs text-muted-foreground">{s.nis}</div>
                                                    </div>
                                                    <Badge variant="destructive">{s.absent_days} hari</Badge>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>
        </>
    );
}

/** Paket siswa */
function StudentDashboard({ stats }: { stats: DashboardStats }) {
    if (!stats.linked) {
        return (
            <EmptyState
                title="Data Siswa Belum Terhubung"
                message="Akun Anda belum terhubung dengan data siswa. Hubungi admin sekolah untuk menautkan akun ini."
            />
        );
    }

    const m = stats.attendance_month;

    return (
        <>
            <div className="grid gap-4 md:grid-cols-3">
                <StatCard
                    title="Kehadiran Bulan Ini"
                    value={stats.attendance_percentage != null ? `${stats.attendance_percentage}%` : '-'}
                    icon={CalendarCheck}
                    color="text-green-600"
                    bg="bg-green-50 dark:bg-green-900/20"
                />
                <StatCard
                    title="Status Hari Ini"
                    value={stats.today_status ? (statusLabels[stats.today_status] ?? stats.today_status) : 'Belum Absen'}
                    icon={Clock}
                    color="text-blue-600"
                    bg="bg-blue-50 dark:bg-blue-900/20"
                />
                <StatCard
                    title="Tagihan Belum Dibayar"
                    value={formatCurrency(stats.unpaid_fees ?? 0)}
                    icon={Wallet}
                    color="text-red-600"
                    bg="bg-red-50 dark:bg-red-900/20"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <CalendarCheck className="h-5 w-5" />
                        Rekap Absensi Bulan Ini
                    </CardTitle>
                    <CardDescription>
                        {stats.class_name ? `Kelas ${stats.class_name} — ` : ''}
                        {todayLabel}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div className="rounded-lg bg-green-50 p-4 text-center dark:bg-green-900/20">
                            <div className="text-3xl font-bold text-green-600">{m?.hadir ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Hadir</div>
                        </div>
                        <div className="rounded-lg bg-yellow-50 p-4 text-center dark:bg-yellow-900/20">
                            <div className="text-3xl font-bold text-yellow-600">{m?.sakit ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Sakit</div>
                        </div>
                        <div className="rounded-lg bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                            <div className="text-3xl font-bold text-blue-600">{m?.izin ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Izin</div>
                        </div>
                        <div className="rounded-lg bg-red-50 p-4 text-center dark:bg-red-900/20">
                            <div className="text-3xl font-bold text-red-600">{m?.alfa ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Alfa</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

/** Paket orang tua — dengan pemilih anak bila lebih dari satu (Fase 3) */
function ParentDashboard({ stats }: { stats: DashboardStats }) {
    const children = stats.children ?? [];
    const [selectedId, setSelectedId] = useState<string>(children[0]?.id ?? '');

    if (!stats.linked || children.length === 0) {
        return (
            <EmptyState
                title="Belum Ada Siswa yang Terhubung"
                message="Akun Anda belum ditautkan dengan data siswa. Hubungi admin sekolah untuk menautkan akun ini dengan putra/putri Anda."
            />
        );
    }

    const child = children.find((c) => c.id === selectedId) ?? children[0];
    const m = child.attendance_month;

    return (
        <>
            {children.length > 1 && (
                <div className="flex items-center gap-3">
                    <span className="text-sm text-muted-foreground">Pilih anak:</span>
                    <Select value={child.id} onValueChange={setSelectedId}>
                        <SelectTrigger className="w-[240px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {children.map((c) => (
                                <SelectItem key={c.id} value={c.id}>
                                    {c.name ?? c.nis} {c.class_name ? `— ${c.class_name}` : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            )}

            <div className="grid gap-4 md:grid-cols-3">
                <StatCard
                    title="Kehadiran Bulan Ini"
                    value={child.attendance_percentage != null ? `${child.attendance_percentage}%` : '-'}
                    icon={CalendarCheck}
                    color="text-green-600"
                    bg="bg-green-50 dark:bg-green-900/20"
                />
                <StatCard
                    title="Status Hari Ini"
                    value={child.today_status ? (statusLabels[child.today_status] ?? child.today_status) : 'Belum Absen'}
                    icon={Clock}
                    color="text-blue-600"
                    bg="bg-blue-50 dark:bg-blue-900/20"
                />
                <StatCard
                    title="Tunggakan"
                    value={formatCurrency(child.unpaid_fees)}
                    icon={Wallet}
                    color="text-red-600"
                    bg="bg-red-50 dark:bg-red-900/20"
                />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center justify-between">
                        <span>{child.name ?? child.nis}</span>
                        {child.class_name && <Badge variant="secondary">{child.class_name}</Badge>}
                    </CardTitle>
                    <CardDescription>NIS {child.nis} — rekap absensi bulan ini</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div className="rounded-lg bg-green-50 p-4 text-center dark:bg-green-900/20">
                            <div className="text-3xl font-bold text-green-600">{m?.hadir ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Hadir</div>
                        </div>
                        <div className="rounded-lg bg-yellow-50 p-4 text-center dark:bg-yellow-900/20">
                            <div className="text-3xl font-bold text-yellow-600">{m?.sakit ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Sakit</div>
                        </div>
                        <div className="rounded-lg bg-blue-50 p-4 text-center dark:bg-blue-900/20">
                            <div className="text-3xl font-bold text-blue-600">{m?.izin ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Izin</div>
                        </div>
                        <div className="rounded-lg bg-red-50 p-4 text-center dark:bg-red-900/20">
                            <div className="text-3xl font-bold text-red-600">{m?.alfa ?? 0}</div>
                            <div className="text-sm text-muted-foreground">Alfa</div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </>
    );
}

export default function Dashboard({ stats }: DashboardProps) {
    const pkg = stats?.package ?? 'none';

    return (
        <MainLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {(pkg === 'admin' || pkg === 'principal' || pkg === 'operational') && (
                    <SchoolDashboard stats={stats} />
                )}
                {pkg === 'finance' && <FinanceDashboard stats={stats} />}
                {pkg === 'teacher' && <TeacherDashboard stats={stats} />}
                {pkg === 'student' && <StudentDashboard stats={stats} />}
                {pkg === 'parent' && <ParentDashboard stats={stats} />}
                {pkg === 'none' && (
                    <EmptyState
                        title="Selamat Datang"
                        message="Akun Anda belum memiliki peran yang dikenali. Hubungi admin sekolah."
                    />
                )}
            </div>
        </MainLayout>
    );
}
