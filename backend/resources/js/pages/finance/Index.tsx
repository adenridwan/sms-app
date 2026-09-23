import { Head, Link } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Receipt,
    FileText,
    BarChart3,
    CreditCard,
    Percent,
    Layers,
    TrendingUp,
    ArrowRight,
    Sparkles,
    AlertTriangle,
    CheckCircle2,
    Clock,
    Wallet,
    ArrowUpRight,
    ArrowDownRight,
} from 'lucide-react';
import { usePermissions } from '@/hooks/usePermissions';

interface FinanceSummary {
    total_billed: number;
    total_billed_formatted: string;
    total_paid: number;
    total_paid_formatted: string;
    total_remaining: number;
    total_remaining_formatted: string;
    collection_rate: number;
    count_by_status: {
        unpaid: number;
        partial: number;
        paid: number;
        overdue: number;
        waived: number;
    };
    pending_payments: number;
    pending_payments_amount: number;
    pending_payments_formatted: string;
}

interface TopOutstanding {
    student_id: string;
    name: string;
    nis: string;
    classroom: string;
    total_outstanding: number;
    total_outstanding_formatted: string;
}

interface RecentPayment {
    id: string;
    invoice_number: string;
    student_name: string;
    amount: number;
    amount_formatted: string;
    status: string;
    paid_at: string | null;
}

interface Props {
    stats: {
        today: string;
        academic_year: string;
        summary: FinanceSummary;
        top_outstanding: TopOutstanding[];
        recent_payments: RecentPayment[];
        monthly_collection: {
            month: string;
            amount: number;
        }[];
    };
}

