import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
import { Badge } from '@/components/ui/badge';
import {
    Search,
    Plus,
    Loader2,
    RefreshCw,
    Trophy,
    Pencil,
    Trash2,
    FileText,
    Medal,
    Award,
} from 'lucide-react';
import { achievementApi } from '@/services/api';

interface StudentOption {
    id: string;
    nis: string;
    full_name: string;
    class: string | null;
}

interface CategoryOption {
    value: string;
    label: string;
}

interface LevelOption {
    value: string;
    label: string;
}

interface Achievement {
    id: string;
    student_id: string;
    student?: {
        id: string;
        nis: string;
        full_name: string;
        photo_url: string | null;
    };
    title: string;
    description: string | null;
    category: string;
    category_label: string;
    level: string;
    level_label: string;
    rank: string | null;
    achievement_date: string;
    organizer: string | null;
    certificate_url: string | null;
}

interface Props {
    students: StudentOption[];
    categories: CategoryOption[];
    levels: LevelOption[];
}

export default function StudentAchievements({ students, categories, levels }: Props) {
    const [achievements, setAchievements] = useState<Achievement[]>([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [filterCategory, setFilterCategory] = useState('');
    const [filterLevel, setFilterLevel] = useState('');
    const [filterStudent, setFilterStudent] = useState('');

    // Form state
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Achievement | null>(null);
    const [saving, setSaving] = useState(false);
    const [formData, setFormData] = useState({
        student_id: '',
        title: '',
        description: '',
        category: '',
        level: '',
        rank: '',
        achievement_date: '',
        organizer: '',
    });
    const [certificateFile, setCertificateFile] = useState<File | null>(null);

    // Delete state
    const [deleting, setDeleting] = useState<Achievement | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const loadAchievements = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (filterCategory) params.category = filterCategory;
            if (filterLevel) params.level = filterLevel;
            if (filterStudent) params.student_id = filterStudent;

            const response = await achievementApi.list(params);
            setAchievements(response.data.data || []);
        } catch {
            toast.error('Gagal memuat data prestasi');
        } finally {
            setLoading(false);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        loadAchievements();
    };

    const openCreateForm = () => {
        setEditing(null);
        setFormData({
            student_id: '',
            title: '',
            description: '',
            category: '',
            level: '',
            rank: '',
            achievement_date: '',
            organizer: '',
        });
        setCertificateFile(null);
        setFormOpen(true);
    };

    const openEditForm = (achievement: Achievement) => {
        setEditing(achievement);
        setFormData({
            student_id: achievement.student_id,
            title: achievement.title,
            description: achievement.description || '',
            category: achievement.category,
            level: achievement.level,
            rank: achievement.rank || '',
            achievement_date: achievement.achievement_date,
            organizer: achievement.organizer || '',
        });
        setCertificateFile(null);
        setFormOpen(true);
    };

    const handleSave = async () => {
        if (!formData.student_id || !formData.title || !formData.category || !formData.level || !formData.achievement_date) {
            toast.error('Lengkapi semua field wajib');
            return;
        }

        setSaving(true);
        try {
            const data = new FormData();
            data.append('student_id', formData.student_id);
            data.append('title', formData.title);
            if (formData.description) data.append('description', formData.description);
            data.append('category', formData.category);
            data.append('level', formData.level);
            if (formData.rank) data.append('rank', formData.rank);
            data.append('achievement_date', formData.achievement_date);
            if (formData.organizer) data.append('organizer', formData.organizer);
            if (certificateFile) data.append('certificate', certificateFile);

            if (editing) {
                data.append('_method', 'PUT');
                await achievementApi.update(editing.id, data);
                toast.success('Prestasi berhasil diperbarui');
            } else {
                await achievementApi.create(data);
                toast.success('Prestasi berhasil ditambahkan');
            }

            setFormOpen(false);
            loadAchievements();
        } catch (error) {
            const message =
                (error as { response?: { data?: { message?: string } } }).response?.data?.message ||
                'Gagal menyimpan prestasi';
            toast.error(message);
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deleting) return;

        setDeleteLoading(true);
        try {
            await achievementApi.delete(deleting.id);
            toast.success('Prestasi berhasil dihapus');
            setDeleting(null);
            loadAchievements();
        } catch {
            toast.error('Gagal menghapus prestasi');
        } finally {
            setDeleteLoading(false);
        }
    };

    const getCategoryBadge = (category: string, label: string) => {
        const colors: Record<string, string> = {
            academic: 'bg-blue-100 text-blue-800',
            sports: 'bg-green-100 text-green-800',
            arts: 'bg-purple-100 text-purple-800',
            science: 'bg-orange-100 text-orange-800',
            other: 'bg-gray-100 text-gray-800',
        };
        return (
            <Badge variant="outline" className={colors[category] || colors.other}>
                {label}
            </Badge>
        );
    };

    const getLevelBadge = (level: string, label: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'outline'> = {
            international: 'default',
            national: 'default',
            province: 'secondary',
            city: 'secondary',
            district: 'outline',
            school: 'outline',
        };
        return <Badge variant={variants[level] || 'outline'}>{label}</Badge>;
    };

    return (
        <MainLayout>
            <Head title="Prestasi Siswa" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Prestasi Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola data prestasi dan penghargaan siswa
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={loadAchievements} disabled={loading}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button onClick={openCreateForm}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Prestasi
                        </Button>
                    </div>
                </div>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Prestasi</CardTitle>
                            <Trophy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{achievements.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Siswa</CardTitle>
                            <Medal className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{students.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Kategori</CardTitle>
                            <Award className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{categories.length}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter & List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Prestasi</CardTitle>
                        <CardDescription>
                            Klik "Refresh" untuk memuat data prestasi siswa
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSearch} className="mb-4 flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <Label>Cari</Label>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Judul, penyelenggara, NIS..."
                                        className="w-[200px] pl-8"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </div>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Siswa</Label>
                                <Select value={filterStudent || 'all'} onValueChange={(v) => setFilterStudent(v === 'all' ? '' : v)}>
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Semua Siswa" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Siswa</SelectItem>
                                        {students.map((s) => (
                                            <SelectItem key={s.id} value={s.id}>
                                                {s.nis} - {s.full_name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Kategori</Label>
                                <Select value={filterCategory || 'all'} onValueChange={(v) => setFilterCategory(v === 'all' ? '' : v)}>
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Semua" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua</SelectItem>
                                        {categories.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Tingkat</Label>
                                <Select value={filterLevel || 'all'} onValueChange={(v) => setFilterLevel(v === 'all' ? '' : v)}>
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Semua" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua</SelectItem>
                                        {levels.map((l) => (
                                            <SelectItem key={l.value} value={l.value}>
                                                {l.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <Button type="submit" disabled={loading}>
                                {loading ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Search className="mr-2 h-4 w-4" />
                                )}
                                Tampilkan
                            </Button>
                        </form>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[50px]">No</TableHead>
                                        <TableHead>Siswa</TableHead>
                                        <TableHead>Judul Prestasi</TableHead>
                                        <TableHead>Kategori</TableHead>
                                        <TableHead>Tingkat</TableHead>
                                        <TableHead>Peringkat</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead className="w-[100px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading ? (
                                        <TableRow>
                                            <TableCell colSpan={8} className="py-8 text-center">
                                                <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
                                            </TableCell>
                                        </TableRow>
                                    ) : achievements.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={8} className="py-8 text-center text-muted-foreground">
                                                Tidak ada data. Klik "Tampilkan" untuk memuat data.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        achievements.map((achievement, index) => (
                                            <TableRow key={achievement.id}>
                                                <TableCell className="tabular-nums text-muted-foreground">
                                                    {index + 1}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{achievement.student?.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {achievement.student?.nis}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{achievement.title}</div>
                                                        {achievement.organizer && (
                                                            <div className="text-sm text-muted-foreground">
                                                                {achievement.organizer}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {getCategoryBadge(achievement.category, achievement.category_label)}
                                                </TableCell>
                                                <TableCell>
                                                    {getLevelBadge(achievement.level, achievement.level_label)}
                                                </TableCell>
                                                <TableCell>{achievement.rank || '-'}</TableCell>
                                                <TableCell>
                                                    {achievement.achievement_date
                                                        ? new Date(achievement.achievement_date).toLocaleDateString('id-ID')
                                                        : '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex gap-1">
                                                        {achievement.certificate_url && (
                                                            <Button
                                                                variant="ghost"
                                                                size="icon"
                                                                asChild
                                                            >
                                                                <a
                                                                    href={achievement.certificate_url}
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    title="Lihat Sertifikat"
                                                                >
                                                                    <FileText className="h-4 w-4" />
                                                                </a>
                                                            </Button>
                                                        )}
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => openEditForm(achievement)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-destructive"
                                                            onClick={() => setDeleting(achievement)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editing ? 'Edit Prestasi' : 'Tambah Prestasi'}
                        </DialogTitle>
                        <DialogDescription>
                            {editing
                                ? 'Perbarui data prestasi siswa'
                                : 'Catat prestasi baru siswa'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-1.5">
                            <Label>Siswa *</Label>
                            <Select
                                value={formData.student_id}
                                onValueChange={(v) => setFormData({ ...formData, student_id: v })}
                                disabled={!!editing}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih siswa" />
                                </SelectTrigger>
                                <SelectContent>
                                    {students.map((s) => (
                                        <SelectItem key={s.id} value={s.id}>
                                            {s.nis} - {s.full_name} {s.class && `(${s.class})`}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Judul Prestasi *</Label>
                            <Input
                                value={formData.title}
                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                placeholder="Mis: Juara 1 Olimpiade Matematika"
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label>Kategori *</Label>
                                <Select
                                    value={formData.category}
                                    onValueChange={(v) => setFormData({ ...formData, category: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem key={c.value} value={c.value}>
                                                {c.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Tingkat *</Label>
                                <Select
                                    value={formData.level}
                                    onValueChange={(v) => setFormData({ ...formData, level: v })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tingkat" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {levels.map((l) => (
                                            <SelectItem key={l.value} value={l.value}>
                                                {l.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label>Peringkat</Label>
                                <Input
                                    value={formData.rank}
                                    onChange={(e) => setFormData({ ...formData, rank: e.target.value })}
                                    placeholder="Mis: Juara 1, Medali Emas"
                                />
                            </div>

                            <div className="space-y-1.5">
                                <Label>Tanggal *</Label>
                                <Input
                                    type="date"
                                    value={formData.achievement_date}
                                    onChange={(e) => setFormData({ ...formData, achievement_date: e.target.value })}
                                />
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <Label>Penyelenggara</Label>
                            <Input
                                value={formData.organizer}
                                onChange={(e) => setFormData({ ...formData, organizer: e.target.value })}
                                placeholder="Mis: Dinas Pendidikan Kota Bandung"
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label>Deskripsi</Label>
                            <Textarea
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                placeholder="Deskripsi singkat tentang prestasi ini..."
                                rows={3}
                            />
                        </div>

                        <div className="space-y-1.5">
                            <Label>Sertifikat (PDF/Gambar, maks 5MB)</Label>
                            <Input
                                type="file"
                                accept=".pdf,.jpg,.jpeg,.png"
                                onChange={(e) => setCertificateFile(e.target.files?.[0] || null)}
                            />
                            {editing?.certificate_url && !certificateFile && (
                                <p className="text-sm text-muted-foreground">
                                    Sudah ada sertifikat.{' '}
                                    <a
                                        href={editing.certificate_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-primary underline"
                                    >
                                        Lihat
                                    </a>
                                </p>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSave} disabled={saving}>
                            {saving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {editing ? 'Simpan Perubahan' : 'Tambah Prestasi'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation */}
            <AlertDialog open={!!deleting} onOpenChange={(open) => !open && setDeleting(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Prestasi</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus prestasi{' '}
                            <span className="font-medium">{deleting?.title}</span>? Tindakan ini
                            tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={deleteLoading}>Batal</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={(e) => {
                                e.preventDefault();
                                handleDelete();
                            }}
                            disabled={deleteLoading}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            {deleteLoading ? 'Menghapus...' : 'Hapus'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
