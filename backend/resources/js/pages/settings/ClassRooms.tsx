import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback, useRef } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { classroomsApi, majorsApi, gradeLevelsApi, academicYearsApi, type ImportResult } from '@/services/api';
import type { Classroom, Major, GradeLevel, AcademicYear, PaginationMeta } from '@/types';

interface ClassroomForm {
    code: string;
    name: string;
    academic_year_id: string;
    grade_level_id: string;
    major_id: string;
    room: string;
    capacity: string;
    is_active: boolean;
}

const emptyForm: ClassroomForm = {
    code: '',
    name: '',
    academic_year_id: '',
    grade_level_id: '',
    major_id: '',
    room: '',
    capacity: '30',
    is_active: true,
};

const NONE_VALUE = 'none';

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

function getErrorStatus(error: unknown): number | null {
    if (error && typeof error === 'object' && 'response' in error) {
        return (error as { response?: { status?: number } }).response?.status ?? null;
    }
    return null;
}

export default function SettingsClassRooms() {
    const [classrooms, setClassrooms] = useState<Classroom[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    // Reference data for form selects
    const [academicYears, setAcademicYears] = useState<AcademicYear[]>([]);
    const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([]);
    const [majors, setMajors] = useState<Major[]>([]);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingClassroom, setEditingClassroom] = useState<Classroom | null>(null);
    const [form, setForm] = useState<ClassroomForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingClassroom, setDeletingClassroom] = useState<Classroom | null>(null);
    const [deleting, setDeleting] = useState(false);
    // Penjaga anti klik ganda: state `deleting` baru terlihat setelah render
    // berikutnya, sedangkan dua klik cepat bisa masuk sebelum itu.
    const deletingRef = useRef(false);

    // Import dialog
    const [importOpen, setImportOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [importing, setImporting] = useState(false);
    const [importResult, setImportResult] = useState<ImportResult | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [exporting, setExporting] = useState(false);

    const fetchClassrooms = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await classroomsApi.list(params);
            const payload = response.data.data;
            setClassrooms(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data kelas');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    const fetchReferenceData = useCallback(async () => {
        try {
            const [yearsRes, levelsRes, majorsRes] = await Promise.all([
                academicYearsApi.list({ per_page: 100 }),
                gradeLevelsApi.list({ per_page: 100 }),
                majorsApi.list({ per_page: 100 }),
            ]);
            setAcademicYears(yearsRes.data.data.data ?? []);
            setGradeLevels(levelsRes.data.data.data ?? []);
            setMajors(majorsRes.data.data.data ?? []);
        } catch {
            toast.error('Gagal memuat data referensi (tahun ajaran/tingkat/jurusan)');
        }
    }, []);

    useEffect(() => {
        fetchClassrooms();
    }, [fetchClassrooms]);

    useEffect(() => {
        fetchReferenceData();
    }, [fetchReferenceData]);

    const openCreate = () => {
        setEditingClassroom(null);
        const activeYear = academicYears.find((year) => year.is_active);
        setForm({ ...emptyForm, academic_year_id: activeYear?.id ?? '' });
        setFormOpen(true);
    };

    const openEdit = (classroom: Classroom) => {
        setEditingClassroom(classroom);
        setForm({
            code: classroom.code,
            name: classroom.name,
            academic_year_id: classroom.academic_year_id,
            grade_level_id: classroom.grade_level_id,
            major_id: classroom.major_id ?? '',
            room: classroom.room ?? '',
            capacity: String(classroom.capacity),
            is_active: classroom.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim()) {
            toast.error('Kode dan nama kelas wajib diisi');
            return;
        }
        if (!form.academic_year_id) {
            toast.error('Tahun ajaran wajib dipilih');
            return;
        }
        if (!form.grade_level_id) {
            toast.error('Tingkat wajib dipilih');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim(),
                name: form.name.trim(),
                academic_year_id: form.academic_year_id,
                grade_level_id: form.grade_level_id,
                major_id: form.major_id || null,
                room: form.room.trim() || null,
                capacity: Number(form.capacity) || 30,
                is_active: form.is_active,
            };

            if (editingClassroom) {
                await classroomsApi.update(editingClassroom.id, payload);
                toast.success('Kelas berhasil diperbarui');
            } else {
                await classroomsApi.create(payload);
                toast.success('Kelas berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchClassrooms();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan kelas'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingClassroom || deletingRef.current) return;
        deletingRef.current = true;
        setDeleting(true);
        try {
            await classroomsApi.delete(deletingClassroom.id);
            toast.success('Kelas berhasil dihapus');
        } catch (error) {
            // 404 = barisnya memang sudah tidak ada (klik konfirmasi dobel,
            // atau dihapus dari tab/perangkat lain). Bagi pengguna hasilnya
            // sama saja: kelas terhapus — jangan tampilkan itu sebagai error.
            if (getErrorStatus(error) === 404) {
                toast.success('Kelas berhasil dihapus');
            } else {
                toast.error(getErrorMessage(error, 'Gagal menghapus kelas'));
            }
        } finally {
            deletingRef.current = false;
            setDeleting(false);
            setDeletingClassroom(null);
            fetchClassrooms();
        }
    };

    const handleExport = async () => {
        setExporting(true);
        try {
            const response = await classroomsApi.export();
            downloadBlob(response.data, 'kelas.xlsx');
            toast.success('Data kelas berhasil diexport');
        } catch {
            toast.error('Gagal export data kelas');
        } finally {
            setExporting(false);
        }
    };

    const handleDownloadTemplate = async () => {
        try {
            const response = await classroomsApi.template();
            downloadBlob(response.data, 'template-import-kelas.xlsx');
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
            const response = await classroomsApi.import(importFile);
            const result = response.data.data;
            setImportResult(result);
            toast.success(`Import selesai: ${result.created} ditambahkan, ${result.updated} diperbarui`);
            fetchClassrooms();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal import data kelas'));
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
        <MainLayout title="Kelas">
            <Head title="Pengaturan Kelas" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Kelas</h1>
                        <p className="text-muted-foreground">
                            Kelola data kelas per tahun ajaran
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
                            Tambah Kelas
                        </Button>
                    </div>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Kelas</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} kelas terdaftar` : 'Memuat data kelas'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama kelas..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchClassrooms} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : classrooms.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data kelas
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[120px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Tingkat</TableHead>
                                            <TableHead>Jurusan</TableHead>
                                            <TableHead>Tahun Ajaran</TableHead>
                                            <TableHead>Ruangan</TableHead>
                                            <TableHead className="w-[90px]">Kapasitas</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {classrooms.map((classroom) => (
                                            <TableRow key={classroom.id}>
                                                <TableCell className="font-medium">{classroom.code}</TableCell>
                                                <TableCell>{classroom.name}</TableCell>
                                                <TableCell>{classroom.grade_level?.name ?? '-'}</TableCell>
                                                <TableCell>{classroom.major?.name ?? '-'}</TableCell>
                                                <TableCell>{classroom.academic_year?.name ?? '-'}</TableCell>
                                                <TableCell>{classroom.room || '-'}</TableCell>
                                                <TableCell>{classroom.capacity}</TableCell>
                                                <TableCell>
                                                    <Badge variant={classroom.is_active ? 'default' : 'secondary'}>
                                                        {classroom.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEdit(classroom)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingClassroom(classroom)}
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
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingClassroom ? 'Edit Kelas' : 'Tambah Kelas'}</DialogTitle>
                        <DialogDescription>
                            {editingClassroom ? 'Perbarui data kelas' : 'Isi data kelas baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="classroom-code">Kode *</Label>
                                <Input
                                    id="classroom-code"
                                    placeholder="Contoh: X-IPA-1"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="classroom-name">Nama *</Label>
                                <Input
                                    id="classroom-name"
                                    placeholder="Contoh: X IPA 1"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label>Tahun Ajaran *</Label>
                            <Select
                                value={form.academic_year_id}
                                onValueChange={(value) => setForm({ ...form, academic_year_id: value })}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih tahun ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>
                                            {year.name}{year.is_active ? ' (Aktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tingkat *</Label>
                                <Select
                                    value={form.grade_level_id}
                                    onValueChange={(value) => setForm({ ...form, grade_level_id: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tingkat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {gradeLevels.map((level) => (
                                            <SelectItem key={level.id} value={level.id}>
                                                {level.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Jurusan</Label>
                                <Select
                                    value={form.major_id || NONE_VALUE}
                                    onValueChange={(value) =>
                                        setForm({ ...form, major_id: value === NONE_VALUE ? '' : value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih jurusan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE_VALUE}>Tanpa Jurusan</SelectItem>
                                        {majors.map((major) => (
                                            <SelectItem key={major.id} value={major.id}>
                                                {major.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="classroom-room">Ruangan</Label>
                                <Input
                                    id="classroom-room"
                                    placeholder="Contoh: R101"
                                    value={form.room}
                                    onChange={(e) => setForm({ ...form, room: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="classroom-capacity">Kapasitas</Label>
                                <Input
                                    id="classroom-capacity"
                                    type="number"
                                    min={1}
                                    value={form.capacity}
                                    onChange={(e) => setForm({ ...form, capacity: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="classroom-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Kelas aktif dapat digunakan untuk penempatan siswa
                                </p>
                            </div>
                            <Switch
                                id="classroom-active"
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
                            {editingClassroom ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Import Dialog */}
            <Dialog open={importOpen} onOpenChange={(open) => (open ? setImportOpen(true) : closeImportDialog())}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Import Kelas</DialogTitle>
                        <DialogDescription>
                            Upload file Excel (.xlsx, .xls) atau CSV sesuai template. Data akan
                            diimport ke tahun ajaran aktif.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <Alert>
                            <FileSpreadsheet className="h-4 w-4" />
                            <AlertTitle>Template Import</AlertTitle>
                            <AlertDescription>
                                <p className="mb-2">
                                    Gunakan template default agar format kolom sesuai
                                    (kode, nama, tingkat, jurusan, ruangan, kapasitas, aktif).
                                </p>
                                <Button variant="outline" size="sm" onClick={handleDownloadTemplate}>
                                    <Download className="mr-2 h-4 w-4" />
                                    Download Template
                                </Button>
                            </AlertDescription>
                        </Alert>
                        <div className="space-y-2">
                            <Label htmlFor="classroom-import-file">File Import</Label>
                            <Input
                                id="classroom-import-file"
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
            <AlertDialog
                open={!!deletingClassroom}
                onOpenChange={(open) => {
                    if (!open && !deletingRef.current) setDeletingClassroom(null);
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Kelas</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus kelas{' '}
                            <span className="font-medium">{deletingClassroom?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleting} onClick={() => setDeletingClassroom(null)}>
                            Batal
                        </AlertDialogCancel>
                        {/* preventDefault: biarkan dialog tetap terbuka sampai
                            request selesai, supaya tombolnya tidak sempat
                            diklik dua kali selama animasi penutupan. */}
                        <AlertDialogAction
                            disabled={deleting}
                            onClick={(event) => {
                                event.preventDefault();
                                handleDelete();
                            }}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleting ? 'Menghapus...' : 'Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
