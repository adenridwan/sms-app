import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { toast } from 'sonner';
import {
    Battery,
    BatteryCharging,
    BatteryLow,
    BatteryWarning,
    Cloud,
    CloudOff,
    Copy,
    Download,
    Loader2,
    Monitor,
    MoreVertical,
    Plus,
    QrCode,
    RefreshCw,
    Server,
    Signal,
    SignalHigh,
    SignalLow,
    SignalMedium,
    Smartphone,
    Trash2,
    Wifi,
    WifiOff,
} from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { QRCodeSVG } from 'qrcode.react';
import {
    deviceMonitorApi,
    usersApi,
    type DeviceProvisionQr,
    type DeviceSummary,
    type DeviceTodayStats,
    type ScannerDevice,
    type ServerInfo,
} from '@/services/api';
import type { User } from '@/types';

// Status badge component
function StatusBadge({ status }: { status: 'online' | 'idle' | 'offline' }) {
    const config = {
        online: { label: 'Online', className: 'bg-green-500 hover:bg-green-500' },
        idle: { label: 'Idle', className: 'bg-yellow-500 hover:bg-yellow-500' },
        offline: { label: 'Offline', className: 'bg-red-500 hover:bg-red-500' },
    };
    const c = config[status];
    return <Badge className={c.className}>{c.label}</Badge>;
}

// Battery icon component
function BatteryIcon({ level, charging }: { level: number | null; charging?: boolean }) {
    if (level === null) return <Battery className="h-4 w-4 text-muted-foreground" />;
    if (charging) return <BatteryCharging className="h-4 w-4 text-green-500" />;
    if (level <= 20) return <BatteryLow className="h-4 w-4 text-red-500" />;
    if (level <= 50) return <BatteryWarning className="h-4 w-4 text-yellow-500" />;
    return <Battery className="h-4 w-4 text-green-500" />;
}

// Network icon component
function NetworkIcon({ type, latency }: { type: string | null; latency: number | null }) {
    if (!type || type === 'none') return <WifiOff className="h-4 w-4 text-muted-foreground" />;
    if (type === 'wifi') return <Wifi className="h-4 w-4 text-blue-500" />;
    // Mobile
    if (!latency) return <Signal className="h-4 w-4 text-muted-foreground" />;
    if (latency < 100) return <SignalHigh className="h-4 w-4 text-green-500" />;
    if (latency < 300) return <SignalMedium className="h-4 w-4 text-yellow-500" />;
    return <SignalLow className="h-4 w-4 text-red-500" />;
}

// Summary card component
function SummaryCard({
    label,
    value,
    icon: Icon,
    variant,
}: {
    label: string;
    value: number;
    icon: React.ElementType;
    variant: 'default' | 'success' | 'warning' | 'destructive';
}) {
    const colors = {
        default: 'text-foreground',
        success: 'text-green-600',
        warning: 'text-yellow-600',
        destructive: 'text-red-600',
    };
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-4">
                <div className={`rounded-full bg-muted p-3 ${colors[variant]}`}>
                    <Icon className="h-5 w-5" />
                </div>
                <div>
                    <p className="text-2xl font-bold">{value}</p>
                    <p className="text-sm text-muted-foreground">{label}</p>
                </div>
            </CardContent>
        </Card>
    );
}