export default function FinanceIndex({ stats }: Props) {
    const { can } = usePermissions();
    const canManageFinance = can('finance.manage');
    const canViewReports = can('finance.report');

    const summary = stats?.summary || {
        total_billed: 0,
        total_billed_formatted: 'Rp 0',
        total_paid: 0,
        total_paid_formatted: 'Rp 0',
        total_remaining: 0,
        total_remaining_formatted: 'Rp 0',
        collection_rate: 0,
        count_by_status: { unpaid: 0, partial: 0, paid: 0, overdue: 0, waived: 0 },
        pending_payments: 0,
        pending_payments_amount: 0,
        pending_payments_formatted: 'Rp 0',
    };

    // Main feature cards
    const mainFeatures = [
        {
            title: 'Tagihan Siswa',
            description: 'Kelola tagihan dan pembayaran siswa',
            icon: Receipt,
            href: '/finance/fees',
            gradient: 'from-blue-500 to-blue-600',
            iconBg: 'bg-blue-400/20',
            stats: summary.count_by_status.unpaid > 0
                ? `${summary.count_by_status.unpaid} belum bayar`
                : null,
        },
        {
            title: 'Pembayaran',
            description: 'Catat dan verifikasi pembayaran',
            icon: CreditCard,
            href: '/finance/payments',
            gradient: 'from-emerald-500 to-emerald-600',
            iconBg: 'bg-emerald-400/20',
            stats: summary.pending_payments > 0
                ? `${summary.pending_payments} menunggu verifikasi`
                : null,
        },
        ...(canViewReports ? [{
            title: 'Laporan Keuangan',
            description: 'Analisis dan statistik keuangan',
            icon: BarChart3,
            href: '/finance/reports',
            gradient: 'from-violet-500 to-violet-600',
            iconBg: 'bg-violet-400/20',
        }] : []),
    ];

    // Admin/Setup cards
    const setupFeatures = [
        ...(canManageFinance ? [{
            title: 'Jenis Biaya',
            icon: Layers,
            href: '/finance/fee-types',
            color: 'text-orange-600 dark:text-orange-400',
            bg: 'bg-orange-50 dark:bg-orange-900/20',
            hoverBg: 'hover:bg-orange-100 dark:hover:bg-orange-900/30',
        }] : []),
        ...(canManageFinance ? [{
            title: 'Struktur Biaya',
            icon: FileText,
            href: '/finance/fee-structures',
            color: 'text-cyan-600 dark:text-cyan-400',
            bg: 'bg-cyan-50 dark:bg-cyan-900/20',
            hoverBg: 'hover:bg-cyan-100 dark:hover:bg-cyan-900/30',
        }] : []),
        ...(canManageFinance ? [{
            title: 'Metode Pembayaran',
            icon: Wallet,
            href: '/finance/payment-methods',
            color: 'text-indigo-600 dark:text-indigo-400',
            bg: 'bg-indigo-50 dark:bg-indigo-900/20',
            hoverBg: 'hover:bg-indigo-100 dark:hover:bg-indigo-900/30',
        }] : []),
        ...(canManageFinance ? [{
            title: 'Potongan / Diskon',
            icon: Percent,
            href: '/finance/discounts',
            color: 'text-pink-600 dark:text-pink-400',
            bg: 'bg-pink-50 dark:bg-pink-900/20',
            hoverBg: 'hover:bg-pink-100 dark:hover:bg-pink-900/30',
        }] : []),
    ];

    const statusCards = [
        {
            label: 'Total Tagihan',
            value: summary.total_billed_formatted,
            subValue: `${summary.count_by_status.unpaid + summary.count_by_status.partial + summary.count_by_status.paid + summary.count_by_status.overdue} siswa`,
            icon: Receipt,
            color: 'text-blue-600 dark:text-blue-400',
            bg: 'bg-blue-50 dark:bg-blue-900/20',
            ring: 'ring-blue-200 dark:ring-blue-800',
        },
        {
            label: 'Sudah Dibayar',
            value: summary.total_paid_formatted,
            subValue: `${summary.count_by_status.paid} lunas`,
            icon: CheckCircle2,
            color: 'text-emerald-600 dark:text-emerald-400',
            bg: 'bg-emerald-50 dark:bg-emerald-900/20',
            ring: 'ring-emerald-200 dark:ring-emerald-800',
            trend: 'up',
        },
        {
            label: 'Tunggakan',
            value: summary.total_remaining_formatted,
            subValue: `${summary.count_by_status.overdue} jatuh tempo`,
            icon: AlertTriangle,
            color: 'text-rose-600 dark:text-rose-400',
            bg: 'bg-rose-50 dark:bg-rose-900/20',
            ring: 'ring-rose-200 dark:ring-rose-800',
            trend: summary.count_by_status.overdue > 0 ? 'down' : undefined,
        },
        {
            label: 'Pending Verifikasi',
            value: summary.pending_payments_formatted,
            subValue: `${summary.pending_payments} transaksi`,
            icon: Clock,
            color: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-50 dark:bg-amber-900/20',
            ring: 'ring-amber-200 dark:ring-amber-800',
        },
    ];

    const getStatusBadge = (status: string) => {
        const variants: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
            pending: { variant: 'outline', label: 'Pending' },
            processing: { variant: 'secondary', label: 'Diproses' },
            completed: { variant: 'default', label: 'Selesai' },
            failed: { variant: 'destructive', label: 'Gagal' },
        };
        return variants[status] || { variant: 'outline', label: status };
    };

    return (
        <MainLayout title="Keuangan">
            <Head title="Keuangan" />

            <div className="space-y-8">
                {/* Hero Header */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-500 p-6 text-white shadow-lg md:p-8">
                    <div className="absolute -right-8 -top-8 h-40 w-40 rounded-full bg-white/5" />
                    <div className="absolute -bottom-12 -left-12 h-48 w-48 rounded-full bg-white/5" />
                    <div className="absolute right-1/4 top-1/2 h-24 w-24 rounded-full bg-white/5" />

                    <div className="relative z-10 flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5" />
                                <span className="text-sm font-medium opacity-90">Modul Keuangan</span>
                            </div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">
                                Kelola Keuangan Sekolah
                            </h1>
                            <p className="max-w-md text-sm opacity-80 md:text-base">
                                {stats?.academic_year || 'Tahun Ajaran Aktif'} • {stats?.today || new Date().toLocaleDateString('id-ID', {
                                    weekday: 'long',
                                    year: 'numeric',
                                    month: 'long',
                                    day: 'numeric'
                                })}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Button asChild variant="secondary" size="lg" className="shadow-md">
                                <Link href="/finance/fees">
                                    <Receipt className="mr-2 h-4 w-4" />
                                    Lihat Tagihan
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline" className="border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white">
                                <Link href="/finance/payments">
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    Catat Pembayaran
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* Stats Summary */}
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-semibold">Ringkasan Keuangan</h2>
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <TrendingUp className="h-4 w-4 text-emerald-500" />
                            <span>Tingkat penagihan: <strong className="text-foreground">{summary.collection_rate}%</strong></span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        {statusCards.map((item) => (
                            <div
                                key={item.label}
                                className={`group relative overflow-hidden rounded-xl ${item.bg} p-4 ring-1 ${item.ring} transition-all hover:scale-[1.02] hover:shadow-md`}
                            >
                                <div className="flex items-start justify-between">
                                    <div className="space-y-1">
                                        <p className="text-xs font-medium text-muted-foreground">{item.label}</p>
                                        <p className={`text-lg font-bold ${item.color} md:text-xl`}>{item.value}</p>
                                        <p className="text-xs text-muted-foreground">{item.subValue}</p>
                                    </div>
                                    <div className="flex flex-col items-end gap-1">
                                        <div className={`rounded-lg p-2 ${item.bg}`}>
                                            <item.icon className={`h-5 w-5 ${item.color}`} />
                                        </div>
                                        {item.trend && (
                                            <div className={`flex items-center text-xs ${item.trend === 'up' ? 'text-emerald-600' : 'text-rose-600'}`}>
                                                {item.trend === 'up' ? <ArrowUpRight className="h-3 w-3" /> : <ArrowDownRight className="h-3 w-3" />}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Progress Bar */}
                    <div className="rounded-xl bg-muted/50 p-4">
                        <div className="mb-2 flex justify-between text-sm">
                            <span className="text-muted-foreground">Progres Pembayaran</span>
                            <span className="font-medium">{summary.collection_rate}%</span>
                        </div>
                        <div className="flex h-3 w-full overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full bg-gradient-to-r from-emerald-500 to-emerald-400 transition-all duration-500"
                                style={{ width: `${summary.collection_rate}%` }}
                            />
                        </div>
                    </div>
                </div>

                {/* Main Feature Cards */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Menu Utama</h2>
                    <div className={`grid gap-4 sm:grid-cols-2 ${mainFeatures.length === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-2'}`}>
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

                {/* Setup/Admin Cards */}
                {setupFeatures.length > 0 && (
                    <div className="space-y-4">
                        <h2 className="text-lg font-semibold">Pengaturan Keuangan</h2>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            {setupFeatures.map((item) => (
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

                {/* Alerts & Recent Activity */}
                <div className="grid gap-4 md:grid-cols-2">
                    {/* Top Outstanding */}
                    {stats?.top_outstanding && stats.top_outstanding.length > 0 && (
                        <Card className="overflow-hidden border-0 shadow-md">
                            <div className="border-b bg-gradient-to-r from-rose-50 to-red-50 px-5 py-4 dark:from-rose-900/20 dark:to-red-900/20">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-rose-100 p-2 dark:bg-rose-800/30">
                                        <AlertTriangle className="h-5 w-5 text-rose-600 dark:text-rose-400" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">Tunggakan Tertinggi</h3>
                                        <p className="text-xs text-muted-foreground">5 siswa dengan tunggakan terbesar</p>
                                    </div>
                                </div>
                            </div>
                            <CardContent className="p-0">
                                <div className="divide-y">
                                    {stats.top_outstanding.map((student, index) => (
                                        <div key={student.student_id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-muted/50">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs font-semibold">
                                                    {index + 1}
                                                </div>
                                                <div>
                                                    <div className="font-medium">{student.name}</div>
                                                    <div className="text-xs text-muted-foreground">{student.classroom} • {student.nis}</div>
                                                </div>
                                            </div>
                                            <Badge variant="destructive" className="font-semibold">
                                                {student.total_outstanding_formatted}
                                            </Badge>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Recent Payments */}
                    {stats?.recent_payments && stats.recent_payments.length > 0 && (
                        <Card className="overflow-hidden border-0 shadow-md">
                            <div className="border-b bg-gradient-to-r from-emerald-50 to-green-50 px-5 py-4 dark:from-emerald-900/20 dark:to-green-900/20">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-lg bg-emerald-100 p-2 dark:bg-emerald-800/30">
                                        <CreditCard className="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold">Pembayaran Terbaru</h3>
                                        <p className="text-xs text-muted-foreground">5 transaksi terakhir</p>
                                    </div>
                                </div>
                            </div>
                            <CardContent className="p-0">
                                <div className="divide-y">
                                    {stats.recent_payments.map((payment) => {
                                        const badgeInfo = getStatusBadge(payment.status);
                                        return (
                                            <div key={payment.id} className="flex items-center justify-between px-5 py-3 transition-colors hover:bg-muted/50">
                                                <div>
                                                    <div className="font-medium">{payment.student_name}</div>
                                                    <div className="text-xs text-muted-foreground">{payment.invoice_number}</div>
                                                </div>
                                                <div className="text-right">
                                                    <div className="font-semibold text-emerald-600">{payment.amount_formatted}</div>
                                                    <Badge variant={badgeInfo.variant} className="mt-1 text-xs">
                                                        {badgeInfo.label}
                                                    </Badge>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </MainLayout>
    );
}
