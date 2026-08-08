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
import { DatePicker } from '@/components/ui/date-picker';
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
import { Plus, Pencil, Trash2, RefreshCw, Search, Percent, Banknote } from 'lucide-react';
import { discountsApi, feeTypesApi } from '@/services/api';
import type { Discount, FeeType, PaginationMeta } from '@/types';

const NONE_VALUE = 'none';

const DISCOUNT_TYPES = [
    { value: 'percentage', label: 'Persentase', icon: Percent },
    { value: 'fixed', label: 'Nominal Tetap', icon: Banknote },
];

interface DiscountForm {
    code: string;
    name: string;
    description: string;
    // Diturunkan dari tipe domain, bukan `string`, supaya payload yang
    // dikirim ke API tetap cocok dan union-nya ikut tersinkron.
    type: NonNullable<Discount['type']>;
    value: string;
    fee_type_id: string;
    valid_from: string;
    valid_until: string;
    is_active: boolean;
}

const emptyForm: DiscountForm = {
    code: '',
    name: '',
    description: '',
    type: 'percentage',
    value: '',
    fee_type_id: '',
    valid_from: '',
    valid_until: '',
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

export default function Discounts() {
    const [items, setItems] = useState<Discount[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const [feeTypes, setFeeTypes] = useState<FeeType[]>([]);

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<Discount | null>(null);
    const [form, setForm] = useState<DiscountForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<Discount | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await discountsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data potongan/beasiswa');
        } finally {
            setLoading(false);
        }
    }, [page, search]);

    const fetchFeeTypes = useCallback(async () => {
        try {
            const response = await feeTypesApi.list({ per_page: 100, is_active: true });
            setFeeTypes(response.data.data.data ?? []);
        } catch {
            toast.error('Gagal memuat data jenis biaya');
        }
    }, []);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    useEffect(() => {
        fetchFeeTypes();
    }, [fetchFeeTypes]);

    const openCreate = () => {
        setEditingItem(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (item: Discount) => {
        setEditingItem(item);
        setForm({
            code: item.code,
            name: item.name,
            description: item.description ?? '',
            type: item.type ?? 'percentage',
            value: String(item.value),
            fee_type_id: item.fee_type_id ?? '',
            valid_from: item.valid_from ?? '',
            valid_until: item.valid_until ?? '',
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim() || !form.value) {
            toast.error('Kode, nama, dan nilai potongan wajib diisi');
            return;
        }

        const value = parseFloat(form.value);
        if (form.type === 'percentage' && value > 100) {
            toast.error('Nilai persentase tidak boleh lebih dari 100%');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                description: form.description.trim() || null,
                type: form.type,
                value: value,
                fee_type_id: form.fee_type_id || null,
                valid_from: form.valid_from || null,
                valid_until: form.valid_until || null,
                is_active: form.is_active,
            };

            if (editingItem) {
                await discountsApi.update(editingItem.id, payload);
                toast.success('Potongan/beasiswa berhasil diperbarui');
            } else {
                await discountsApi.create(payload);
                toast.success('Potongan/beasiswa berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan potongan/beasiswa'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await discountsApi.delete(deletingItem.id);
            toast.success('Potongan/beasiswa berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus potongan/beasiswa'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Potongan / Beasiswa">
            <Head title="Keuangan - Potongan / Beasiswa" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Potongan / Beasiswa</h1>
                        <p className="text-muted-foreground">
                            Kelola potongan biaya dan beasiswa untuk siswa
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Potongan
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Potongan / Beasiswa</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} potongan terdaftar` : 'Memuat data'}
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
                                Tidak ada data potongan/beasiswa
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[100px]">Tipe</TableHead>
                                            <TableHead className="w-[120px] text-right">Nilai</TableHead>
                                            <TableHead>Jenis Biaya</TableHead>
                                            <TableHead className="w-[100px]">Berlaku</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-mono text-muted-foreground">{item.code}</TableCell>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{item.type_label}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">{item.value_formatted}</TableCell>
                                                <TableCell className="text-muted-foreground">{item.fee_type?.name ?? 'Semua'}</TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_valid ? 'default' : 'secondary'}>
                                                        {item.is_valid ? 'Berlaku' : 'Kadaluarsa'}
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
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Potongan/Beasiswa' : 'Tambah Potongan/Beasiswa'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data potongan/beasiswa' : 'Isi data potongan/beasiswa baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="discount-code">Kode *</Label>
                                <Input
                                    id="discount-code"
                                    placeholder="Contoh: BSW-01"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tipe *</Label>
                                <Select value={form.type} onValueChange={(value) => setForm({ ...form, type: value as DiscountForm['type'] })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {DISCOUNT_TYPES.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="discount-name">Nama *</Label>
                            <Input
                                id="discount-name"
                                placeholder="Contoh: Beasiswa Prestasi"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="discount-value">Nilai * {form.type === 'percentage' ? '(%)' : '(Rp)'}</Label>
                                <Input
                                    id="discount-value"
                                    type="number"
                                    min="0"
                                    max={form.type === 'percentage' ? '100' : undefined}
                                    placeholder={form.type === 'percentage' ? 'Contoh: 50' : 'Contoh: 500000'}
                                    value={form.value}
                                    onChange={(e) => setForm({ ...form, value: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Jenis Biaya</Label>
                                <Select
                                    value={form.fee_type_id || NONE_VALUE}
                                    onValueChange={(value) => setForm({ ...form, fee_type_id: value === NONE_VALUE ? '' : value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua jenis biaya" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value={NONE_VALUE}>Semua Jenis Biaya</SelectItem>
                                        {feeTypes.map((ft) => (
                                            <SelectItem key={ft.id} value={ft.id}>
                                                {ft.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="discount-description">Deskripsi</Label>
                            <Textarea
                                id="discount-description"
                                placeholder="Deskripsi potongan/beasiswa (opsional)"
                                rows={2}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="discount-from">Berlaku Dari</Label>
                                <DatePicker
                                    id="discount-from"
                                    value={form.valid_from}
                                    onChange={(value) => setForm({ ...form, valid_from: value })}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="discount-until">Berlaku Sampai</Label>
                                <DatePicker
                                    id="discount-until"
                                    value={form.valid_until}
                                    onChange={(value) => setForm({ ...form, valid_until: value })}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="discount-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Potongan aktif dapat diterapkan ke siswa
                                </p>
                            </div>
                            <Switch
                                id="discount-active"
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
                        <AlertDialogTitle>Hapus Potongan/Beasiswa</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus potongan/beasiswa{' '}
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
