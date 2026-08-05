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
import { feeTypesApi } from '@/services/api';
import type { FeeType, PaginationMeta } from '@/types';

const FREQUENCIES = [
    { value: 'once', label: 'Sekali' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'semester', label: 'Per Semester' },
    { value: 'yearly', label: 'Tahunan' },
];

interface FeeTypeForm {
    code: string;
    name: string;
    description: string;
    frequency: string;
    is_mandatory: boolean;
    is_active: boolean;
}

const emptyForm: FeeTypeForm = {
    code: '',
    name: '',
    description: '',
    frequency: 'monthly',
    is_mandatory: true,
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

export default function FeeTypes() {
    const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<FeeType | null>(null);
    const [form, setForm] = useState<FeeTypeForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<FeeType | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await feeTypesApi.list(params);
            const payload = response.data.data;
            setFeeTypes(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data jenis biaya');
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

    const openEdit = (item: FeeType) => {
        setEditingItem(item);
        setForm({
            code: item.code,
            name: item.name,
            description: item.description ?? '',
            frequency: item.frequency,
            is_mandatory: item.is_mandatory,
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim()) {
            toast.error('Kode dan nama jenis biaya wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                description: form.description.trim() || null,
                frequency: form.frequency,
                is_mandatory: form.is_mandatory,
                is_active: form.is_active,
            };

            if (editingItem) {
                await feeTypesApi.update(editingItem.id, payload);
                toast.success('Jenis biaya berhasil diperbarui');
            } else {
                await feeTypesApi.create(payload);
                toast.success('Jenis biaya berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan jenis biaya'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await feeTypesApi.delete(deletingItem.id);
            toast.success('Jenis biaya berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus jenis biaya'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Jenis Biaya">
            <Head title="Keuangan - Jenis Biaya" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Jenis Biaya</h1>
                        <p className="text-muted-foreground">
                            Kelola jenis-jenis biaya sekolah (SPP, uang gedung, dll)
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Jenis Biaya
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Jenis Biaya</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} jenis biaya terdaftar` : 'Memuat data'}
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
                        ) : feeTypes.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data jenis biaya
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[120px]">Frekuensi</TableHead>
                                            <TableHead className="w-[100px]">Wajib</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {feeTypes.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-mono text-muted-foreground">{item.code}</TableCell>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{item.frequency_label}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_mandatory ? 'default' : 'secondary'}>
                                                        {item.is_mandatory ? 'Wajib' : 'Opsional'}
                                                    </Badge>
                                                </TableCell>
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

            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Jenis Biaya' : 'Tambah Jenis Biaya'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data jenis biaya' : 'Isi data jenis biaya baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="fee-code">Kode *</Label>
                                <Input
                                    id="fee-code"
                                    placeholder="Contoh: SPP"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Frekuensi *</Label>
                                <Select
                                    value={form.frequency}
                                    onValueChange={(value) => setForm({ ...form, frequency: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih frekuensi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {FREQUENCIES.map((freq) => (
                                            <SelectItem key={freq.value} value={freq.value}>
                                                {freq.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="fee-name">Nama Jenis Biaya *</Label>
                            <Input
                                id="fee-name"
                                placeholder="Contoh: SPP Bulanan"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="fee-description">Deskripsi</Label>
                            <Textarea
                                id="fee-description"
                                placeholder="Deskripsi jenis biaya (opsional)"
                                rows={3}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="fee-mandatory">Wajib</Label>
                                <p className="text-sm text-muted-foreground">
                                    Biaya wajib akan otomatis ditagihkan ke semua siswa
                                </p>
                            </div>
                            <Switch
                                id="fee-mandatory"
                                checked={form.is_mandatory}
                                onCheckedChange={(checked) => setForm({ ...form, is_mandatory: checked })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="fee-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Jenis biaya aktif dapat digunakan pada struktur biaya
                                </p>
                            </div>
                            <Switch
                                id="fee-active"
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
                        <AlertDialogTitle>Hapus Jenis Biaya</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus jenis biaya{' '}
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
