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
import { Plus, Search, MoreHorizontal, Eye, Pencil, Trash2, Download, Upload } from 'lucide-react';
import type { Student, PaginatedResponse } from '@/types';

interface Props {
    students: PaginatedResponse<Student>;
    filters: {
        search?: string;
        status?: string;
        gender?: string;
    };
}

export default function StudentsIndex({ students, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get('/students', { search }, { preserveState: true });
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            graduated: 'secondary',
            transferred: 'outline',
            dropped: 'destructive',
        };
        const labels: Record<string, string> = {
            active: 'Aktif',
            graduated: 'Lulus',
            transferred: 'Pindah',
            dropped: 'Keluar',
        };
        return <Badge variant={variants[status] || 'default'}>{labels[status] || status}</Badge>;
    };

    const handleDelete = (id: string) => {
        if (confirm('Apakah Anda yakin ingin menghapus siswa ini?')) {
            router.delete(`/students/${id}`);
        }
    };

    return (
        <MainLayout>
            <Head title="Data Siswa" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Data Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola data siswa sekolah
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline">
                            <Download className="mr-2 h-4 w-4" />
                            Export
                        </Button>
                        <Button variant="outline">
                            <Upload className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button asChild>
                            <Link href="/students/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Tambah Siswa
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Siswa</CardTitle>
                        <CardDescription>
                            Total {students.meta?.total || 0} siswa terdaftar
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="mb-4">
                            <form onSubmit={handleSearch} className="flex gap-2">
                                <div className="relative flex-1 max-w-sm">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari NIS, nama, atau email..."
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
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama Lengkap</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>Jenis Kelamin</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="w-[70px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {students.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-8 text-muted-foreground">
                                                Tidak ada data siswa
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        students.data.map((student) => (
                                            <TableRow key={student.id}>
                                                <TableCell className="font-medium">{student.nis}</TableCell>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{student.full_name}</div>
                                                        <div className="text-sm text-muted-foreground">{student.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{student.current_class?.name || '-'}</TableCell>
                                                <TableCell>{student.gender_label}</TableCell>
                                                <TableCell>{getStatusBadge(student.status)}</TableCell>
                                                <TableCell>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="icon">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/students/${student.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    Lihat Detail
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/students/${student.id}/edit`}>
                                                                    <Pencil className="mr-2 h-4 w-4" />
                                                                    Edit
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                className="text-destructive"
                                                                onClick={() => handleDelete(student.id)}
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
                        {students.meta && students.meta.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Menampilkan {students.meta.from} - {students.meta.to} dari {students.meta.total} data
                                </p>
                                <div className="flex gap-2">
                                    {students.links?.prev && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(students.links!.prev!)}
                                        >
                                            Sebelumnya
                                        </Button>
                                    )}
                                    {students.links?.next && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get(students.links!.next!)}
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
