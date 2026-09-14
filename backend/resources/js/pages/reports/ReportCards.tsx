import { Head } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import {
    RefreshCw,
    Plus,
    Search,
    Eye,
    Edit,
    FileText,
    CheckCircle,
    Send,
    Users,
    Award,
    TrendingUp,
    BarChart3,
} from 'lucide-react';
import {
    reportCardApi,
    academicYearsApi,
    semestersApi,
    classroomsApi,
    type ReportCard,
    type ReportCardStatistics,
} from '@/services/api';
import type { AcademicYear, Semester, Classroom } from '@/types';

type SelectOption = { id: string; name: string };

const STATUS_OPTIONS = [
    { value: '', label: 'Semua Status' },
    { value: 'draft', label: 'Draf' },
    { value: 'reviewed', label: 'Direview' },
    { value: 'approved', label: 'Disetujui' },
    { value: 'published', label: 'Dipublikasikan' },
    { value: 'distributed', label: 'Didistribusikan' },
];

const PROMOTION_STATUS_OPTIONS = [
    { value: 'pending', label: 'Menunggu' },
    { value: 'promoted', label: 'Naik Kelas' },
    { value: 'retained', label: 'Tinggal Kelas' },
    { value: 'conditional', label: 'Naik Bersyarat' },
];

function getStatusBadge(status: string) {
    switch (status) {
        case 'draft':
            return <Badge variant="secondary">Draf</Badge>;
        case 'reviewed':
            return <Badge variant="outline">Direview</Badge>;
        case 'approved':
            return <Badge className="bg-blue-100 text-blue-800">Disetujui</Badge>;
        case 'published':
            return <Badge className="bg-green-100 text-green-800">Dipublikasikan</Badge>;
        case 'distributed':
            return <Badge className="bg-purple-100 text-purple-800">Didistribusikan</Badge>;
        default:
            return <Badge variant="secondary">{status}</Badge>;
    }
}

function getPromotionBadge(status: string) {
    switch (status) {
        case 'pending':
            return <Badge variant="outline">Menunggu</Badge>;
        case 'promoted':
            return <Badge className="bg-green-100 text-green-800">Naik Kelas</Badge>;
        case 'retained':
            return <Badge className="bg-red-100 text-red-800">Tinggal Kelas</Badge>;
        case 'conditional':
            return <Badge className="bg-yellow-100 text-yellow-800">Naik Bersyarat</Badge>;
        default:
            return <Badge variant="secondary">{status}</Badge>;
    }
}

