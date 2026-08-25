import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import {
    CheckCircle2,
    AlertCircle,
    Clock,
    XCircle,
    Timer,
    QrCode,
    ScanLine,
    FileText,
    CreditCard,
    Copy,
} from 'lucide-react';
import { myAttendanceApi, qrCodeApi } from '@/services/attendance';
import type { MyAttendanceToday, MyAttendanceHistory, QrCodeData } from '@/types/attendance';
import type { PageProps } from '@/types';
import { roleSummary } from '@/lib/roles';
import { usePermissions } from '@/hooks/usePermissions';

const MONTH_NAMES = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const statusMeta: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    present: { label: 'Hadir', variant: 'default' },
    late: { label: 'Terlambat', variant: 'secondary' },
    sick: { label: 'Sakit', variant: 'secondary' },
    permitted: { label: 'Izin', variant: 'outline' },
    absent: { label: 'Tidak Hadir', variant: 'destructive' },
    on_duty: { label: 'Dinas Luar', variant: 'outline' },
    work_from_home: { label: 'WFH', variant: 'outline' },
    belum_scan: { label: 'Belum Scan', variant: 'outline' },
    libur: { label: 'Libur', variant: 'outline' },
};

const getStatusBadge = (status: string) => {
    const meta = statusMeta[status] || { label: status, variant: 'outline' as const };
    return <Badge variant={meta.variant}>{meta.label}</Badge>;
};

const buildMonthOptions = () => {
    const options: { value: string; label: string }[] = [];
    const now = new Date();
    for (let i = 0; i < 6; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        options.push({ value, label: `${MONTH_NAMES[d.getMonth()]} ${d.getFullYear()}` });
    }
    return options;
};

