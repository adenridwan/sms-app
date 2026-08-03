import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { RefreshCw, KeyRound, ShieldOff, Copy } from 'lucide-react';
import { loginSecurityApi, usersApi, type LoginLogEntry } from '@/services/api';
import type { User } from '@/types';

const formatDateTime = (value: string) =>
    new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const failureLabel: Record<string, string> = {
    invalid_credentials: 'Email/password salah',
    inactive_account: 'Akun tidak aktif',
    invalid_otp: 'Kode akses tidak valid',
};

export default function LoginSecurity() {
    const [logs, setLogs] = useState<LoginLogEntry[]>([]);
    const [loadingLogs, setLoadingLogs] = useState(true);
    const [emailFilter, setEmailFilter] = useState('');

    const [userQuery, setUserQuery] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [searching, setSearching] = useState(false);
    const [issuedCode, setIssuedCode] = useState<{ code: string; name: string; minutes: number } | null>(null);

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
            const res = await usersApi.list({ search: userQuery.trim(), per_page: 10 });
            setUsers(res.data.data?.data ?? []);
        } catch {
            toast.error('Gagal mencari pengguna');
        } finally {
            setSearching(false);
        }
    };

    const generateOtp = async (user: User) => {
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

    const revokeSessions = async (user: User) => {
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

    return (
        <MainLayout>
            <Head title="Keamanan Login" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Keamanan Login</h1>
                    <p className="text-muted-foreground text-sm">
                        Riwayat percobaan login, kode akses sekali-pakai, dan pencabutan sesi perangkat.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <KeyRound className="h-4 w-4" /> Kode Akses & Sesi Pengguna
                        </CardTitle>
                        <CardDescription>
                            Cari pengguna, lalu buat kode akses sekali-pakai untuk membantunya masuk di aplikasi
                            mobile (mis. lupa password). Bacakan kode ke pengguna lewat kanal terpercaya — kode
                            hanya ditampilkan sekali di sini.
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

                        {users.length > 0 && (
                            <div className="divide-y rounded-md border">
                                {users.map((u) => (
                                    <div key={u.id} className="flex items-center justify-between gap-4 p-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{u.full_name}</p>
                                            <p className="text-muted-foreground truncate text-xs">{u.email}</p>
                                        </div>
                                        <div className="flex shrink-0 gap-2">
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
                                                    <Badge variant="outline">{log.method === 'otp' ? 'Kode Akses' : 'Password'}</Badge>
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
