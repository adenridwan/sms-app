import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { toast } from 'sonner';
import { libraryMemberApi, type LibraryMember, type BookLoan } from '@/services/api';
import {
    Search,
    Plus,
    MoreVertical,
    Pencil,
    Trash2,
    BookOpen,
    AlertTriangle,
} from 'lucide-react';
import { format, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';

const memberTypeOptions = [
    { value: 'student', label: 'Siswa' },
    { value: 'teacher', label: 'Guru' },
    { value: 'staff', label: 'Staf' },
    { value: 'external', label: 'Eksternal' },
];

const statusOptions = [
    { value: 'active', label: 'Aktif', color: 'bg-green-100 text-green-700' },
    { value: 'inactive', label: 'Nonaktif', color: 'bg-gray-100 text-gray-700' },
    { value: 'suspended', label: 'Ditangguhkan', color: 'bg-red-100 text-red-700' },
    { value: 'expired', label: 'Kedaluwarsa', color: 'bg-orange-100 text-orange-700' },
];

interface AvailableUser {
    id: string;
    full_name: string;
    email: string;
}

export default function Members() {
    // State
    const [members, setMembers] = useState<LibraryMember[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const [totalPages, setTotalPages] = useState(1);

    // Dialog state
    const [formOpen, setFormOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const [loansOpen, setLoansOpen] = useState(false);
    const [selectedMember, setSelectedMember] = useState<LibraryMember | null>(null);
    const [submitting, setSubmitting] = useState(false);

    // Available users for adding new member
    const [availableUsers, setAvailableUsers] = useState<AvailableUser[]>([]);
    const [loadingUsers, setLoadingUsers] = useState(false);

    // Member loans
    const [memberLoans, setMemberLoans] = useState<BookLoan[]>([]);
    const [loadingLoans, setLoadingLoans] = useState(false);

    // Form state
    const [formData, setFormData] = useState({
        user_id: '',
        member_number: '',
        member_type: 'student' as 'student' | 'teacher' | 'staff' | 'external',
        status: 'active' as 'active' | 'inactive' | 'suspended' | 'expired',
        max_borrow_limit: 3,
    });

    // Load members
    const loadMembers = useCallback(async () => {
        try {
            setLoading(true);
            const params: Record<string, unknown> = {
                page: currentPage,
                per_page: 10,
            };
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;

            const response = await libraryMemberApi.list(params);
            setMembers(response.data.data || []);
            setTotalPages(response.data.meta?.last_page || 1);
        } catch (error) {
            console.error('Failed to load members:', error);
            toast.error('Gagal memuat data anggota');
        } finally {
            setLoading(false);
        }
    }, [currentPage, search, statusFilter]);

    // Load available users
    const loadAvailableUsers = async () => {
        try {
            setLoadingUsers(true);
            const response = await libraryMemberApi.availableUsers();
            setAvailableUsers(response.data.data || []);
        } catch (error) {
            console.error('Failed to load available users:', error);
        } finally {
            setLoadingUsers(false);
        }
    };

    // Load member loans
    const loadMemberLoans = async (memberId: string) => {
        try {
            setLoadingLoans(true);
            const response = await libraryMemberApi.loans(memberId);
            setMemberLoans((response.data.data || []) as BookLoan[]);
        } catch (error) {
            console.error('Failed to load member loans:', error);
            toast.error('Gagal memuat data peminjaman');
        } finally {
            setLoadingLoans(false);
        }
    };

    useEffect(() => {
        loadMembers();
    }, [loadMembers]);

    // Open form for create/edit
    const openForm = (member?: LibraryMember) => {
        if (member) {
            setSelectedMember(member);
            setFormData({
                user_id: member.user?.id || '',
                member_number: member.member_number,
                member_type: member.member_type,
                status: member.status,
                max_borrow_limit: member.max_borrow_limit,
            });
        } else {
            setSelectedMember(null);
            setFormData({
                user_id: '',
                member_number: '',
                member_type: 'student',
                status: 'active',
                max_borrow_limit: 3,
            });
            loadAvailableUsers();
        }
        setFormOpen(true);
    };

    // Handle submit
    const handleSubmit = async () => {
        if (!selectedMember && !formData.user_id) {
            toast.error('Pilih pengguna');
            return;
        }

        setSubmitting(true);
        try {
            if (selectedMember) {
                await libraryMemberApi.update(selectedMember.id, formData);
                toast.success('Anggota berhasil diperbarui');
            } else {
                await libraryMemberApi.create(formData);
                toast.success('Anggota berhasil ditambahkan');
            }
            setFormOpen(false);
            loadMembers();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(selectedMember ? 'Gagal memperbarui anggota' : 'Gagal menambahkan anggota', {
                description: err.response?.data?.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // Handle delete
    const handleDelete = async () => {
        if (!selectedMember) return;

        setSubmitting(true);
        try {
            await libraryMemberApi.delete(selectedMember.id);
            toast.success('Anggota berhasil dihapus');
            setDeleteOpen(false);
            loadMembers();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal menghapus anggota', {
                description: err.response?.data?.message,
            });
        } finally {
            setSubmitting(false);
        }
    };

    // View member loans
    const viewLoans = (member: LibraryMember) => {
        setSelectedMember(member);
        setMemberLoans([]);
        setLoansOpen(true);
        loadMemberLoans(member.id);
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

    // Get member type label
    const getMemberTypeLabel = (type: string) => {
        const option = memberTypeOptions.find((t) => t.value === type);
        return option?.label || type;
    };

    // Format date
    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '-';
        try {
            return format(parseISO(dateStr), 'dd MMM yyyy', { locale: localeId });
        } catch {
            return dateStr;
        }
    };

    return (
        <MainLayout title="Anggota Perpustakaan">
            <Head title="Anggota Perpustakaan" />

            <div className="space-y-6">
                {/* Main Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Daftar Anggota</CardTitle>
                                <CardDescription>Kelola anggota perpustakaan</CardDescription>
                            </div>
                            <Button onClick={() => openForm()}>
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Anggota
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-4 flex flex-wrap gap-4">
                            <div className="relative flex-1 min-w-[200px]">
                                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    placeholder="Cari anggota..."
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setCurrentPage(1);
                                    }}
                                    className="pl-9"
                                />
                            </div>
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
                                        <TableHead>No. Anggota</TableHead>
                                        <TableHead>Nama</TableHead>
                                        <TableHead>Tipe</TableHead>
                                        <TableHead>Aktif Pinjam</TableHead>
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
                                    ) : members.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8">
                                                Tidak ada data anggota
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        members.map((member) => (
                                            <TableRow key={member.id}>
                                                <TableCell className="font-medium">
                                                    {member.member_number}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">
                                                            {member.user?.full_name || '-'}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {member.user?.email}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">
                                                        {getMemberTypeLabel(member.member_type)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <BookOpen className="h-4 w-4 text-muted-foreground" />
                                                        <span>{member.current_borrowed || 0}</span>
                                                        <span className="text-muted-foreground">
                                                            / {member.max_borrow_limit}
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getStatusBadge(member.status)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreVertical className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onClick={() => viewLoans(member)}>
                                                                <BookOpen className="mr-2 h-4 w-4" />
                                                                Lihat Peminjaman
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem onClick={() => openForm(member)}>
                                                                <Pencil className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    setSelectedMember(member);
                                                                    setDeleteOpen(true);
                                                                }}
                                                                className="text-red-600"
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

            {/* Form Dialog */}
            <Dialog open={formOpen} onOpenChange={setFormOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>
                            {selectedMember ? 'Edit Anggota' : 'Tambah Anggota'}
                        </DialogTitle>
                        <DialogDescription>
                            {selectedMember
                                ? 'Perbarui informasi anggota perpustakaan'
                                : 'Daftarkan pengguna sebagai anggota perpustakaan'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        {!selectedMember && (
                            <div className="space-y-2">
                                <Label>Pengguna *</Label>
                                <Select
                                    value={formData.user_id}
                                    onValueChange={(value) =>
                                        setFormData({ ...formData, user_id: value })
                                    }
                                    disabled={loadingUsers}
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder={loadingUsers ? 'Memuat...' : 'Pilih pengguna'}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableUsers.map((user) => (
                                            <SelectItem key={user.id} value={user.id}>
                                                {user.full_name} ({user.email})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                        <div className="space-y-2">
                            <Label htmlFor="member_number">No. Anggota</Label>
                            <Input
                                id="member_number"
                                value={formData.member_number}
                                onChange={(e) =>
                                    setFormData({ ...formData, member_number: e.target.value })
                                }
                                placeholder="Otomatis jika kosong"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tipe Anggota *</Label>
                            <Select
                                value={formData.member_type}
                                onValueChange={(value: 'student' | 'teacher' | 'staff') =>
                                    setFormData({ ...formData, member_type: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {memberTypeOptions.map((t) => (
                                        <SelectItem key={t.value} value={t.value}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Status *</Label>
                            <Select
                                value={formData.status}
                                onValueChange={(value: 'active' | 'inactive' | 'suspended') =>
                                    setFormData({ ...formData, status: value })
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {statusOptions.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="max_borrow_limit">Maks. Pinjam</Label>
                            <Input
                                id="max_borrow_limit"
                                type="number"
                                min={1}
                                max={20}
                                value={formData.max_borrow_limit}
                                onChange={(e) =>
                                    setFormData({
                                        ...formData,
                                        max_borrow_limit: parseInt(e.target.value) || 3,
                                    })
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={submitting}>
                            {submitting ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteOpen} onOpenChange={setDeleteOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Hapus Anggota</DialogTitle>
                        <DialogDescription>
                            Apakah Anda yakin ingin menghapus anggota "{selectedMember?.user?.full_name}"?
                            Tindakan ini tidak dapat dibatalkan.
                        </DialogDescription>
                    </DialogHeader>
                    {selectedMember && (selectedMember.current_borrowed || 0) > 0 && (
                        <div className="bg-yellow-50 p-4 rounded-lg flex items-start gap-2">
                            <AlertTriangle className="h-5 w-5 text-yellow-600 mt-0.5" />
                            <div>
                                <p className="text-yellow-800 font-medium">Anggota memiliki peminjaman aktif</p>
                                <p className="text-yellow-600 text-sm">
                                    Pastikan semua buku dikembalikan sebelum menghapus anggota
                                </p>
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={handleDelete}
                            disabled={submitting || (selectedMember?.current_borrowed || 0) > 0}
                        >
                            {submitting ? 'Menghapus...' : 'Hapus'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Loans History Dialog */}
            <Dialog open={loansOpen} onOpenChange={setLoansOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Riwayat Peminjaman</DialogTitle>
                        <DialogDescription>
                            Riwayat peminjaman buku oleh {selectedMember?.user?.full_name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="max-h-[400px] overflow-y-auto">
                        {loadingLoans ? (
                            <div className="text-center py-8 text-muted-foreground">Memuat...</div>
                        ) : memberLoans.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Tidak ada riwayat peminjaman
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Buku</TableHead>
                                        <TableHead>Tgl Pinjam</TableHead>
                                        <TableHead>Jatuh Tempo</TableHead>
                                        <TableHead>Dikembalikan</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {memberLoans.map((loan) => (
                                        <TableRow key={loan.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {loan.book_copy?.book?.title || '-'}
                                                </div>
                                            </TableCell>
                                            <TableCell>{formatDate(loan.borrow_date)}</TableCell>
                                            <TableCell>{formatDate(loan.due_date)}</TableCell>
                                            <TableCell>{formatDate(loan.return_date)}</TableCell>
                                            <TableCell>
                                                <Badge
                                                    className={
                                                        loan.status === 'returned'
                                                            ? 'bg-green-100 text-green-700'
                                                            : loan.is_overdue
                                                              ? 'bg-red-100 text-red-700'
                                                              : 'bg-blue-100 text-blue-700'
                                                    }
                                                >
                                                    {loan.status === 'returned'
                                                        ? 'Dikembalikan'
                                                        : loan.is_overdue
                                                          ? 'Terlambat'
                                                          : 'Dipinjam'}
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setLoansOpen(false)}>
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
