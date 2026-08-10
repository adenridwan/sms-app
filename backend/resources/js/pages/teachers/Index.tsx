import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { qrCodeApi } from '@/services/attendance';
import { teachersApi, type ImportResult } from '@/services/api';
import { printAttendanceCardsWithTemplate } from '@/lib/attendanceCardPrint';
import type { PageProps } from '@/types';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    Printer,
    Download,
    Upload,
    FileSpreadsheet,
    Loader2,
    RefreshCw,
} from 'lucide-react';
import type { Teacher, PaginatedResponse } from '@/types';

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

interface Props {
    teachers: PaginatedResponse<Teacher>;
    filters: {
        search?: string;
        status?: string;
        employment_status?: string;
    };
}

export default function TeachersIndex({ teachers, filters }: Props) {
    const { tenant, auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [printingId, setPrintingId] = useState<string | null>(null);
    const [refreshing, setRefreshing] = useState(false);

    // Import/export hanya untuk yang memang berizin — guru hanya punya
    // teachers.view-own, siswa & orang tua tidak punya keduanya, jadi
    // tombolnya tidak ditampilkan alih-alih berujung 403.
    const isSuperAdmin = auth?.user?.user_type === 'super_admin';
    const permissions = auth?.user?.permissions ?? [];
    const canExport = isSuperAdmin || permissions.includes('teachers.view');
    const canImport = isSuperAdmin || permissions.includes('teachers.create');

    // Export & import (pola mengikuti menu Kelas)
    const [exporting, setExporting] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const handleExport = async (format: 'xlsx' | 'csv') => {
        setExporting(true);
        try {
            const response = await teachersApi.export(format);
            downloadBlob(response.data, `guru.${format}`);
            toast.success('Data guru berhasil diexport');
        } catch {
            toast.error('Gagal export data guru');
        } finally {
            setExporting(false);
        }
    };

    const handleDownloadTemplate = async (format: 'xlsx' | 'csv') => {
        try {
            const response = await teachersApi.template(format);
            downloadBlob(response.data, `template-import-guru.${format}`);
            toast.success('Template berhasil didownload');
        } catch {
            toast.error('Gagal download template');
        }
    };

    const handleImport = async () => {
        if (!importFile) {
            toast.error('Pilih file terlebih dahulu');
            return;
        }

        setImporting(true);
        setImportResult(null);
        try {
            const response = await teachersApi.import(importFile);
            const result = response.data.data;
            setImportResult(result);
            toast.success(
                `Import selesai: ${result.created} guru ditambahkan, ${result.updated} diperbarui`
            );
            // Daftar guru dirender server-side (Inertia), jadi disegarkan
            // lewat router, bukan state lokal.
            router.reload({ only: ['teachers'] });
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal import data guru'));
        } finally {
            setImporting(false);
        }
    };

    const closeImportDialog = () => {
        setImportOpen(false);
        setImportFile(null);
        setImportResult(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const handlePrintCard = async (teacher: Teacher) => {
        setPrintingId(teacher.id);
        try {
            const response = await qrCodeApi.teacher(teacher.id);
            const qr = response.data.data;
            if (!qr) {
                toast.error('Data QR guru tidak ditemukan');
                return;
            }

            const opened = await printAttendanceCardsWithTemplate('teacher', [
                {
                    ...qr,
                    employment_status_label: qr.employment_status_label ?? teacher.employment_status_label ?? null,
                },
            ], {
                title: `Kartu Guru - ${qr.name}`,
                schoolName: tenant?.name,
                schoolLogo: tenant?.logo,
            });

            if (!opened) {
                toast.error('Popup blocker mungkin aktif. Izinkan popup untuk mencetak.');
            }
        } catch {
            toast.error('Gagal memuat kartu guru');
        } finally {
            setPrintingId(null);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/teachers', { search }, { preserveState: true });
    };

    // Ambil ulang hanya prop `teachers` dari server — filter, pencarian, dan
    // halaman yang sedang aktif tetap seperti apa adanya.
    const handleRefresh = () => {
        setRefreshing(true);
        router.reload({
            only: ['teachers'],
            onFinish: () => setRefreshing(false),
        });
    };

    const getStatusBadge = (teacher: Teacher) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            inactive: 'destructive',
            on_leave: 'outline',
            retired: 'secondary',
            terminated: 'destructive',
        };
        return (
            <Badge variant={variants[teacher.status] || 'default'}>
                {teacher.status_label}
            </Badge>
        );
    };

    return (
        <MainLayout>
            <Head title="Data Guru" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Data Guru</h1>
                        <p className="text-muted-foreground">
                            Kelola data guru sekolah
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={handleRefresh} disabled={refreshing}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        {canExport && (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" disabled={exporting}>
                                        <Download className="mr-2 h-4 w-4" />
                                        Export
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={() => handleExport('xlsx')}>
                                        Excel (.xlsx)
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => handleExport('csv')}>
                                        CSV (.csv)
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        )}
                        {canImport && (
                            <Button variant="outline" onClick={() => setImportOpen(true)}>
                                <Upload className="mr-2 h-4 w-4" />
                                Import
                            </Button>
                        )}
                        <Button asChild>
                            <Link href="/teachers/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Guru
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Guru</CardTitle>
                        <CardDescription>
                            Total {teachers.meta?.total || 0} guru terdaftar
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari NIP, NUPTK, nama, atau email..."
                                        className="pl-8"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </div>
                                <Button type="submit" variant="secondary">
                                    Cari
                                </Button>
                            </form>
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>NIP</TableHead>
                                        <TableHead>Nama Lengkap</TableHead>
                                        <TableHead>No. HP</TableHead>
                                        <TableHead>Kepegawaian</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {teachers.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                                                Tidak ada data guru
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        teachers.data.map((teacher) => (
                                            <TableRow key={teacher.id}>
                                                <TableCell className="font-medium">{teacher.nip || '-'}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{teacher.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">{teacher.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{teacher.phone || '-'}</TableCell>
                                                <TableCell>{teacher.employment_status_label}</TableCell>
                                                <TableCell>{getStatusBadge(teacher)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/teachers/${teacher.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Lihat Detail
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/teachers/${teacher.id}/edit`}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                disabled={printingId === teacher.id}
                                                                onClick={() => handlePrintCard(teacher)}
                                                            >
                                                                <Printer className="mr-2 h-4 w-4" />
                                                                Cetak Kartu
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {teachers.meta && teachers.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {teachers.meta.from} - {teachers.meta.to} dari {teachers.meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    {teachers.links?.prev && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(teachers.links!.prev!)}
                                        >
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {teachers.links?.next && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(teachers.links!.next!)}
                                        >
                                            Selanjutnya
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Import Dialog */}
            <Dialog open={importOpen} onOpenChange={(open) => (open ? setImportOpen(true) : closeImportDialog())}>
                <DialogContent className="max-h-[85vh] max-w-md overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Import Guru</DialogTitle>
                        <DialogDescription>
                            Upload file CSV atau Excel sesuai template. Guru dengan NIP yang sudah
                            terdaftar akan diperbarui, bukan digandakan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <Alert>
                            <FileSpreadsheet className="h-4 w-4" />
                            <AlertTitle>Template Import</AlertTitle>
                            <AlertDescription>
                                <p className="mb-2">
                                    Kolom wajib: nama_depan, email, no_hp, nip, jenis_kelamin,
                                    tanggal_lahir.
                                </p>
                                <p className="mb-2 text-xs text-muted-foreground">
                                    Jenis kelamin diisi L atau P. Tanggal memakai format
                                    YYYY-MM-DD atau DD/MM/YYYY. Tanggal lahir dipakai sebagai
                                    password awal guru.
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleDownloadTemplate('xlsx')}
                                    >
                                        <Download className="mr-2 h-4 w-4" />
                                        Excel
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => handleDownloadTemplate('csv')}
                                    >
                                        <Download className="mr-2 h-4 w-4" />
                                        CSV
                                    </Button>
                                </div>
                            </AlertDescription>
                        </Alert>
                        <div className="space-y-2">
                            <Label htmlFor="teacher-import-file">File Import</Label>
                            <Input
                                id="teacher-import-file"
                                ref={fileInputRef}
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                        {importResult && (
                            <Alert variant={importResult.errors.length > 0 ? 'destructive' : 'default'}>
                                <AlertTitle>Hasil Import</AlertTitle>
                                <AlertDescription>
                                    <p>
                                        {importResult.created} guru ditambahkan,{' '}
                                        {importResult.updated} guru diperbarui
                                    </p>
                                    {importResult.errors.length > 0 && (
                                        <ul className="mt-2 max-h-40 list-disc space-y-1 overflow-y-auto pl-4 text-xs">
                                            {importResult.errors.map((message, index) => (
                                                <li key={index}>{message}</li>
                                            ))}
                                        </ul>
                                    )}
                                </AlertDescription>
                            </Alert>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeImportDialog} disabled={importing}>
                            Tutup
                        </Button>
                        <Button onClick={handleImport} disabled={importing || !importFile}>
                            {importing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
