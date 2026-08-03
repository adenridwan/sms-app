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
import { curriculaApi } from '@/services/api';
import type { Curriculum, PaginationMeta } from '@/types';

interface CurriculumForm {
    code: string;
    name: string;
    description: string;
    is_active: boolean;
}

const emptyForm: CurriculumForm = {
    code: '',
    name: '',
    description: '',
    is_active: true,
};

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response;
        const firstFieldError = Object.values(response?.data?.errors ?? {})[0]?.[0];
        if (firstFieldError) return firstFieldError;
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

export default function AcademicCurricula() {
    const [curricula, setCurricula] = useState<Curriculum[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingCurriculum, setEditingCurriculum] = useState<Curriculum | null>(null);
    const [form, setForm] = useState<CurriculumForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingCurriculum, setDeletingCurriculum] = useState<Curriculum | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchCurricula = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await curriculaApi.list(params);
            const payload = response.data.data;
            setCurricula(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data kurikulum');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    useEffect(() => {
        fetchCurricula();
    }, [fetchCurricula]);

    const openCreate = () => {
        setEditingCurriculum(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (curriculum: Curriculum) => {
        setEditingCurriculum(curriculum);
        setForm({
            code: curriculum.code ?? '',
            name: curriculum.name,
            description: curriculum.description ?? '',
            is_active: curriculum.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.name.trim()) {
            toast.error('Nama kurikulum wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase() || null,
                name: form.name.trim(),
                description: form.description.trim() || null,
                is_active: form.is_active,
            };

            if (editingCurriculum) {
                await curriculaApi.update(editingCurriculum.id, payload);
                toast.success('Kurikulum berhasil diperbarui');
            } else {
                await curriculaApi.create(payload);
                toast.success('Kurikulum berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchCurricula();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan kurikulum'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingCurriculum || deleting) return;
        setDeleting(true);
        try {
            await curriculaApi.delete(deletingCurriculum.id);
            toast.success('Kurikulum berhasil dihapus');
            setDeletingCurriculum(null);
            fetchCurricula();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus kurikulum'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Kurikulum">
            <Head title="Akademik - Kurikulum" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Kurikulum</h1>
                        <p className="text-muted-foreground">
                            Kelola kurikulum yang digunakan sekolah
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Kurikulum
                    </Button>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Kurikulum</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} kurikulum terdaftar` : 'Memuat data kurikulum'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama kurikulum..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchCurricula} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : curricula.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data kurikulum
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead className="w-[110px]">Mapel</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {curricula.map((curriculum) => (
                                            <TableRow key={curriculum.id}>
                                                <TableCell className="font-medium">{curriculum.name}</TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {curriculum.code || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {curriculum.subjects_count ?? 0}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={curriculum.is_active ? 'default' : 'secondary'}>
                                                        {curriculum.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEdit(curriculum)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600 disabled:opacity-40"
                                                            disabled={!!curriculum.subjects_count}
                                                            title={
                                                                curriculum.subjects_count
                                                                    ? `Masih ada ${curriculum.subjects_count} mata pelajaran terkait`
                                                                    : undefined
                                                            }
                                                            onClick={() => setDeletingCurriculum(curriculum)}
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
                        <DialogTitle>{editingCurriculum ? 'Edit Kurikulum' : 'Tambah Kurikulum'}</DialogTitle>
                        <DialogDescription>
                            {editingCurriculum
                                ? 'Perbarui data kurikulum'
                                : 'Isi data kurikulum baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="curriculum-name">Nama Kurikulum *</Label>
                            <Input
                                id="curriculum-name"
                                placeholder="Contoh: Kurikulum Merdeka"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="curriculum-code">Kode</Label>
                            <Input
                                id="curriculum-code"
                                placeholder="Contoh: KM"
                                value={form.code}
                                onChange={(e) => setForm({ ...form, code: e.target.value })}
                                onBlur={(e) => setForm((f) => ({ ...f, code: e.target.value.toUpperCase() }))}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="curriculum-description">Deskripsi</Label>
                            <Textarea
                                id="curriculum-description"
                                placeholder="Deskripsi kurikulum (opsional)"
                                rows={3}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="curriculum-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Kurikulum aktif dapat dipilih pada mata pelajaran
                                </p>
                            </div>
                            <Switch
                                id="curriculum-active"
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
                            {editingCurriculum ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingCurriculum} onOpenChange={(open) => !open && !deleting && setDeletingCurriculum(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Kurikulum</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus kurikulum{' '}
                            <span className="font-medium">{deletingCurriculum?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingCurriculum(null)} disabled={deleting}>
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
        </MainLayout>
    );
}