export default function ReportCards() {
    const [loading, setLoading] = useState(false);
    const [reportCards, setReportCards] = useState<ReportCard[]>([]);
    const [statistics, setStatistics] = useState<ReportCardStatistics | null>(null);
    const [meta, setMeta] = useState({ current_page: 1, last_page: 1, total: 0 });

    // Filter options
    const [academicYears, setAcademicYears] = useState<SelectOption[]>([]);
    const [semesters, setSemesters] = useState<SelectOption[]>([]);
    const [classrooms, setClassrooms] = useState<SelectOption[]>([]);

    // Filters
    const [filters, setFilters] = useState({
        search: '',
        academic_year_id: '',
        semester_id: '',
        classroom_id: '',
        status: '',
        page: 1,
    });

    // Selection for bulk operations
    const [selectedIds, setSelectedIds] = useState<string[]>([]);

    // Dialogs
    const [generateDialogOpen, setGenerateDialogOpen] = useState(false);
    const [viewDialogOpen, setViewDialogOpen] = useState(false);
    const [editDialogOpen, setEditDialogOpen] = useState(false);
    const [selectedReportCard, setSelectedReportCard] = useState<ReportCard | null>(null);

    // Generate form
    const [generateForm, setGenerateForm] = useState({
        academic_year_id: '',
        semester_id: '',
        classroom_id: '',
    });
    const [generating, setGenerating] = useState(false);

    // Edit form
    const [editForm, setEditForm] = useState({
        homeroom_notes: '',
        principal_notes: '',
        promotion_status: 'pending' as 'pending' | 'promoted' | 'retained' | 'conditional',
        next_classroom: '',
        total_present_days: 0,
        total_absent_days: 0,
        total_sick_days: 0,
        total_permitted_days: 0,
    });
    const [saving, setSaving] = useState(false);

    // Load filter options
    useEffect(() => {
        const loadOptions = async () => {
            try {
                const [yearsRes, semestersRes, classroomsRes] = await Promise.all([
                    academicYearsApi.list({ per_page: 100 }),
                    semestersApi.list({ per_page: 100 }),
                    classroomsApi.list({ per_page: 100 }),
                ]);
                setAcademicYears((yearsRes.data.data?.data || []) as unknown as SelectOption[]);
                setSemesters((semestersRes.data.data?.data || []) as unknown as SelectOption[]);
                setClassrooms((classroomsRes.data.data?.data || []) as unknown as SelectOption[]);
            } catch (error) {
                console.error('Failed to load options:', error);
            }
        };
        loadOptions();
    }, []);

    // Load report cards
    const loadReportCards = useCallback(async () => {
        setLoading(true);
        try {
            const params: Record<string, unknown> = {
                page: filters.page,
                per_page: 15,
            };
            if (filters.search) params.search = filters.search;
            if (filters.academic_year_id) params.academic_year_id = filters.academic_year_id;
            if (filters.semester_id) params.semester_id = filters.semester_id;
            if (filters.classroom_id) params.classroom_id = filters.classroom_id;
            if (filters.status) params.status = filters.status;

            const response = await reportCardApi.list(params);
            setReportCards(response.data.data || []);
            if (response.data.meta) {
                setMeta({
                    current_page: response.data.meta.current_page,
                    last_page: response.data.meta.last_page,
                    total: response.data.meta.total,
                });
            }
            setSelectedIds([]);
        } catch (error) {
            console.error('Failed to load report cards:', error);
            toast.error('Gagal memuat data rapor');
        } finally {
            setLoading(false);
        }
    }, [filters]);

    // Load statistics
    const loadStatistics = useCallback(async () => {
        try {
            const params: Record<string, string> = {};
            if (filters.academic_year_id) params.academic_year_id = filters.academic_year_id;
            if (filters.semester_id) params.semester_id = filters.semester_id;

            const response = await reportCardApi.statistics(params);
            setStatistics(response.data.data || null);
        } catch (error) {
            console.error('Failed to load statistics:', error);
        }
    }, [filters.academic_year_id, filters.semester_id]);

    useEffect(() => {
        loadReportCards();
    }, [loadReportCards]);

    useEffect(() => {
        loadStatistics();
    }, [loadStatistics]);

    // Generate report cards
    const handleGenerate = async () => {
        if (!generateForm.academic_year_id || !generateForm.semester_id || !generateForm.classroom_id) {
            toast.error('Lengkapi semua field');
            return;
        }

        setGenerating(true);
        try {
            const response = await reportCardApi.generate(generateForm);
            const result = response.data.data;
            if (result) {
                toast.success(`Berhasil generate ${result.created} rapor. Dilewati: ${result.skipped}. Error: ${result.errors}`);
            }
            setGenerateDialogOpen(false);
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal generate rapor');
        } finally {
            setGenerating(false);
        }
    };

    // View report card
    const handleView = async (reportCard: ReportCard) => {
        try {
            const response = await reportCardApi.get(reportCard.id);
            setSelectedReportCard(response.data.data || null);
            setViewDialogOpen(true);
        } catch (error) {
            console.error('Failed to load report card:', error);
            toast.error('Gagal memuat detail rapor');
        }
    };

    // Edit report card
    const handleEdit = (reportCard: ReportCard) => {
        setSelectedReportCard(reportCard);
        setEditForm({
            homeroom_notes: reportCard.homeroom_notes || '',
            principal_notes: reportCard.principal_notes || '',
            promotion_status: reportCard.promotion_status,
            next_classroom: reportCard.next_classroom || '',
            total_present_days: reportCard.total_present_days || 0,
            total_absent_days: reportCard.total_absent_days || 0,
            total_sick_days: reportCard.total_sick_days || 0,
            total_permitted_days: reportCard.total_permitted_days || 0,
        });
        setEditDialogOpen(true);
    };

    const handleSaveEdit = async () => {
        if (!selectedReportCard) return;

        setSaving(true);
        try {
            await reportCardApi.update(selectedReportCard.id, editForm);
            toast.success('Rapor berhasil diperbarui');
            setEditDialogOpen(false);
            loadReportCards();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal memperbarui rapor');
        } finally {
            setSaving(false);
        }
    };

    // Submit for review
    const handleSubmitForReview = async (reportCard: ReportCard) => {
        try {
            await reportCardApi.submitForReview(reportCard.id);
            toast.success('Rapor berhasil diajukan untuk review');
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal mengajukan rapor');
        }
    };

    // Approve
    const handleApprove = async (reportCard: ReportCard) => {
        try {
            await reportCardApi.approve(reportCard.id);
            toast.success('Rapor berhasil disetujui');
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal menyetujui rapor');
        }
    };

    // Publish
    const handlePublish = async (reportCard: ReportCard) => {
        try {
            await reportCardApi.publish(reportCard.id);
            toast.success('Rapor berhasil dipublikasikan');
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal mempublikasikan rapor');
        }
    };

    // Bulk approve
    const handleBulkApprove = async () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih rapor terlebih dahulu');
            return;
        }

        try {
            const response = await reportCardApi.bulkApprove(selectedIds);
            const result = response.data.data;
            if (result) {
                toast.success(`${result.approved} rapor disetujui, ${result.skipped} dilewati`);
            }
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal menyetujui rapor');
        }
    };

    // Bulk publish
    const handleBulkPublish = async () => {
        if (selectedIds.length === 0) {
            toast.error('Pilih rapor terlebih dahulu');
            return;
        }

        try {
            const response = await reportCardApi.bulkPublish(selectedIds);
            const result = response.data.data;
            if (result) {
                toast.success(`${result.published} rapor dipublikasikan, ${result.skipped} dilewati`);
            }
            loadReportCards();
            loadStatistics();
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string } } };
            toast.error(err.response?.data?.message || 'Gagal mempublikasikan rapor');
        }
    };

    // Toggle selection
    const toggleSelect = (id: string) => {
        setSelectedIds(prev =>
            prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
        );
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === reportCards.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(reportCards.map(r => r.id));
        }
    };

    return (
        <MainLayout title="Rapor Siswa">
            <Head title="Rapor Siswa" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Rapor Siswa</h1>
                        <p className="text-muted-foreground">
                            Kelola rapor siswa per semester
                        </p>
                    </div>
                    <Button onClick={() => setGenerateDialogOpen(true)}>
                        <Plus className="h-4 w-4 mr-2" />
                        Generate Rapor
                    </Button>
                </div>

                {/* Statistics Cards */}
                {statistics && (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total Rapor</CardTitle>
                                <FileText className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.total}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Draf</CardTitle>
                                <Edit className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.by_status?.draft || 0}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Disetujui</CardTitle>
                                <CheckCircle className="h-4 w-4 text-blue-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.by_status?.approved || 0}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Dipublikasikan</CardTitle>
                                <Send className="h-4 w-4 text-green-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{statistics.by_status?.published || 0}</div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                                <Search className="h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Cari nama/NIS siswa..."
                                    value={filters.search}
                                    onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value, page: 1 }))}
                                    className="w-[250px]"
                                />
                            </div>
                            <Select
                                value={filters.academic_year_id}
                                onValueChange={(value) => setFilters(prev => ({ ...prev, academic_year_id: value, page: 1 }))}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Tahun</SelectItem>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>{year.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.semester_id}
                                onValueChange={(value) => setFilters(prev => ({ ...prev, semester_id: value, page: 1 }))}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Semester</SelectItem>
                                    {semesters.map((semester) => (
                                        <SelectItem key={semester.id} value={semester.id}>{semester.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.classroom_id}
                                onValueChange={(value) => setFilters(prev => ({ ...prev, classroom_id: value, page: 1 }))}
                            >
                                <SelectTrigger className="w-[180px]">
                                    <SelectValue placeholder="Kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Semua Kelas</SelectItem>
                                    {classrooms.map((classroom) => (
                                        <SelectItem key={classroom.id} value={classroom.id}>{classroom.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Select
                                value={filters.status}
                                onValueChange={(value) => setFilters(prev => ({ ...prev, status: value, page: 1 }))}
                            >
                                <SelectTrigger className="w-[150px]">
                                    <SelectValue placeholder="Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {STATUS_OPTIONS.map((opt) => (
                                        <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button variant="outline" onClick={loadReportCards} disabled={loading}>
                                <RefreshCw className={`h-4 w-4 mr-2 ${loading ? 'animate-spin' : ''}`} />
                                Refresh
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Actions */}
                {selectedIds.length > 0 && (
                    <Card>
                        <CardContent className="py-3">
                            <div className="flex items-center justify-between">
                                <span className="text-sm text-muted-foreground">
                                    {selectedIds.length} rapor dipilih
                                </span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" onClick={handleBulkApprove}>
                                        <CheckCircle className="h-4 w-4 mr-2" />
                                        Setujui
                                    </Button>
                                    <Button variant="outline" size="sm" onClick={handleBulkPublish}>
                                        <Send className="h-4 w-4 mr-2" />
                                        Publikasikan
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Table */}
                <Card>
                    <CardContent className="pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[50px]">
                                        <Checkbox
                                            checked={selectedIds.length === reportCards.length && reportCards.length > 0}
                                            onCheckedChange={toggleSelectAll}
                                        />
                                    </TableHead>
                                    <TableHead>Siswa</TableHead>
                                    <TableHead>Kelas</TableHead>
                                    <TableHead>Semester</TableHead>
                                    <TableHead className="text-center">Rata-rata</TableHead>
                                    <TableHead className="text-center">Ranking</TableHead>
                                    <TableHead>Kenaikan</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reportCards.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={9} className="text-center py-12 text-muted-foreground">
                                            {loading ? 'Memuat...' : 'Tidak ada data rapor'}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    reportCards.map((reportCard) => (
                                        <TableRow key={reportCard.id}>
                                            <TableCell>
                                                <Checkbox
                                                    checked={selectedIds.includes(reportCard.id)}
                                                    onCheckedChange={() => toggleSelect(reportCard.id)}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div className="font-medium">{reportCard.student?.full_name}</div>
                                                    <div className="text-sm text-muted-foreground">{reportCard.student?.nis}</div>
                                                </div>
                                            </TableCell>
                                            <TableCell>{reportCard.classroom?.name}</TableCell>
                                            <TableCell>{reportCard.semester?.name}</TableCell>
                                            <TableCell className="text-center font-mono">
                                                {reportCard.average_score?.toFixed(2) || '-'}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {reportCard.rank_in_class && reportCard.total_students_in_class
                                                    ? `${reportCard.rank_in_class}/${reportCard.total_students_in_class}`
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>{getPromotionBadge(reportCard.promotion_status)}</TableCell>
                                            <TableCell>{getStatusBadge(reportCard.status)}</TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button variant="ghost" size="icon" onClick={() => handleView(reportCard)}>
                                                        <Eye className="h-4 w-4" />
                                                    </Button>
                                                    {reportCard.can_edit && (
                                                        <Button variant="ghost" size="icon" onClick={() => handleEdit(reportCard)}>
                                                            <Edit className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {reportCard.status === 'draft' && (
                                                        <Button variant="ghost" size="icon" onClick={() => handleSubmitForReview(reportCard)} title="Ajukan Review">
                                                            <Send className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {reportCard.can_approve && (
                                                        <Button variant="ghost" size="icon" onClick={() => handleApprove(reportCard)} title="Setujui">
                                                            <CheckCircle className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                    {reportCard.can_publish && (
                                                        <Button variant="ghost" size="icon" onClick={() => handlePublish(reportCard)} title="Publikasikan">
                                                            <Award className="h-4 w-4" />
                                                        </Button>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {meta.last_page > 1 && (
                            <div className="flex items-center justify-between mt-4">
                                <p className="text-sm text-muted-foreground">
                                    Halaman {meta.current_page} dari {meta.last_page} ({meta.total} data)
                                </p>
                                <div className="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page === 1}
                                        onClick={() => setFilters(prev => ({ ...prev, page: prev.page - 1 }))}
                                    >
                                        Sebelumnya
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={meta.current_page === meta.last_page}
                                        onClick={() => setFilters(prev => ({ ...prev, page: prev.page + 1 }))}
                                    >
                                        Selanjutnya
                                    </Button>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Generate Dialog */}
            <Dialog open={generateDialogOpen} onOpenChange={setGenerateDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Generate Rapor</DialogTitle>
                        <DialogDescription>
                            Generate rapor untuk semua siswa di kelas yang dipilih
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <Label>Tahun Ajaran</Label>
                            <Select
                                value={generateForm.academic_year_id}
                                onValueChange={(value) => setGenerateForm(prev => ({ ...prev, academic_year_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Tahun Ajaran" />
                                </SelectTrigger>
                                <SelectContent>
                                    {academicYears.map((year) => (
                                        <SelectItem key={year.id} value={year.id}>{year.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Semester</Label>
                            <Select
                                value={generateForm.semester_id}
                                onValueChange={(value) => setGenerateForm(prev => ({ ...prev, semester_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    {semesters.map((semester) => (
                                        <SelectItem key={semester.id} value={semester.id}>{semester.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Kelas</Label>
                            <Select
                                value={generateForm.classroom_id}
                                onValueChange={(value) => setGenerateForm(prev => ({ ...prev, classroom_id: value }))}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Pilih Kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    {classrooms.map((classroom) => (
                                        <SelectItem key={classroom.id} value={classroom.id}>{classroom.name}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setGenerateDialogOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleGenerate} disabled={generating}>
                            {generating ? 'Generating...' : 'Generate'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* View Dialog */}
            <Dialog open={viewDialogOpen} onOpenChange={setViewDialogOpen}>
                <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Detail Rapor</DialogTitle>
                        <DialogDescription>
                            {selectedReportCard?.student?.full_name} - {selectedReportCard?.semester?.name}
                        </DialogDescription>
                    </DialogHeader>
                    {selectedReportCard && (
                        <div className="space-y-6 py-4">
                            {/* Student Info */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground">Siswa</p>
                                    <p className="font-medium">{selectedReportCard.student?.full_name}</p>
                                    <p className="text-sm">{selectedReportCard.student?.nis}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Kelas</p>
                                    <p className="font-medium">{selectedReportCard.classroom?.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Tahun Ajaran</p>
                                    <p className="font-medium">{selectedReportCard.academic_year?.name}</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground">Semester</p>
                                    <p className="font-medium">{selectedReportCard.semester?.name}</p>
                                </div>
                            </div>

                            {/* Statistics */}
                            <div className="grid grid-cols-4 gap-4">
                                <Card>
                                    <CardContent className="pt-4 text-center">
                                        <p className="text-2xl font-bold">{selectedReportCard.average_score?.toFixed(2) || '-'}</p>
                                        <p className="text-sm text-muted-foreground">Rata-rata</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-4 text-center">
                                        <p className="text-2xl font-bold">
                                            {selectedReportCard.rank_in_class || '-'}/{selectedReportCard.total_students_in_class || '-'}
                                        </p>
                                        <p className="text-sm text-muted-foreground">Ranking</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-4 text-center">
                                        <p className="text-2xl font-bold">{selectedReportCard.total_subjects || 0}</p>
                                        <p className="text-sm text-muted-foreground">Mata Pelajaran</p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardContent className="pt-4 text-center">
                                        {getPromotionBadge(selectedReportCard.promotion_status)}
                                        <p className="text-sm text-muted-foreground mt-1">Kenaikan</p>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Grades */}
                            {selectedReportCard.grades && selectedReportCard.grades.length > 0 && (
                                <div>
                                    <h3 className="font-semibold mb-2">Nilai Mata Pelajaran</h3>
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead>Mata Pelajaran</TableHead>
                                                <TableHead className="text-center">Pengetahuan</TableHead>
                                                <TableHead className="text-center">Keterampilan</TableHead>
                                                <TableHead className="text-center">Sikap</TableHead>
                                                <TableHead className="text-center">Nilai Akhir</TableHead>
                                                <TableHead className="text-center">Predikat</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {selectedReportCard.grades.map((grade) => (
                                                <TableRow key={grade.id}>
                                                    <TableCell>{grade.subject.name}</TableCell>
                                                    <TableCell className="text-center font-mono">{grade.knowledge_score}</TableCell>
                                                    <TableCell className="text-center font-mono">{grade.skill_score}</TableCell>
                                                    <TableCell className="text-center font-mono">{grade.attitude_score}</TableCell>
                                                    <TableCell className="text-center font-mono font-semibold">{grade.final_score}</TableCell>
                                                    <TableCell className="text-center">
                                                        <Badge variant={grade.is_passed ? 'default' : 'destructive'}>
                                                            {grade.grade_letter}
                                                        </Badge>
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>
                            )}

                            {/* Attendance */}
                            <div>
                                <h3 className="font-semibold mb-2">Kehadiran</h3>
                                <div className="grid grid-cols-4 gap-4">
                                    <div className="border rounded-lg p-3 text-center">
                                        <p className="text-xl font-bold text-green-600">{selectedReportCard.total_present_days || 0}</p>
                                        <p className="text-sm text-muted-foreground">Hadir</p>
                                    </div>
                                    <div className="border rounded-lg p-3 text-center">
                                        <p className="text-xl font-bold text-yellow-600">{selectedReportCard.total_sick_days || 0}</p>
                                        <p className="text-sm text-muted-foreground">Sakit</p>
                                    </div>
                                    <div className="border rounded-lg p-3 text-center">
                                        <p className="text-xl font-bold text-blue-600">{selectedReportCard.total_permitted_days || 0}</p>
                                        <p className="text-sm text-muted-foreground">Izin</p>
                                    </div>
                                    <div className="border rounded-lg p-3 text-center">
                                        <p className="text-xl font-bold text-red-600">{selectedReportCard.total_absent_days || 0}</p>
                                        <p className="text-sm text-muted-foreground">Alpha</p>
                                    </div>
                                </div>
                            </div>

                            {/* Notes */}
                            {(selectedReportCard.homeroom_notes || selectedReportCard.principal_notes) && (
                                <div className="space-y-4">
                                    {selectedReportCard.homeroom_notes && (
                                        <div>
                                            <h3 className="font-semibold mb-1">Catatan Wali Kelas</h3>
                                            <p className="text-sm bg-muted p-3 rounded-lg">{selectedReportCard.homeroom_notes}</p>
                                        </div>
                                    )}
                                    {selectedReportCard.principal_notes && (
                                        <div>
                                            <h3 className="font-semibold mb-1">Catatan Kepala Sekolah</h3>
                                            <p className="text-sm bg-muted p-3 rounded-lg">{selectedReportCard.principal_notes}</p>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            {/* Edit Dialog */}
            <Dialog open={editDialogOpen} onOpenChange={setEditDialogOpen}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Edit Rapor</DialogTitle>
                        <DialogDescription>
                            {selectedReportCard?.student?.full_name} - {selectedReportCard?.semester?.name}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-2">
                                <Label>Status Kenaikan</Label>
                                <Select
                                    value={editForm.promotion_status}
                                    onValueChange={(value) => setEditForm(prev => ({
                                        ...prev,
                                        promotion_status: value as typeof editForm.promotion_status
                                    }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PROMOTION_STATUS_OPTIONS.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="space-y-2">
                                <Label>Kelas Selanjutnya</Label>
                                <Input
                                    value={editForm.next_classroom}
                                    onChange={(e) => setEditForm(prev => ({ ...prev, next_classroom: e.target.value }))}
                                    placeholder="Contoh: X MIPA 2"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-4 gap-4">
                            <div className="space-y-2">
                                <Label>Hadir</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={editForm.total_present_days}
                                    onChange={(e) => setEditForm(prev => ({ ...prev, total_present_days: parseInt(e.target.value) || 0 }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Sakit</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={editForm.total_sick_days}
                                    onChange={(e) => setEditForm(prev => ({ ...prev, total_sick_days: parseInt(e.target.value) || 0 }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Izin</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={editForm.total_permitted_days}
                                    onChange={(e) => setEditForm(prev => ({ ...prev, total_permitted_days: parseInt(e.target.value) || 0 }))}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Alpha</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    value={editForm.total_absent_days}
                                    onChange={(e) => setEditForm(prev => ({ ...prev, total_absent_days: parseInt(e.target.value) || 0 }))}
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label>Catatan Wali Kelas</Label>
                            <Textarea
                                value={editForm.homeroom_notes}
                                onChange={(e) => setEditForm(prev => ({ ...prev, homeroom_notes: e.target.value }))}
                                placeholder="Masukkan catatan wali kelas..."
                                rows={3}
                            />
                        </div>

                        <div className="space-y-2">
                            <Label>Catatan Kepala Sekolah</Label>
                            <Textarea
                                value={editForm.principal_notes}
                                onChange={(e) => setEditForm(prev => ({ ...prev, principal_notes: e.target.value }))}
                                placeholder="Masukkan catatan kepala sekolah..."
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditDialogOpen(false)}>
                            Batal
                        </Button>
                        <Button onClick={handleSaveEdit} disabled={saving}>
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
