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
import { Plus, Pencil, Trash2, RefreshCw, Calculator } from 'lucide-react';
import { taxBracketsApi } from '@/services/api';
import type { TaxBracket, PaginationMeta } from '@/types';

const currentYear = new Date().getFullYear();
const YEARS = Array.from({ length: 10 }, (_, i) => currentYear - 5 + i);

interface TaxBracketForm {
    min_amount: string;
    max_amount: string;
    rate: string;
    effective_year: string;
    effective_from: string;
    effective_until: string;
    is_active: boolean;
}

const emptyForm: TaxBracketForm = {
    min_amount: '',
    max_amount: '',
    rate: '',
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

export default function TaxBrackets() {
    const [items, setItems] = useState<TaxBracket[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [page, setPage] = useState(1);
    const [filterYear, setFilterYear] = useState<string>(String(currentYear));

    const [formOpen, setFormOpen] = useState(false);
    const [editingItem, setEditingItem] = useState<TaxBracket | null>(null);
    const [form, setForm] = useState<TaxBracketForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    const [deletingItem, setDeletingItem] = useState<TaxBracket | null>(null);
    const [deleting, setDeleting] = useState(false);

    // Calculator
    const [calcOpen, setCalcOpen] = useState(false);
    const [calcPkp, setCalcPkp] = useState('');
    const [calcYear, setCalcYear] = useState(String(currentYear));
    const [calcResult, setCalcResult] = useState<{ pkp_formatted: string; tax_formatted: string; effective_rate: number } | null>(null);
    const [calculating, setCalculating] = useState(false);

    const fetchData = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (filterYear) params.effective_year = parseInt(filterYear);

            const response = await taxBracketsApi.list(params);
            const payload = response.data.data;
            setItems(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data tarif pajak');
        } finally {
            setLoading(false);
        }
    }, [page, filterYear]);

    useEffect(() => {
        fetchData();
    }, [fetchData]);

    const openCreate = () => {
        setEditingItem(null);
        setForm({ ...emptyForm, effective_year: filterYear });
        setFormOpen(true);
    };

    const openEdit = (item: TaxBracket) => {
        setEditingItem(item);
        setForm({
            min_amount: formatCurrency(String(item.min_amount)),
            max_amount: item.max_amount ? formatCurrency(String(item.max_amount)) : '',
            rate: String(item.rate),
            effective_year: String(item.effective_year),
            effective_from: item.effective_from ?? '',
            effective_until: item.effective_until ?? '',
            is_active: item.is_active,
        });
        setFormOpen(true);
    };

    const handleSubmit = async () => {
        if (!form.min_amount || !form.rate) {
            toast.error('Batas minimum dan tarif wajib diisi');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                min_amount: parseFloat(parseCurrency(form.min_amount)) || 0,
                max_amount: form.max_amount ? parseFloat(parseCurrency(form.max_amount)) : null,
                rate: parseFloat(form.rate) || 0,
                effective_year: parseInt(form.effective_year),
                effective_from: form.effective_from || null,
                effective_until: form.effective_until || null,
                is_active: form.is_active,
            };

            if (editingItem) {
                await taxBracketsApi.update(editingItem.id, payload);
                toast.success('Tarif pajak berhasil diperbarui');
            } else {
                await taxBracketsApi.create(payload);
                toast.success('Tarif pajak berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan tarif pajak'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingItem || deleting) return;
        setDeleting(true);
        try {
            await taxBracketsApi.delete(deletingItem.id);
            toast.success('Tarif pajak berhasil dihapus');
            setDeletingItem(null);
            fetchData();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus tarif pajak'));
        } finally {
            setDeleting(false);
        }
    };

    const handleCalculate = async () => {
        if (!calcPkp) {
            toast.error('PKP wajib diisi');
            return;
        }

        setCalculating(true);
        try {
            const response = await taxBracketsApi.calculate({
                pkp: parseFloat(parseCurrency(calcPkp)),
                year: parseInt(calcYear),
            });
            setCalcResult(response.data.data);
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghitung pajak'));
        } finally {
            setCalculating(false);
        }
    };

    return (
        <MainLayout title="Tarif Pajak PPh 21">
            <Head title="Payroll - Tarif Pajak PPh 21" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Tarif Pajak PPh 21</h1>
                        <p className="text-muted-foreground">
                            Kelola tarif pajak progresif PPh 21 per tahun
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setCalcOpen(true)}>
                            <Calculator className="mr-2 h-4 w-4" />
                            Kalkulator
                        </Button>
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Tarif
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Tarif Pajak Progresif</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} bracket tarif terdaftar` : 'Memuat data'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <Select value={filterYear} onValueChange={(value) => { setFilterYear(value); setPage(1); }}>
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Tahun" />
                                </SelectTrigger>
                                <SelectContent>
                                    {YEARS.map((year) => (
                                        <SelectItem key={year} value={String(year)}>
                                            Tahun {year}
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
                                Tidak ada data tarif pajak untuk tahun {filterYear}
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Rentang PKP</TableHead>
                                            <TableHead className="w-[150px] text-right">Batas Minimum</TableHead>
                                            <TableHead className="w-[150px] text-right">Batas Maksimum</TableHead>
                                            <TableHead className="w-[100px] text-right">Tarif</TableHead>
                                            <TableHead className="w-[100px]">Status</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell className="font-medium">{item.range_label}</TableCell>
                                                <TableCell className="text-right font-mono">{item.min_amount_formatted}</TableCell>
                                                <TableCell className="text-right font-mono">{item.max_amount_formatted}</TableCell>
                                                <TableCell className="text-right font-mono font-semibold">{item.rate_formatted}</TableCell>
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

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editingItem ? 'Edit Tarif Pajak' : 'Tambah Tarif Pajak'}</DialogTitle>
                        <DialogDescription>
                            {editingItem ? 'Perbarui data tarif pajak' : 'Isi data tarif pajak baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label>Tahun Pajak *</Label>
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
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="tax-min">Batas Minimum (Rp) *</Label>
                                <Input
                                    id="tax-min"
                                    placeholder="Contoh: 0"
                                    value={form.min_amount}
                                    onChange={(e) => setForm({ ...form, min_amount: formatCurrency(e.target.value) })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tax-max">Batas Maksimum (Rp)</Label>
                                <Input
                                    id="tax-max"
                                    placeholder="Kosongkan jika tidak terbatas"
                                    value={form.max_amount}
                                    onChange={(e) => setForm({ ...form, max_amount: formatCurrency(e.target.value) })}
                                />
                            </div>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="tax-rate">Tarif (%) *</Label>
                            <Input
                                id="tax-rate"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                placeholder="Contoh: 5"
                                value={form.rate}
                                onChange={(e) => setForm({ ...form, rate: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3">
                            <div>
                                <Label htmlFor="tax-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Tarif aktif digunakan dalam perhitungan PPh 21
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

            {/* Calculator Dialog */}
            <Dialog open={calcOpen} onOpenChange={setCalcOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Calculator className="h-5 w-5" />
                            Kalkulator PPh 21
                        </DialogTitle>
                        <DialogDescription>
                            Hitung pajak progresif berdasarkan PKP (Penghasilan Kena Pajak)
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="calc-pkp">PKP (Rp)</Label>
                                <Input
                                    id="calc-pkp"
                                    placeholder="Contoh: 100.000.000"
                                    value={calcPkp}
                                    onChange={(e) => {
                                        setCalcPkp(formatCurrency(e.target.value));
                                        setCalcResult(null);
                                    }}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Tahun Pajak</Label>
                                <Select value={calcYear} onValueChange={(value) => { setCalcYear(value); setCalcResult(null); }}>
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
                        {calcResult && (
                            <div className="rounded-lg border bg-muted/50 p-4 space-y-2">
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">PKP:</span>
                                    <span className="font-mono">{calcResult.pkp_formatted}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">PPh 21 Terutang:</span>
                                    <span className="font-mono font-semibold text-primary">{calcResult.tax_formatted}</span>
                                </div>
                                <div className="flex justify-between text-sm">
                                    <span className="text-muted-foreground">Tarif Efektif:</span>
                                    <span className="font-mono">{calcResult.effective_rate}%</span>
                                </div>
                            </div>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCalcOpen(false)}>
                            Tutup
                        </Button>
                        <Button onClick={handleCalculate} disabled={calculating}>
                            {calculating && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            Hitung
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingItem} onOpenChange={(open) => !open && !deleting && setDeletingItem(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Tarif Pajak</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus tarif pajak rentang{' '}
                            <span className="font-medium">{deletingItem?.range_label}</span>? Tindakan ini tidak dapat dibatalkan.
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
