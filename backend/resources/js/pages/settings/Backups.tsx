import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
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
import { toast } from 'sonner';
import {
    DatabaseBackup,
    Download,
    Trash2,
    RefreshCw,
    Info,
    Lock,
    Unlock,
    KeyRound,
    CheckCircle2,
    XCircle,
    ShieldAlert,
    Tag,
} from 'lucide-react';
import { backupsApi, dbConnectionApi, type DbConnectionInput } from '@/services/api';
import type { BackupFile, DbConnectionInfo, PageProps } from '@/types';

function downloadBlob(data: Blob, filename: string) {
    const url = URL.createObjectURL(data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

function formatBytes(bytes: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let value = bytes;
    let i = 0;
    while (value >= 1024 && i < units.length - 1) {
        value /= 1024;
        i++;
    }
    return `${value.toFixed(2)} ${units[i]}`;
}

interface DbConnectionForm {
    host: string;
    port: string;
    database: string;
    username: string;
    password: string;
}

const emptyDbForm: DbConnectionForm = {
    host: '',
    port: '5432',
    database: '',
    username: '',
    password: '',
};

export default function SettingsBackups() {
    // ---------- Versi Aplikasi ----------
    const { app } = usePage<PageProps>().props;
    const [appVersion, setAppVersion] = useState(app.version);
    const [savingVersion, setSavingVersion] = useState(false);

    const handleSaveAppVersion = async () => {
        if (!appVersion.trim()) {
            toast.error('Versi aplikasi wajib diisi');
            return;
        }
        setSavingVersion(true);
        try {
            await dbConnectionApi.updateAppVersion(appVersion.trim());
            toast.success('Versi aplikasi berhasil disimpan');
            // Partial reload — footer (MainLayout) baca app.version dari
            // shared prop yang sama, langsung ikut ter-update tanpa navigasi.
            router.reload({ only: ['app'] });
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan versi aplikasi'));
        } finally {
            setSavingVersion(false);
        }
    };

    // ---------- Backup list ----------
    const [backups, setBackups] = useState<BackupFile[]>([]);
    const [loading, setLoading] = useState(false);
    const [creating, setCreating] = useState(false);
    const [downloadingFile, setDownloadingFile] = useState<string | null>(null);
    const [deletingBackup, setDeletingBackup] = useState<BackupFile | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchBackups = useCallback(async () => {
        setLoading(true);
        try {
            const response = await backupsApi.list();
            setBackups(response.data.data ?? []);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal memuat daftar backup'));
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchBackups();
    }, [fetchBackups]);

    const handleCreate = async () => {
        setCreating(true);
        try {
            await backupsApi.create();
            toast.success('Backup berhasil dibuat');
            fetchBackups();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal membuat backup'));
        } finally {
            setCreating(false);
        }
    };

    const handleDownload = async (backup: BackupFile) => {
        setDownloadingFile(backup.filename);
        try {
            const response = await backupsApi.download(backup.filename);
            downloadBlob(response.data, backup.filename);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengunduh backup'));
        } finally {
            setDownloadingFile(null);
        }
    };

    const handleDelete = async () => {
        if (!deletingBackup || deleting) return;
        setDeleting(true);
        try {
            await backupsApi.delete(deletingBackup.filename);
            toast.success('Backup berhasil dihapus');
            setDeletingBackup(null);
            fetchBackups();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus backup'));
        } finally {
            setDeleting(false);
        }
    };

    // ---------- Koneksi Database Aplikasi ----------
    const [dbAccessConfigured, setDbAccessConfigured] = useState<boolean | null>(null);
    const [dbUnlockInput, setDbUnlockInput] = useState('');
    const [dbUnlocking, setDbUnlocking] = useState(false);
    const [dbAccessPassword, setDbAccessPassword] = useState<string | null>(null);
    const [dbConnection, setDbConnection] = useState<DbConnectionInfo | null>(null);

    const [dbEditing, setDbEditing] = useState(false);
    const [dbForm, setDbForm] = useState(emptyDbForm);
    const [dbTesting, setDbTesting] = useState(false);
    const [dbTestResult, setDbTestResult] = useState<{ ok: boolean; message: string } | null>(null);
    const [dbSaving, setDbSaving] = useState(false);

    const [accessDialogOpen, setAccessDialogOpen] = useState(false);
    const [accessForm, setAccessForm] = useState({ current_password: '', new_password: '', new_password_confirmation: '' });
    const [savingAccessPassword, setSavingAccessPassword] = useState(false);

    const fetchAccessStatus = useCallback(async () => {
        try {
            const response = await dbConnectionApi.accessStatus();
            setDbAccessConfigured(response.data.data.configured);
        } catch {
            toast.error('Gagal memuat status password akses koneksi database');
        }
    }, []);

    useEffect(() => {
        fetchAccessStatus();
    }, [fetchAccessStatus]);

    const lockConnection = () => {
        setDbAccessPassword(null);
        setDbConnection(null);
        setDbEditing(false);
        setDbTestResult(null);
    };

    const handleUnlock = async () => {
        if (!dbUnlockInput.trim()) return;
        setDbUnlocking(true);
        try {
            const response = await dbConnectionApi.reveal(dbUnlockInput);
            setDbConnection(response.data.data);
            setDbAccessPassword(dbUnlockInput);
            setDbUnlockInput('');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Password akses salah'));
        } finally {
            setDbUnlocking(false);
        }
    };

    const startEditConnection = () => {
        if (!dbConnection) return;
        setDbForm({
            host: dbConnection.host,
            port: String(dbConnection.port),
            database: dbConnection.database,
            username: dbConnection.username,
            password: '',
        });
        setDbTestResult(null);
        setDbEditing(true);
    };

    const buildDbPayload = (): DbConnectionInput => ({
        host: dbForm.host.trim(),
        port: Number(dbForm.port) || 5432,
        database: dbForm.database.trim(),
        username: dbForm.username.trim(),
        password: dbForm.password || undefined,
    });

    const handleTestConnection = async () => {
        if (!dbAccessPassword) return;
        setDbTesting(true);
        setDbTestResult(null);
        try {
            await dbConnectionApi.test(dbAccessPassword, buildDbPayload());
            setDbTestResult({ ok: true, message: 'Koneksi berhasil.' });
        } catch (error) {
            if ((error as { response?: { status?: number } })?.response?.status === 403) {
                toast.error('Password akses salah — silakan buka kunci lagi');
                lockConnection();
                return;
            }
            setDbTestResult({ ok: false, message: getErrorMessage(error, 'Koneksi gagal') });
        } finally {
            setDbTesting(false);
        }
    };

    const handleSaveConnection = async () => {
        if (!dbAccessPassword) return;
        setDbSaving(true);
        try {
            const response = await dbConnectionApi.update(dbAccessPassword, buildDbPayload());
            toast.success(response.data.message ?? 'Koneksi database berhasil diubah', { duration: 8000 });
            // Sengaja TIDAK langsung memanggil reveal() lagi di sini — kalau
            // database yang dituju benar-benar berbeda, sesi saat ini bisa
            // langsung berakhir (data sesi/token ada di database lama).
            // Kunci lagi & minta buka manual supaya jelas kalau masih login.
            lockConnection();
        } catch (error) {
            if ((error as { response?: { status?: number } })?.response?.status === 403) {
                toast.error('Password akses salah — silakan buka kunci lagi');
                lockConnection();
                return;
            }
            toast.error(getErrorMessage(error, 'Gagal menyimpan koneksi database'));
        } finally {
            setDbSaving(false);
        }
    };

    const openAccessDialog = () => {
        setAccessForm({ current_password: '', new_password: '', new_password_confirmation: '' });
        setAccessDialogOpen(true);
    };

    const handleSaveAccessPassword = async () => {
        if (accessForm.new_password.length < 8) {
            toast.error('Password baru minimal 8 karakter');
            return;
        }
        if (accessForm.new_password !== accessForm.new_password_confirmation) {
            toast.error('Konfirmasi password baru tidak cocok');
            return;
        }
        setSavingAccessPassword(true);
        try {
            await dbConnectionApi.setAccessPassword(accessForm);
            toast.success(dbAccessConfigured ? 'Password akses berhasil diubah' : 'Password akses berhasil dibuat');
            setAccessDialogOpen(false);
            setDbAccessConfigured(true);
            lockConnection();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan password akses'));
        } finally {
            setSavingAccessPassword(false);
        }
    };

    return (
        <MainLayout title="Backup Database">
            <Head title="Pengaturan - Backup Database" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Backup Database</h1>
                        <p className="text-muted-foreground">Backup manual dan terjadwal — khusus Super Admin</p>
                    </div>
                    <Button onClick={handleCreate} disabled={creating}>
                        {creating ? (
                            <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                        ) : (
                            <DatabaseBackup className="mr-2 h-4 w-4" />
                        )}
                        Backup Sekarang
                    </Button>
                </div>

                <Alert>
                    <Info className="h-4 w-4" />
                    <AlertTitle>Backup terjadwal</AlertTitle>
                    <AlertDescription>
                        Selain backup manual di sini, sistem menjalankan <code>backup:run</code> otomatis setiap hari
                        (lihat <code>routes/console.php</code>). Backup lebih lama dari masa retensi
                        (<code>BACKUP_RETENTION_DAYS</code>, default 14 hari) otomatis dihapus.
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Backup</CardTitle>
                        <CardDescription>
                            {backups.length ? `${backups.length} file backup tersimpan` : 'Belum ada backup'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex justify-end">
                            <Button variant="outline" size="icon" onClick={fetchBackups} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : backups.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Belum ada backup. Klik "Backup Sekarang" untuk membuat yang pertama.
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nama File</TableHead>
                                            <TableHead className="w-[120px]">Ukuran</TableHead>
                                            <TableHead className="w-[200px]">Dibuat</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {backups.map((backup) => (
                                            <TableRow key={backup.filename}>
                                                <TableCell className="font-mono text-sm">{backup.filename}</TableCell>
                                                <TableCell className="text-muted-foreground">{formatBytes(backup.size)}</TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {new Date(backup.created_at).toLocaleString('id-ID')}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => handleDownload(backup)}
                                                            disabled={downloadingFile === backup.filename}
                                                        >
                                                            {downloadingFile === backup.filename ? (
                                                                <RefreshCw className="h-4 w-4 animate-spin" />
                                                            ) : (
                                                                <Download className="h-4 w-4" />
                                                            )}
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingBackup(backup)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ---------- Koneksi Database Aplikasi ---------- */}
                <Card className="border-amber-300 dark:border-amber-800">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <ShieldAlert className="h-5 w-5 text-amber-600" />
                            Koneksi Database Aplikasi
                        </CardTitle>
                        <CardDescription>
                            Lihat & ubah ke mana .env (DB_HOST/DB_DATABASE/dst) mengarah. Area sensitif — dikunci
                            password akses terpisah dari password login.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {dbAccessConfigured === null ? (
                            <div className="py-4 text-center text-muted-foreground">Memuat...</div>
                        ) : !dbAccessConfigured ? (
                            <Alert>
                                <KeyRound className="h-4 w-4" />
                                <AlertTitle>Password akses belum dibuat</AlertTitle>
                                <AlertDescription className="space-y-2">
                                    <p>Buat password akses dulu sebelum bisa melihat/mengubah koneksi database.</p>
                                    <Button size="sm" onClick={openAccessDialog}>
                                        Buat Password Akses
                                    </Button>
                                </AlertDescription>
                            </Alert>
                        ) : !dbConnection ? (
                            <div className="flex flex-wrap items-end gap-2">
                                <div className="max-w-xs flex-1 space-y-2">
                                    <Label htmlFor="db-unlock">Password Akses</Label>
                                    <Input
                                        id="db-unlock"
                                        type="password"
                                        value={dbUnlockInput}
                                        onChange={(e) => setDbUnlockInput(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleUnlock()}
                                        placeholder="Masukkan password akses"
                                    />
                                </div>
                                <Button onClick={handleUnlock} disabled={dbUnlocking || !dbUnlockInput.trim()}>
                                    {dbUnlocking ? (
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    ) : (
                                        <Unlock className="mr-2 h-4 w-4" />
                                    )}
                                    Buka
                                </Button>
                                <Button variant="ghost" size="sm" onClick={openAccessDialog}>
                                    Lupa / ubah password akses
                                </Button>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <Alert className="flex-1 border-green-300 dark:border-green-800">
                                        <Unlock className="h-4 w-4" />
                                        <AlertTitle>Terbuka</AlertTitle>
                                        <AlertDescription>
                                            Password aktual tidak pernah ditampilkan, hanya host/port/nama
                                            database/username.
                                        </AlertDescription>
                                    </Alert>
                                    <Button variant="outline" size="sm" className="ml-2" onClick={lockConnection}>
                                        <Lock className="mr-2 h-4 w-4" />
                                        Kunci Lagi
                                    </Button>
                                </div>

                                {!dbEditing ? (
                                    <div className="grid grid-cols-2 gap-4 rounded-md border p-4 text-sm sm:grid-cols-4">
                                        <div>
                                            <div className="text-muted-foreground">Host</div>
                                            <div className="font-mono">{dbConnection.host}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">Port</div>
                                            <div className="font-mono">{dbConnection.port}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">Database</div>
                                            <div className="font-mono">{dbConnection.database}</div>
                                        </div>
                                        <div>
                                            <div className="text-muted-foreground">Username</div>
                                            <div className="font-mono">{dbConnection.username}</div>
                                        </div>
                                        <div className="col-span-2 sm:col-span-4">
                                            <Button size="sm" onClick={startEditConnection}>
                                                Ubah Koneksi
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="space-y-4 rounded-md border p-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="db-host">Host</Label>
                                                <Input
                                                    id="db-host"
                                                    value={dbForm.host}
                                                    onChange={(e) => setDbForm({ ...dbForm, host: e.target.value })}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="db-port">Port</Label>
                                                <Input
                                                    id="db-port"
                                                    type="number"
                                                    value={dbForm.port}
                                                    onChange={(e) => setDbForm({ ...dbForm, port: e.target.value })}
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="db-database">Nama Database</Label>
                                            <Input
                                                id="db-database"
                                                value={dbForm.database}
                                                onChange={(e) => setDbForm({ ...dbForm, database: e.target.value })}
                                                placeholder="Contoh: sms_enterprise"
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="db-username">Username</Label>
                                                <Input
                                                    id="db-username"
                                                    value={dbForm.username}
                                                    onChange={(e) => setDbForm({ ...dbForm, username: e.target.value })}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="db-password">Password DB</Label>
                                                <Input
                                                    id="db-password"
                                                    type="password"
                                                    value={dbForm.password}
                                                    onChange={(e) => setDbForm({ ...dbForm, password: e.target.value })}
                                                    placeholder="Kosongkan jika tidak berubah"
                                                />
                                            </div>
                                        </div>

                                        {dbTestResult && (
                                            <Alert
                                                className={
                                                    dbTestResult.ok
                                                        ? 'border-green-300 dark:border-green-800'
                                                        : 'border-red-300 dark:border-red-800'
                                                }
                                            >
                                                {dbTestResult.ok ? (
                                                    <CheckCircle2 className="h-4 w-4" />
                                                ) : (
                                                    <XCircle className="h-4 w-4" />
                                                )}
                                                <AlertTitle>{dbTestResult.ok ? 'Koneksi berhasil' : 'Koneksi gagal'}</AlertTitle>
                                                <AlertDescription>{dbTestResult.message}</AlertDescription>
                                            </Alert>
                                        )}

                                        <div className="flex flex-wrap justify-end gap-2">
                                            <Button variant="outline" onClick={() => setDbEditing(false)} disabled={dbSaving}>
                                                Batal
                                            </Button>
                                            <Button variant="secondary" onClick={handleTestConnection} disabled={dbTesting || dbSaving}>
                                                {dbTesting && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                                Cek Koneksi
                                            </Button>
                                            <Button onClick={handleSaveConnection} disabled={dbSaving || dbTesting}>
                                                {dbSaving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                                Simpan
                                            </Button>
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Simpan selalu menguji koneksi baru dulu — .env tidak akan diubah kalau
                                            koneksinya gagal. <strong>Kalau nama database yang dituju berbeda</strong>,
                                            sesi login Anda saat ini bisa langsung berakhir (data sesi ada di database
                                            lama) — siapkan untuk login ulang.
                                        </p>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* ---------- Versi Aplikasi ---------- */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Tag className="h-5 w-5" />
                            Versi Aplikasi
                        </CardTitle>
                        <CardDescription>Ditampilkan di footer setiap halaman.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-end gap-2">
                            <div className="max-w-xs flex-1 space-y-2">
                                <Label htmlFor="app-version">Versi</Label>
                                <Input
                                    id="app-version"
                                    value={appVersion}
                                    onChange={(e) => setAppVersion(e.target.value)}
                                    placeholder="Contoh: 1.0.0"
                                />
                            </div>
                            <Button onClick={handleSaveAppVersion} disabled={savingVersion}>
                                {savingVersion && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                                Simpan
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Delete Backup Dialog */}
            <AlertDialog open={!!deletingBackup} onOpenChange={(open) => !open && !deleting && setDeletingBackup(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Backup</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus{' '}
                            <span className="font-mono">{deletingBackup?.filename}</span>? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingBackup(null)} disabled={deleting}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                handleDelete();
                            }}
                            disabled={deleting}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Setel/Ubah Password Akses Dialog */}
            <Dialog open={accessDialogOpen} onOpenChange={setAccessDialogOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{dbAccessConfigured ? 'Ubah Password Akses' : 'Buat Password Akses'}</DialogTitle>
                        <DialogDescription>
                            Password ini terpisah dari password login — dipakai khusus untuk melihat/mengubah koneksi
                            database.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {dbAccessConfigured && (
                            <div className="space-y-2">
                                <Label htmlFor="access-current">Password Akses Saat Ini</Label>
                                <Input
                                    id="access-current"
                                    type="password"
                                    value={accessForm.current_password}
                                    onChange={(e) => setAccessForm({ ...accessForm, current_password: e.target.value })}
                                />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="access-new">Password Akses Baru</Label>
                            <Input
                                id="access-new"
                                type="password"
                                value={accessForm.new_password}
                                onChange={(e) => setAccessForm({ ...accessForm, new_password: e.target.value })}
                                placeholder="Minimal 8 karakter"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="access-confirm">Ulangi Password Akses Baru</Label>
                            <Input
                                id="access-confirm"
                                type="password"
                                value={accessForm.new_password_confirmation}
                                onChange={(e) =>
                                    setAccessForm({ ...accessForm, new_password_confirmation: e.target.value })
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAccessDialogOpen(false)} disabled={savingAccessPassword}>
                            Batal
                        </Button>
                        <Button onClick={handleSaveAccessPassword} disabled={savingAccessPassword}>
                            {savingAccessPassword && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Simpan
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
