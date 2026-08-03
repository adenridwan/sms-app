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
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { subjectsApi, curriculaApi } from '@/services/api';
import { SUBJECT_CATEGORIES, type Subject, type Curriculum, type PaginationMeta, type SubjectCategory } from '@/types';

const NONE_VALUE = 'none';

interface SubjectForm {
    curriculum_id: string;
    code: string;
    name: string;
    category: SubjectCategory | '';
    description: string;
    is_active: boolean;
}

const emptyForm: SubjectForm = {
    curriculum_id: '',
    code: '',
    name: '',
    category: '',
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

export default function AcademicSubjects() {
    const [subjects, setSubjects] = useState<Subject[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const [curricula, setCurricula] = useState<Curriculum[]>([]);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingSubject, setEditingSubject] = useState<Subject | null>(null);
    const [form, setForm] = useState<SubjectForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Delete dialog
    const [deletingSubject, setDeletingSubject] = useState<Subject | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchSubjects = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await subjectsApi.list(params);
            const payload = response.data.data;
            setSubjects(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data mata pelajaran');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    const fetchCurricula = useCallback(async () => {
        try {
            const response = await curriculaApi.list({ per_page: 100 });
            setCurricula(response.data.data.data ?? []);
        } catch {
            toast.error('Gagal memuat data kurikulum');
        }
    }, []);

    useEffect(() => {
        fetchSubjects();
    }, [fetchSubjects]);

    useEffect(() => {
        fetchCurricula();
    }, [fetchCurricula]);

    const openCreate = () => {
        setEditingSubject(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (subject: Subject) => {
        setEditingSubject(subject);
        setForm({
            curriculum_id: subject.curriculum_id ?? '',
            code: subject.code,
            name: subject.name,
            category: subject.category ?? '',
            description: subject.description ?? '',
            is_active: subject.is_active,
        });
        // Kalau kurikulum mata pelajaran ini tidak ada di daftar referensi
        // (mis. sudah nonaktif), sisipkan manual supaya Select tidak kosong.
        if (subject.curriculum && !curricula.some((c) => c.id === subject.curriculum!.id)) {
            setCurricula((prev) => [...prev, { ...subject.curriculum!, is_active: false } as Curriculum]);
        }
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim() || !form.category) {
            toast.error('Kode, nama, dan kategori mata pelajaran wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                curriculum_id: form.curriculum_id || null,
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                category: form.category,
                description: form.description.trim() || null,
                is_active: form.is_active,
            };

            if (editingSubject) {
                await subjectsApi.update(editingSubject.id, payload);
                toast.success('Mata pelajaran berhasil diperbarui');
            } else {
                await subjectsApi.create(payload);
                toast.success('Mata pelajaran berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchSubjects();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan mata pelajaran'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingSubject || deleting) return;
        setDeleting(true);
        try {
            await subjectsApi.delete(deletingSubject.id);
            toast.success('Mata pelajaran berhasil dihapus');
            setDeletingSubject(null);
            fetchSubjects();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus mata pelajaran'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Mata Pelajaran">
            <Head title="Akademik - Mata Pelajaran" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Mata Pelajaran</h1>
                        <p className="text-muted-foreground">
                            Kelola mata pelajaran per kurikulum
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Mata Pelajaran
                    </Button>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Mata Pelajaran</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} mata pelajaran terdaftar` : 'Memuat data mata pelajaran'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama mata pelajaran..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchSubjects} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : subjects.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data mata pelajaran
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Kurikulum</TableHead>
                                            <TableHead className="w-[160px]">Kategori</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {subjects.map((subject) => (
                                            <TableRow key={subject.id}>
                                                <TableCell className="font-medium">{subject.name}</TableCell>
                                                <TableCell className="text-muted-foreground">{subject.code}</TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {subject.curriculum?.name ?? '-'}
                                                </TableCell>
                                                <TableCell>
                                                    {subject.category ? (
                                                        <Badge variant="outline">{subject.category}</Badge>
                                                    ) : (
                                                        '-'
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={subject.is_active ? 'default' : 'secondary'}>
                                                        {subject.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEdit(subject)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingSubject(subject)}
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
                        <DialogTitle>{editingSubject ? 'Edit Mata Pelajaran' : 'Tambah Mata Pelajaran'}</DialogTitle>
                        <DialogDescription>
                            {editingSubject
                                ? 'Perbarui data mata pelajaran'
                                : 'Isi data mata pelajaran baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Kurikulum</Label>
                            <Select
                                value={form.curriculum_id || NONE_VALUE}
                                onValueChange={(value) =>
                                    setForm({ ...form, curriculum_id: value === NONE_VALUE ? '' : value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih kurikulum" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NONE_VALUE}>Tanpa Kurikulum</SelectItem>
                                    {curricula.map((curriculum) => (
                                        <SelectItem key={curriculum.id} value={curriculum.id}>
                                            {curriculum.name}{curriculum.is_active === false ? ' (Nonaktif)' : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="subject-code">Kode *</Label>
                                <Input
                                    id="subject-code"
                                    placeholder="Contoh: MTK"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Kategori *</Label>
                                <Select
                                    value={form.category || undefined}
                                    onValueChange={(value) => setForm({ ...form, category: value as SubjectCategory })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {SUBJECT_CATEGORIES.map((category) => (
                                            <SelectItem key={category} value={category}>
                                                {category}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="subject-name">Nama Mata Pelajaran *</Label>
                            <Input
                                id="subject-name"
                                placeholder="Contoh: Matematika"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="subject-description">Deskripsi</Label>
                            <Textarea
                                id="subject-description"
                                placeholder="Deskripsi mata pelajaran (opsional)"
                                rows={3}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="subject-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Mata pelajaran aktif dapat dipilih pada jadwal
                                </p>
                            </div>
                            <Switch
                                id="subject-active"
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
                            {editingSubject ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingSubject} onOpenChange={(open) => !open && !deleting && setDeletingSubject(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Mata Pelajaran</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus mata pelajaran{' '}
                            <span className="font-medium">{deletingSubject?.name}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingSubject(null)} disabled={deleting}>
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
