import { Head, Link, router, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { toast } from 'sonner';
import { qrCodeApi } from '@/services/attendance';
import { studentsApi, type ImportResult } from '@/services/api';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    Trash2,
    Download,
    Upload,
    Printer,
    Camera,
    FileSpreadsheet,
    Loader2,
} from 'lucide-react';
import type { Student, PaginatedResponse } from '@/types';

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
    students: PaginatedResponse<Student>;
    filters: {
        search?: string;
        status?: string;
        gender?: string;
    };
}

export default function StudentsIndex({ students, filters }: Props) {
    const { tenant, auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [printingId, setPrintingId] = useState<string | null>(null);

    // Import/export hanya untuk yang memang berizin (admin & tata usaha pada
    // seeder bawaan) — guru, wali kelas, siswa, dan orang tua tidak punya
    // students.export/import, jadi tombolnya tidak ditampilkan sama sekali
    // alih-alih memunculkan tombol yang berujung 403.
    const isSuperAdmin = auth?.user?.user_type === 'super_admin';
    const permissions = auth?.user?.permissions ?? [];
    const canExport = isSuperAdmin || permissions.includes('students.export');
    const canImport = isSuperAdmin || permissions.includes('students.import');

    const [exporting, setExporting] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const importInputRef = useRef<HTMLInputElement>(null);
    const [photoStudent, setPhotoStudent] = useState<Student | null>(null);
    const [photoPreview, setPhotoPreview] = useState<string | null>(null);
    const [photoUploading, setPhotoUploading] = useState(false);
    const photoInputRef = useRef<HTMLInputElement>(null);

    const openPhotoDialog = (student: Student) => {
        setPhotoStudent(student);
        setPhotoPreview(student.photo_url ?? null);
    };

    const handlePhotoSelect = async (file: File) => {
        if (!photoStudent) return;

        setPhotoUploading(true);
        try {
            const response = await studentsApi.uploadPhoto(photoStudent.id, file);
            setPhotoPreview(response.data.data?.photo_url ?? null);
            toast.success('Foto berhasil diperbarui');
            router.reload({ only: ['students'] });
        } catch {
            toast.error('Gagal mengunggah foto');
        } finally {
            setPhotoUploading(false);
        }
    };

    const handlePhotoDelete = async () => {
        if (!photoStudent) return;

        setPhotoUploading(true);
        try {
            await studentsApi.deletePhoto(photoStudent.id);
            setPhotoPreview(null);
            toast.success('Foto berhasil dihapus');
            router.reload({ only: ['students'] });
        } catch {
            toast.error('Gagal menghapus foto');
        } finally {
            setPhotoUploading(false);
        }
    };

    const handlePrintCard = async (student: Student) => {
        setPrintingId(student.id);
        try {
            const response = await qrCodeApi.student(student.id);
            const qr = response.data.data;
            if (!qr) {
                toast.error('Data QR siswa tidak ditemukan');
                return;
            }

            const opened = await printAttendanceCardsWithTemplate('student', [
                {
                    ...qr,
                    classroom: qr.classroom ?? student.current_class?.name ?? null,
                },
            ], {
                title: `Kartu Siswa - ${qr.name}`,
                schoolName: tenant?.name,
                schoolLogo: tenant?.logo,
            });

            if (!opened) {
                toast.error('Popup blocker mungkin aktif. Izinkan popup untuk mencetak.');
            }
        } catch {
            toast.error('Gagal memuat kartu siswa');
        } finally {
            setPrintingId(null);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/students', { search }, { preserveState: true });
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            graduated: 'secondary',
            transferred: 'outline',
            dropped: 'destructive',
        };
        const labels: Record<string, string> = {
            active: 'Aktif',
            graduated: 'Lulus',
            transferred: 'Pindah',
            dropped: 'Keluar',
        };
        return <Badge variant={variants[status] || 'default'}>{labels[status] || status}</Badge>;
    };

    const handleDelete = (id: string) => {
        if (confirm('Apakah Anda yakin ingin menghapus siswa ini?')) {
            router.delete(`/students/${id}`);
        }
    };

    const handleExport = async (format: 'xlsx' | 'csv') => {
        setExporting(true);
        try {
            const response = await studentsApi.export(format);
            downloadBlob(response.data, `siswa.${format}`);
            toast.success('Data siswa berhasil diexport');
        } catch {
            toast.error('Gagal export data siswa');
        } finally {
            setExporting(false);
        }
    };

    const handleDownloadTemplate = async (format: 'xlsx' | 'csv') => {
        try {
            const response = await studentsApi.template(format);
            downloadBlob(response.data, `template-import-siswa.${format}`);
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
            const response = await studentsApi.import(importFile);
            const result = response.data.data;
            setImportResult(result);
            toast.success(
                `Import selesai: ${result.created} siswa ditambahkan, ${result.updated} diperbarui`
            );
            router.reload({ only: ['students'] });
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal import data siswa'));
        } finally {
            setImporting(false);
        }
    };

    const closeImportDialog = () => {
        setImportOpen(false);
        setImportFile(null);
        setImportResult(null);
        if (importInputRef.current) importInputRef.current.value = '';
    };

    return (
        <MainLayout>
            <Head title="Data Siswa" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Data Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola data siswa sekolah
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
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
                            <Link href="/students/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Siswa
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Siswa</CardTitle>
                        <CardDescription>
                            Total {students.meta?.total || 0} siswa terdaftar
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari NIS, nama, atau email..."
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
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama Lengkap</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>Jenis Kelamin</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {students.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                                                Tidak ada data siswa
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        students.data.map((student) => (
                                            <TableRow key={student.id}>
                                                <TableCell className="font-medium">{student.nis}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{student.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">{student.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{student.current_class?.name || '-'}</TableCell>
                                                <TableCell>{student.gender_label}</TableCell>
                                                <TableCell>{getStatusBadge(student.status)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/students/${student.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Lihat Detail
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/students/${student.id}/edit`}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                disabled={printingId === student.id}
                                                                onClick={() => handlePrintCard(student)}
                                                            >
                                                                <Printer className="mr-2 h-4 w-4" />
                                                                Cetak Kartu
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => openPhotoDialog(student)}>
                                                                <Camera className="mr-2 h-4 w-4" />
                                                                Foto Profil
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                className="text-destructive"
                                                                onClick={() => handleDelete(student.id)}
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                Hapus
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
                        {students.meta && students.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {students.meta.from} - {students.meta.to} dari {students.meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    {students.links?.prev && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(students.links!.prev!)}
                                        >
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {students.links?.next && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(students.links!.next!)}
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

            {/* Photo Dialog */}
            <Dialog open={!!photoStudent} onOpenChange={(open) => !open && setPhotoStudent(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Foto Profil</DialogTitle>
                        <DialogDescription>{photoStudent?.full_name}</DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col items-center gap-4 py-4">
                        <Avatar className="h-32 w-32">
                            <AvatarImage src={photoPreview ?? undefined} />
                            <AvatarFallback className="text-2xl">
                                {photoStudent?.full_name?.charAt(0) ?? '?'}
                            </AvatarFallback>
                        </Avatar>
                        <input
                            ref={photoInputRef}
                            type="file"
                            accept="image/png,image/jpeg,image/jpg"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) handlePhotoSelect(file);
                                e.target.value = '';
                            }}
                        />
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                disabled={photoUploading}
                                onClick={() => photoInputRef.current?.click()}
                            >
                                <Camera className="mr-2 h-4 w-4" />
                                {photoUploading ? 'Memproses...' : 'Pilih Foto'}
                            </Button>
                            {photoPreview && (
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    disabled={photoUploading}
                                    onClick={handlePhotoDelete}
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Hapus
                                </Button>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setPhotoStudent(null)}>
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Import Dialog */}
            <Dialog open={importOpen} onOpenChange={(open) => (open ? setImportOpen(true) : closeImportDialog())}>
                <DialogContent className="max-h-[85vh] max-w-md overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Import Siswa</DialogTitle>
                        <DialogDescription>
                            Upload file CSV atau Excel sesuai template. Siswa dengan NIS yang sudah
                            terdaftar akan diperbarui, bukan digandakan.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <Alert>
                            <FileSpreadsheet className="h-4 w-4" />
                            <AlertTitle>Template Import</AlertTitle>
                            <AlertDescription>
                                <p className="mb-2">
                                    Kolom wajib: nis, nama_depan, email, jenis_kelamin,
                                    tanggal_lahir.
                                </p>
                                <p className="mb-2 text-xs text-muted-foreground">
                                    Jenis kelamin diisi L atau P. Tanggal memakai format
                                    YYYY-MM-DD atau DD/MM/YYYY, dan tanggal lahir dipakai sebagai
                                    password awal siswa. Kolom <span className="font-medium">kelas</span>{' '}
                                    opsional — diisi kode kelas bila sekaligus ingin menempatkan
                                    siswa pada tahun ajaran aktif.
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
                            <Label htmlFor="student-import-file">File Import</Label>
                            <Input
                                id="student-import-file"
                                ref={importInputRef}
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
                                        {importResult.created} siswa ditambahkan,{' '}
                                        {importResult.updated} siswa diperbarui
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
