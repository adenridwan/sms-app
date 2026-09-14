import { useState, useEffect, useCallback } from 'react';
import { Head } from '@inertiajs/react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
    examApi,
    type Exam,
    type ExamScore,
} from '@/services/api';
import { Save, CheckCircle, XCircle } from 'lucide-react';

export default function GradeInput() {
    // State
    const [exams, setExams] = useState<Exam[]>([]);
    const [selectedExamId, setSelectedExamId] = useState<string>('');
    const [selectedExam, setSelectedExam] = useState<Exam | null>(null);
    const [scores, setScores] = useState<ExamScore[]>([]);
    const [loading, setLoading] = useState(true);
    const [loadingScores, setLoadingScores] = useState(false);
    const [saving, setSaving] = useState(false);

    // Edited scores state
    const [editedScores, setEditedScores] = useState<Record<string, { score: string; is_absent: boolean }>>({});

    // Load exams that can accept scores
    const loadExams = useCallback(async () => {
        try {
            setLoading(true);
            const response = await examApi.list({ per_page: 100 });
            // Filter exams that can accept scores (ongoing or completed)
            const acceptableExams = (response.data.data || []).filter(
                (exam) => exam.can_accept_scores
            );
            setExams(acceptableExams);
        } catch (error) {
            console.error('Failed to load exams:', error);
            toast.error('Gagal memuat data ujian');
        } finally {
            setLoading(false);
        }
    }, []);

    // Load scores for selected exam
    const loadScores = useCallback(async (examId: string) => {
        try {
            setLoadingScores(true);
            const [examRes, scoresRes] = await Promise.all([
                examApi.get(examId),
                examApi.scores(examId),
            ]);
            setSelectedExam(examRes.data.data);
            setScores(scoresRes.data.data || []);

            // Initialize edited scores
            const initial: Record<string, { score: string; is_absent: boolean }> = {};
            (scoresRes.data.data || []).forEach((s) => {
                initial[s.student_id] = {
                    score: s.score?.toString() || '',
                    is_absent: s.is_absent,
                };
            });
            setEditedScores(initial);
        } catch (error) {
            console.error('Failed to load scores:', error);
            toast.error('Gagal memuat data nilai');
        } finally {
            setLoadingScores(false);
        }
    }, []);

    useEffect(() => {
        loadExams();
    }, [loadExams]);

    useEffect(() => {
        if (selectedExamId) {
            loadScores(selectedExamId);
        } else {
            setSelectedExam(null);
            setScores([]);
            setEditedScores({});
        }
    }, [selectedExamId, loadScores]);

    // Handle score change
    const handleScoreChange = (studentId: string, value: string) => {
        setEditedScores((prev) => ({
            ...prev,
            [studentId]: {
                ...prev[studentId],
                score: value,
                is_absent: false, // Clear absent flag when entering score
            },
        }));
    };

    // Handle absent toggle
    const handleAbsentToggle = (studentId: string, checked: boolean) => {
        setEditedScores((prev) => ({
            ...prev,
            [studentId]: {
                ...prev[studentId],
                is_absent: checked,
                score: checked ? '' : prev[studentId]?.score || '',
            },
        }));
    };

    // Save all scores
    const handleSaveAll = async () => {
        if (!selectedExamId) return;

        const scoresToSave = Object.entries(editedScores).map(([studentId, data]) => ({
            student_id: studentId,
            score: data.is_absent ? null : parseFloat(data.score) || null,
            is_absent: data.is_absent,
        }));

        if (scoresToSave.length === 0) {
            toast.error('Tidak ada nilai untuk disimpan');
            return;
        }

        setSaving(true);
        try {
            await examApi.storeBulkScores(selectedExamId, scoresToSave);
            toast.success('Nilai berhasil disimpan');
            loadScores(selectedExamId); // Refresh
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error('Gagal menyimpan nilai', {
                description: err.response?.data?.message,
            });
        } finally {
            setSaving(false);
        }
    };

    // Calculate if passed
    const isPassed = (studentId: string): boolean | null => {
        const edited = editedScores[studentId];
        if (!edited || edited.is_absent) return null;
        if (!edited.score) return null;

        const score = parseFloat(edited.score);
        if (isNaN(score)) return null;

        return score >= (selectedExam?.passing_score || 0);
    };

    return (
        <MainLayout title="Input Nilai">
            <Head title="Input Nilai" />

            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Input Nilai Ujian</CardTitle>
                        <CardDescription>
                            Pilih ujian dan masukkan nilai untuk setiap siswa
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {/* Exam Selection */}
                        <div className="mb-6">
                            <Label>Pilih Ujian</Label>
                            <Select
                                value={selectedExamId}
                                onValueChange={setSelectedExamId}
                                disabled={loading}
                            >
                                <SelectTrigger className="w-full max-w-md">
                                    <SelectValue placeholder={loading ? 'Memuat...' : 'Pilih ujian'} />
                                </SelectTrigger>
                                <SelectContent>
                                    {exams.map((exam) => (
                                        <SelectItem key={exam.id} value={exam.id}>
                                            {exam.name} - {exam.subject?.name} ({exam.classroom?.name})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Exam Info */}
                        {selectedExam && (
                            <div className="mb-6 p-4 bg-muted rounded-lg">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Mata Pelajaran:</span>
                                        <p className="font-medium">{selectedExam.subject?.name}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Kelas:</span>
                                        <p className="font-medium">{selectedExam.classroom?.name}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Nilai Maks:</span>
                                        <p className="font-medium">{selectedExam.max_score}</p>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">KKM:</span>
                                        <p className="font-medium">{selectedExam.passing_score}</p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Scores Table */}
                        {loadingScores ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Memuat data nilai...
                            </div>
                        ) : selectedExamId && scores.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                Belum ada data siswa untuk ujian ini.
                                Pastikan ujian sudah ditautkan dengan kelas yang memiliki siswa.
                            </div>
                        ) : selectedExamId ? (
                            <>
                                <div className="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-[50px]">No</TableHead>
                                                <TableHead>NIS</TableHead>
                                                <TableHead>Nama Siswa</TableHead>
                                                <TableHead className="w-[150px]">Nilai</TableHead>
                                                <TableHead className="w-[100px]">Absen</TableHead>
                                                <TableHead className="w-[100px]">Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {scores.map((score, index) => {
                                                const edited = editedScores[score.student_id];
                                                const passed = isPassed(score.student_id);

                                                return (
                                                    <TableRow key={score.id}>
                                                        <TableCell>{index + 1}</TableCell>
                                                        <TableCell>{score.student?.nis}</TableCell>
                                                        <TableCell className="font-medium">
                                                            {score.student?.full_name}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                max={selectedExam?.max_score || 100}
                                                                value={edited?.score || ''}
                                                                onChange={(e) =>
                                                                    handleScoreChange(score.student_id, e.target.value)
                                                                }
                                                                disabled={edited?.is_absent}
                                                                className="w-24"
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            <Checkbox
                                                                checked={edited?.is_absent || false}
                                                                onCheckedChange={(checked) =>
                                                                    handleAbsentToggle(score.student_id, checked === true)
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell>
                                                            {edited?.is_absent ? (
                                                                <Badge variant="outline">Absen</Badge>
                                                            ) : passed === true ? (
                                                                <Badge className="bg-green-100 text-green-700">
                                                                    <CheckCircle className="h-3 w-3 mr-1" />
                                                                    Lulus
                                                                </Badge>
                                                            ) : passed === false ? (
                                                                <Badge className="bg-red-100 text-red-700">
                                                                    <XCircle className="h-3 w-3 mr-1" />
                                                                    Belum Lulus
                                                                </Badge>
                                                            ) : (
                                                                <span className="text-muted-foreground">-</span>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                );
                                            })}
                                        </TableBody>
                                    </Table>
                                </div>

                                {/* Save Button */}
                                <div className="mt-4 flex justify-end">
                                    <Button onClick={handleSaveAll} disabled={saving}>
                                        <Save className="mr-2 h-4 w-4" />
                                        {saving ? 'Menyimpan...' : 'Simpan Semua Nilai'}
                                    </Button>
                                </div>
                            </>
                        ) : (
                            <div className="text-center py-8 text-muted-foreground">
                                Pilih ujian untuk memulai input nilai
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}
