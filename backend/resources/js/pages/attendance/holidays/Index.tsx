import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Plus, RefreshCw, Trash2, Edit, Wand2 } from 'lucide-react';
import { holidayApi } from '@/services/attendance';
import type { Holiday, HolidayFormData } from '@/types/attendance';

const months = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 5 }, (_, i) => currentYear - 1 + i);

export default function HolidayIndex() {
    const [holidays, setHolidays] = useState<Holiday[]>([]);
    const [loading, setLoading] = useState(false);
    const [monthFilter, setMonthFilter] = useState<string>(String(new Date().getMonth() + 1));
    const [yearFilter, setYearFilter] = useState<string>(String(currentYear));
    const [selectedIds, setSelectedIds] = useState<string[]>([]);
    const [editingHoliday, setEditingHoliday] = useState<Holiday | null>(null);
    const [showCreateDialog, setShowCreateDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [deletingId, setDeletingId] = useState<string | null>(null);
    const [showGenerateDialog, setShowGenerateDialog] = useState(false);
    const [form, setForm] = useState<HolidayFormData>({
        tanggal: new Date().toISOString().split('T')[0],
        keterangan: '',
        is_recurring: false,
    });

    const fetchHolidays = async () => {
        setLoading(true);
        try {
            const response = await holidayApi.list({
                month: parseInt(monthFilter),
                year: parseInt(yearFilter),
            });
            if (response.data.data) {
                setHolidays(response.data.data);
            }
        } catch (error) {
            toast.error('Gagal memuat data hari libur');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchHolidays();
    }, [monthFilter, yearFilter]);

    const handleCreate = async () => {
        if (!form.tanggal || !form.keterangan.trim()) {
            toast.error('Tanggal dan keterangan harus diisi');
            return;
        }

        try {
            await holidayApi.create(form);
            toast.success('Hari libur berhasil ditambahkan');
            setShowCreateDialog(false);
            setForm({ tanggal: new Date().toISOString().split('T')[0], keterangan: '', is_recurring: false });
            fetchHolidays();
        } catch (error) {
            toast.error('Gagal menambahkan hari libur');
        }
    };

    const handleUpdate = async () => {
        if (!editingHoliday || !form.keterangan.trim()) {
            toast.error('Keterangan harus diisi');
            return;
        }

        try {
            await holidayApi.update(editingHoliday.id, form);
            toast.success('Hari libur berhasil diperbarui');
            setEditingHoliday(null);
            fetchHolidays();
        } catch (error) {
            toast.error('Gagal memperbarui hari libur');
        }
    };

    const handleDelete = async () => {
        if (!deletingId) return;

        try {
            await holidayApi.delete(deletingId);
            toast.success('Hari libur berhasil dihapus');
            setShowDeleteDialog(false);
            setDeletingId(null);
            fetchHolidays();
        } catch (error) {
            toast.error('Gagal menghapus hari libur');
        }
    };

    const handleBulkDelete = async () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih hari libur yang akan dihapus');
            return;
        }

        try {
            await holidayApi.bulkDelete(selectedIds);
            toast.success(`${selectedIds.length} hari libur berhasil dihapus`);
            setSelectedIds([]);
            fetchHolidays();
        } catch (error) {
            toast.error('Gagal menghapus hari libur');
        }
    };

    const handleGenerateWeekends = async () => {
        try {
            const response = await holidayApi.generateWeekends(parseInt(monthFilter), parseInt(yearFilter));
            const count = response.data.data?.count || 0;
            toast.success(`${count} hari libur weekend berhasil ditambahkan`);
            setShowGenerateDialog(false);
            fetchHolidays();
        } catch (error) {
            toast.error('Gagal generate hari libur weekend');
        }
    };

    const openEditDialog = (holiday: Holiday) => {
        setEditingHoliday(holiday);
        setForm({
            tanggal: holiday.tanggal,
            keterangan: holiday.keterangan,
            is_recurring: holiday.is_recurring,
        });
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === holidays.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(holidays.map(h => h.id));
        }
    };

    const toggleSelect = (id: string) => {
        setSelectedIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    return (
        <MainLayout title="Hari Libur">
            <Head title="Hari Libur" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Hari Libur</h1>
                        <p className="text-muted-foreground">
                            Kelola kalender hari libur sekolah
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={() => setShowGenerateDialog(true)}>
                            <Wand2 className="mr-2 h-4 w-4" />
                            Generate Weekend
                        </Button>
                        <Button onClick={() => setShowCreateDialog(true)}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Libur
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4">
                            <div className="w-[180px]">
                                <Label>Bulan</Label>
                                <Select value={monthFilter} onValueChange={setMonthFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {months.map((m) => (
                                            <SelectItem key={m.value} value={m.value}>
                                                {m.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-[120px]">
                                <Label>Tahun</Label>
                                <Select value={yearFilter} onValueChange={setYearFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {years.map((y) => (
                                            <SelectItem key={y} value={String(y)}>
                                                {y}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button onClick={fetchHolidays} variant="outline" disabled={loading}>
                                    <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                                {selectedIds.length > 0 && (
                                    <Button variant="destructive" onClick={handleBulkDelete}>
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Hapus ({selectedIds.length})
                                    </Button>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Hari Libur</CardTitle>
                        <CardDescription>
                            {holidays.length} hari libur di {months.find(m => m.value === monthFilter)?.label} {yearFilter}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : holidays.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada hari libur di bulan ini
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[50px]">
                                                <Checkbox
                                                    checked={selectedIds.length === holidays.length}
                                                    onCheckedChange={toggleSelectAll}
                                                />
                                            </TableHead>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Keterangan</TableHead>
                                            <TableHead>Berulang</TableHead>
                                            <TableHead className="w-[100px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {holidays.map((holiday) => (
                                            <TableRow key={holiday.id}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={selectedIds.includes(holiday.id)}
                                                        onCheckedChange={() => toggleSelect(holiday.id)}
                                                    />
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {formatDate(holiday.tanggal)}
                                                </TableCell>
                                                <TableCell>{holiday.keterangan}</TableCell>
                                                <TableCell>
                                                    {holiday.is_recurring ? (
                                                        <Badge variant="secondary">Ya</Badge>
                                                    ) : (
                                                        <Badge variant="outline">Tidak</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEditDialog(holiday)}
                                                        >
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-red-600 hover:text-red-700"
                                                            onClick={() => {
                                                                setDeletingId(holiday.id);
                                                                setShowDeleteDialog(true);
                                                            }}
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
                    </CardContent>
                </Card>
            </div>

            {/* Create Dialog */}
            <Dialog open={showCreateDialog} onOpenChange={setShowCreateDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah Hari Libur</DialogTitle>
                        <DialogDescription>
                            Tambahkan hari libur baru ke kalender
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Tanggal</Label>
                            <Input
                                type="date"
                                value={form.tanggal}
                                onChange={(e) => setForm(prev => ({ ...prev, tanggal: e.target.value }))}
                            />
                        </div>
                        <div>
                            <Label>Keterangan</Label>
                            <Input
                                value={form.keterangan}
                                onChange={(e) => setForm(prev => ({ ...prev, keterangan: e.target.value }))}
                                placeholder="Contoh: Hari Kemerdekaan RI"
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_recurring"
                                checked={form.is_recurring}
                                onCheckedChange={(checked) => setForm(prev => ({ ...prev, is_recurring: !!checked }))}
                            />
                            <Label htmlFor="is_recurring">Berulang setiap tahun</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowCreateDialog(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleCreate}>Simpan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={!!editingHoliday} onOpenChange={() => setEditingHoliday(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Edit Hari Libur</DialogTitle>
                        <DialogDescription>
                            Perbarui informasi hari libur
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Tanggal</Label>
                            <Input
                                type="date"
                                value={form.tanggal}
                                onChange={(e) => setForm(prev => ({ ...prev, tanggal: e.target.value }))}
                            />
                        </div>
                        <div>
                            <Label>Keterangan</Label>
                            <Input
                                value={form.keterangan}
                                onChange={(e) => setForm(prev => ({ ...prev, keterangan: e.target.value }))}
                            />
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="edit_is_recurring"
                                checked={form.is_recurring}
                                onCheckedChange={(checked) => setForm(prev => ({ ...prev, is_recurring: !!checked }))}
                            />
                            <Label htmlFor="edit_is_recurring">Berulang setiap tahun</Label>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditingHoliday(null)}>
                            Batal
                        </Button>
                        <Button onClick={handleUpdate}>Simpan</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Hari Libur</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus hari libur ini? Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Generate Weekend Dialog */}
            <AlertDialog open={showGenerateDialog} onOpenChange={setShowGenerateDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Generate Hari Libur Weekend</AlertDialogTitle>
                        <AlertDialogDescription>
                            Otomatis menambahkan Sabtu dan Minggu sebagai hari libur untuk{' '}
                            {months.find(m => m.value === monthFilter)?.label} {yearFilter}.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleGenerateWeekends}>
                            Generate
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
