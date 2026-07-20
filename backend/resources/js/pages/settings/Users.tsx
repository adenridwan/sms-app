import { Head, Link, usePage } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { toast } from 'sonner';
import {
    Plus,
    Pencil,
    Trash2,
    RefreshCw,
    Search,
    MoreHorizontal,
    KeyRound,
    UserCheck,
    UserX,
    ShieldAlert,
} from 'lucide-react';
import { usersApi } from '@/services/api';
import type { User, PageProps, PaginationMeta } from '@/types';

interface GuardianStudentEntry {
    student_id: string;
    label: string;
    relationship: string;
    is_primary_contact: boolean;
}

interface UserForm {
    first_name: string;
    last_name: string;
    username: string;
    email: string;
    phone: string;
    password: string;
    role: string;
    is_active: boolean;
    // seksi dinamis (R1)
    staffEnabled: boolean;
    staffEmployeeId: string;
    staffJoinDate: string;
    staffEmployment: string;
    // penautan anak (R8)
    guardianStudents: GuardianStudentEntry[];
}

const emptyForm: UserForm = {
    first_name: '',
    last_name: '',
    username: '',
    email: '',
    phone: '',
    password: '',
    role: '',
    is_active: true,
    staffEnabled: false,
    staffEmployeeId: '',
    staffJoinDate: '',
    staffEmployment: 'permanent',
    guardianStudents: [],
};

// Role → seksi data tertaut yang otomatis dicentang (bisa di-override)
const TEACHER_ROLES = ['guru', 'wali_kelas'];
const STAFF_ROLES = ['kepala_sekolah', 'wakil_kepala_sekolah', 'tata_usaha', 'bendahara', 'pustakawan'];

const employmentLabels: Record<string, string> = {
    permanent: 'Tetap',
    contract: 'Kontrak',
    honorary: 'Honorer',
    part_time: 'Paruh Waktu',
};

const teacherStatusLabels: Record<string, string> = {
    active: 'Aktif',
    inactive: 'Nonaktif',
    on_leave: 'Cuti',
    retired: 'Pensiun',
    terminated: 'Berhenti',
};

const relationshipLabels: Record<string, string> = {
    father: 'Ayah',
    mother: 'Ibu',
    guardian: 'Wali',
    other: 'Lainnya',
};

const roleLabels: Record<string, string> = {
    super_admin: 'Super Admin',
    admin: 'Admin',
    kepala_sekolah: 'Kepala Sekolah',
    wakil_kepala_sekolah: 'Wakil Kepala Sekolah',
    guru: 'Guru',
    wali_kelas: 'Wali Kelas',
    tata_usaha: 'Tata Usaha',
    bendahara: 'Bendahara',
    pustakawan: 'Pustakawan',
    siswa: 'Siswa',
    orang_tua: 'Orang Tua',
};

// Role → user_type on the users table, kept in sync when saving
const roleToUserType: Record<string, string> = {
    super_admin: 'super_admin',
    admin: 'admin',
    kepala_sekolah: 'staff',
    wakil_kepala_sekolah: 'staff',
    guru: 'teacher',
    wali_kelas: 'teacher',
    tata_usaha: 'staff',
    bendahara: 'staff',
    pustakawan: 'staff',
    siswa: 'student',
    orang_tua: 'parent',
};

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

function formatDate(value: string | null) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getErrorMessage(error: unknown, fallback: string): string {
    if (error && typeof error === 'object' && 'response' in error) {
        const response = (error as {
            response?: { data?: { message?: string; errors?: Record<string, string[]> } };
        }).response;
        const errors = response?.data?.errors;
        if (errors) {
            const first = Object.values(errors)[0];
            if (first?.[0]) return first[0];
        }
        if (response?.data?.message) return response.data.message;
    }
    return fallback;
}

