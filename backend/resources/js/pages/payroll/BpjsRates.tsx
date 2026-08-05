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
import { Plus, Pencil, Trash2, RefreshCw, Search, Shield } from 'lucide-react';
import { bpjsRatesApi } from '@/services/api';
import type { BpjsRate, PaginationMeta } from '@/types';

const BPJS_TYPES = [
    { value: 'kesehatan', label: 'BPJS Kesehatan' },
    { value: 'jht', label: 'Jaminan Hari Tua (JHT)' },
    { value: 'jkk', label: 'Jaminan Kecelakaan Kerja (JKK)' },
    { value: 'jkm', label: 'Jaminan Kematian (JKM)' },
    { value: 'jp', label: 'Jaminan Pensiun (JP)' },
];

interface BpjsRateForm {
    type: string;
    name: string;
    employee_rate: string;
    employer_rate: string;
    min_salary: string;
    max_salary: string;
    effective_from: string;
    effective_until: string;
    is_active: boolean;
    notes: string;
}

const emptyForm: BpjsRateForm = {
    type: 'kesehatan',
    name: '',
    employee_rate: '',
    employer_rate: '',
    min_salary: '',
    max_salary: '',
    effective_from: '',
    effective_until: '',
    is_active: true,
    notes: '',
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

export default function BpjsRates() {
    const [items, setItems] = useState<BpjsRate[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [filterType, setFilterType] = useState<string>('');

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<BpjsRate | null>(null);
    const [form, setForm] = useState<BpjsRateForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<BpjsRate | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterType) params.type = filterType;

            const response = await bpjsRatesApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data tarif BPJS');
        } finally {
            setLoading(false);
        }
    }, [page, search, filterType]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const openCreate = () => {
        setEditingItem(null);
        setForm(emptyForm);
        setFormOpen(true);
    };

    const openEdit = (item: BpjsRate) => {
        setEditingItem(item);
        setForm({
            type: item.type,
            name: item.name,
            employee_rate: String(item.employee_rate),
            employer_rate: String(item.employer_rate),
            min_salary: item.min_salary ? String(item.min_salary) : '',
            max_salary: item.max_salary ? String(item.max_salary) : '',
            effective_from: item.effective_from,
            effective_until: item.effective_until ?? '',
            is_active: item.is_active,
            notes: item.notes ?? '',
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.name.trim() || !form.effective_from) {
            toast.error('Nama dan tanggal berlaku wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                type: form.type,
                name: form.name.trim(),
                employee_rate: parseFloat(form.employee_rate) || 0,
                employer_rate: parseFloat(form.employer_rate) || 0,
                min_salary: form.min_salary ? parseFloat(form.min_salary) : null,
                max_salary: form.max_salary ? parseFloat(form.max_salary) : null,
                effective_from: form.effective_from,
                effective_until: form.effective_until || null,
                is_active: form.is_active,
                notes: form.notes.trim() || null,
            };

            if (editingItem) {
                await bpjsRatesApi.update(editingItem.id, payload);
                toast.success('Tarif BPJS berhasil diperbarui');
            } else {
                await bpjsRatesApi.create(payload);
                toast.success('Tarif BPJS berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan tarif BPJS'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await bpjsRatesApi.delete(deletingItem.id);
            toast.success('Tarif BPJS berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus tarif BPJS'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Tarif BPJS">
            <Head title="Payroll - Tarif BPJS" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tarif BPJS</h1>
                        <p className="text-muted-foreground">
                            Kelola tarif iuran BPJS Kesehatan dan Ketenagakerjaan
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Tarif
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Daftar Tarif BPJS
                        </CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} tarif terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select value={filterType} onValueChange={(value) => { setFilterType(value === 'all' ? '' : value); setPage(1); }}>
                                <SelectTrigger className="w-[200px]">
                                    <SelectValue placeholder="Semua Tipe BPJS" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Tipe BPJS</SelectItem>
                                    {BPJS_TYPES.map((type) => (
                                        <SelectItem key={type.value} value={type.value}>
                                            {type.label}
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
                                Tidak ada data tarif BPJS
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[100px] text-right">Karyawan</TableHead>
                                            <TableHead className="w-[100px] text-right">Perusahaan</TableHead>
                                            <TableHead className="w-[100px] text-right">Total</TableHead>
                                            <TableHead className="w-[120px]">Berlaku Dari</TableHead>
                                            <TableHead className="w-[80px]">Efektif</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    <Badge variant="outline">{item.type_label}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">{item.name}</TableCell>
                                                <TableCell className="text-right font-mono">{item.employee_rate_formatted}</TableCell>
                                                <TableCell className="text-right font-mono">{item.employer_rate_formatted}</TableCell>
                                                <TableCell className="text-right font-mono font-semibold">{item.total_rate_formatted}</TableCell>
                                                <TableCell className="text-muted-foreground">{item.effective_from}</TableCell>
                                                <TableCell>
                                                    <Badge variant={item.is_effective ? 'default' : 'secondary'}>
                                                        {item.is_effective ? 'Ya' : 'Tidak'}
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
                        <DialogTitle>{editingItem ? 'Edit Tarif BPJS' : 'Tambah Tarif BPJS'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data tarif BPJS' : 'Isi data tarif BPJS baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tipe BPJS *</Label>
                                <Select value={form.type} onValueChange={(value) => setForm({ ...form, type: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {BPJS_TYPES.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-name">Nama *</Label>
                                <Input
                                    id="bpjs-name"
                                    placeholder="Contoh: BPJS Kesehatan 2024"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-employee">Tarif Karyawan (%)</Label>
                                <Input
                                    id="bpjs-employee"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="Contoh: 1"
                                    value={form.employee_rate}
                                    onChange={(e) => setForm({ ...form, employee_rate: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-employer">Tarif Perusahaan (%)</Label>
                                <Input
                                    id="bpjs-employer"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    placeholder="Contoh: 4"
                                    value={form.employer_rate}
                                    onChange={(e) => setForm({ ...form, employer_rate: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-min">Batas Gaji Min (Rp)</Label>
                                <Input
                                    id="bpjs-min"
                                    type="number"
                                    min="0"
                                    placeholder="Kosongkan jika tidak ada"
                                    value={form.min_salary}
                                    onChange={(e) => setForm({ ...form, min_salary: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-max">Batas Gaji Max (Rp)</Label>
                                <Input
                                    id="bpjs-max"
                                    type="number"
                                    min="0"
                                    placeholder="Kosongkan jika tidak ada"
                                    value={form.max_salary}
                                    onChange={(e) => setForm({ ...form, max_salary: e.target.value })}
                                />
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-from">Berlaku Dari *</Label>
                                <DatePicker
                                    id="bpjs-from"
                                    value={form.effective_from}
                                    onChange={(value) => setForm({ ...form, effective_from: value })}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="bpjs-until">Berlaku Sampai</Label>
                                <DatePicker
                                    id="bpjs-until"
                                    value={form.effective_until}
                                    onChange={(value) => setForm({ ...form, effective_until: value })}
                                    placeholder="Pilih tanggal"
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="bpjs-notes">Catatan</Label>
                            <Textarea
                                id="bpjs-notes"
                                placeholder="Catatan tambahan (opsional)"
                                rows={2}
                                value={form.notes}
                                onChange={(e) => setForm({ ...form, notes: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="bpjs-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Tarif aktif digunakan dalam perhitungan gaji
                                </p>
                            </div>
                            <Switch
                                id="bpjs-active"
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
                        <AlertDialogTitle>Hapus Tarif BPJS</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus tarif BPJS{' '}
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
