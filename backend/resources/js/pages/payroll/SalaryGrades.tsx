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
import { salaryGradesApi } from '@/services/api';
import type { SalaryGrade, PaginationMeta } from '@/types';

interface SalaryGradeForm {
    code: string;
    name: string;
    base_salary: string;
    description: string;
    order: string;
    is_active: boolean;
}

const emptyForm: SalaryGradeForm = {
    code: '',
    name: '',
    base_salary: '',
    description: '',
    order: '0',
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

function formatCurrency(value: string): string {
    const num = value.replace(/\D/g, '');
    return num.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function parseCurrency(value: string): string {
    return value.replace(/\./g, '');
}

export default function SalaryGrades() {
    const [items, setItems] = useState<SalaryGrade[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<SalaryGrade | null>(null);
    const [form, setForm] = useState<SalaryGradeForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<SalaryGrade | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await salaryGradesApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data golongan gaji');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const openCreate = () => {
        setEditingItem(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (item: SalaryGrade) => {
        setEditingItem(item);
        setForm({
            code: item.code,
            name: item.name,
            base_salary: formatCurrency(String(item.base_salary)),
            description: item.description ?? '',
            order: String(item.order),
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim() || !form.base_salary) {
            toast.error('Kode, nama, dan gaji pokok wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                base_salary: parseFloat(parseCurrency(form.base_salary)) || 0,
                description: form.description.trim() || null,
                order: parseInt(form.order) || 0,
                is_active: form.is_active,
            };

            if (editingItem) {
                await salaryGradesApi.update(editingItem.id, payload);
                toast.success('Golongan gaji berhasil diperbarui');
            } else {
                await salaryGradesApi.create(payload);
                toast.success('Golongan gaji berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan golongan gaji'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await salaryGradesApi.delete(deletingItem.id);
            toast.success('Golongan gaji berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus golongan gaji'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Golongan Gaji">
            <Head title="Payroll - Golongan Gaji" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Golongan Gaji</h1>
                        <p className="text-muted-foreground">
                            Kelola golongan/grade gaji karyawan (I-A, II-B, III-C, dst)
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Golongan
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Golongan Gaji</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} golongan terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari kode atau nama..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Button variant="outline" size="icon" onClick={fetchData} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : items.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data golongan gaji
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[60px]">Urutan</TableHead>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[180px] text-right">Gaji Pokok</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="text-center text-muted-foreground">{item.order}</TableCell>
                                                <TableCell className="font-mono text-muted-foreground">{item.code}</TableCell>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell className="text-right font-mono">{item.base_salary_formatted}</TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_active ? 'default' : 'secondary'}>
                                                        {item.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button variant="ghost" size="icon" onClick={() => openEdit(item)}>
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-muted-foreground hover:text-red-600"
                                                            onClick={() => setDeletingItem(item)}
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

                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {meta.from}-{meta.to} dari {meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" disabled={page <= 1 || loading} onClick={() => setPage((p) => p - 1)}>
                                        Sebelumnya
                                    </Button>
                                    <Button variant="outline" size="sm" disabled={page >= meta.last_page || loading} onClick={() => setPage((p) => p + 1)}>
                                        Berikutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Golongan Gaji' : 'Tambah Golongan Gaji'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data golongan gaji' : 'Isi data golongan gaji baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="grade-code">Kode *</Label>
                                <Input
                                    id="grade-code"
                                    placeholder="Contoh: I-A"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="grade-order">Urutan</Label>
                                <Input
                                    id="grade-order"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    value={form.order}
                                    onChange={(e) => setForm({ ...form, order: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="grade-name">Nama *</Label>
                            <Input
                                id="grade-name"
                                placeholder="Contoh: Golongan I-A"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="grade-salary">Gaji Pokok (Rp) *</Label>
                            <Input
                                id="grade-salary"
                                placeholder="Contoh: 5.000.000"
                                value={form.base_salary}
                                onChange={(e) => setForm({ ...form, base_salary: formatCurrency(e.target.value) })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="grade-description">Deskripsi</Label>
                            <Textarea
                                id="grade-description"
                                placeholder="Deskripsi golongan gaji (opsional)"
                                rows={2}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="grade-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Golongan aktif dapat dipilih pada pengaturan gaji karyawan
                                </p>
                            </div>
                            <Switch
                                id="grade-active"
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
                            {editingItem ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={!!deletingItem} onOpenChange={(open) => !open && !deleting && setDeletingItem(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Golongan Gaji</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus golongan gaji{' '}
                            <span className="font-medium">{deletingItem?.name}</span>? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingItem(null)} disabled={deleting}>
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
