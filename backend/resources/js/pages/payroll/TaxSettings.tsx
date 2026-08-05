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
import { Plus, Pencil, Trash2, RefreshCw, Search, Settings } from 'lucide-react';
import { taxSettingsApi } from '@/services/api';
import type { TaxSetting, PaginationMeta } from '@/types';

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: 10 }, (_, i) => currentYear - 5 + i);

const CATEGORIES = [
    { value: 'ptkp', label: 'PTKP (Penghasilan Tidak Kena Pajak)' },
    { value: 'biaya_jabatan', label: 'Biaya Jabatan' },
    { value: 'ter', label: 'TER (Tarif Efektif Rata-rata)' },
    { value: 'other', label: 'Lainnya' },
];

const PTKP_KEYS = [
    { value: 'ptkp_tk0', label: 'TK/0 - Tidak Kawin, 0 Tanggungan' },
    { value: 'ptkp_tk1', label: 'TK/1 - Tidak Kawin, 1 Tanggungan' },
    { value: 'ptkp_tk2', label: 'TK/2 - Tidak Kawin, 2 Tanggungan' },
    { value: 'ptkp_tk3', label: 'TK/3 - Tidak Kawin, 3 Tanggungan' },
    { value: 'ptkp_k0', label: 'K/0 - Kawin, 0 Tanggungan' },
    { value: 'ptkp_k1', label: 'K/1 - Kawin, 1 Tanggungan' },
    { value: 'ptkp_k2', label: 'K/2 - Kawin, 2 Tanggungan' },
    { value: 'ptkp_k3', label: 'K/3 - Kawin, 3 Tanggungan' },
];

interface TaxSettingForm {
    setting_key: string;
    setting_name: string;
    setting_value: string;
    category: string;
    description: string;
    effective_year: string;
    effective_from: string;
    effective_until: string;
    is_active: boolean;
}

