import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
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
import { libraryBookApi, bookCategoryApi, type LibraryBook, type BookCategory } from '@/services/api';
import {
    Plus,
    Search,
    MoreVertical,
    Pencil,
    Trash2,
    Eye,
    BookOpen,
    BookCopy,
    X,
} from 'lucide-react';

const statusOptions = [
    { value: 'available', label: 'Tersedia', color: 'bg-green-100 text-green-700' },
    { value: 'unavailable', label: 'Tidak Tersedia', color: 'bg-gray-100 text-gray-700' },
    { value: 'damaged', label: 'Rusak', color: 'bg-orange-100 text-orange-700' },
    { value: 'lost', label: 'Hilang', color: 'bg-red-100 text-red-700' },
];

export default function Books() {
    // State
    const [books, setBooks] = useState<LibraryBook[]>([]);
    const [categories, setCategories] = useState<BookCategory[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Dialog state
    const [formOpen, setFormOpen] = useState(false);
    const [viewOpen, setViewOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [selectedBook, setSelectedBook] = useState<LibraryBook | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        title: '',
        isbn: '',
        category_id: '',
        edition: '',
        publish_year: '',
        language: 'Indonesia',
        pages: '',
        description: '',
        price: '',
        total_copies: '1',
    });
    const [coverFile, setCoverFile] = useState<File | null>(null);
    const [coverPreview, setCoverPreview] = useState<string | null>(null);

    // Load books
    const loadBooks = useCallback(async () => {
        try {
            setLoading(true);
            const params: Record<string, unknown> = {
                page: currentPage,
                per_page: 10,
            };
            if (search) params.search = search;
            if (categoryFilter) params.category_id = categoryFilter;
            if (statusFilter) params.status = statusFilter;

            const response = await libraryBookApi.list(params);
            setBooks(response.data.data || []);
            setTotalPages(response.data.meta?.last_page || 1);
        } catch (error) {
            console.error('Failed to load books:', error);
            toast.error('Gagal memuat data buku');
        } finally {
            setLoading(false);
        }
    }, [currentPage, search, categoryFilter, statusFilter]);

    // Load categories for dropdown
    const loadCategories = useCallback(async () => {
        try {
            const response = await bookCategoryApi.list({ per_page: 100 });
            setCategories(response.data.data || []);
        } catch (error) {
            console.error('Failed to load categories:', error);
        }
    }, []);

    useEffect(() => {
        loadBooks();
        loadCategories();
    }, [loadBooks, loadCategories]);

    // Reset form
    const resetForm = () => {
        setFormData({
            title: '',
            isbn: '',
            category_id: '',
            edition: '',
            publish_year: '',
            language: 'Indonesia',
            pages: '',
            description: '',
            price: '',
            total_copies: '1',
        });
        setCoverFile(null);
        setCoverPreview(null);
        setSelectedBook(null);
    };

    // Open create dialog
    const handleCreate = () => {
        resetForm();
        setFormOpen(true);
    };

    // Open edit dialog
    const handleEdit = (book: LibraryBook) => {
        setSelectedBook(book);
        setFormData({
            title: book.title,
            isbn: book.isbn || '',
            category_id: book.category?.id || '',
            edition: book.edition || '',
            publish_year: book.publish_year?.toString() || '',
            language: book.language,
            pages: book.pages?.toString() || '',
            description: book.description || '',
            price: book.price?.toString() || '',
            total_copies: book.total_copies.toString(),
        });
        setCoverPreview(book.cover_url);
        setFormOpen(true);
    };

    // Open view dialog
    const handleView = (book: LibraryBook) => {
        setSelectedBook(book);
        setViewOpen(true);
    };

    // Open delete dialog
    const handleDeleteClick = (book: LibraryBook) => {
        setSelectedBook(book);
        setDeleteOpen(true);
    };

    // Handle cover selection
    const handleCoverChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setCoverFile(file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setCoverPreview(reader.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    // Remove cover
    const handleRemoveCover = () => {
        setCoverFile(null);
        setCoverPreview(null);
    };

    // Submit form
    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setSubmitting(true);

        try {
            const data = new FormData();
            data.append('title', formData.title);
            if (formData.isbn) data.append('isbn', formData.isbn);
            if (formData.category_id) data.append('category_id', formData.category_id);
            if (formData.edition) data.append('edition', formData.edition);
            if (formData.publish_year) data.append('publish_year', formData.publish_year);
            if (formData.language) data.append('language', formData.language);
            if (formData.pages) data.append('pages', formData.pages);
            if (formData.description) data.append('description', formData.description);
            if (formData.price) data.append('price', formData.price);
            if (!selectedBook && formData.total_copies) {
                data.append('total_copies', formData.total_copies);
            }
            if (coverFile) data.append('cover', coverFile);

            if (selectedBook) {
                await libraryBookApi.update(selectedBook.id, data);
                toast.success('Buku berhasil diperbarui');
            } else {
                await libraryBookApi.create(data);
                toast.success('Buku berhasil ditambahkan');
            }

            setFormOpen(false);
            resetForm();
            loadBooks();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(
                selectedBook ? 'Gagal memperbarui buku' : 'Gagal menambahkan buku',
                { description: err.response?.data?.message }
            );
        } finally {
            setSubmitting(false);
        }
    };

    // Handle delete
    const handleDelete = async () => {
        if (!selectedBook) return;

        try {
            await libraryBookApi.delete(selectedBook.id);
            toast.success('Buku berhasil dihapus');
            setDeleteOpen(false);
            loadBooks();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal menghapus buku', {
                description: err.response?.data?.message,
            });
        }
    };

    // Get status badge
    const getStatusBadge = (status: string) => {
        const option = statusOptions.find((s) => s.value === status);
        return option ? (
            <Badge className={option.color}>{option.label}</Badge>
        ) : (
            <Badge variant="secondary">{status}</Badge>
        );
    };

    return (
        <MainLayout title="Katalog Buku">
            <Head title="Katalog Buku" />

            <div className="space-y-6">
                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Buku</CardTitle>
                            <BookOpen className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{books.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Eksemplar</CardTitle>
                            <BookCopy className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {books.reduce((sum, b) => sum + b.total_copies, 0)}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Tersedia</CardTitle>
                            <BookOpen className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {books.reduce((sum, b) => sum + b.available_copies, 0)}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Daftar Buku</CardTitle>
                                <CardDescription>Kelola katalog buku perpustakaan</CardDescription>
                            </div>
                            <Button onClick={handleCreate}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Buku
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex flex-wrap gap-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Cari judul atau ISBN..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setCurrentPage(1);
                                    }}
                                    className="pl-9"
                                />
                            </div>
                            <Select
                                value={categoryFilter}
                                onValueChange={(value) => {
                                    setCategoryFilter(value);
                                    setCurrentPage(1);
                                }}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Kategori</SelectItem>
                                    {categories.map((cat) => (
                                        <SelectItem key={cat.id} value={cat.id}>
                                            {cat.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={statusFilter}
                                onValueChange={(value) => {
                                    setStatusFilter(value);
                                    setCurrentPage(1);
                                }}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Status</SelectItem>
                                    {statusOptions.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Table */}
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Judul</TableHead>
                                        <TableHead>ISBN</TableHead>
                                        <TableHead>Kategori</TableHead>
                                        <TableHead>Eksemplar</TableHead>
                                        <TableHead>Status</TableHead>
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
                                    ) : books.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Tidak ada buku
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        books.map((book) => (
                                            <TableRow key={book.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        {book.cover_url ? (
                                                            <img
                                                                src={book.cover_url}
                                                                alt={book.title}
                                                                className="h-12 w-9 rounded object-cover"
                                                            />
                                                        ) : (
                                                            <div className="h-12 w-9 rounded bg-muted flex items-center justify-center">
                                                                <BookOpen className="h-5 w-5 text-muted-foreground" />
                                                            </div>
                                                        )}
                                                        <div>
                                                            <div className="font-medium">{book.title}</div>
                                                            {book.authors && book.authors.length > 0 && (
                                                                <div className="text-sm text-muted-foreground">
                                                                    {book.authors.map((a) => a.name).join(', ')}
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{book.isbn || '-'}</TableCell>
                                                <TableCell>{book.category?.name || '-'}</TableCell>
                                                <TableCell>
                                                    {book.available_copies}/{book.total_copies}
                                                </TableCell>
                                                <TableCell>{getStatusBadge(book.status)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onClick={() => handleView(book)}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                Lihat
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => handleEdit(book)}>
                                                                <Pencil className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => handleDeleteClick(book)}
                                                                className="text-destructive"
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                Hapus
                                                            </DropdownMenuItem>
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
                        <DialogTitle>{selectedBook ? 'Edit Buku' : 'Tambah Buku'}</DialogTitle>
                        <DialogDescription>
                            {selectedBook ? 'Perbarui informasi buku' : 'Tambahkan buku baru ke katalog'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="col-span-2 space-y-2">
                                <Label htmlFor="title">Judul *</Label>
                                <Input
                                    id="title"
                                    value={formData.title}
                                    onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="isbn">ISBN</Label>
                                <Input
                                    id="isbn"
                                    value={formData.isbn}
                                    onChange={(e) => setFormData({ ...formData, isbn: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Kategori</Label>
                                <Select
                                    value={formData.category_id}
                                    onValueChange={(value) => setFormData({ ...formData, category_id: value })}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kategori" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((cat) => (
                                            <SelectItem key={cat.id} value={cat.id}>
                                                {cat.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="edition">Edisi</Label>
                                <Input
                                    id="edition"
                                    value={formData.edition}
                                    onChange={(e) => setFormData({ ...formData, edition: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="publish_year">Tahun Terbit</Label>
                                <Input
                                    id="publish_year"
                                    type="number"
                                    min="1900"
                                    max={new Date().getFullYear() + 1}
                                    value={formData.publish_year}
                                    onChange={(e) => setFormData({ ...formData, publish_year: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="language">Bahasa</Label>
                                <Input
                                    id="language"
                                    value={formData.language}
                                    onChange={(e) => setFormData({ ...formData, language: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="pages">Jumlah Halaman</Label>
                                <Input
                                    id="pages"
                                    type="number"
                                    min="1"
                                    value={formData.pages}
                                    onChange={(e) => setFormData({ ...formData, pages: e.target.value })}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="price">Harga (Rp)</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    min="0"
                                    value={formData.price}
                                    onChange={(e) => setFormData({ ...formData, price: e.target.value })}
                                />
                            </div>
                            {!selectedBook && (
                                <div className="space-y-2">
                                    <Label htmlFor="total_copies">Jumlah Eksemplar</Label>
                                    <Input
                                        id="total_copies"
                                        type="number"
                                        min="1"
                                        value={formData.total_copies}
                                        onChange={(e) => setFormData({ ...formData, total_copies: e.target.value })}
                                    />
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Deskripsi</Label>
                            <Textarea
                                id="description"
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                rows={3}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="cover">Cover</Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="cover"
                                    type="file"
                                    accept="image/*"
                                    onChange={handleCoverChange}
                                    className="flex-1"
                                />
                                {coverPreview && (
                                    <Button type="button" variant="ghost" size="icon" onClick={handleRemoveCover}>
                                        <X className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                            {coverPreview && (
                                <img
                                    src={coverPreview}
                                    alt="Preview"
                                    className="mt-2 h-32 w-auto rounded-md object-cover"
                                />
                            )}
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                                Batal
                            </Button>
                            <Button type="submit" disabled={submitting}>
                                {submitting ? 'Menyimpan...' : selectedBook ? 'Simpan' : 'Tambah'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={viewOpen} onOpenChange={setViewOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{selectedBook?.title}</DialogTitle>
                    </DialogHeader>
                    <div className="grid grid-cols-2 gap-4">
                        {selectedBook?.cover_url && (
                            <div className="col-span-2 flex justify-center">
                                <img
                                    src={selectedBook.cover_url}
                                    alt={selectedBook.title}
                                    className="h-48 w-auto rounded-md object-cover"
                                />
                            </div>
                        )}
                        <div>
                            <Label className="text-muted-foreground">ISBN</Label>
                            <p>{selectedBook?.isbn || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Kategori</Label>
                            <p>{selectedBook?.category?.name || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Edisi</Label>
                            <p>{selectedBook?.edition || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Tahun Terbit</Label>
                            <p>{selectedBook?.publish_year || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Bahasa</Label>
                            <p>{selectedBook?.language || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Halaman</Label>
                            <p>{selectedBook?.pages || '-'}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Total Eksemplar</Label>
                            <p>{selectedBook?.total_copies}</p>
                        </div>
                        <div>
                            <Label className="text-muted-foreground">Tersedia</Label>
                            <p>{selectedBook?.available_copies}</p>
                        </div>
                        {selectedBook?.description && (
                            <div className="col-span-2">
                                <Label className="text-muted-foreground">Deskripsi</Label>
                                <p className="whitespace-pre-wrap">{selectedBook.description}</p>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <AlertDialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Buku</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus buku "{selectedBook?.title}"? Semua eksemplar juga akan dihapus.
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
