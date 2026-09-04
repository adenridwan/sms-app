import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import type { PageProps } from '@/types';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Badge } from '@/components/ui/badge';
import {
    Plus,
    Search,
    MoreHorizontal,
    Eye,
    Pencil,
    RefreshCw,
} from 'lucide-react';
import type { Staff, PaginatedResponse } from '@/types';

interface Props {
    staff: PaginatedResponse<Staff>;
    filters: {
        search?: string;
        status?: string;
        employment_status?: string;
        department_id?: string;
    };
}

export default function StaffIndex({ staff, filters }: Props) {
    const { auth } = usePage<PageProps>().props;
    const [search, setSearch] = useState(filters.search || '');
    const [refreshing, setRefreshing] = useState(false);

    const isSuperAdmin = auth?.user?.user_type === 'super_admin';
    const permissions = auth?.user?.permissions ?? [];
    const canCreate = isSuperAdmin || permissions.includes('staff.create');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/staff', { search }, { preserveState: true });
    };

    const handleRefresh = () => {
        setRefreshing(true);
        router.reload({
            only: ['staff'],
            onFinish: () => setRefreshing(false),
        });
    };

    const getStatusBadge = (staffMember: Staff) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            inactive: 'destructive',
            on_leave: 'outline',
            retired: 'secondary',
            terminated: 'destructive',
        };
        return (
            <Badge variant={variants[staffMember.status] || 'default'}>
                {staffMember.status_label}
            </Badge>
        );
    };

    return (
        <MainLayout>
            <Head title="Data Staf" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Data Staf</h1>
                        <p className="text-muted-foreground">
                            Kelola data staf sekolah (non-teaching)
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={handleRefresh} disabled={refreshing}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        {canCreate && (
                            <Button asChild>
                                <Link href="/staff/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Tambah Staf
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Staf</CardTitle>
                        <CardDescription>
                            Total {staff.meta?.total || 0} staf terdaftar
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari ID, nama, atau email..."
                                        className="pl-8"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </div>
                                <Button type="submit" variant="secondary">
                                    Cari
                                </Button>
                            </form>
                        </div>

                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>ID Pegawai</TableHead>
                                        <TableHead>Nama Lengkap</TableHead>
                                        <TableHead>No. HP</TableHead>
                                        <TableHead>Jabatan</TableHead>
                                        <TableHead>Kepegawaian</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {staff.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                                                Tidak ada data staf
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        staff.data.map((staffMember) => (
                                            <TableRow key={staffMember.id}>
                                                <TableCell className="font-medium">{staffMember.employee_id || '-'}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{staffMember.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">{staffMember.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{staffMember.phone || '-'}</TableCell>
                                                <TableCell>{staffMember.position_name || '-'}</TableCell>
                                                <TableCell>{staffMember.employment_status_label}</TableCell>
                                                <TableCell>{getStatusBadge(staffMember)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/staff/${staffMember.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Lihat Detail
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/staff/${staffMember.id}/edit`}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
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
                        {staff.meta && staff.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {staff.meta.from} - {staff.meta.to} dari {staff.meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    {staff.links?.prev && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(staff.links!.prev!)}
                                        >
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {staff.links?.next && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(staff.links!.next!)}
                                        >
                                            Selanjutnya
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}
