import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
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
import {
    gradeApi,
    semestersApi,
    classroomsApi,
    subjectsApi,
} from '@/services/api';
import { Calculator } from 'lucide-react';

interface SelectOption {
    id: string;
    name: string;
}

interface ClassroomGrade {
    student: { id: string; nis: string; full_name: string };
    grades: Array<{
        id: string;
        subject_id: string;
        subject_name: string;
        final_score: number;
        grade_letter: string;
        is_passed: boolean;
    }>;
    average: number;
}

interface ClassroomSummary {
    total_students: number;
    class_average: number | null;
}

export default function GradeRecap() {
    // State
    const [semesters, setSemesters] = useState<SelectOption[]>([]);
    const [classrooms, setClassrooms] = useState<SelectOption[]>([]);
    const [subjects, setSubjects] = useState<SelectOption[]>([]);

    const [selectedSemester, setSelectedSemester] = useState<string>('');
    const [selectedClassroom, setSelectedClassroom] = useState<string>('');
    const [selectedSubject, setSelectedSubject] = useState<string>('');

    const [loading, setLoading] = useState(false);
    const [loadingOptions, setLoadingOptions] = useState(true);
    const [finalizing, setFinalizing] = useState(false);

    const [grades, setGrades] = useState<ClassroomGrade[]>([]);
    const [summary, setSummary] = useState<ClassroomSummary | null>(null);

    // Load select options
    const loadOptions = async () => {
        try {
            setLoadingOptions(true);
            const [semestersRes, classroomsRes, subjectsRes] = await Promise.all([
                semestersApi.list({ all: true }),
                classroomsApi.list({ all: true }),
                subjectsApi.list({ all: true }),
            ]);

            setSemesters((semestersRes.data.data || []) as unknown as SelectOption[]);
            setClassrooms((classroomsRes.data.data || []) as unknown as SelectOption[]);
            setSubjects((subjectsRes.data.data || []) as unknown as SelectOption[]);
        } catch (error) {
            console.error('Failed to load options:', error);
            toast.error('Gagal memuat data');
        } finally {
            setLoadingOptions(false);
        }
    };

    // Load grades
    const loadGrades = useCallback(async () => {
        if (!selectedSemester || !selectedClassroom) {
            setGrades([]);
            setSummary(null);
            return;
        }

        try {
            setLoading(true);
            const params: { semester_id: string; subject_id?: string } = {
                semester_id: selectedSemester,
            };
            if (selectedSubject) {
                params.subject_id = selectedSubject;
            }

            const response = await gradeApi.byClassroom(selectedClassroom, params);
            setGrades(response.data.data?.students || []);
            setSummary(response.data.data?.summary || null);
        } catch (error) {
            console.error('Failed to load grades:', error);
            toast.error('Gagal memuat data nilai');
        } finally {
            setLoading(false);
        }
    }, [selectedSemester, selectedClassroom, selectedSubject]);

    useEffect(() => {
        loadOptions();
    }, []);

    useEffect(() => {
        loadGrades();
    }, [loadGrades]);

    // Handle finalize grades
    const handleFinalize = async () => {
        if (!selectedSemester || !selectedClassroom || !selectedSubject) {
            toast.error('Pilih semester, kelas, dan mata pelajaran untuk finalisasi');
            return;
        }

        setFinalizing(true);
        try {
            const response = await gradeApi.finalize({
                semester_id: selectedSemester,
                classroom_id: selectedClassroom,
                subject_id: selectedSubject,
            });

            const result = response.data.data;
            if (result?.errors > 0) {
                toast.warning(`Finalisasi selesai dengan ${result.errors} error`, {
                    description: result.error_details?.map((e) => e.student_name).join(', '),
                });
            } else {
                toast.success(`${result?.processed} nilai berhasil difinalisasi`);
            }
            loadGrades();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal memfinalisasi nilai', {
                description: err.response?.data?.message,
            });
        } finally {
            setFinalizing(false);
        }
    };

    // Get unique subjects from grades
    const uniqueSubjects = grades.length > 0
        ? [...new Map(grades[0]?.grades.map((g) => [g.subject_id, { id: g.subject_id, name: g.subject_name }])).values()]
        : [];

    return (
        <MainLayout title="Rekap Nilai">
            <Head title="Rekap Nilai" />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Rekap Nilai Kelas</CardTitle>
                        <CardDescription>
                            Lihat dan finalisasi nilai per kelas dan mata pelajaran
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Filters */}
                        <div className="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div className="space-y-2">
                                <Label>Semester</Label>
                                <Select
                                    value={selectedSemester}
                                    onValueChange={setSelectedSemester}
                                    disabled={loadingOptions}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih semester" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {semesters.map((sem) => (
                                            <SelectItem key={sem.id} value={sem.id}>
                                                {sem.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Kelas</Label>
                                <Select
                                    value={selectedClassroom}
                                    onValueChange={setSelectedClassroom}
                                    disabled={loadingOptions}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {classrooms.map((cls) => (
                                            <SelectItem key={cls.id} value={cls.id}>
                                                {cls.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Mata Pelajaran (Opsional)</Label>
                                <Select
                                    value={selectedSubject}
                                    onValueChange={setSelectedSubject}
                                    disabled={loadingOptions}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Semua mapel" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">Semua Mata Pelajaran</SelectItem>
                                        {subjects.map((subj) => (
                                            <SelectItem key={subj.id} value={subj.id}>
                                                {subj.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>&nbsp;</Label>
                                <Button
                                    className="w-full"
                                    onClick={handleFinalize}
                                    disabled={finalizing || !selectedSemester || !selectedClassroom || !selectedSubject}
                                >
                                    <Calculator className="mr-2 h-4 w-4" />
                                    {finalizing ? 'Memproses...' : 'Finalisasi Nilai'}
                                </Button>
                            </div>
                        </div>

                        {/* Summary */}
                        {summary && (
                            <div className="mb-6 p-4 bg-muted rounded-lg">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Total Siswa:</span>
                                        <p className="font-medium text-lg">{summary.total_students}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Rata-rata Kelas:</span>
                                        <p className="font-medium text-lg">
                                            {summary.class_average?.toFixed(2) || '-'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Grades Table */}
                        {loading ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Memuat data nilai...
                            </div>
                        ) : !selectedSemester || !selectedClassroom ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Pilih semester dan kelas untuk melihat rekap nilai
                            </div>
                        ) : grades.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Tidak ada data nilai untuk filter yang dipilih
                            </div>
                        ) : (
                            <div className="rounded-md border overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[50px] sticky left-0 bg-background">No</TableHead>
                                            <TableHead className="sticky left-[50px] bg-background min-w-[100px]">NIS</TableHead>
                                            <TableHead className="sticky left-[150px] bg-background min-w-[200px]">Nama</TableHead>
                                            {uniqueSubjects.map((subj) => (
                                                <TableHead key={subj.id} className="text-center min-w-[100px]">
                                                    {subj.name}
                                                </TableHead>
                                            ))}
                                            <TableHead className="text-center min-w-[100px]">Rata-rata</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {grades.map((student, index) => (
                                            <TableRow key={student.student.id}>
                                                <TableCell className="sticky left-0 bg-background">{index + 1}</TableCell>
                                                <TableCell className="sticky left-[50px] bg-background">{student.student.nis}</TableCell>
                                                <TableCell className="sticky left-[150px] bg-background font-medium">
                                                    {student.student.full_name}
                                                </TableCell>
                                                {uniqueSubjects.map((subj) => {
                                                    const grade = student.grades.find((g) => g.subject_id === subj.id);
                                                    return (
                                                        <TableCell key={subj.id} className="text-center">
                                                            {grade ? (
                                                                <div className="flex flex-col items-center gap-1">
                                                                    <span className="font-medium">
                                                                        {grade.final_score.toFixed(0)}
                                                                    </span>
                                                                    <Badge
                                                                        className={
                                                                            grade.is_passed
                                                                                ? 'bg-green-100 text-green-700'
                                                                                : 'bg-red-100 text-red-700'
                                                                        }
                                                                        variant="outline"
                                                                    >
                                                                        {grade.grade_letter}
                                                                    </Badge>
                                                                </div>
                                                            ) : (
                                                                <span className="text-muted-foreground">-</span>
                                                            )}
                                                        </TableCell>
                                                    );
                                                })}
                                                <TableCell className="text-center">
                                                    <span className="font-bold text-lg">
                                                        {student.average.toFixed(1)}
                                                    </span>
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
        </MainLayout>
    );
}
