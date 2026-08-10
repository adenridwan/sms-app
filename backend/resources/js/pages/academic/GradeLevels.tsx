import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
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
import { toast } from 'sonner';
import { Plus, Pencil, Trash2, RefreshCw, Search } from 'lucide-react';
import { gradeLevelsApi } from '@/services/api';
import { usePermissions } from '@/hooks/usePermissions';
import type { GradeLevel, PaginationMeta } from '@/types';

interface GradeLevelForm {
    name: string;
    order: string;
    description: string;
    is_active: boolean;
}

const emptyForm: GradeLevelForm = {
    name: '',
    order: '0',
    description: '',
    is_active: true,
};

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string } } }).response;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

export default function AcademicGradeLevels() {
    // Guru hanya punya grade-levels.view; tombol kelola disembunyikan agar
    // konsisten dengan penjagaan `grade-levels.manage` di GradeLevelController.
    const { can } = usePermissions();
    const canManage = can('grade-levels.manage');

    const [gradeLevels, setGradeLevels] = useState<GradeLevel[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingGradeLevel, setEditingGradeLevel] = useState<GradeLevel | null>(null);
    const [form, setForm] = useState<GradeLevelForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingGradeLevel, setDeletingGradeLevel] = useState<GradeLevel | null>(null);

    const fetchGradeLevels = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await gradeLevelsApi.list(params);
            const payload = response.data.data;
            setGradeLevels(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data tingkat kelas');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    useEffect(() => {
        fetchGradeLevels();
    }, [fetchGradeLevels]);

    const openCreate = () => {
        setEditingGradeLevel(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (gradeLevel: GradeLevel) => {
        setEditingGradeLevel(gradeLevel);
        setForm({
            name: gradeLevel.name,
            order: String(gradeLevel.order ?? 0),
            description: gradeLevel.description ?? '',
            is_active: gradeLevel.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.name.trim()) {
            toast.error('Nama tingkat wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                name: form.name.trim(),
                order: Number(form.order) || 0,
                description: form.description.trim() || null,
                is_active: form.is_active,
            };

            if (editingGradeLevel) {
                await gradeLevelsApi.update(editingGradeLevel.id, payload);
                toast.success('Tingkat kelas berhasil diperbarui');
            } else {
                await gradeLevelsApi.create(payload);
                toast.success('Tingkat kelas berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchGradeLevels();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan tingkat kelas'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingGradeLevel) return;
        try {
            await gradeLevelsApi.delete(deletingGradeLevel.id);
            toast.success('Tingkat kelas berhasil dihapus');
            setDeletingGradeLevel(null);
            fetchGradeLevels();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus tingkat kelas'));
            setDeletingGradeLevel(null);
        }
    };

    return (
        <MainLayout title="Tingkat Kelas">
            <Head title="Akademik - Tingkat Kelas" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tingkat Kelas</h1>
                        <p className="text-muted-foreground">
                            Kelola master data tingkat/peringkat kelas (mis. Kelas 10, 11, 12)
                        </p>
                    </div>
                    {canManage && (
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Tingkat
                        </Button>
                    )}
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Tingkat Kelas</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} tingkat terdaftar` : 'Memuat data tingkat kelas'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama tingkat..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchGradeLevels} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : gradeLevels.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data tingkat kelas
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            {/* Kode sengaja tidak ditampilkan di tabel (generated otomatis,
                                                bukan identitas yang perlu dilihat pengguna). */}
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[90px]">Urutan</TableHead>
                                            <TableHead>Deskripsi</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {gradeLevels.map((level) => (
                                            <TableRow key={level.id}>
                                                <TableCell className="font-medium">{level.name}</TableCell>
                                                <TableCell>{level.order}</TableCell>
                                                <TableCell className="max-w-[300px] truncate text-muted-foreground">
                                                    {level.description || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={level.is_active ? 'default' : 'secondary'}>
                                                        {level.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        {canManage ? (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    onClick={() => openEdit(level)}
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-muted-foreground hover:text-red-600"
                                                                    onClick={() => setDeletingGradeLevel(level)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">-</span>
                                                        )}
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
                        <DialogTitle className="flex items-center gap-2">
                            {editingGradeLevel ? 'Edit Tingkat Kelas' : 'Tambah Tingkat Kelas'}
                            {editingGradeLevel && (
                                <Badge variant="outline">{editingGradeLevel.code}</Badge>
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {editingGradeLevel
                                ? 'Perbarui data tingkat kelas. Kode dibuat otomatis dan tidak dapat diubah.'
                                : 'Isi data tingkat kelas baru. Kode akan dibuat otomatis.'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="gradelevel-name">Nama *</Label>
                            <Input
                                id="gradelevel-name"
                                placeholder="Contoh: Kelas 10"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="gradelevel-order">Urutan</Label>
                            <Input
                                id="gradelevel-order"
                                type="number"
                                min={0}
                                inputMode="numeric"
                                value={form.order}
                                onChange={(e) =>
                                    setForm({ ...form, order: e.target.value.replace(/[^0-9]/g, '') })
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="gradelevel-description">Deskripsi</Label>
                            <Textarea
                                id="gradelevel-description"
                                placeholder="Deskripsi tingkat kelas (opsional)"
                                rows={3}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="gradelevel-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Tingkat aktif dapat dipilih saat membuat kelas baru
                                </p>
                            </div>
                            <Switch
                                id="gradelevel-active"
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
                            {editingGradeLevel ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingGradeLevel} onOpenChange={() => setDeletingGradeLevel(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Tingkat Kelas</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus tingkat{' '}
                            <span className="font-medium">{deletingGradeLevel?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingGradeLevel(null)}>
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
