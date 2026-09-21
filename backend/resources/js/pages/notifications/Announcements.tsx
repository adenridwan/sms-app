import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { usePermissions } from '@/hooks/usePermissions';
import { announcementApi, type Announcement, type AnnouncementStatistics } from '@/services/api';
import {
    Plus,
    Search,
    MoreVertical,
    Pencil,
    Trash2,
    Eye,
    Pin,
    Send,
    FileText,
    Clock,
    Users,
    Image as ImageIcon,
    X,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';

const priorityOptions = [
    { value: 'low', label: 'Rendah', color: 'bg-slate-100 text-slate-700' },
    { value: 'normal', label: 'Normal', color: 'bg-blue-100 text-blue-700' },
    { value: 'high', label: 'Tinggi', color: 'bg-orange-100 text-orange-700' },
    { value: 'urgent', label: 'Mendesak', color: 'bg-red-100 text-red-700' },
];

export default function Announcements() {
    const { can, isSuperAdmin } = usePermissions();
    const canManage = isSuperAdmin || can('announcements.manage');

    // State
    const [announcements, setAnnouncements] = useState<Announcement[]>([]);
    const [statistics, setStatistics] = useState<AnnouncementStatistics | null>(null);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [priorityFilter, setPriorityFilter] = useState<string>('');
    const [publishedFilter, setPublishedFilter] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Dialog state
    const [formOpen, setFormOpen] = useState(false);
    const [viewOpen, setViewOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [selectedAnnouncement, setSelectedAnnouncement] = useState<Announcement | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        title: '',
        content: '',
        priority: 'normal',
        is_pinned: false,
        is_published: false,
        send_notification: true,
        publish_at: '',
        expires_at: '',
    });
    const [imageFile, setImageFile] = useState<File | null>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);

    // Load announcements
    const loadAnnouncements = useCallback(async () => {
        try {
            setLoading(true);
            const params: Record<string, unknown> = {
                page: currentPage,
                per_page: 10,
            };
            if (search) params.search = search;
            if (priorityFilter) params.priority = priorityFilter;
            if (publishedFilter !== '') params.is_published = publishedFilter;

            const response = await announcementApi.list(params);
            setAnnouncements(response.data.data || []);
            setTotalPages(response.data.meta?.last_page || 1);
        } catch (error) {
            console.error('Failed to load announcements:', error);
            toast.error('Gagal memuat pengumuman');
        } finally {
            setLoading(false);
        }
    }, [currentPage, search, priorityFilter, publishedFilter]);

    // Load statistics
    const loadStatistics = useCallback(async () => {
        if (!canManage) return;
        try {
            const response = await announcementApi.statistics();
            setStatistics(response.data.data);
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    }, [canManage]);

    useEffect(() => {
        loadAnnouncements();
        loadStatistics();
    }, [loadAnnouncements, loadStatistics]);

    // Reset form
    const resetForm = () => {
        setFormData({
            title: '',
            content: '',
            priority: 'normal',
            is_pinned: false,
            is_published: false,
            send_notification: true,
            publish_at: '',
            expires_at: '',
        });
        setImageFile(null);
        setImagePreview(null);
        setSelectedAnnouncement(null);
    };

    // Open create dialog
    const handleCreate = () => {
        resetForm();
        setFormOpen(true);
    };

    // Open edit dialog
    const handleEdit = (announcement: Announcement) => {
        setSelectedAnnouncement(announcement);
        setFormData({
            title: announcement.title,
            content: announcement.content,
            priority: announcement.priority,
            is_pinned: announcement.is_pinned,
            is_published: announcement.is_published,
            send_notification: announcement.send_notification,
            publish_at: announcement.publish_at ? announcement.publish_at.slice(0, 16) : '',
            expires_at: announcement.expires_at ? announcement.expires_at.slice(0, 16) : '',
        });
        setImagePreview(announcement.image_url);
        setFormOpen(true);
    };

    // Open view dialog
    const handleView = async (announcement: Announcement) => {
        setSelectedAnnouncement(announcement);
        setViewOpen(true);
        // Mark as read
        try {
            await announcementApi.markAsRead(announcement.id);
        } catch {
            // Ignore
        }
    };

    // Open delete dialog
    const handleDeleteClick = (announcement: Announcement) => {
        setSelectedAnnouncement(announcement);
        setDeleteOpen(true);
    };

    // Handle image selection
    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setImagePreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    // Remove image
    const handleRemoveImage = async () => {
        if (selectedAnnouncement?.image_url) {
            try {
                await announcementApi.deleteImage(selectedAnnouncement.id);
                toast.success('Gambar berhasil dihapus');
            } catch {
                toast.error('Gagal menghapus gambar');
                return;
            }
        }
        setImageFile(null);
        setImagePreview(null);
    };

    // Submit form
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            const data = new FormData();
            data.append('title', formData.title);
            data.append('content', formData.content);
            data.append('priority', formData.priority);
            data.append('is_pinned', formData.is_pinned ? '1' : '0');
            data.append('is_published', formData.is_published ? '1' : '0');
            data.append('send_notification', formData.send_notification ? '1' : '0');
            if (formData.publish_at) data.append('publish_at', formData.publish_at);
            if (formData.expires_at) data.append('expires_at', formData.expires_at);
            if (imageFile) data.append('image', imageFile);

            if (selectedAnnouncement) {
                await announcementApi.update(selectedAnnouncement.id, data);
                toast.success('Pengumuman berhasil diperbarui');
            } else {
                await announcementApi.create(data);
                toast.success('Pengumuman berhasil dibuat');
            }

            setFormOpen(false);
            resetForm();
            loadAnnouncements();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(
                selectedAnnouncement ? 'Gagal memperbarui pengumuman' : 'Gagal membuat pengumuman',
                { description: err.response?.data?.message }
            );
        } finally {
            setSubmitting(false);
        }
    };

    // Handle delete
    const handleDelete = async () => {
        if (!selectedAnnouncement) return;

        try {
            await announcementApi.delete(selectedAnnouncement.id);
            toast.success('Pengumuman berhasil dihapus');
            setDeleteOpen(false);
            loadAnnouncements();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal menghapus pengumuman', {
                description: err.response?.data?.message,
            });
        }
    };

    // Handle publish toggle
    const handlePublishToggle = async (announcement: Announcement) => {
        try {
            await announcementApi.publish(announcement.id, !announcement.is_published);
            toast.success(
                announcement.is_published
                    ? 'Pengumuman di-unpublish'
                    : 'Pengumuman dipublikasikan'
            );
            loadAnnouncements();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal mengubah status publikasi', {
                description: err.response?.data?.message,
            });
        }
    };

    // Get priority badge color
    const getPriorityBadge = (priority: string) => {
        const option = priorityOptions.find((p) => p.value === priority);
        return option ? (
            <Badge className={option.color}>{option.label}</Badge>
        ) : (
            <Badge variant="secondary">{priority}</Badge>
        );
    };

    // Format date
    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        try {
            return format(parseISO(dateStr), 'dd MMM yyyy HH:mm', { locale: localeId });
        } catch {
            return dateStr;
        }
    };

    return (
        <MainLayout title="Pengumuman">
            <Head title="Pengumuman" />

            <div className="space-y-6">
                {/* Statistics Cards */}
                {canManage && statistics && (
                    <div className="grid gap-4 md:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total</CardTitle>
                                <FileText className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.total}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Dipublikasikan</CardTitle>
                                <Send className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.published}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Draf</CardTitle>
                                <Clock className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.draft}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Disematkan</CardTitle>
                                <Pin className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.pinned}</div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Main Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Daftar Pengumuman</CardTitle>
                                <CardDescription>
                                    Kelola pengumuman untuk siswa, guru, dan orang tua
                                </CardDescription>
                            </div>
                            {canManage && (
                                <Button onClick={handleCreate}>
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah Pengumuman
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex flex-wrap gap-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Cari pengumuman..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setCurrentPage(1);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select
                                value={priorityFilter}
                                onValueChange={(value) => {
                                    setPriorityFilter(value);
                                    setCurrentPage(1);
                                }}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Prioritas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Prioritas</SelectItem>
                                    {priorityOptions.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {canManage && (
                                <Select
                                    value={publishedFilter}
                                    onValueChange={(value) => {
                                        setPublishedFilter(value);
                                        setCurrentPage(1);
                                    }}
                                >
                                    <SelectTrigger className="w-[150px]">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua Status</SelectItem>
                                        <SelectItem value="true">Dipublikasikan</SelectItem>
                                        <SelectItem value="false">Draf</SelectItem>
                                    </SelectContent>
                                </Select>
                            )}
                        </div>

                        {/* Table */}
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Judul</TableHead>
                                        <TableHead>Prioritas</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Dibaca</TableHead>
                                        <TableHead className="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {loading ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Memuat...
                                            </TableCell>
                                        </TableRow>
                                    ) : announcements.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Tidak ada pengumuman
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        announcements.map((announcement) => (
                                            <TableRow key={announcement.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        {announcement.is_pinned && (
                                                            <Pin className="h-4 w-4 text-orange-500" />
                                                        )}
                                                        <span
                                                            className={
                                                                announcement.is_read
                                                                    ? 'text-muted-foreground'
                                                                    : 'font-medium'
                                                            }
                                                        >
                                                            {announcement.title}
                                                        </span>
                                                        {announcement.image_url && (
                                                            <ImageIcon className="h-4 w-4 text-muted-foreground" />
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getPriorityBadge(announcement.priority)}</TableCell>
                                                <TableCell>
                                                    {announcement.is_published ? (
                                                        <Badge variant="default">Dipublikasikan</Badge>
                                                    ) : (
                                                        <Badge variant="secondary">Draf</Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>{formatDate(announcement.created_at)}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Users className="h-4 w-4 text-muted-foreground" />
                                                        <span>{announcement.read_count}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onClick={() => handleView(announcement)}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                Lihat
                                                            </DropdownMenuItem>
                                                            {canManage && (
                                                                <>
                                                                    <DropdownMenuItem onClick={() => handleEdit(announcement)}>
                                                                        <Pencil className="mr-2 h-4 w-4" />
                                                                        Edit
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        onClick={() => handlePublishToggle(announcement)}
                                                                    >
                                                                        <Send className="mr-2 h-4 w-4" />
                                                                        {announcement.is_published ? 'Unpublish' : 'Publikasikan'}
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleDeleteClick(announcement)}
                                                                        className="text-destructive"
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        Hapus
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Pagination */}
                        {totalPages > 1 && (
                            <div className="mt-4 flex justify-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                                    disabled={currentPage === 1}
                                >
                                    Sebelumnya
                                </Button>
                                <span className="flex items-center px-3 text-sm">
                                    Halaman {currentPage} dari {totalPages}
                                </span>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                                    disabled={currentPage === totalPages}
                                >
                                    Selanjutnya
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Create/Edit Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedAnnouncement ? 'Edit Pengumuman' : 'Tambah Pengumuman'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedAnnouncement
                                ? 'Perbarui informasi pengumuman'
                                : 'Buat pengumuman baru untuk dikirim ke pengguna'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="title">Judul *</Label>
                            <Input
                                id="title"
                                value={formData.title}
                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                required
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="content">Isi Pengumuman *</Label>
                            <Textarea
                                id="content"
                                value={formData.content}
                                onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                                rows={6}
                                required
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="priority">Prioritas</Label>
                                <Select
                                    value={formData.priority}
                                    onValueChange={(value) => setFormData({ ...formData, priority: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {priorityOptions.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="image">Gambar</Label>
                                <div className="flex items-center gap-2">
                                    <Input
                                        id="image"
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="flex-1"
                                    />
                                    {imagePreview && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={handleRemoveImage}
                                        >
                                            <X className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                                {imagePreview && (
                                    <img
                                        src={imagePreview}
                                        alt="Preview"
                                        className="mt-2 h-32 w-auto rounded-md object-cover"
                                    />
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label htmlFor="publish_at">Tanggal Publikasi</Label>
                                <DateTimePicker
                                    id="publish_at"
                                    value={formData.publish_at}
                                    onChange={(v) => setFormData({ ...formData, publish_at: v })}
                                    placeholder="Terbitkan segera"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="expires_at">Tanggal Kedaluwarsa</Label>
                                <DateTimePicker
                                    id="expires_at"
                                    value={formData.expires_at}
                                    onChange={(v) => setFormData({ ...formData, expires_at: v })}
                                    placeholder="Tanpa batas waktu"
                                />
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Sematkan</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Tampilkan di bagian atas daftar
                                    </p>
                                </div>
                                <Switch
                                    checked={formData.is_pinned}
                                    onCheckedChange={(checked) => setFormData({ ...formData, is_pinned: checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Publikasikan Langsung</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Tampilkan pengumuman ke pengguna
                                    </p>
                                </div>
                                <Switch
                                    checked={formData.is_published}
                                    onCheckedChange={(checked) => setFormData({ ...formData, is_published: checked })}
                                />
                            </div>
                            <div className="flex items-center justify-between">
                                <div className="space-y-0.5">
                                    <Label>Kirim Notifikasi</Label>
                                    <p className="text-sm text-muted-foreground">
                                        Kirim notifikasi ke pengguna saat dipublikasikan
                                    </p>
                                </div>
                                <Switch
                                    checked={formData.send_notification}
                                    onCheckedChange={(checked) =>
                                        setFormData({ ...formData, send_notification: checked })
                                    }
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Menyimpan...' : selectedAnnouncement ? 'Simpan' : 'Buat'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={viewOpen} onOpenChange={setViewOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            {selectedAnnouncement?.is_pinned && <Pin className="h-4 w-4 text-orange-500" />}
                            {selectedAnnouncement?.title}
                        </DialogTitle>
                        <DialogDescription>
                            <div className="flex items-center gap-2 mt-2">
                                {selectedAnnouncement && getPriorityBadge(selectedAnnouncement.priority)}
                                {selectedAnnouncement?.is_published ? (
                                    <Badge variant="default">Dipublikasikan</Badge>
                                ) : (
                                    <Badge variant="secondary">Draf</Badge>
                                )}
                            </div>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {selectedAnnouncement?.image_url && (
                            <img
                                src={selectedAnnouncement.image_url}
                                alt={selectedAnnouncement.title}
                                className="w-full max-h-64 object-cover rounded-md"
                            />
                        )}
                        <div className="prose prose-sm dark:prose-invert max-w-none whitespace-pre-wrap">
                            {selectedAnnouncement?.content}
                        </div>
                        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
                            {selectedAnnouncement?.author && (
                                <div>
                                    <span className="font-medium">Penulis:</span>{' '}
                                    {selectedAnnouncement.author.full_name}
                                </div>
                            )}
                            <div>
                                <span className="font-medium">Dibuat:</span>{' '}
                                {formatDate(selectedAnnouncement?.created_at ?? null)}
                            </div>
                            {selectedAnnouncement?.publish_at && (
                                <div>
                                    <span className="font-medium">Dipublikasikan:</span>{' '}
                                    {formatDate(selectedAnnouncement.publish_at)}
                                </div>
                            )}
                            {selectedAnnouncement?.expires_at && (
                                <div>
                                    <span className="font-medium">Kedaluwarsa:</span>{' '}
                                    {formatDate(selectedAnnouncement.expires_at)}
                                </div>
                            )}
                            <div>
                                <span className="font-medium">Dibaca:</span> {selectedAnnouncement?.read_count} orang
                            </div>
                        </div>
                    </div>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Pengumuman</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus pengumuman "{selectedAnnouncement?.title}"?
                            Tindakan ini tidak dapat dibatalkan.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Batal</AlertDialogCancel>
                        <AlertDialogAction onClick={handleDelete} className="bg-destructive text-destructive-foreground">
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
