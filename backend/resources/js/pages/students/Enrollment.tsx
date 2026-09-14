import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Search,
    UserPlus,
    Users,
    Loader2,
    RefreshCw,
    GraduationCap,
    Calendar,
} from 'lucide-react';
import { enrollmentApi } from '@/services/api';

interface AcademicYear {
    id: string;
    name: string;
    is_active: boolean;
}

interface Classroom {
    id: string;
    name: string;
    code: string | null;
    grade_level: string | null;
}

interface UnenrolledStudent {
    id: string;
    nis: string;
    full_name: string;
}

interface Enrollment {
    id: string;
    student_id: string;
    student: {
        id: string;
        nis: string;
        full_name: string;
        photo_url: string | null;
    };
    academic_year: {
        id: string;
        name: string;
        is_active: boolean;
    };
    classroom: {
        id: string;
        name: string;
        code: string | null;
    };
    student_number_in_class: string | null;
    status: string;
    status_label: string;
    enrollment_date: string;
}

interface Props {
    academicYears: AcademicYear[];
    classrooms: Classroom[];
    unenrolledStudents: UnenrolledStudent[];
    activeYearId: string | null;
}

export default function StudentEnrollment({
    academicYears,
    classrooms,
    unenrolledStudents,
    activeYearId,
}: Props) {
    const [selectedYear, setSelectedYear] = useState(activeYearId || '');
    const [selectedClassroom, setSelectedClassroom] = useState('');
    const [enrollments, setEnrollments] = useState<Enrollment[]>([]);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState('');

    // Bulk enroll state
    const [bulkDialogOpen, setBulkDialogOpen] = useState(false);
    const [bulkYear, setBulkYear] = useState(activeYearId || '');
    const [bulkClassroom, setBulkClassroom] = useState('');
    const [selectedStudents, setSelectedStudents] = useState<string[]>([]);
    const [bulkEnrolling, setBulkEnrolling] = useState(false);
    const [studentSearch, setStudentSearch] = useState('');

    const filteredUnenrolled = unenrolledStudents.filter(
        (s) =>
            s.full_name.toLowerCase().includes(studentSearch.toLowerCase()) ||
            s.nis.includes(studentSearch)
    );

    const loadEnrollments = async () => {
        if (!selectedYear) return;

        setLoading(true);
        try {
            const params: Record<string, string> = { academic_year_id: selectedYear };
            if (selectedClassroom) params.classroom_id = selectedClassroom;
            if (search) params.search = search;

            const response = await enrollmentApi.list(params);
            setEnrollments(response.data.data || []);
        } catch {
            toast.error('Gagal memuat data pendaftaran');
        } finally {
            setLoading(false);
        }
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        loadEnrollments();
    };

    const handleYearChange = (value: string) => {
        setSelectedYear(value);
        setEnrollments([]);
    };

    const handleClassroomChange = (value: string) => {
        setSelectedClassroom(value === 'all' ? '' : value);
    };

    const handleBulkEnroll = async () => {
        if (!bulkYear || !bulkClassroom || selectedStudents.length === 0) {
            toast.error('Pilih tahun ajaran, kelas, dan minimal satu siswa');
            return;
        }

        setBulkEnrolling(true);
        try {
            const response = await enrollmentApi.bulkEnroll({
                student_ids: selectedStudents,
                academic_year_id: bulkYear,
                classroom_id: bulkClassroom,
            });

            const result = response.data.data;
            toast.success(`${result.enrolled} siswa berhasil didaftarkan`);

            setBulkDialogOpen(false);
            setSelectedStudents([]);
            setBulkClassroom('');

            // Reload enrollments and page data
            loadEnrollments();
            router.reload({ only: ['unenrolledStudents'] });
        } catch (error) {
            const message =
                (error as { response?: { data?: { message?: string } } }).response?.data?.message ||
                'Gagal mendaftarkan siswa';
            toast.error(message);
        } finally {
            setBulkEnrolling(false);
        }
    };

    const toggleStudent = (studentId: string) => {
        setSelectedStudents((prev) =>
            prev.includes(studentId)
                ? prev.filter((id) => id !== studentId)
                : [...prev, studentId]
        );
    };

    const toggleAllStudents = () => {
        if (selectedStudents.length === filteredUnenrolled.length) {
            setSelectedStudents([]);
        } else {
            setSelectedStudents(filteredUnenrolled.map((s) => s.id));
        }
    };

    const getStatusBadge = (status: string, label: string) => {
        const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
            active: 'default',
            promoted: 'secondary',
            retained: 'outline',
            transferred: 'outline',
            dropped: 'destructive',
        };
        return <Badge variant={variants[status] || 'default'}>{label}</Badge>;
    };

    return (
        <MainLayout>
            <Head title="Pendaftaran Siswa" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pendaftaran Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola pendaftaran siswa ke kelas per tahun ajaran
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={loadEnrollments} disabled={loading || !selectedYear}>
                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                            Refresh
                        </Button>
                        <Button onClick={() => setBulkDialogOpen(true)} disabled={unenrolledStudents.length === 0}>
                            <UserPlus className="mr-2 h-4 w-4" />
                            Daftarkan Siswa
                        </Button>
                    </div>
                </div>

                {/* Statistics */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Tahun Ajaran</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{academicYears.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Kelas</CardTitle>
                            <GraduationCap className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{classrooms.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Belum Terdaftar</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{unenrolledStudents.length}</div>
                            <p className="text-xs text-muted-foreground">siswa aktif belum punya kelas</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filter & List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Pendaftaran</CardTitle>
                        <CardDescription>
                            Pilih tahun ajaran untuk melihat data pendaftaran siswa
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSearch} className="mb-4 flex flex-wrap items-end gap-3">
                            <div className="space-y-1.5">
                                <Label>Tahun Ajaran</Label>
                                <Select value={selectedYear} onValueChange={handleYearChange}>
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Pilih tahun ajaran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {academicYears.map((year) => (
                                            <SelectItem key={year.id} value={year.id}>
                                                {year.name} {year.is_active && '(Aktif)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Kelas</Label>
                                <Select
                                    value={selectedClassroom || 'all'}
                                    onValueChange={handleClassroomChange}
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Semua kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Kelas</SelectItem>
                                        {classrooms.map((cls) => (
                                            <SelectItem key={cls.id} value={cls.id}>
                                                {cls.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Cari</Label>
                                <div className="relative">
                                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        type="search"
                                        placeholder="Cari NIS atau nama..."
                                        className="w-[200px] pl-8"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                    />
                                </div>
                            </div>

                            <Button type="submit" disabled={!selectedYear || loading}>
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
                                        <TableHead className="w-[60px]">No</TableHead>
                                        <TableHead>NIS</TableHead>
                                        <TableHead>Nama Siswa</TableHead>
                                        <TableHead>Kelas</TableHead>
                                        <TableHead>No. Absen</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Tanggal Daftar</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {!selectedYear ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                                Pilih tahun ajaran terlebih dahulu
                                            </TableCell>
                                        </TableRow>
                                    ) : loading ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="py-8 text-center">
                                                <Loader2 className="mx-auto h-6 w-6 animate-spin text-muted-foreground" />
                                            </TableCell>
                                        </TableRow>
                                    ) : enrollments.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                                Tidak ada data pendaftaran. Klik "Tampilkan" untuk memuat data.
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        enrollments.map((enrollment, index) => (
                                            <TableRow key={enrollment.id}>
                                                <TableCell className="tabular-nums text-muted-foreground">
                                                    {index + 1}
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {enrollment.student?.nis}
                                                </TableCell>
                                                <TableCell>{enrollment.student?.full_name}</TableCell>
                                                <TableCell>{enrollment.classroom?.name}</TableCell>
                                                <TableCell>{enrollment.student_number_in_class || '-'}</TableCell>
                                                <TableCell>
                                                    {getStatusBadge(enrollment.status, enrollment.status_label)}
                                                </TableCell>
                                                <TableCell>
                                                    {enrollment.enrollment_date
                                                        ? new Date(enrollment.enrollment_date).toLocaleDateString('id-ID')
                                                        : '-'}
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

            {/* Bulk Enroll Dialog */}
            <Dialog open={bulkDialogOpen} onOpenChange={setBulkDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Daftarkan Siswa ke Kelas</DialogTitle>
                        <DialogDescription>
                            Pilih siswa yang akan didaftarkan ke kelas pada tahun ajaran tertentu
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-1.5">
                                <Label>Tahun Ajaran</Label>
                                <Select value={bulkYear} onValueChange={setBulkYear}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tahun ajaran" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {academicYears.map((year) => (
                                            <SelectItem key={year.id} value={year.id}>
                                                {year.name} {year.is_active && '(Aktif)'}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1.5">
                                <Label>Kelas Tujuan</Label>
                                <Select value={bulkClassroom} onValueChange={setBulkClassroom}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classrooms.map((cls) => (
                                            <SelectItem key={cls.id} value={cls.id}>
                                                {cls.name} {cls.grade_level && `(${cls.grade_level})`}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="space-y-1.5">
                            <div className="flex items-center justify-between">
                                <Label>Pilih Siswa ({selectedStudents.length} dipilih)</Label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={toggleAllStudents}
                                >
                                    {selectedStudents.length === filteredUnenrolled.length
                                        ? 'Batal Pilih Semua'
                                        : 'Pilih Semua'}
                                </Button>
                            </div>
                            <div className="relative">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    type="search"
                                    placeholder="Cari siswa..."
                                    className="pl-8"
                                    value={studentSearch}
                                    onChange={(e) => setStudentSearch(e.target.value)}
                                />
                            </div>
                            <ScrollArea className="h-[250px] rounded-md border p-2">
                                {filteredUnenrolled.length === 0 ? (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        {unenrolledStudents.length === 0
                                            ? 'Semua siswa aktif sudah terdaftar di tahun ajaran aktif'
                                            : 'Tidak ada siswa yang cocok'}
                                    </div>
                                ) : (
                                    <div className="space-y-1">
                                        {filteredUnenrolled.map((student) => (
                                            <label
                                                key={student.id}
                                                className="flex cursor-pointer items-center gap-3 rounded-md p-2 hover:bg-muted"
                                            >
                                                <Checkbox
                                                    checked={selectedStudents.includes(student.id)}
                                                    onCheckedChange={() => toggleStudent(student.id)}
                                                />
                                                <span className="font-mono text-sm text-muted-foreground">
                                                    {student.nis}
                                                </span>
                                                <span className="text-sm">{student.full_name}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </ScrollArea>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setBulkDialogOpen(false)}
                            disabled={bulkEnrolling}
                        >
                            Batal
                        </Button>
                        <Button
                            onClick={handleBulkEnroll}
                            disabled={
                                bulkEnrolling ||
                                !bulkYear ||
                                !bulkClassroom ||
                                selectedStudents.length === 0
                            }
                        >
                            {bulkEnrolling && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            <UserPlus className="mr-2 h-4 w-4" />
                            Daftarkan {selectedStudents.length} Siswa
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