export default function MyAttendanceIndex() {
    const { auth } = usePage<PageProps>().props;
    const { canAny } = usePermissions();
    const monthOptions = useMemo(buildMonthOptions, []);
    const [month, setMonth] = useState(monthOptions[0].value);
    const [today, setToday] = useState<MyAttendanceToday | null>(null);
    const [history, setHistory] = useState<MyAttendanceHistory | null>(null);
    const [loading, setLoading] = useState(false);
    const [qrOpen, setQrOpen] = useState(false);
    const [qrData, setQrData] = useState<QrCodeData | null>(null);
    const [qrLoading, setQrLoading] = useState(false);

    const fetchToday = async () => {
        try {
            const response = await myAttendanceApi.today();
            setToday(response.data.data ?? null);
        } catch (error) {
            toast.error('Gagal memuat status kehadiran hari ini');
        }
    };

    const fetchHistory = async (targetMonth: string) => {
        setLoading(true);
        try {
            const response = await myAttendanceApi.history(targetMonth);
            setHistory(response.data.data ?? null);
        } catch (error) {
            toast.error('Gagal memuat riwayat kehadiran');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchToday();
    }, []);

    useEffect(() => {
        fetchHistory(month);
    }, [month]);

    const openQr = async () => {
        if (!today?.qr.available || !today.qr.teacher_id) return;

        setQrOpen(true);
        setQrLoading(true);
        try {
            const response = await qrCodeApi.teacher(today.qr.teacher_id);
            setQrData(response.data.data ?? null);
        } catch (error) {
            toast.error('Gagal memuat QR kehadiran');
            setQrOpen(false);
        } finally {
            setQrLoading(false);
        }
    };

    return (
        <MainLayout title="Absensi Saya">
            <Head title="Absensi Saya" />

            <div className="space-y-6">
                {/* Header */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Absensi Saya</h1>
                    <p className="text-muted-foreground">Riwayat dan status kehadiran Anda</p>
                </div>

                {/* Kartu status hari ini */}
                <Card className="overflow-hidden p-0">
                    <div className="flex flex-col sm:flex-row">
                        <div className="flex flex-1 flex-col gap-2 p-6">
                            <div className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                {today?.date_label || '—'}
                            </div>
                            <div className="flex items-baseline gap-3">
                                {today && getStatusBadge(today.status)}
                                {today && today.late_minutes > 0 && (
                                    <span className="text-sm text-destructive">
                                        Terlambat {today.late_minutes} menit
                                    </span>
                                )}
                            </div>
                            <div className="mt-2 flex gap-6">
                                <div>
                                    <div className="text-xs uppercase tracking-wide text-muted-foreground">Jam Masuk</div>
                                    <div className="font-heading text-2xl font-bold">{today?.check_in_time || '-'}</div>
                                </div>
                                <div className="w-px bg-border" />
                                <div>
                                    <div className="text-xs uppercase tracking-wide text-muted-foreground">Jam Pulang</div>
                                    <div className="font-heading text-2xl font-bold">{today?.check_out_time || '-'}</div>
                                </div>
                            </div>
                        </div>
                        <div className="flex w-full flex-shrink-0 flex-col justify-center gap-2 bg-primary p-4 text-primary-foreground sm:w-[200px]">
                            {today?.qr.available && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={openQr}
                                    className="w-full"
                                >
                                    <QrCode className="mr-2 h-4 w-4" />
                                    Tampilkan QR
                                </Button>
                            )}
                            {today?.rfid_code ? (
                                <div className="flex items-center justify-between gap-2 rounded-md border border-primary-foreground/30 bg-primary-foreground/10 px-3 py-2">
                                    <div className="min-w-0">
                                        <div className="text-[10px] uppercase tracking-wide text-primary-foreground/70">
                                            Ref ID
                                        </div>
                                        <div className="truncate font-mono text-sm">{today.rfid_code}</div>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7 shrink-0 text-primary-foreground hover:bg-primary-foreground/20 hover:text-primary-foreground"
                                        onClick={() => {
                                            navigator.clipboard.writeText(today.rfid_code as string);
                                            toast.success('Ref ID disalin');
                                        }}
                                    >
                                        <Copy className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            ) : today?.qr.available ? (
                                <div className="flex items-center gap-2 rounded-md border border-dashed border-primary-foreground/30 px-3 py-2 text-xs text-primary-foreground/70">
                                    <CreditCard className="h-3.5 w-3.5 shrink-0" />
                                    Kartu RFID belum ditautkan
                                </div>
                            ) : null}
                            {canAny('attendance.scan-students', 'attendance.scan-staff') && (
                                <Button asChild variant="outline" className="w-full border-primary-foreground/50 bg-transparent text-primary-foreground hover:bg-primary-foreground/10 hover:text-primary-foreground">
                                    <Link href="/scanner">
                                        <ScanLine className="mr-2 h-4 w-4" />
                                        Buka Scanner
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Filter periode + aksi */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="w-[220px]">
                        <Label>Periode</Label>
                        <Select value={month} onValueChange={setMonth}>
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {monthOptions.map((opt) => (
                                    <SelectItem key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <Button asChild variant="outline">
                        <Link href="/attendance/permissions/create">
                            <FileText className="mr-2 h-4 w-4" />
                            Ajukan Izin / Sakit
                        </Link>
                    </Button>
                </div>

                {/* Ringkasan */}
                {history && (
                    <div className="grid gap-4 md:grid-cols-5">
                        <Card>
                            <CardContent className="flex items-center justify-between pt-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Hadir</div>
                                    <div className="text-2xl font-bold">{history.summary.hadir}</div>
                                </div>
                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center justify-between pt-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Sakit</div>
                                    <div className="text-2xl font-bold">{history.summary.sakit}</div>
                                </div>
                                <AlertCircle className="h-5 w-5 text-yellow-600" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center justify-between pt-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Izin</div>
                                    <div className="text-2xl font-bold">{history.summary.izin}</div>
                                </div>
                                <Clock className="h-5 w-5 text-blue-600" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center justify-between pt-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Alfa</div>
                                    <div className="text-2xl font-bold">{history.summary.alfa}</div>
                                </div>
                                <XCircle className="h-5 w-5 text-red-600" />
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="flex items-center justify-between pt-4">
                                <div>
                                    <div className="text-sm text-muted-foreground">Terlambat</div>
                                    <div className="text-2xl font-bold">{history.summary.telat}</div>
                                </div>
                                <Timer className="h-5 w-5 text-gray-600" />
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Riwayat */}
                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Kehadiran</CardTitle>
                        <CardDescription>
                            {history
                                ? `${history.month_label} · ${history.total_working_days} hari kerja tercatat`
                                : 'Memuat...'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : !history || history.history.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">Tidak ada data untuk periode ini</div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Hari</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Jam Masuk</TableHead>
                                            <TableHead>Jam Pulang</TableHead>
                                            <TableHead>Terlambat</TableHead>
                                            <TableHead>Catatan</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {history.history.map((rec) => (
                                            <TableRow key={rec.date}>
                                                <TableCell className="font-medium">{rec.date_short}</TableCell>
                                                <TableCell className="text-muted-foreground">{rec.day}</TableCell>
                                                <TableCell>{getStatusBadge(rec.status)}</TableCell>
                                                <TableCell className="text-muted-foreground">{rec.check_in_time || '-'}</TableCell>
                                                <TableCell className="text-muted-foreground">{rec.check_out_time || '-'}</TableCell>
                                                <TableCell>
                                                    {rec.late_minutes > 0 ? (
                                                        <Badge variant="destructive">{rec.late_minutes} menit</Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">—</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">{rec.notes || '—'}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Modal QR */}
            <Dialog open={qrOpen} onOpenChange={setQrOpen}>
                <DialogContent className="max-w-xs text-center">
                    <DialogHeader>
                        <DialogTitle>QR Kehadiran Saya</DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col items-center gap-3">
                        {qrLoading ? (
                            <div className="flex h-52 w-52 items-center justify-center text-sm text-muted-foreground">
                                Memuat QR...
                            </div>
                        ) : qrData ? (
                            <img
                                src={qrData.qr_code}
                                alt={`QR Code ${qrData.name}`}
                                className="h-52 w-52 rounded-lg border"
                            />
                        ) : null}
                        <div>
                            <div className="font-semibold">{auth.user?.full_name}</div>
                            <DialogDescription>
                                {roleSummary(auth.user?.roles)}
                                {today?.identity_number ? ` · ${today.identity_number}` : ''}
                            </DialogDescription>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Tunjukkan QR ini ke perangkat scanner untuk presensi masuk/pulang.
                        </p>
                    </div>
                    <DialogFooter className="justify-center sm:justify-center">
                        <Button variant="outline" onClick={() => setQrOpen(false)}>
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
