import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
import { Plus, Search, MoreHorizontal, Eye, Pencil } from 'lucide-react';
import type { Teacher, PaginatedResponse } from '@/types';

interface Props {
    teachers: PaginatedResponse<Teacher>;
    filters: {
        search?: string;
        status?: string;
        employment_status?: string;
    };
}

export default function TeachersIndex({ teachers, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/teachers', { search }, { preserveState: true });
    };

    const getStatusBadge = (teacher: Teacher) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            inactive: 'destructive',
            on_leave: 'outline',
            retired: 'secondary',
            terminated: 'destructive',
        };
        return (
            <Badge variant={variants[teacher.status] || 'default'}>
                {teacher.status_label}
            </Badge>
        );
    };

    return (
        <MainLayout>
            <Head title="Data Guru" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Data Guru</h1>
                        <p className="text-muted-foreground">
                            Kelola data guru sekolah
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/teachers/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Guru
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Guru</CardTitle>
                        <CardDescription>
                            Total {teachers.meta?.total || 0} guru terdaftar
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari NIP, NUPTK, nama, atau email..."
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
                                        <TableHead>NIP</TableHead>
                                        <TableHead>Nama Lengkap</TableHead>
                                        <TableHead>No. HP</TableHead>
                                        <TableHead>Kepegawaian</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {teachers.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                                                Tidak ada data guru
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        teachers.data.map((teacher) => (
                                            <TableRow key={teacher.id}>
                                                <TableCell className="font-medium">{teacher.nip || '-'}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{teacher.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">{teacher.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{teacher.phone || '-'}</TableCell>
                                                <TableCell>{teacher.employment_status_label}</TableCell>
                                                <TableCell>{getStatusBadge(teacher)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/teachers/${teacher.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Lihat Detail
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/teachers/${teacher.id}/edit`}>
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
                        {teachers.meta && teachers.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {teachers.meta.from} - {teachers.meta.to} dari {teachers.meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    {teachers.links?.prev && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(teachers.links!.prev!)}
                                        >
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {teachers.links?.next && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(teachers.links!.next!)}
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
