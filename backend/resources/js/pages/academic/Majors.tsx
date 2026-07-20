import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
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
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { toast } from 'sonner';
import {
    Plus,
    Pencil,
    Trash2,
    RefreshCw,
    Download,
    Upload,
    FileSpreadsheet,
    Search,
    AlertTriangle,
} from 'lucide-react';
import { majorsApi, type ImportResult } from '@/services/api';
import type { Major, PaginationMeta } from '@/types';

interface MajorForm {
    code: string;
    name: string;
    description: string;
    is_active: boolean;
}

const emptyForm: MajorForm = {
    code: '',
    name: '',
    description: '',
    is_active: true,
};

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

export default function AcademicMajors() {
    const [majors, setMajors] = useState<Major[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingMajor, setEditingMajor] = useState<Major | null>(null);
    const [form, setForm] = useState<MajorForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingMajor, setDeletingMajor] = useState<Major | null>(null);

    // Import dialog
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [exporting, setExporting] = useState(false);

    const fetchMajors = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await majorsApi.list(params);
            const payload = response.data.data;
            setMajors(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data jurusan');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    useEffect(() => {
        fetchMajors();
    }, [fetchMajors]);

    const openCreate = () => {
        setEditingMajor(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (major: Major) => {
        setEditingMajor(major);
        setForm({
            code: major.code,
            name: major.name,
            description: major.description ?? '',
            is_active: major.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim()) {
            toast.error('Kode dan nama jurusan wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim(),
                name: form.name.trim(),
                description: form.description.trim() || null,
                is_active: form.is_active,
            };

            if (editingMajor) {
                await majorsApi.update(editingMajor.id, payload);
                toast.success('Jurusan berhasil diperbarui');
            } else {
                await majorsApi.create(payload);
                toast.success('Jurusan berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchMajors();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan jurusan'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingMajor) return;
        try {
            await majorsApi.delete(deletingMajor.id);
            toast.success('Jurusan berhasil dihapus');
            setDeletingMajor(null);
            fetchMajors();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus jurusan'));
            setDeletingMajor(null);
        }
    };

    const handleExport = async () => {
        setExporting(true);
        try {
            const response = await majorsApi.export();
            downloadBlob(response.data, 'jurusan.xlsx');
            toast.success('Data jurusan berhasil diexport');
        } catch {
            toast.error('Gagal export data jurusan');
        } finally {
            setExporting(false);
        }
    };

    const handleDownloadTemplate = async () => {
        try {
            const response = await majorsApi.template();
            downloadBlob(response.data, 'template-import-jurusan.xlsx');
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
            const response = await majorsApi.import(importFile);
            const result = response.data.data;
            setImportResult(result);
            toast.success(`Import selesai: ${result.created} ditambahkan, ${result.updated} diperbarui`);
            fetchMajors();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal import data jurusan'));
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

    return (
        <MainLayout title="Jurusan">
            <Head title="Akademik - Jurusan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Jurusan</h1>
                        <p className="text-muted-foreground">
                            Kelola data jurusan sekolah
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={handleExport} disabled={exporting}>
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                        <Button variant="outline" onClick={() => setImportOpen(true)}>
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Jurusan
                        </Button>
                    </div>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Jurusan</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} jurusan terdaftar` : 'Memuat data jurusan'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama jurusan..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchMajors} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : majors.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data jurusan
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[120px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Deskripsi</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {majors.map((major) => (
                                            <TableRow key={major.id}>
                                                <TableCell className="font-medium">{major.code}</TableCell>
                                                <TableCell>{major.name}</TableCell>
                                                <TableCell className="max-w-[300px] truncate text-muted-foreground">
                                                    {major.description || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={major.is_active ? 'default' : 'secondary'}>
                                                        {major.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEdit(major)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingMajor(major)}
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

                        {/* Pagination */}
                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {meta.from}-{meta.to} dari {meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page <= 1 || loading}
                                        onClick={() => setPage((p) => p - 1)}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page >= meta.last_page || loading}
                                        onClick={() => setPage((p) => p + 1)}
                                    >
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingMajor ? 'Edit Jurusan' : 'Tambah Jurusan'}</DialogTitle>
                        <DialogDescription>
                            {editingMajor
                                ? 'Perbarui data jurusan'
                                : 'Isi data jurusan baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="major-code">Kode *</Label>
                            <Input
                                id="major-code"
                                placeholder="Contoh: IPA"
                                value={form.code}
                                onChange={(e) => setForm({ ...form, code: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="major-name">Nama *</Label>
                            <Input
                                id="major-name"
                                placeholder="Contoh: Ilmu Pengetahuan Alam"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="major-description">Deskripsi</Label>
                            <Textarea
                                id="major-description"
                                placeholder="Deskripsi jurusan (opsional)"
                                rows={3}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="major-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Jurusan aktif dapat digunakan pada kelas
                                </p>
                            </div>
                            <Switch
                                id="major-active"
                                checked={form.is_active}
                                onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {editingMajor ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Import Dialog */}
            <Dialog open={importOpen} onOpenChange={(open) => (open ? setImportOpen(true) : closeImportDialog())}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Jurusan</DialogTitle>
                        <DialogDescription>
                            Upload file Excel (.xlsx, .xls) atau CSV sesuai template
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <Alert>
                            <FileSpreadsheet className="h-4 w-4" />
                            <AlertTitle>Template Import</AlertTitle>
                            <AlertDescription>
                                <p className="mb-2">
                                    Gunakan template default agar format kolom sesuai
                                    (kode, nama, deskripsi, aktif).
                                </p>
                                <Button variant="outline" size="sm" onClick={handleDownloadTemplate}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download Template
                                </Button>
                            </AlertDescription>
                        </Alert>
                        <div className="space-y-2">
                            <Label htmlFor="major-import-file">File Import</Label>
                            <Input
                                id="major-import-file"
                                ref={fileInputRef}
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setImportFile(e.target.files?.[0] ?? null)}
                            />
                        </div>
                        {importResult && (
                            <div className="space-y-2">
                                <Alert>
                                    <AlertTitle>Hasil Import</AlertTitle>
                                    <AlertDescription>
                                        {importResult.created} data ditambahkan, {importResult.updated} data diperbarui
                                    </AlertDescription>
                                </Alert>
                                {importResult.errors.length > 0 && (
                                    <Alert variant="destructive">
                                        <AlertTriangle className="h-4 w-4" />
                                        <AlertTitle>{importResult.errors.length} baris gagal</AlertTitle>
                                        <AlertDescription>
                                            <ul className="max-h-40 list-disc space-y-1 overflow-y-auto pl-4">
                                                {importResult.errors.map((error, index) => (
                                                    <li key={index}>{error}</li>
                                                ))}
                                            </ul>
                                        </AlertDescription>
                                    </Alert>
                                )}
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={closeImportDialog} disabled={importing}>
                            Tutup
                        </Button>
                        <Button onClick={handleImport} disabled={importing || !importFile}>
                            {importing && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingMajor} onOpenChange={() => setDeletingMajor(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Jurusan</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus jurusan{' '}
                            <span className="font-medium">{deletingMajor?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingMajor(null)}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
