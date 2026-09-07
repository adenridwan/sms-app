import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { RefreshCw, KeyRound, ShieldOff, Copy, QrCode, Download, Smartphone } from 'lucide-react';
import {
    loginSecurityApi,
    provisionApi,
    type LoginLogEntry,
    type LoginSecurityUser,
    type ProvisionTokenResponse,
} from '@/services/api';
import { QRCodeSVG } from 'qrcode.react';

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const failureLabel: Record<string, string> = {
    invalid_credentials: 'Email/password salah',
    inactive_account: 'Akun tidak aktif',
    invalid_otp: 'Kode akses tidak valid',
    invalid_token: 'Token QR tidak valid',
};

const methodLabel: Record<string, string> = {
    password: 'Password',
    otp: 'Kode Akses',
    activation: 'Aktivasi Akun',
    provision: 'QR Provisioning',
};

export default function LoginSecurity() {
    const [logs, setLogs] = useState<LoginLogEntry[]>([]);
    const [loadingLogs, setLoadingLogs] = useState(true);
    const [emailFilter, setEmailFilter] = useState('');

    const [userQuery, setUserQuery] = useState('');
    const [users, setUsers] = useState<LoginSecurityUser[]>([]);
    const [searching, setSearching] = useState(false);
    const [issuedCode, setIssuedCode] = useState<{ code: string; name: string; minutes: number } | null>(null);

    // QR Provisioning state
    const [provisionData, setProvisionData] = useState<ProvisionTokenResponse | null>(null);
    const [generatingQr, setGeneratingQr] = useState<string | null>(null);

    const fetchLogs = async (email?: string) => {
        setLoadingLogs(true);
        try {
            const res = await loginSecurityApi.logs(email ? { email } : undefined);
            setLogs(res.data.data?.data ?? []);
        } catch {
            toast.error('Gagal memuat riwayat login');
        } finally {
            setLoadingLogs(false);
        }
    };

    useEffect(() => {
        fetchLogs();
    }, []);

    const searchUsers = async () => {
        if (!userQuery.trim()) return;
        setSearching(true);
        try {
            const res = await loginSecurityApi.users(userQuery.trim());
            setUsers(res.data.data?.data ?? []);
        } catch {
            toast.error('Gagal mencari pengguna');
        } finally {
            setSearching(false);
        }
    };

    const generateOtp = async (user: LoginSecurityUser) => {
        try {
            const res = await loginSecurityApi.generateOtp(user.id);
            const data = res.data.data;
            if (data) {
                setIssuedCode({ code: data.code, name: data.user.full_name, minutes: data.expires_in_minutes });
            }
        } catch {
            toast.error('Gagal membuat kode akses');
        }
    };

    const revokeSessions = async (user: LoginSecurityUser) => {
        if (!confirm(`Cabut semua sesi perangkat milik ${user.full_name}? Semua perangkatnya akan ter-logout.`)) {
            return;
        }
        try {
            const res = await loginSecurityApi.revokeSessions(user.id);
            toast.success(`${res.data.data?.revoked ?? 0} sesi dicabut.`);
        } catch {
            toast.error('Gagal mencabut sesi');
        }
    };

    const generateProvisionQr = async (user: LoginSecurityUser) => {
        setGeneratingQr(user.id);
        try {
            const res = await provisionApi.generate(user.id);
            if (res.data.data) {
                setProvisionData(res.data.data);
            }
        } catch {
            toast.error('Gagal membuat QR provisioning');
        } finally {
            setGeneratingQr(null);
        }
    };

    const downloadQrCode = () => {
        if (!provisionData) return;
        const svg = document.getElementById('provision-qr-svg');
        if (!svg) return;

        const svgData = new XMLSerializer().serializeToString(svg);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();
        img.onload = () => {
            canvas.width = img.width;
            canvas.height = img.height;
            ctx?.drawImage(img, 0, 0);
            const pngUrl = canvas.toDataURL('image/png');
            const link = document.createElement('a');
            link.download = `qr-akses-${provisionData.user.name.replace(/\s+/g, '-').toLowerCase()}.png`;
            link.href = pngUrl;
            link.click();
        };
        img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)));
    };

    return (
        <MainLayout>
            <Head title="Keamanan Login" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Keamanan Login</h1>
                    <p className="text-muted-foreground text-sm">
                        QR provisioning, kode akses sekali-pakai, pencabutan sesi, dan riwayat login.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="h-4 w-4" /> Kode Akses & Sesi Pengguna
                        </CardTitle>
                        <CardDescription>
                            Cari pengguna, lalu pilih aksi: <strong>QR Akses</strong> untuk scan langsung di mobile
                            tanpa ketik password, <strong>Buat Kode</strong> untuk kode 6 digit yang dibacakan/dikirim,
                            atau <strong>Cabut Sesi</strong> untuk logout paksa. QR dan kode berlaku 15 menit, sekali pakai.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex gap-2">
                            <Input
                                placeholder="Cari nama atau email pengguna…"
                                value={userQuery}
                                onChange={(e) => setUserQuery(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && searchUsers()}
                            />
                            <Button onClick={searchUsers} disabled={searching}>
                                {searching ? <RefreshCw className="h-4 w-4 animate-spin" /> : 'Cari'}
                            </Button>
                        </div>

                        {issuedCode && (
                            <div className="rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950">
                                <p className="text-sm">
                                    Kode akses untuk <strong>{issuedCode.name}</strong> (berlaku {issuedCode.minutes} menit,
                                    sekali pakai):
                                </p>
                                <div className="mt-2 flex items-center gap-3">
                                    <code className="font-mono text-2xl tracking-widest">{issuedCode.code}</code>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => {
                                            navigator.clipboard.writeText(issuedCode.code);
                                            toast.success('Kode disalin');
                                        }}
                                    >
                                        <Copy className="mr-1 h-3 w-3" /> Salin
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => setIssuedCode(null)}>
                                        Tutup
                                    </Button>
                                </div>
                            </div>
                        )}

                        {provisionData && (
                            <div className="rounded-md border border-emerald-300 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-950">
                                <div className="flex items-start gap-4">
                                    <div className="rounded-lg bg-white p-3">
                                        <QRCodeSVG
                                            id="provision-qr-svg"
                                            value={provisionData.qr_content}
                                            size={160}
                                            level="M"
                                            includeMargin={false}
                                        />
                                    </div>
                                    <div className="flex-1 space-y-2">
                                        <div className="flex items-center gap-2">
                                            <Smartphone className="h-4 w-4 text-emerald-600" />
                                            <p className="font-medium text-emerald-800 dark:text-emerald-200">
                                                QR Akses untuk {provisionData.user.name}
                                            </p>
                                        </div>
                                        <p className="text-sm text-emerald-700 dark:text-emerald-300">
                                            Scan QR ini di aplikasi mobile untuk login tanpa ketik password.
                                            Berlaku <strong>{provisionData.expires_in_minutes} menit</strong>, sekali pakai.
                                        </p>
                                        <p className="text-xs text-emerald-600 dark:text-emerald-400">
                                            Kedaluwarsa: {new Date(provisionData.expires_at).toLocaleString('id-ID')}
                                        </p>
                                        <div className="flex gap-2 pt-2">
                                            <Button size="sm" variant="outline" onClick={downloadQrCode}>
                                                <Download className="mr-1 h-3 w-3" /> Unduh QR
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => {
                                                    navigator.clipboard.writeText(provisionData.qr_content);
                                                    toast.success('Link disalin');
                                                }}
                                            >
                                                <Copy className="mr-1 h-3 w-3" /> Salin Link
                                            </Button>
                                            <Button size="sm" variant="ghost" onClick={() => setProvisionData(null)}>
                                                Tutup
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {users.length > 0 && (
                            <div className="divide-y rounded-md border">
                                {users.map((u) => (
                                    <div key={u.id} className="flex items-center justify-between gap-4 p-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <p className="truncate font-medium">{u.full_name}</p>
                                                {u.status === 'pending' && (
                                                    <Badge className="shrink-0 bg-amber-500 hover:bg-amber-500">
                                                        Menunggu aktivasi
                                                    </Badge>
                                                )}
                                                {(u.status === 'inactive' || u.status === 'suspended') && (
                                                    <Badge variant="destructive" className="shrink-0">
                                                        {u.status === 'suspended' ? 'Ditangguhkan' : 'Nonaktif'}
                                                    </Badge>
                                                )}
                                            </div>
                                            <p className="text-muted-foreground truncate text-xs">{u.email}</p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => generateProvisionQr(u)}
                                                disabled={generatingQr === u.id || u.status !== 'active'}
                                                title={u.status !== 'active' ? 'Hanya akun aktif yang bisa di-provision' : 'Generate QR untuk login di mobile'}
                                            >
                                                {generatingQr === u.id ? (
                                                    <RefreshCw className="mr-1 h-3 w-3 animate-spin" />
                                                ) : (
                                                    <QrCode className="mr-1 h-3 w-3" />
                                                )}
                                                QR Akses
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => generateOtp(u)}>
                                                <KeyRound className="mr-1 h-3 w-3" /> Buat Kode
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={() => revokeSessions(u)}>
                                                <ShieldOff className="mr-1 h-3 w-3" /> Cabut Sesi
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Riwayat Login</CardTitle>
                        <CardDescription>Percobaan login terbaru, termasuk yang gagal.</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex gap-2">
                            <Input
                                placeholder="Filter email…"
                                value={emailFilter}
                                onChange={(e) => setEmailFilter(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && fetchLogs(emailFilter)}
                            />
                            <Button variant="outline" onClick={() => fetchLogs(emailFilter)}>
                                <RefreshCw className="mr-1 h-4 w-4" /> Muat Ulang
                            </Button>
                        </div>

                        {loadingLogs ? (
                            <p className="text-muted-foreground text-sm">Memuat…</p>
                        ) : logs.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Belum ada riwayat login.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="text-muted-foreground border-b text-left">
                                        <tr>
                                            <th className="p-2 font-medium">Waktu</th>
                                            <th className="p-2 font-medium">Email</th>
                                            <th className="p-2 font-medium">Metode</th>
                                            <th className="p-2 font-medium">Status</th>
                                            <th className="p-2 font-medium">IP</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {logs.map((log) => (
                                            <tr key={log.id}>
                                                <td className="whitespace-nowrap p-2">{formatDateTime(log.created_at)}</td>
                                                <td className="p-2">{log.user?.full_name ?? log.email ?? '—'}</td>
                                                <td className="p-2">
                                                    <Badge variant="outline">{methodLabel[log.method] ?? log.method}</Badge>
                                                </td>
                                                <td className="p-2">
                                                    {log.successful ? (
                                                        <Badge>Berhasil</Badge>
                                                    ) : (
                                                        <Badge variant="destructive">
                                                            {failureLabel[log.failure_reason ?? ''] ?? 'Gagal'}
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="text-muted-foreground p-2">{log.ip_address ?? '—'}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}
