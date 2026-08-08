import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { paymentMethodsApi } from '@/services/api';
import type { PaymentMethod, PaginationMeta } from '@/types';

const PAYMENT_TYPES = [
    { value: 'cash', label: 'Tunai' },
    { value: 'bank_transfer', label: 'Transfer Bank' },
    { value: 'virtual_account', label: 'Virtual Account' },
    { value: 'e_wallet', label: 'E-Wallet' },
    { value: 'credit_card', label: 'Kartu Kredit' },
    { value: 'other', label: 'Lainnya' },
];

interface PaymentMethodForm {
    code: string;
    name: string;
    // Diturunkan dari tipe domain, bukan `string`, supaya payload yang
    // dikirim ke API tetap cocok dan union-nya ikut tersinkron.
    type: NonNullable<PaymentMethod['type']>;
    provider: string;
    admin_fee: string;
    is_active: boolean;
}

const emptyForm: PaymentMethodForm = {
    code: '',
    name: '',
    type: 'cash',
    provider: '',
    admin_fee: '0',
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

export default function PaymentMethods() {
    const [items, setItems] = useState<PaymentMethod[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<PaymentMethod | null>(null);
    const [form, setForm] = useState<PaymentMethodForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<PaymentMethod | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();

            const response = await paymentMethodsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data metode pembayaran');
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

    const openEdit = (item: PaymentMethod) => {
        setEditingItem(item);
        setForm({
            code: item.code,
            name: item.name,
            type: item.type ?? 'cash',
            provider: item.provider ?? '',
            admin_fee: String(item.admin_fee),
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim()) {
            toast.error('Kode dan nama metode pembayaran wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                type: form.type,
                provider: form.provider.trim() || null,
                admin_fee: parseFloat(form.admin_fee) || 0,
                is_active: form.is_active,
            };

            if (editingItem) {
                await paymentMethodsApi.update(editingItem.id, payload);
                toast.success('Metode pembayaran berhasil diperbarui');
            } else {
                await paymentMethodsApi.create(payload);
                toast.success('Metode pembayaran berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan metode pembayaran'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await paymentMethodsApi.delete(deletingItem.id);
            toast.success('Metode pembayaran berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus metode pembayaran'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Metode Pembayaran">
            <Head title="Keuangan - Metode Pembayaran" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Metode Pembayaran</h1>
                        <p className="text-muted-foreground">
                            Kelola metode pembayaran yang tersedia (tunai, transfer, e-wallet, dll)
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Metode
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Metode Pembayaran</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} metode terdaftar` : 'Memuat data'}
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
                                Tidak ada data metode pembayaran
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[130px]">Tipe</TableHead>
                                            <TableHead>Provider</TableHead>
                                            <TableHead className="w-[130px] text-right">Biaya Admin</TableHead>
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
                                                <TableCell className="text-muted-foreground">{item.provider ?? '-'}</TableCell>
                                                <TableCell className="text-right font-mono">{item.admin_fee_formatted}</TableCell>
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
                        <DialogTitle>{editingItem ? 'Edit Metode Pembayaran' : 'Tambah Metode Pembayaran'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data metode pembayaran' : 'Isi data metode pembayaran baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="pm-code">Kode *</Label>
                                <Input
                                    id="pm-code"
                                    placeholder="Contoh: BCA"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tipe *</Label>
                                <Select value={form.type} onValueChange={(value) => setForm({ ...form, type: value as PaymentMethodForm['type'] })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PAYMENT_TYPES.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="pm-name">Nama *</Label>
                            <Input
                                id="pm-name"
                                placeholder="Contoh: Bank BCA"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="pm-provider">Provider</Label>
                                <Input
                                    id="pm-provider"
                                    placeholder="Contoh: Midtrans"
                                    value={form.provider}
                                    onChange={(e) => setForm({ ...form, provider: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pm-fee">Biaya Admin (Rp)</Label>
                                <Input
                                    id="pm-fee"
                                    type="number"
                                    min="0"
                                    placeholder="0"
                                    value={form.admin_fee}
                                    onChange={(e) => setForm({ ...form, admin_fee: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="pm-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Metode aktif dapat dipilih saat pembayaran
                                </p>
                            </div>
                            <Switch
                                id="pm-active"
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
                        <AlertDialogTitle>Hapus Metode Pembayaran</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus metode pembayaran{' '}
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