const emptyForm: TaxSettingForm = {
    setting_key: '',
    setting_name: '',
    setting_value: '',
    category: 'ptkp',
    description: '',
    effective_year: String(currentYear),
    effective_from: '',
    effective_until: '',
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

export default function TaxSettings() {
    const [items, setItems] = useState<TaxSetting[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [filterYear, setFilterYear] = useState<string>(String(currentYear));
    const [filterCategory, setFilterCategory] = useState<string>('');

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<TaxSetting | null>(null);
    const [form, setForm] = useState<TaxSettingForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<TaxSetting | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterYear) params.effective_year = parseInt(filterYear);
            if (filterCategory) params.category = filterCategory;

            const response = await taxSettingsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data pengaturan pajak');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterYear, filterCategory]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const openCreate = () => {
        setEditingItem(null);
        setForm({ ...emptyForm, effective_year: filterYear });
        setFormOpen(true);
    };

    const openEdit = (item: TaxSetting) => {
        setEditingItem(item);
        setForm({
            setting_key: item.setting_key,
            setting_name: item.setting_name,
            setting_value: formatCurrency(String(item.setting_value)),
            category: item.category,
            description: item.description ?? '',
            effective_year: String(item.effective_year),
            effective_from: item.effective_from ?? '',
            effective_until: item.effective_until ?? '',
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.setting_key.trim() || !form.setting_name.trim() || !form.setting_value) {
            toast.error('Key, nama, dan nilai wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                setting_key: form.setting_key.trim().toLowerCase(),
                setting_name: form.setting_name.trim(),
                setting_value: parseFloat(parseCurrency(form.setting_value)) || 0,
                category: form.category,
                description: form.description.trim() || null,
                effective_year: parseInt(form.effective_year),
                effective_from: form.effective_from || null,
                effective_until: form.effective_until || null,
                is_active: form.is_active,
            };

            if (editingItem) {
                await taxSettingsApi.update(editingItem.id, payload);
                toast.success('Pengaturan pajak berhasil diperbarui');
            } else {
                await taxSettingsApi.create(payload);
                toast.success('Pengaturan pajak berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan pengaturan pajak'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await taxSettingsApi.delete(deletingItem.id);
            toast.success('Pengaturan pajak berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus pengaturan pajak'));
        } finally {
            setDeleting(false);
        }
    };

    const handlePtkpKeySelect = (value: string) => {
        const ptkp = PTKP_KEYS.find((p) => p.value === value);
        if (ptkp) {
            setForm({
                ...form,
                setting_key: ptkp.value,
                setting_name: ptkp.label,
            });
        }
    };

    return (
        <MainLayout title="Pengaturan Pajak">
            <Head title="Payroll - Pengaturan Pajak" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pengaturan Pajak</h1>
                        <p className="text-muted-foreground">
                            Kelola PTKP, biaya jabatan, dan pengaturan pajak lainnya
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Pengaturan
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Settings className="h-5 w-5" />
                            Daftar Pengaturan Pajak
                        </CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} pengaturan terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari key atau nama..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select value={filterYear} onValueChange={(value) => { setFilterYear(value); setPage(1); }}>
                                <SelectTrigger className="w-[130px]">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((year) => (
                                        <SelectItem key={year} value={String(year)}>
                                            {year}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select value={filterCategory} onValueChange={(value) => { setFilterCategory(value === 'all' ? '' : value); setPage(1); }}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Semua Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Kategori</SelectItem>
                                    {CATEGORIES.map((cat) => (
                                        <SelectItem key={cat.value} value={cat.value}>
                                            {cat.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="icon" onClick={fetchData} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : items.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data pengaturan pajak
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[140px]">Key</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[120px]">Kategori</TableHead>
                                            <TableHead className="w-[150px] text-right">Nilai</TableHead>
                                            <TableHead className="w-[80px]">Tahun</TableHead>
                                            <TableHead className="w-[80px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-mono text-xs text-muted-foreground">{item.setting_key}</TableCell>
                                                <TableCell className="font-medium">{item.setting_name}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{item.category_label}</Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">{item.setting_value_formatted}</TableCell>
                                                <TableCell className="text-center">{item.effective_year}</TableCell>
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
                        <DialogTitle>{editingItem ? 'Edit Pengaturan Pajak' : 'Tambah Pengaturan Pajak'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data pengaturan pajak' : 'Isi data pengaturan pajak baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Kategori *</Label>
                                <Select value={form.category} onValueChange={(value) => setForm({ ...form, category: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CATEGORIES.map((cat) => (
                                            <SelectItem key={cat.value} value={cat.value}>
                                                {cat.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Tahun Efektif *</Label>
                                <Select value={form.effective_year} onValueChange={(value) => setForm({ ...form, effective_year: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tahun" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {YEARS.map((year) => (
                                            <SelectItem key={year} value={String(year)}>
                                                {year}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        {form.category === 'ptkp' && !editingItem && (
                            <div className="space-y-2">
                                <Label>Pilih PTKP</Label>
                                <Select onValueChange={handlePtkpKeySelect}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih status PTKP" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PTKP_KEYS.map((ptkp) => (
                                            <SelectItem key={ptkp.value} value={ptkp.value}>
                                                {ptkp.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="tax-key">Key *</Label>
                                <Input
                                    id="tax-key"
                                    placeholder="Contoh: ptkp_tk0"
                                    value={form.setting_key}
                                    onChange={(e) => setForm({ ...form, setting_key: e.target.value })}
                                    disabled={!!editingItem}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tax-value">Nilai (Rp) *</Label>
                                <Input
                                    id="tax-value"
                                    placeholder="Contoh: 54.000.000"
                                    value={form.setting_value}
                                    onChange={(e) => setForm({ ...form, setting_value: formatCurrency(e.target.value) })}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tax-name">Nama *</Label>
                            <Input
                                id="tax-name"
                                placeholder="Contoh: PTKP TK/0 - Tidak Kawin, 0 Tanggungan"
                                value={form.setting_name}
                                onChange={(e) => setForm({ ...form, setting_name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tax-description">Deskripsi</Label>
                            <Textarea
                                id="tax-description"
                                placeholder="Deskripsi tambahan (opsional)"
                                rows={2}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="tax-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Pengaturan aktif digunakan dalam perhitungan pajak
                                </p>
                            </div>
                            <Switch
                                id="tax-active"
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
                        <AlertDialogTitle>Hapus Pengaturan Pajak</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus pengaturan{' '}
                            <span className="font-medium">{deletingItem?.setting_name}</span>? Tindakan ini tidak dapat dibatalkan.
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