export default function SettingsUsers() {
    const { auth } = usePage<PageProps>().props;
    const isSuperAdmin = auth.user?.user_type === 'super_admin';

    const [users, setUsers] = useState<User[]>([]);
    const [meta, setMeta] = useState<PaginationMeta | null>(null);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');
    const [roleFilter, setRoleFilter] = useState('all');
    const [page, setPage] = useState(1);
    const [roles, setRoles] = useState<Array<{ id: string; name: string }>>([]);

    // Create/Edit dialog
    const [formOpen, setFormOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [form, setForm] = useState<UserForm>(emptyForm);
    const [saving, setSaving] = useState(false);

    // Data guru tertaut (G4): dibaca-saja di form Pengguna, dikelola dari
    // menu Data Guru — lihat linkedSectionRules di Admin/UserController.
    const [linkedTeacher, setLinkedTeacher] = useState<NonNullable<User['teacher']> | null>(null);

    // Delete dialog
    const [deletingUser, setDeletingUser] = useState<User | null>(null);

    // Reset password dialog
    const [resetUser, setResetUser] = useState<User | null>(null);
    const [newPassword, setNewPassword] = useState('');
    const [resetting, setResetting] = useState(false);

    // Pemilih siswa untuk penautan orang tua (R8)
    const [studentSearch, setStudentSearch] = useState('');
    const [studentOptions, setStudentOptions] = useState<
        Array<{ id: string; nis: string; name: string | null; class_name: string | null }>
    >([]);
    const [searchingStudents, setSearchingStudents] = useState(false);

    const fetchUsers = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = { page, per_page: 15 };
            if (search.trim()) params.search = search.trim();
            if (roleFilter !== 'all') params.role = roleFilter;

            const response = await usersApi.list(params);
            const payload = response.data.data;
            setUsers(payload.data ?? []);
            setMeta(payload.meta ?? null);
        } catch {
            toast.error('Gagal memuat data pengguna');
        } finally {
            setLoading(false);
        }
    }, [page, search, roleFilter]);

    useEffect(() => {
        if (!isSuperAdmin) return;
        fetchUsers();
    }, [fetchUsers, isSuperAdmin]);

    useEffect(() => {
        if (!isSuperAdmin) return;
        usersApi
            .roles()
            .then((response) => setRoles(response.data.data ?? []))
            .catch(() => toast.error('Gagal memuat daftar role'));
    }, [isSuperAdmin]);

    // Pencarian siswa (debounce) untuk pemilih anak orang tua
    useEffect(() => {
        if (!isSuperAdmin || !formOpen || form.role !== 'orang_tua' || studentSearch.trim().length < 2) {
            setStudentOptions([]);
            return;
        }
        const timer = setTimeout(() => {
            setSearchingStudents(true);
            usersApi
                .studentOptions(studentSearch.trim())
                .then((response) => setStudentOptions(response.data.data ?? []))
                .catch(() => setStudentOptions([]))
                .finally(() => setSearchingStudents(false));
        }, 350);
        return () => clearTimeout(timer);
    }, [isSuperAdmin, formOpen, form.role, studentSearch]);

    if (!isSuperAdmin) {
        return (
            <MainLayout title="Pengguna">
                <Head title="Pengguna" />
                <Card className="mx-auto mt-12 max-w-md">
                    <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
                        <ShieldAlert className="h-12 w-12 text-destructive" />
                        <h2 className="text-lg font-semibold">Akses Ditolak</h2>
                        <p className="text-sm text-muted-foreground">
                            Halaman manajemen pengguna hanya dapat diakses oleh Super Admin.
                        </p>
                    </CardContent>
                </Card>
            </MainLayout>
        );
    }

    const openCreate = () => {
        setEditingUser(null);
        setForm(emptyForm);
        setLinkedTeacher(null);
        setStudentSearch('');
        setFormOpen(true);
    };

    // Ganti role → set default seksi tertaut (bisa di-override manual)
    const applyRole = (role: string) => {
        setForm((f) => ({
            ...f,
            role,
            staffEnabled: STAFF_ROLES.includes(role) ? true : f.staffEnabled,
        }));
    };

    const openEdit = async (user: User) => {
        setEditingUser(user);
        setStudentSearch('');
        setLinkedTeacher(null);
        const base: UserForm = {
            ...emptyForm,
            first_name: user.first_name ?? '',
            last_name: user.last_name ?? '',
            username: user.username,
            email: user.email,
            phone: user.phone ?? '',
            password: '',
            role: user.roles?.[0] ?? '',
            is_active: user.is_active,
        };
        setForm(base);
        setFormOpen(true);

        // Muat data tertaut (teacher/staff/anak) untuk prefill
        try {
            const response = await usersApi.get(user.id);
            const detail = response.data.data;
            setLinkedTeacher(detail.teacher ?? null);
            setForm((f) => ({
                ...f,
                staffEnabled: !!detail.staff,
                staffEmployeeId: detail.staff?.employee_id ?? '',
                staffJoinDate: detail.staff?.join_date ?? '',
                staffEmployment: detail.staff?.employment_status ?? 'permanent',
                guardianStudents: (detail.guardian_students ?? []).map((g) => ({
                    student_id: g.student_id,
                    label: `${g.name ?? g.nis ?? ''} (${g.nis ?? '-'})`,
                    relationship: g.relationship,
                    is_primary_contact: g.is_primary_contact,
                })),
            }));
        } catch {
            toast.error('Gagal memuat data tertaut pengguna');
        }
    };

    const handleSubmit = async () => {
        if (!form.first_name.trim() || !form.username.trim() || !form.email.trim()) {
            toast.error('Nama depan, username, dan email wajib diisi');
            return;
        }
        if (!editingUser && form.password.length < 8) {
            toast.error('Password minimal 8 karakter');
            return;
        }
        if (editingUser && form.password && form.password.length < 8) {
            toast.error('Password baru minimal 8 karakter');
            return;
        }
        if (!form.role) {
            toast.error('Role wajib dipilih');
            return;
        }

        setSaving(true);
        try {
            const payload = {
                first_name: form.first_name.trim(),
                last_name: form.last_name.trim() || null,
                username: form.username.trim(),
                email: form.email.trim(),
                phone: form.phone.trim() || null,
                status: form.is_active ? 'active' : 'inactive',
                user_type: roleToUserType[form.role] ?? 'staff',
                roles: [form.role],
                ...(form.password ? { password: form.password } : {}),
                // Data kepegawaian guru TIDAK lagi dikirim dari form ini
                // (G4, TEACHER-MODULE-PLAN.md) — dikelola dari menu Data
                // Guru supaya tidak ada input ganda. API masih menerima
                // payload `teacher` demi kompatibilitas, hanya saja form
                // ini tidak lagi mengirimkannya.
                ...(form.staffEnabled
                    ? {
                          staff: {
                              employee_id: form.staffEmployeeId.trim() || null,
                              join_date: form.staffJoinDate || null,
                              employment_status: form.staffEmployment,
                          },
                      }
                    : {}),
                ...(form.role === 'orang_tua'
                    ? {
                          guardian_students: form.guardianStudents.map((g) => ({
                              student_id: g.student_id,
                              relationship: g.relationship,
                              is_primary_contact: g.is_primary_contact,
                          })),
                      }
                    : {}),
            };

            if (editingUser) {
                await usersApi.update(editingUser.id, payload);
                toast.success('Pengguna berhasil diperbarui');
            } else {
                await usersApi.create(payload as typeof payload & { password: string });
                toast.success('Pengguna berhasil ditambahkan');
            }
            setFormOpen(false);
            fetchUsers();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menyimpan pengguna'));
        } finally {
            setSaving(false);
        }
    };

    const handleDelete = async () => {
        if (!deletingUser) return;
        try {
            await usersApi.delete(deletingUser.id);
            toast.success('Pengguna berhasil dihapus');
            setDeletingUser(null);
            fetchUsers();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal menghapus pengguna'));
            setDeletingUser(null);
        }
    };

    const handleToggleActive = async (user: User) => {
        try {
            if (user.is_active) {
                await usersApi.deactivate(user.id);
                toast.success(`${user.full_name} dinonaktifkan`);
            } else {
                await usersApi.activate(user.id);
                toast.success(`${user.full_name} diaktifkan`);
            }
            fetchUsers();
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mengubah status pengguna'));
        }
    };

    const handleResetPassword = async () => {
        if (!resetUser) return;
        if (newPassword.length < 8) {
            toast.error('Password minimal 8 karakter');
            return;
        }
        setResetting(true);
        try {
            await usersApi.resetPassword(resetUser.id, newPassword);
            toast.success(`Password ${resetUser.full_name} berhasil direset`);
            setResetUser(null);
            setNewPassword('');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Gagal mereset password'));
        } finally {
            setResetting(false);
        }
    };

    return (
        <MainLayout title="Pengguna">
            <Head title="Manajemen Pengguna" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pengguna</h1>
                        <p className="text-muted-foreground">
                            Kelola akun pengguna dan hak akses sistem
                        </p>
                    </div>
                    <Button onClick={openCreate}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Pengguna
                    </Button>
                </div>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Pengguna</CardTitle>
                        <CardDescription>
                            {meta ? `${meta.total} pengguna terdaftar` : 'Memuat data pengguna'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative w-full max-w-sm">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama, username, atau email..."
                                    className="pl-8"
                                    value={search}
                                    onChange={(e) => {
                                        setSearch(e.target.value);
                                        setPage(1);
                                    }}
                                />
                            </div>
                            <Select
                                value={roleFilter}
                                onValueChange={(value) => {
                                    setRoleFilter(value);
                                    setPage(1);
                                }}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Semua Role" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua Role</SelectItem>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={role.name}>
                                            {roleLabels[role.name] ?? role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" size="icon" onClick={fetchUsers} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            </Button>
                        </div>

                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : users.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data pengguna
                            </div>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Pengguna</TableHead>
                                            <TableHead>Username</TableHead>
                                            <TableHead>Role</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Login Terakhir</TableHead>
                                            <TableHead className="w-[60px]"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {users.map((user) => (
                                            <TableRow key={user.id}>
                                                <TableCell>
                                                    <div className="flex items-center gap-3">
                                                        <Avatar className="h-9 w-9">
                                                            <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                                                {getInitials(user.full_name || user.username)}
                                                            </AvatarFallback>
                                                        </Avatar>
                                                        <div className="min-w-0">
                                                            <div className="truncate font-medium">
                                                                {user.full_name || user.username}
                                                            </div>
                                                            <div className="truncate text-sm text-muted-foreground">
                                                                {user.email}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="font-mono text-sm">
                                                    {user.username}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-wrap gap-1">
                                                        {(user.roles ?? []).length === 0 ? (
                                                            <span className="text-sm text-muted-foreground">-</span>
                                                        ) : (
                                                            user.roles.map((role) => (
                                                                <Badge
                                                                    key={role}
                                                                    variant={
                                                                        role === 'super_admin'
                                                                            ? 'destructive'
                                                                            : 'secondary'
                                                                    }
                                                                >
                                                                    {roleLabels[role] ?? role}
                                                                </Badge>
                                                            ))
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={user.is_active ? 'default' : 'outline'}>
                                                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground">
                                                    {formatDate(user.last_login_at)}
                                                </TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem onClick={() => openEdit(user)}>
                                                                <Pencil className="mr-2 h-4 w-4" />
                                                                Edit
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => setResetUser(user)}
                                                            >
                                                                <KeyRound className="mr-2 h-4 w-4" />
                                                                Reset Password
                                                            </DropdownMenuItem>
                                                            {user.id !== auth.user?.id && (
                                                                <>
                                                                    <DropdownMenuItem
                                                                        onClick={() => handleToggleActive(user)}
                                                                    >
                                                                        {user.is_active ? (
                                                                            <>
                                                                                <UserX className="mr-2 h-4 w-4" />
                                                                                Nonaktifkan
                                                                            </>
                                                                        ) : (
                                                                            <>
                                                                                <UserCheck className="mr-2 h-4 w-4" />
                                                                                Aktifkan
                                                                            </>
                                                                        )}
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem
                                                                        className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                                                                        onClick={() => setDeletingUser(user)}
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
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        {/* Pagination */}
                        {meta && meta.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {meta.from}-{meta.to} dari {meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page <= 1 || loading}
                                        onClick={() => setPage((p) => p - 1)}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={page >= meta.last_page || loading}
                                        onClick={() => setPage((p) => p + 1)}
                                    >
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
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {editingUser ? 'Edit Pengguna' : 'Tambah Pengguna'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingUser
                                ? 'Perbarui data akun pengguna'
                                : 'Isi data akun pengguna baru'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="user-first-name">Nama Depan *</Label>
                            <Input
                                id="user-first-name"
                                placeholder="Contoh: Budi"
                                value={form.first_name}
                                onChange={(e) => setForm({ ...form, first_name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-last-name">Nama Belakang</Label>
                            <Input
                                id="user-last-name"
                                placeholder="Contoh: Santoso"
                                value={form.last_name}
                                onChange={(e) => setForm({ ...form, last_name: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-username">Username *</Label>
                            <Input
                                id="user-username"
                                placeholder="Contoh: budi.santoso"
                                value={form.username}
                                onChange={(e) => setForm({ ...form, username: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-email">Email *</Label>
                            <Input
                                id="user-email"
                                type="email"
                                placeholder="nama@sekolah.sch.id"
                                value={form.email}
                                onChange={(e) => setForm({ ...form, email: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-phone">No. HP</Label>
                            <Input
                                id="user-phone"
                                placeholder="08xxxxxxxxxx"
                                value={form.phone}
                                onChange={(e) => setForm({ ...form, phone: e.target.value })}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="user-role">Role *</Label>
                            <Select value={form.role} onValueChange={applyRole}>
                                <SelectTrigger id="user-role">
                                    <SelectValue placeholder="Pilih role" />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem key={role.id} value={role.name}>
                                            {roleLabels[role.name] ?? role.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Seksi dinamis: data kepegawaian (R1) */}
                        {form.role && form.role !== 'orang_tua' && form.role !== 'siswa' && (
                            <div className="space-y-3 rounded-md border p-3 sm:col-span-2">
                                {/* Data Guru: read-only sejak G4 — dikelola dari menu Data Guru
                                    supaya tidak ada input ganda dengan tabel `teachers`. */}
                                {(TEACHER_ROLES.includes(form.role) || linkedTeacher) && (
                                    <div className="space-y-2 rounded-md border bg-muted/30 p-3">
                                        <Label className="text-sm font-medium">Data Guru</Label>
                                        {linkedTeacher ? (
                                            <>
                                                <div className="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-2">
                                                    <div>
                                                        <span className="text-muted-foreground">NIP: </span>
                                                        {linkedTeacher.nip || '-'}
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Status: </span>
                                                        {teacherStatusLabels[linkedTeacher.status ?? ''] ?? linkedTeacher.status ?? '-'}
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Kepegawaian: </span>
                                                        {employmentLabels[linkedTeacher.employment_status ?? ''] ?? '-'}
                                                    </div>
                                                    <div>
                                                        <span className="text-muted-foreground">Pendidikan: </span>
                                                        {linkedTeacher.education_level || '-'}
                                                    </div>
                                                </div>
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/teachers/${linkedTeacher.id}/edit`}>
                                                        Kelola di Data Guru
                                                    </Link>
                                                </Button>
                                            </>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                Data kepegawaian (NIP, status, pendidikan) belum ada. Lengkapi
                                                lewat menu{' '}
                                                <Link href="/teachers" className="font-medium text-primary underline">
                                                    Data Guru
                                                </Link>{' '}
                                                setelah akun ini dibuat.
                                            </p>
                                        )}
                                    </div>
                                )}

                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <Switch
                                        checked={form.staffEnabled}
                                        onCheckedChange={(v) => setForm({ ...form, staffEnabled: v })}
                                    />
                                    Data Staf
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Aktifkan agar akun langsung terhubung ke Data Staf.
                                </p>

                                {form.staffEnabled && (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="staff-empid">No. Pegawai</Label>
                                            <Input
                                                id="staff-empid"
                                                placeholder="Contoh: STF-001"
                                                value={form.staffEmployeeId}
                                                onChange={(e) => setForm({ ...form, staffEmployeeId: e.target.value })}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="staff-join">Tanggal Masuk</Label>
                                            <Input
                                                id="staff-join"
                                                type="date"
                                                value={form.staffJoinDate}
                                                onChange={(e) => setForm({ ...form, staffJoinDate: e.target.value })}
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>Status Kepegawaian</Label>
                                            <Select
                                                value={form.staffEmployment}
                                                onValueChange={(v) => setForm({ ...form, staffEmployment: v })}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(employmentLabels).map(([value, label]) => (
                                                        <SelectItem key={value} value={value}>
                                                            {label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Seksi dinamis: penautan anak untuk orang tua (R8) */}
                        {form.role === 'orang_tua' && (
                            <div className="space-y-3 rounded-md border p-3 sm:col-span-2">
                                <div>
                                    <Label className="text-sm font-medium">Siswa yang Diampu</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Cari lalu pilih putra/putri dari akun orang tua ini
                                        (boleh lebih dari satu).
                                    </p>
                                </div>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Cari nama atau NIS siswa (min. 2 huruf)..."
                                        className="pl-8"
                                        value={studentSearch}
                                        onChange={(e) => setStudentSearch(e.target.value)}
                                    />
                                </div>
                                {searchingStudents && (
                                    <p className="text-xs text-muted-foreground">Mencari...</p>
                                )}
                                {studentOptions.length > 0 && (
                                    <div className="max-h-40 space-y-1 overflow-y-auto rounded-md border p-1">
                                        {studentOptions
                                            .filter((o) => !form.guardianStudents.some((g) => g.student_id === o.id))
                                            .map((option) => (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-muted"
                                                    onClick={() => {
                                                        setForm({
                                                            ...form,
                                                            guardianStudents: [
                                                                ...form.guardianStudents,
                                                                {
                                                                    student_id: option.id,
                                                                    label: `${option.name ?? option.nis} (${option.nis})`,
                                                                    relationship: 'father',
                                                                    is_primary_contact: form.guardianStudents.length === 0,
                                                                },
                                                            ],
                                                        });
                                                        setStudentSearch('');
                                                    }}
                                                >
                                                    <span>
                                                        {option.name ?? option.nis}
                                                        <span className="ml-2 text-muted-foreground">{option.nis}</span>
                                                    </span>
                                                    <Badge variant="secondary">{option.class_name ?? '-'}</Badge>
                                                </button>
                                            ))}
                                    </div>
                                )}
                                {form.guardianStudents.length === 0 ? (
                                    <p className="py-2 text-center text-sm text-muted-foreground">
                                        Belum ada siswa dipilih
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {form.guardianStudents.map((g, index) => (
                                            <div
                                                key={g.student_id}
                                                className="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2"
                                            >
                                                <span className="min-w-0 flex-1 truncate text-sm font-medium">
                                                    {g.label}
                                                </span>
                                                <Select
                                                    value={g.relationship}
                                                    onValueChange={(v) => {
                                                        const next = [...form.guardianStudents];
                                                        next[index] = { ...g, relationship: v };
                                                        setForm({ ...form, guardianStudents: next });
                                                    }}
                                                >
                                                    <SelectTrigger className="h-8 w-[110px]">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(relationshipLabels).map(([value, label]) => (
                                                            <SelectItem key={value} value={value}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-muted-foreground hover:text-red-600"
                                                    onClick={() =>
                                                        setForm({
                                                            ...form,
                                                            guardianStudents: form.guardianStudents.filter(
                                                                (x) => x.student_id !== g.student_id
                                                            ),
                                                        })
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                        <div className="space-y-2 sm:col-span-2">
                            <Label htmlFor="user-password">
                                {editingUser ? 'Password Baru' : 'Password *'}
                            </Label>
                            <Input
                                id="user-password"
                                type="password"
                                placeholder={
                                    editingUser
                                        ? 'Kosongkan jika tidak diubah'
                                        : 'Minimal 8 karakter'
                                }
                                value={form.password}
                                onChange={(e) => setForm({ ...form, password: e.target.value })}
                            />
                        </div>
                        <div className="flex items-center justify-between rounded-md border p-3 sm:col-span-2">
                            <div>
                                <Label htmlFor="user-active">Aktif</Label>
                                <p className="text-sm text-muted-foreground">
                                    Pengguna aktif dapat login ke sistem
                                </p>
                            </div>
                            <Switch
                                id="user-active"
                                checked={form.is_active}
                                onCheckedChange={(checked) =>
                                    setForm({ ...form, is_active: checked })
                                }
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setFormOpen(false)} disabled={saving}>
                            Batal
                        </Button>
                        <Button onClick={handleSubmit} disabled={saving}>
                            {saving && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            {editingUser ? 'Simpan Perubahan' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reset Password Dialog */}
            <Dialog
                open={!!resetUser}
                onOpenChange={(open) => {
                    if (!open) {
                        setResetUser(null);
                        setNewPassword('');
                    }
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset Password</DialogTitle>
                        <DialogDescription>
                            Atur password baru untuk{' '}
                            <span className="font-medium">{resetUser?.full_name}</span>
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="reset-password">Password Baru *</Label>
                        <Input
                            id="reset-password"
                            type="password"
                            placeholder="Minimal 8 karakter"
                            value={newPassword}
                            onChange={(e) => setNewPassword(e.target.value)}
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setResetUser(null);
                                setNewPassword('');
                            }}
                            disabled={resetting}
                        >
                            Batal
                        </Button>
                        <Button onClick={handleResetPassword} disabled={resetting}>
                            {resetting && <RefreshCw className="mr-2 h-4 w-4 animate-spin" />}
                            <KeyRound className="mr-2 h-4 w-4" />
                            Reset Password
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <AlertDialog open={!!deletingUser} onOpenChange={() => setDeletingUser(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Hapus Pengguna</AlertDialogTitle>
                        <AlertDialogDescription>
                            Apakah Anda yakin ingin menghapus pengguna{' '}
                            <span className="font-medium">{deletingUser?.full_name}</span> (
                            {deletingUser?.email})? Pengguna tidak akan bisa login lagi.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setDeletingUser(null)}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleDelete}
                            className="bg-red-600 hover:bg-red-700"
                        >
                            Hapus
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
