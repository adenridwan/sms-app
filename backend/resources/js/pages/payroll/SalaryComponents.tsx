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
import { Plus, Pencil, Trash2, RefreshCw, Search, TrendingUp, TrendingDown } from 'lucide-react';
import { salaryComponentsApi } from '@/services/api';
import type { SalaryComponent, PaginationMeta } from '@/types';

const COMPONENT_TYPES = [
    { value: 'earning', label: 'Pendapatan', icon: TrendingUp, color: 'text-green-600' },
    { value: 'deduction', label: 'Potongan', icon: TrendingDown, color: 'text-red-600' },
];

const CALCULATION_TYPES = [
    { value: 'fixed', label: 'Nominal Tetap' },
    { value: 'percentage', label: 'Persentase' },
    { value: 'per_day', label: 'Per Hari' },
    { value: 'per_hour', label: 'Per Jam' },
    { value: 'formula', label: 'Rumus Khusus' },
];

interface SalaryComponentForm {
    code: string;
    name: string;
    type: string;
    calculation_type: string;
    default_value: string;
    percentage_of: string;
    formula: string;
    is_taxable: boolean;
    is_mandatory: boolean;
    is_active: boolean;
    order: string;
    description: string;
}

const emptyForm: SalaryComponentForm = {
    code: '',
    name: '',
    type: 'earning',
    calculation_type: 'fixed',
    default_value: '',
    percentage_of: '',
    formula: '',
    is_taxable: true,
    is_mandatory: false,
    is_active: true,
    order: '0',
    description: '',
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

export default function SalaryComponents() {
    const [items, setItems] = useState<SalaryComponent[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [page, setPage] = useState(1);
    const [filterType, setFilterType] = useState<string>('');

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<SalaryComponent | null>(null);
    const [form, setForm] = useState<SalaryComponentForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<SalaryComponent | null>(null);
    const [deleting, setDeleting] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (filterType) params.type = filterType;

            const response = await salaryComponentsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data komponen gaji');
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

    const openEdit = (item: SalaryComponent) => {
        setEditingItem(item);
        setForm({
            code: item.code,
            name: item.name,
            type: item.type,
            calculation_type: item.calculation_type,
            default_value: String(item.default_value),
            percentage_of: item.percentage_of ?? '',
            formula: item.formula ?? '',
            is_taxable: item.is_taxable,
            is_mandatory: item.is_mandatory,
            is_active: item.is_active,
            order: String(item.order),
            description: item.description ?? '',
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.code.trim() || !form.name.trim() || !form.default_value) {
            toast.error('Kode, nama, dan nilai default wajib diisi');
            return;
        }

        const value = parseFloat(form.default_value);
        if (form.calculation_type === 'percentage' && value > 100) {
            toast.error('Nilai persentase tidak boleh lebih dari 100%');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                code: form.code.trim().toUpperCase(),
                name: form.name.trim(),
                type: form.type,
                calculation_type: form.calculation_type,
                default_value: value,
                percentage_of: form.percentage_of.trim() || null,
                formula: form.formula.trim() || null,
                is_taxable: form.is_taxable,
                is_mandatory: form.is_mandatory,
                is_active: form.is_active,
                order: parseInt(form.order) || 0,
                description: form.description.trim() || null,
            };

            if (editingItem) {
                await salaryComponentsApi.update(editingItem.id, payload);
                toast.success('Komponen gaji berhasil diperbarui');
            } else {
                await salaryComponentsApi.create(payload);
                toast.success('Komponen gaji berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan komponen gaji'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await salaryComponentsApi.delete(deletingItem.id);
            toast.success('Komponen gaji berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus komponen gaji'));
        } finally {
            setDeleting(false);
        }
    };

    return (
        <MainLayout title="Komponen Gaji">
            <Head title="Payroll - Komponen Gaji" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Komponen Gaji</h1>
                        <p className="text-muted-foreground">
                            Kelola komponen pendapatan dan potongan gaji
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Komponen
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Komponen Gaji</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} komponen terdaftar` : 'Memuat data'}
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
                            <Select value={filterType} onValueChange={(value) => { setFilterType(value === 'all' ? '' : value); setPage(1); }}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Semua Tipe" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Tipe</SelectItem>
                                    {COMPONENT_TYPES.map((type) => (
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
                                Tidak ada data komponen gaji
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[100px]">Kode</TableHead>
                                            <TableHead>Nama</TableHead>
                                            <TableHead className="w-[100px]">Tipe</TableHead>
                                            <TableHead className="w-[130px]">Kalkulasi</TableHead>
                                            <TableHead className="w-[130px] text-right">Nilai Default</TableHead>
                                            <TableHead className="w-[80px]">Pajak</TableHead>
                                            <TableHead className="w-[80px]">Wajib</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => {
                                            const TypeIcon = item.type === 'earning' ? TrendingUp : TrendingDown;
                                            return (
                                                <TableRow key={item.id}>
                                                    <TableCell className="font-mono text-muted-foreground">{item.code}</TableCell>
                                                    <TableCell className="font-medium">{item.name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={item.type === 'earning' ? 'default' : 'destructive'} className="gap-1">
                                                            <TypeIcon className="h-3 w-3" />
                                                            {item.type_label}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant="outline">{item.calculation_type_label}</Badge>
                                                    </TableCell>
                                                    <TableCell className="text-right font-mono">{item.default_value_formatted}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={item.is_taxable ? 'secondary' : 'outline'}>
                                                            {item.is_taxable ? 'Ya' : 'Tidak'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={item.is_mandatory ? 'default' : 'outline'}>
                                                            {item.is_mandatory ? 'Ya' : 'Tidak'}
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
                                            );
                                        })}
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
                        <DialogTitle>{editingItem ? 'Edit Komponen Gaji' : 'Tambah Komponen Gaji'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data komponen gaji' : 'Isi data komponen gaji baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 max-h-[60vh] overflow-y-auto pr-2">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="comp-code">Kode *</Label>
                                <Input
                                    id="comp-code"
                                    placeholder="Contoh: TJ-JABATAN"
                                    value={form.code}
                                    onChange={(e) => setForm({ ...form, code: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tipe *</Label>
                                <Select value={form.type} onValueChange={(value) => setForm({ ...form, type: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {COMPONENT_TYPES.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="comp-name">Nama *</Label>
                            <Input
                                id="comp-name"
                                placeholder="Contoh: Tunjangan Jabatan"
                                value={form.name}
                                onChange={(e) => setForm({ ...form, name: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Tipe Kalkulasi *</Label>
                                <Select value={form.calculation_type} onValueChange={(value) => setForm({ ...form, calculation_type: value })}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kalkulasi" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {CALCULATION_TYPES.map((type) => (
                                            <SelectItem key={type.value} value={type.value}>
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="comp-value">
                                    Nilai Default * {form.calculation_type === 'percentage' ? '(%)' : '(Rp)'}
                                </Label>
                                <Input
                                    id="comp-value"
                                    type="number"
                                    min="0"
                                    max={form.calculation_type === 'percentage' ? '100' : undefined}
                                    placeholder={form.calculation_type === 'percentage' ? 'Contoh: 5' : 'Contoh: 500000'}
                                    value={form.default_value}
                                    onChange={(e) => setForm({ ...form, default_value: e.target.value })}
                                />
                            </div>
                        </div>
                        {form.calculation_type === 'percentage' && (
                            <div className="space-y-2">
                                <Label htmlFor="comp-percentage-of">Persentase Dari</Label>
                                <Input
                                    id="comp-percentage-of"
                                    placeholder="Contoh: base_salary, gross_salary"
                                    value={form.percentage_of}
                                    onChange={(e) => setForm({ ...form, percentage_of: e.target.value })}
                                />
                            </div>
                        )}
                        {form.calculation_type === 'formula' && (
                            <div className="space-y-2">
                                <Label htmlFor="comp-formula">Rumus</Label>
                                <Textarea
                                    id="comp-formula"
                                    placeholder="Rumus kalkulasi khusus"
                                    rows={2}
                                    value={form.formula}
                                    onChange={(e) => setForm({ ...form, formula: e.target.value })}
                                />
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="comp-description">Deskripsi</Label>
                            <Textarea
                                id="comp-description"
                                placeholder="Deskripsi komponen gaji (opsional)"
                                rows={2}
                                value={form.description}
                                onChange={(e) => setForm({ ...form, description: e.target.value })}
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-2">
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label htmlFor="comp-taxable" className="text-sm">Kena Pajak</Label>
                                <Switch
                                    id="comp-taxable"
                                    checked={form.is_taxable}
                                    onCheckedChange={(checked) => setForm({ ...form, is_taxable: checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label htmlFor="comp-mandatory" className="text-sm">Wajib</Label>
                                <Switch
                                    id="comp-mandatory"
                                    checked={form.is_mandatory}
                                    onCheckedChange={(checked) => setForm({ ...form, is_mandatory: checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between rounded-md border p-3">
                                <Label htmlFor="comp-active" className="text-sm">Aktif</Label>
                                <Switch
                                    id="comp-active"
                                    checked={form.is_active}
                                    onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                                />
                            </div>
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
                        <AlertDialogTitle>Hapus Komponen Gaji</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus komponen gaji{' '}
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