// Device card component
function DeviceCard({
    device,
    onViewDetail,
    onGenerateQr,
    onDelete,
}: {
    device: ScannerDevice;
    onViewDetail: () => void;
    onGenerateQr: () => void;
    onDelete: () => void;
}) {
    return (
        <Card className="overflow-hidden">
            <CardContent className="p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="flex items-start gap-3">
                        <div className="rounded-full bg-muted p-2">
                            <Smartphone className="h-5 w-5" />
                        </div>
                        <div className="space-y-1">
                            <div className="flex items-center gap-2">
                                <h3 className="font-medium">{device.device_name}</h3>
                                <StatusBadge status={device.connection_status} />
                            </div>
                            {device.location && (
                                <p className="text-sm text-muted-foreground">{device.location}</p>
                            )}
                            {device.user && (
                                <p className="text-sm">
                                    <span className="text-muted-foreground">User: </span>
                                    {device.user.name}
                                </p>
                            )}
                        </div>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon">
                                <MoreVertical className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem onClick={onViewDetail}>
                                <Monitor className="mr-2 h-4 w-4" /> Detail
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={onGenerateQr}>
                                <QrCode className="mr-2 h-4 w-4" /> Generate QR
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={onDelete} className="text-destructive">
                                <Trash2 className="mr-2 h-4 w-4" /> Hapus
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-4 border-t pt-4 text-sm md:grid-cols-4">
                    <div className="flex items-center gap-2">
                        <NetworkIcon type={device.network_type ?? null} latency={device.latency_ms ?? null} />
                        <span className="text-muted-foreground">
                            {device.network_type === 'wifi'
                                ? device.network_name ?? 'WiFi'
                                : device.network_type ?? '-'}
                            {device.latency_ms ? ` (${device.latency_ms}ms)` : ''}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        <BatteryIcon level={device.battery_level ?? null} charging={device.battery_charging} />
                        <span className="text-muted-foreground">
                            {device.battery_level !== null ? `${device.battery_level}%` : '-'}
                        </span>
                    </div>
                    <div className="flex items-center gap-2">
                        {device.connection_status === 'online' ? (
                            <Cloud className="h-4 w-4 text-green-500" />
                        ) : (
                            <CloudOff className="h-4 w-4 text-muted-foreground" />
                        )}
                        <span className="text-muted-foreground">{device.last_seen ?? 'Belum pernah'}</span>
                    </div>
                    <div className="flex items-center gap-2">
                        {device.pending_sync_count > 0 ? (
                            <Badge variant="secondary" className="gap-1">
                                <RefreshCw className="h-3 w-3" /> {device.pending_sync_count} pending
                            </Badge>
                        ) : (
                            <span className="text-muted-foreground">Sync OK</span>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function Devices() {
    const [loading, setLoading] = useState(true);
    const [serverInfo, setServerInfo] = useState<ServerInfo | null>(null);
    const [summary, setSummary] = useState<DeviceSummary | null>(null);
    const [devices, setDevices] = useState<ScannerDevice[]>([]);
    const [statusFilter, setStatusFilter] = useState<string>('all');

    // Dialog states
    const [showAddDevice, setShowAddDevice] = useState(false);
    const [showQrDialog, setShowQrDialog] = useState(false);
    const [showDetailDialog, setShowDetailDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [selectedDevice, setSelectedDevice] = useState<ScannerDevice | null>(null);
    const [deviceDetail, setDeviceDetail] = useState<{ device: ScannerDevice; today_stats: DeviceTodayStats } | null>(null);
    const [qrData, setQrData] = useState<DeviceProvisionQr | null>(null);

    // Form states
    const [addForm, setAddForm] = useState({ device_name: '', location: '', user_id: '' });
    const [qrForm, setQrForm] = useState({ user_id: '', device_name: '', location: '' });
    const [userSearch, setUserSearch] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [searchingUsers, setSearchingUsers] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // Fetch data
    const fetchData = useCallback(async () => {
        try {
            const [infoRes, summaryRes, devicesRes] = await Promise.all([
                deviceMonitorApi.serverInfo(),
                deviceMonitorApi.summary(),
                deviceMonitorApi.list({ status: statusFilter !== 'all' ? statusFilter : undefined }),
            ]);
            setServerInfo(infoRes.data.data ?? null);
            setSummary(summaryRes.data.data ?? null);
            setDevices(devicesRes.data.data?.data ?? []);
        } catch {
            toast.error('Gagal memuat data device');
        } finally {
            setLoading(false);
        }
    }, [statusFilter]);

    useEffect(() => {
        fetchData();
        // Auto refresh setiap 30 detik
        const interval = setInterval(fetchData, 30000);
        return () => clearInterval(interval);
    }, [fetchData]);

    // Search users
    const searchUsers = async () => {
        if (!userSearch.trim()) return;
        setSearchingUsers(true);
        try {
            const res = await usersApi.list({ search: userSearch, per_page: 10 });
            setUsers(res.data.data?.data ?? []);
        } catch {
            toast.error('Gagal mencari user');
        } finally {
            setSearchingUsers(false);
        }
    };

    // Add device
    const handleAddDevice = async () => {
        if (!addForm.device_name.trim()) {
            toast.error('Nama device harus diisi');
            return;
        }
        setSubmitting(true);
        try {
            await deviceMonitorApi.create({
                device_name: addForm.device_name,
                location: addForm.location || undefined,
                user_id: addForm.user_id || undefined,
            });
            toast.success('Device berhasil ditambahkan');
            setShowAddDevice(false);
            setAddForm({ device_name: '', location: '', user_id: '' });
            fetchData();
        } catch {
            toast.error('Gagal menambahkan device');
        } finally {
            setSubmitting(false);
        }
    };

    // Generate QR
    const handleGenerateQr = async () => {
        if (!qrForm.user_id) {
            toast.error('Pilih user terlebih dahulu');
            return;
        }
        setSubmitting(true);
        try {
            const res = await deviceMonitorApi.generateProvisionQr({
                user_id: qrForm.user_id,
                device_name: qrForm.device_name || undefined,
                location: qrForm.location || undefined,
            });
            setQrData(res.data.data ?? null);
        } catch {
            toast.error('Gagal generate QR');
        } finally {
            setSubmitting(false);
        }
    };

    // View detail
    const handleViewDetail = async (device: ScannerDevice) => {
        setSelectedDevice(device);
        setShowDetailDialog(true);
        try {
            const res = await deviceMonitorApi.get(device.id);
            setDeviceDetail(res.data.data ?? null);
        } catch {
            toast.error('Gagal memuat detail device');
        }
    };

    // Delete device
    const handleDelete = async () => {
        if (!selectedDevice) return;
        setSubmitting(true);
        try {
            await deviceMonitorApi.delete(selectedDevice.id);
            toast.success('Device berhasil dihapus');
            setShowDeleteDialog(false);
            setSelectedDevice(null);
            fetchData();
        } catch {
            toast.error('Gagal menghapus device');
        } finally {
            setSubmitting(false);
        }
    };

    // Copy to clipboard
    const copyToClipboard = (text: string, label: string) => {
        navigator.clipboard.writeText(text);
        toast.success(`${label} disalin`);
    };

    // Download QR
    const downloadQr = () => {
        if (!qrData) return;
        const svg = document.getElementById('provision-qr-svg') as SVGSVGElement | null;
        if (!svg) return;

        // Ukuran QR yang diinginkan (lebih besar untuk hasil yang jelas)
        const qrSize = 400;
        const padding = 40;
        const totalSize = qrSize + padding * 2;

        // Clone SVG dan set dimensi eksplisit
        const svgClone = svg.cloneNode(true) as SVGSVGElement;
        svgClone.setAttribute('width', String(qrSize));
        svgClone.setAttribute('height', String(qrSize));
        svgClone.setAttribute('viewBox', `0 0 ${svg.viewBox.baseVal.width || qrSize} ${svg.viewBox.baseVal.height || qrSize}`);

        const svgData = new XMLSerializer().serializeToString(svgClone);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) return;

        canvas.width = totalSize;
        canvas.height = totalSize;

        // Background putih
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, totalSize, totalSize);

        const img = new Image();
        img.onload = () => {
            // Gambar QR di tengah dengan padding
            ctx.drawImage(img, padding, padding, qrSize, qrSize);

            const pngUrl = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = `qr-device-${qrData.user.name.replace(/\s+/g, '-').toLowerCase()}.png`;
            link.href = pngUrl;
            link.click();
        };
        img.onerror = () => {
            toast.error('Gagal mengunduh QR. Coba screenshot manual.');
        };
        img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
    };

    return (
        <MainLayout>
            <Head title="Monitor Device" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Monitor Device</h1>
                        <p className="text-sm text-muted-foreground">
                            Kelola dan pantau perangkat scanner absensi
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={fetchData} disabled={loading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button onClick={() => setShowAddDevice(true)}>
                            <Plus className="mr-2 h-4 w-4" /> Tambah Device
                        </Button>
                    </div>
                </div>

                {/* Server Info */}
                {serverInfo && (
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Server className="h-4 w-4" /> Informasi Server
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-1">
                                    <p className="text-sm text-muted-foreground">IP Lokal</p>
                                    <div className="flex items-center gap-2">
                                        <code className="rounded bg-muted px-2 py-1 text-sm">
                                            {serverInfo.local_ip}:{serverInfo.port}
                                        </code>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            onClick={() => copyToClipboard(`${serverInfo.local_ip}:${serverInfo.port}`, 'IP')}
                                        >
                                            <Copy className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm text-muted-foreground">URL API</p>
                                    <div className="flex items-center gap-2">
                                        <code className="max-w-[250px] truncate rounded bg-muted px-2 py-1 text-sm">
                                            {serverInfo.api_url}
                                        </code>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            onClick={() => copyToClipboard(serverInfo.api_url, 'URL API')}
                                        >
                                            <Copy className="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-sm text-muted-foreground">URL Publik</p>
                                    <p className="text-sm">
                                        {serverInfo.public_url ?? (
                                            <span className="text-muted-foreground">Belum dikonfigurasi</span>
                                        )}
                                    </p>
                                </div>
                            </div>
                            <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950">
                                <p className="text-sm text-blue-800 dark:text-blue-200">
                                    <strong>Cara koneksi device:</strong> Pastikan HP dan komputer terhubung ke WiFi
                                    yang sama, lalu scan QR provisioning di aplikasi mobile.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Summary Cards */}
                {loading ? (
                    <div className="grid gap-4 md:grid-cols-4">
                        {[...Array(4)].map((_, i) => (
                            <Skeleton key={i} className="h-24" />
                        ))}
                    </div>
                ) : summary ? (
                    <div className="grid gap-4 md:grid-cols-4">
                        <SummaryCard label="Total Device" value={summary.total} icon={Smartphone} variant="default" />
                        <SummaryCard label="Online" value={summary.online} icon={Cloud} variant="success" />
                        <SummaryCard label="Offline" value={summary.offline} icon={CloudOff} variant="destructive" />
                        <SummaryCard
                            label="Scan Pending"
                            value={summary.pending_sync_total}
                            icon={RefreshCw}
                            variant={summary.pending_sync_total > 0 ? 'warning' : 'default'}
                        />
                    </div>
                ) : null}

                {/* Filter */}
                <div className="flex items-center gap-4">
                    <Label>Filter Status:</Label>
                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                        <SelectTrigger className="w-[150px]">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua</SelectItem>
                            <SelectItem value="online">Online</SelectItem>
                            <SelectItem value="idle">Idle</SelectItem>
                            <SelectItem value="offline">Offline</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                {/* Device List */}
                {loading ? (
                    <div className="space-y-4">
                        {[...Array(3)].map((_, i) => (
                            <Skeleton key={i} className="h-32" />
                        ))}
                    </div>
                ) : devices.length === 0 ? (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Smartphone className="h-12 w-12 text-muted-foreground" />
                            <h3 className="mt-4 font-medium">Belum ada device</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Tambahkan device baru atau generate QR provisioning
                            </p>
                            <div className="mt-4 flex gap-2">
                                <Button variant="outline" onClick={() => setShowAddDevice(true)}>
                                    <Plus className="mr-2 h-4 w-4" /> Tambah Device
                                </Button>
                                <Button onClick={() => setShowQrDialog(true)}>
                                    <QrCode className="mr-2 h-4 w-4" /> Generate QR
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {devices.map((device) => (
                            <DeviceCard
                                key={device.id}
                                device={device}
                                onViewDetail={() => handleViewDetail(device)}
                                onGenerateQr={() => {
                                    setQrForm({
                                        user_id: device.user?.id ?? '',
                                        device_name: device.device_name,
                                        location: device.location ?? '',
                                    });
                                    setShowQrDialog(true);
                                }}
                                onDelete={() => {
                                    setSelectedDevice(device);
                                    setShowDeleteDialog(true);
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/* Add Device Dialog */}
            <Dialog open={showAddDevice} onOpenChange={setShowAddDevice}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Device Baru</DialogTitle>
                        <DialogDescription>
                            Daftarkan perangkat scanner baru ke sistem
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="device-name">Nama Device *</Label>
                            <Input
                                id="device-name"
                                placeholder="HP Gerbang Depan"
                                value={addForm.device_name}
                                onChange={(e) => setAddForm({ ...addForm, device_name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="device-location">Lokasi</Label>
                            <Input
                                id="device-location"
                                placeholder="Gerbang Depan"
                                value={addForm.location}
                                onChange={(e) => setAddForm({ ...addForm, location: e.target.value })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowAddDevice(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleAddDevice} disabled={submitting}>
                            {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Generate QR Dialog */}
            <Dialog open={showQrDialog} onOpenChange={(open) => { setShowQrDialog(open); if (!open) setQrData(null); }}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Generate QR Provisioning</DialogTitle>
                        <DialogDescription>
                            Buat QR untuk login device tanpa ketik password
                        </DialogDescription>
                    </DialogHeader>

                    {!qrData ? (
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Cari User</Label>
                                <div className="flex gap-2">
                                    <Input
                                        placeholder="Nama atau email..."
                                        value={userSearch}
                                        onChange={(e) => setUserSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && searchUsers()}
                                    />
                                    <Button onClick={searchUsers} disabled={searchingUsers}>
                                        {searchingUsers ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Cari'}
                                    </Button>
                                </div>
                            </div>

                            {users.length > 0 && (
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-2">
                                    {users.map((user) => (
                                        <button
                                            key={user.id}
                                            className={`w-full rounded px-3 py-2 text-left text-sm hover:bg-muted ${qrForm.user_id === user.id ? 'bg-muted' : ''}`}
                                            onClick={() => setQrForm({ ...qrForm, user_id: user.id })}
                                        >
                                            <p className="font-medium">{user.full_name}</p>
                                            <p className="text-xs text-muted-foreground">{user.email}</p>
                                        </button>
                                    ))}
                                </div>
                            )}

                            <div className="space-y-2">
                                <Label>Nama Device (opsional)</Label>
                                <Input
                                    placeholder="HP Gerbang Depan"
                                    value={qrForm.device_name}
                                    onChange={(e) => setQrForm({ ...qrForm, device_name: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Lokasi (opsional)</Label>
                                <Input
                                    placeholder="Gerbang Depan"
                                    value={qrForm.location}
                                    onChange={(e) => setQrForm({ ...qrForm, location: e.target.value })}
                                />
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex justify-center rounded-lg bg-white p-4">
                                <QRCodeSVG
                                    id="provision-qr-svg"
                                    value={qrData.qr_content}
                                    size={200}
                                    level="H"
                                    includeMargin={true}
                                />
                            </div>
                            <div className="space-y-2 text-sm">
                                <p>
                                    <span className="text-muted-foreground">User:</span> {qrData.user.name}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">Server:</span> {qrData.server_url}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">Berlaku:</span> {qrData.expires_in_minutes} menit
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="outline" className="flex-1" onClick={downloadQr}>
                                    <Download className="mr-2 h-4 w-4" /> Unduh QR
                                </Button>
                                <Button
                                    variant="outline"
                                    className="flex-1"
                                    onClick={() => copyToClipboard(qrData.qr_content, 'Link QR')}
                                >
                                    <Copy className="mr-2 h-4 w-4" /> Salin Link
                                </Button>
                            </div>
                        </div>
                    )}

                    <DialogFooter>
                        {!qrData ? (
                            <>
                                <Button variant="outline" onClick={() => setShowQrDialog(false)}>
                                    Batal
                                </Button>
                                <Button onClick={handleGenerateQr} disabled={submitting || !qrForm.user_id}>
                                    {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Generate QR
                                </Button>
                            </>
                        ) : (
                            <Button onClick={() => { setShowQrDialog(false); setQrData(null); }}>
                                Selesai
                            </Button>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Detail Dialog */}
            <Dialog open={showDetailDialog} onOpenChange={setShowDetailDialog}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Detail Device</DialogTitle>
                    </DialogHeader>

                    {deviceDetail ? (
                        <div className="space-y-4">
                            <div className="flex items-center gap-3">
                                <div className="rounded-full bg-muted p-3">
                                    <Smartphone className="h-6 w-6" />
                                </div>
                                <div>
                                    <h3 className="font-medium">{deviceDetail.device.device_name}</h3>
                                    <StatusBadge status={deviceDetail.device.connection_status} />
                                </div>
                            </div>

                            <div className="grid gap-3 rounded-lg border p-4 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Lokasi</span>
                                    <span>{deviceDetail.device.location ?? '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">User</span>
                                    <span>{deviceDetail.device.user?.name ?? '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Model</span>
                                    <span>{deviceDetail.device.device_model ?? '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">OS</span>
                                    <span>{deviceDetail.device.os_version ?? '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Versi App</span>
                                    <span>{deviceDetail.device.app_version ?? '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Baterai</span>
                                    <span className="flex items-center gap-2">
                                        <BatteryIcon
                                            level={deviceDetail.device.battery_level ?? null}
                                            charging={deviceDetail.device.battery_charging}
                                        />
                                        {deviceDetail.device.battery_level !== null
                                            ? `${deviceDetail.device.battery_level}%`
                                            : '-'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Koneksi</span>
                                    <span className="flex items-center gap-2">
                                        <NetworkIcon
                                            type={deviceDetail.device.network_type ?? null}
                                            latency={deviceDetail.device.latency_ms ?? null}
                                        />
                                        {deviceDetail.device.network_type ?? '-'}
                                        {deviceDetail.device.latency_ms ? ` (${deviceDetail.device.latency_ms}ms)` : ''}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Terakhir Dilihat</span>
                                    <span>{deviceDetail.device.last_seen ?? '-'}</span>
                                </div>
                            </div>

                            <div className="rounded-lg border p-4">
                                <h4 className="mb-3 font-medium">Statistik Hari Ini</h4>
                                <div className="grid grid-cols-3 gap-4 text-center">
                                    <div>
                                        <p className="text-2xl font-bold">{deviceDetail.today_stats.total_scans}</p>
                                        <p className="text-xs text-muted-foreground">Total Scan</p>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-green-600">{deviceDetail.today_stats.synced}</p>
                                        <p className="text-xs text-muted-foreground">Synced</p>
                                    </div>
                                    <div>
                                        <p className="text-2xl font-bold text-yellow-600">{deviceDetail.today_stats.pending}</p>
                                        <p className="text-xs text-muted-foreground">Pending</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="flex items-center justify-center py-8">
                            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                    )}

                    <DialogFooter>
                        <Button onClick={() => setShowDetailDialog(false)}>Tutup</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Device?</AlertDialogTitle>
                        <AlertDialogDescription>
                            Device "{selectedDevice?.device_name}" akan dihapus dari sistem. Tindakan ini tidak dapat
                            dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} disabled={submitting} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
