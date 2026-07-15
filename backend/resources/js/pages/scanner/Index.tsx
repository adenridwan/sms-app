import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
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
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import {
    Camera,
    Keyboard,
    Wifi,
    WifiOff,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle,
    RefreshCw,
    Volume2,
    VolumeX,
    User,
    Building2,
} from 'lucide-react';
import { scannerApi } from '@/services/attendance';
import { useOfflineQueue } from '@/hooks/useOfflineQueue';
import type { ScanResult, ScannerBootstrap, ScanType } from '@/types/attendance';

export default function ScannerIndex() {
    const [bootstrap, setBootstrap] = useState<ScannerBootstrap | null>(null);
    const [loading, setLoading] = useState(true);
    const [scanMode, setScanMode] = useState<'camera' | 'rfid'>('rfid');
    const [scanType, setScanType] = useState<ScanType>('masuk');
    const [soundEnabled, setSoundEnabled] = useState(true);
    const [isOnline, setIsOnline] = useState(navigator.onLine);
    const [lastScan, setLastScan] = useState<ScanResult | null>(null);
    const [showResult, setShowResult] = useState(false);
    const [scanning, setScanning] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const audioSuccessRef = useRef<HTMLAudioElement>(null);
    const audioErrorRef = useRef<HTMLAudioElement>(null);

    const { queueScan, pendingCount, syncQueue, syncing } = useOfflineQueue();

    // Online/offline detection
    useEffect(() => {
        const handleOnline = () => {
            setIsOnline(true);
            toast.success('Koneksi tersambung');
            // Auto sync when back online
            if (pendingCount > 0) {
                syncQueue();
            }
        };
        const handleOffline = () => {
            setIsOnline(false);
            toast.warning('Anda sedang offline. Scan akan disimpan lokal.');
        };

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [pendingCount, syncQueue]);

    // Fetch bootstrap data
    const fetchBootstrap = async () => {
        try {
            const response = await scannerApi.bootstrap();
            if (response.data.data) {
                setBootstrap(response.data.data);
            }
        } catch (error) {
            console.error('Failed to fetch bootstrap:', error);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchBootstrap();
        // Refresh bootstrap every minute
        const interval = setInterval(fetchBootstrap, 60000);
        return () => clearInterval(interval);
    }, []);

    // Focus input on RFID mode
    useEffect(() => {
        if (scanMode === 'rfid' && inputRef.current) {
            inputRef.current.focus();
        }
    }, [scanMode]);

    // Auto-determine scan type based on time
    useEffect(() => {
        if (bootstrap) {
            const now = new Date();
            const currentTime = now.toTimeString().slice(0, 5);
            const checkOutStart = bootstrap.settings.check_out_start;

            if (currentTime >= checkOutStart) {
                setScanType('pulang');
            } else {
                setScanType('masuk');
            }
        }
    }, [bootstrap]);

    const playSound = (type: 'success' | 'error') => {
        if (!soundEnabled) return;
        const audio = type === 'success' ? audioSuccessRef.current : audioErrorRef.current;
        if (audio) {
            audio.currentTime = 0;
            audio.play().catch(() => {});
        }
    };

    const handleScan = useCallback(async (uniqueCode: string) => {
        if (!uniqueCode.trim() || scanning) return;

        setScanning(true);

        try {
            if (!isOnline) {
                // Queue for offline sync
                await queueScan({
                    unique_code: uniqueCode,
                    waktu: scanType,
                    scanned_at: new Date().toISOString(),
                });
                playSound('success');
                setLastScan({
                    success: true,
                    message: 'Scan tersimpan (offline)',
                    data: null,
                });
                setShowResult(true);
            } else {
                const response = await scannerApi.scan({
                    unique_code: uniqueCode,
                    waktu: scanType,
                });

                setLastScan(response.data);
                setShowResult(true);

                if (response.data.success) {
                    playSound('success');
                } else {
                    playSound('error');
                }
            }
        } catch (error: any) {
            playSound('error');
            const message = error.response?.data?.message || 'Gagal memproses scan';
            setLastScan({
                success: false,
                message,
                data: null,
            });
            setShowResult(true);
        } finally {
            setScanning(false);
            // Clear input and refocus
            if (inputRef.current) {
                inputRef.current.value = '';
                inputRef.current.focus();
            }
        }
    }, [isOnline, scanType, scanning, queueScan]);

    const handleInputKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'Enter') {
            const value = (e.target as HTMLInputElement).value;
            handleScan(value);
        }
    };

    const formatTime = (timeStr: string) => {
        return timeStr;
    };

    if (loading) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
                <RefreshCw className="h-8 w-8 animate-spin text-blue-600" />
            </div>
        );
    }

    return (
        <>
            <Head title="Scanner Absensi" />

            {/* Audio elements */}
            <audio ref={audioSuccessRef} src="/sounds/success.mp3" preload="auto" />
            <audio ref={audioErrorRef} src="/sounds/error.mp3" preload="auto" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-4 dark:from-gray-900 dark:to-gray-800">
                <div className="mx-auto max-w-2xl space-y-4">
                    {/* Header */}
                    <Card>
                        <CardContent className="flex items-center justify-between p-4">
                            <div className="flex items-center gap-3">
                                <Building2 className="h-8 w-8 text-blue-600" />
                                <div>
                                    <h1 className="text-xl font-bold">Scanner Absensi</h1>
                                    <p className="text-sm text-muted-foreground">
                                        {bootstrap?.today}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {isOnline ? (
                                    <Badge variant="default" className="gap-1">
                                        <Wifi className="h-3 w-3" /> Online
                                    </Badge>
                                ) : (
                                    <Badge variant="destructive" className="gap-1">
                                        <WifiOff className="h-3 w-3" /> Offline
                                    </Badge>
                                )}
                                {pendingCount > 0 && (
                                    <Badge variant="secondary" className="gap-1">
                                        <Clock className="h-3 w-3" /> {pendingCount} pending
                                    </Badge>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Holiday Warning */}
                    {bootstrap?.is_holiday && (
                        <Card className="border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20">
                            <CardContent className="flex items-center gap-3 p-4">
                                <AlertCircle className="h-5 w-5 text-yellow-600" />
                                <div>
                                    <div className="font-medium text-yellow-800 dark:text-yellow-200">
                                        Hari Libur
                                    </div>
                                    <div className="text-sm text-yellow-700 dark:text-yellow-300">
                                        {bootstrap.holiday_info?.keterangan || 'Hari ini adalah hari libur'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {/* Time Info */}
                    <Card>
                        <CardContent className="p-4">
                            <div className="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div className="text-sm text-muted-foreground">Jam Masuk</div>
                                    <div className="font-semibold">
                                        {bootstrap?.settings.check_in_start} - {bootstrap?.settings.check_in_end}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Waktu Sekarang</div>
                                    <div className="text-2xl font-bold text-blue-600">
                                        {bootstrap?.current_time}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-sm text-muted-foreground">Jam Pulang</div>
                                    <div className="font-semibold">
                                        {bootstrap?.settings.check_out_start} - {bootstrap?.settings.check_out_end}
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Scan Controls */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Mode Scan</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex gap-2">
                                <Button
                                    variant={scanMode === 'rfid' ? 'default' : 'outline'}
                                    className="flex-1"
                                    onClick={() => setScanMode('rfid')}
                                >
                                    <Keyboard className="mr-2 h-4 w-4" />
                                    RFID / Manual
                                </Button>
                                <Button
                                    variant={scanMode === 'camera' ? 'default' : 'outline'}
                                    className="flex-1"
                                    onClick={() => setScanMode('camera')}
                                >
                                    <Camera className="mr-2 h-4 w-4" />
                                    Kamera QR
                                </Button>
                            </div>

                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-4">
                                    <Label>Tipe Scan:</Label>
                                    <Select value={scanType} onValueChange={(v) => setScanType(v as ScanType)}>
                                        <SelectTrigger className="w-[150px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="masuk">Masuk</SelectItem>
                                            <SelectItem value="pulang">Pulang</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Switch
                                        checked={soundEnabled}
                                        onCheckedChange={setSoundEnabled}
                                    />
                                    {soundEnabled ? (
                                        <Volume2 className="h-4 w-4" />
                                    ) : (
                                        <VolumeX className="h-4 w-4" />
                                    )}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Scanner Area */}
                    <Card className="overflow-hidden">
                        <CardContent className="p-6">
                            {scanMode === 'rfid' ? (
                                <div className="space-y-4">
                                    <div className="text-center">
                                        <Keyboard className="mx-auto h-16 w-16 text-blue-600" />
                                        <p className="mt-2 text-muted-foreground">
                                            Tempelkan kartu RFID atau masukkan kode secara manual
                                        </p>
                                    </div>
                                    <Input
                                        ref={inputRef}
                                        type="text"
                                        placeholder="Scan atau ketik kode..."
                                        className="h-14 text-center text-lg"
                                        onKeyDown={handleInputKeyDown}
                                        autoFocus
                                        disabled={scanning}
                                    />
                                    {scanning && (
                                        <div className="flex justify-center">
                                            <RefreshCw className="h-6 w-6 animate-spin text-blue-600" />
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    <div className="aspect-video rounded-lg bg-gray-900">
                                        {/* QR Camera Scanner would go here */}
                                        <div className="flex h-full items-center justify-center text-white">
                                            <div className="text-center">
                                                <Camera className="mx-auto h-16 w-16" />
                                                <p className="mt-2">Kamera QR Scanner</p>
                                                <p className="text-sm text-gray-400">
                                                    (Requires camera permission)
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <p className="text-center text-sm text-muted-foreground">
                                        Arahkan kamera ke QR Code
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Sync Button (when offline with pending scans) */}
                    {pendingCount > 0 && isOnline && (
                        <Button
                            className="w-full"
                            onClick={syncQueue}
                            disabled={syncing}
                        >
                            <RefreshCw className={`mr-2 h-4 w-4 ${syncing ? 'animate-spin' : ''}`} />
                            Sinkronkan {pendingCount} scan tertunda
                        </Button>
                    )}

                    {/* Instructions */}
                    <Card>
                        <CardContent className="p-4">
                            <div className="text-center text-sm text-muted-foreground">
                                <p>Scan QR Code atau tempelkan kartu RFID untuk absensi.</p>
                                <p className="mt-1">
                                    Toleransi keterlambatan: {bootstrap?.settings.late_tolerance_minutes} menit
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            {/* Result Dialog */}
            <Dialog open={showResult} onOpenChange={setShowResult}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {lastScan?.success ? (
                                <CheckCircle2 className="h-6 w-6 text-green-600" />
                            ) : (
                                <XCircle className="h-6 w-6 text-red-600" />
                            )}
                            {lastScan?.success ? 'Berhasil' : 'Gagal'}
                        </DialogTitle>
                        <DialogDescription>
                            {lastScan?.message}
                        </DialogDescription>
                    </DialogHeader>

                    {lastScan?.data && (
                        <div className="space-y-4 py-4">
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                    <User className="h-8 w-8 text-blue-600" />
                                </div>
                                <div>
                                    <div className="text-lg font-semibold">
                                        {lastScan.data.student?.name || lastScan.data.teacher?.name}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {lastScan.data.student?.nis || lastScan.data.teacher?.nip}
                                        {lastScan.data.student?.classroom && (
                                            <span> • {lastScan.data.student.classroom}</span>
                                        )}
                                    </div>
                                </div>
                            </div>

                            <div className="rounded-lg bg-muted p-4">
                                <div className="grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <div className="text-sm text-muted-foreground">
                                            {lastScan.data.action === 'check_in' ? 'Jam Masuk' : 'Jam Pulang'}
                                        </div>
                                        <div className="text-xl font-bold">{lastScan.data.time}</div>
                                    </div>
                                    <div>
                                        <div className="text-sm text-muted-foreground">Status</div>
                                        <div className="text-xl font-bold">
                                            {lastScan.data.late ? (
                                                <span className="text-red-600">
                                                    Terlambat {lastScan.data.late_minutes} mnt
                                                </span>
                                            ) : (
                                                <span className="text-green-600">Tepat Waktu</span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {lastScan.data.late_info && (
                                <div className="rounded-lg border border-red-200 bg-red-50 p-3 dark:border-red-800 dark:bg-red-900/20">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm">Kategori Keterlambatan:</span>
                                        <Badge variant="destructive">
                                            {lastScan.data.late_info.label}
                                        </Badge>
                                    </div>
                                    <div className="mt-2 flex items-center justify-between text-sm">
                                        <span>Poin Pelanggaran:</span>
                                        <span className="font-semibold text-red-600">
                                            +{lastScan.data.late_info.points} poin
                                        </span>
                                    </div>
                                    {lastScan.data.total_violation_points !== undefined && (
                                        <div className="mt-1 flex items-center justify-between text-sm">
                                            <span>Total Akumulasi:</span>
                                            <span className="font-semibold">
                                                {lastScan.data.total_violation_points} poin
                                            </span>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="flex justify-center">
                        <Button onClick={() => setShowResult(false)} className="w-full">
                            OK
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
