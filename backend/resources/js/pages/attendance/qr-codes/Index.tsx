import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { toast } from 'sonner';
import { Download, RefreshCw, Printer, QrCode, Users, GraduationCap } from 'lucide-react';
import { qrCodeApi } from '@/services/attendance';
import type { QrCodeData } from '@/types/attendance';
import type { ClassRoom } from '@/types';

interface Props {
    classrooms: ClassRoom[];
}

export default function QrCodeIndex({ classrooms }: Props) {
    const [activeTab, setActiveTab] = useState('students');
    const [classroomId, setClassroomId] = useState('');
    const [studentQrCodes, setStudentQrCodes] = useState<QrCodeData[]>([]);
    const [teacherQrCodes, setTeacherQrCodes] = useState<QrCodeData[]>([]);
    const [loading, setLoading] = useState(false);
    const [regenerating, setRegenerating] = useState<string | null>(null);
    const [selectedQr, setSelectedQr] = useState<QrCodeData | null>(null);
    const printRef = useRef<HTMLDivElement>(null);

    const fetchStudentQrCodes = async () => {
        if (!classroomId) return;

        setLoading(true);
        try {
            const response = await qrCodeApi.bulkStudents(classroomId);
            if (response.data.data) {
                setStudentQrCodes(response.data.data.students);
            }
        } catch (error) {
            toast.error('Gagal memuat QR Code siswa');
        } finally {
            setLoading(false);
        }
    };

    const fetchTeacherQrCodes = async () => {
        setLoading(true);
        try {
            const response = await qrCodeApi.bulkTeachers();
            if (response.data.data) {
                setTeacherQrCodes(response.data.data.teachers);
            }
        } catch (error) {
            toast.error('Gagal memuat QR Code guru');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (activeTab === 'students' && classroomId) {
            fetchStudentQrCodes();
        } else if (activeTab === 'teachers') {
            fetchTeacherQrCodes();
        }
    }, [activeTab, classroomId]);

    const handleRegenerateStudent = async (studentId: string) => {
        setRegenerating(studentId);
        try {
            await qrCodeApi.regenerateStudent(studentId);
            toast.success('QR Code berhasil diperbarui');
            fetchStudentQrCodes();
        } catch (error) {
            toast.error('Gagal regenerate QR Code');
        } finally {
            setRegenerating(null);
        }
    };

    const handleRegenerateTeacher = async (teacherId: string) => {
        setRegenerating(teacherId);
        try {
            await qrCodeApi.regenerateTeacher(teacherId);
            toast.success('QR Code berhasil diperbarui');
            fetchTeacherQrCodes();
        } catch (error) {
            toast.error('Gagal regenerate QR Code');
        } finally {
            setRegenerating(null);
        }
    };

    const handleDownload = (qrCode: QrCodeData) => {
        const link = document.createElement('a');
        link.href = qrCode.qr_code;
        link.download = `qr-${qrCode.nis || qrCode.nip || qrCode.name}.png`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handlePrintAll = () => {
        const printWindow = window.open('', '_blank');
        if (!printWindow) {
            toast.error('Popup blocker mungkin aktif. Izinkan popup untuk mencetak.');
            return;
        }

        const qrCodes = activeTab === 'students' ? studentQrCodes : teacherQrCodes;
        const title = activeTab === 'students'
            ? `QR Code Siswa - ${classrooms.find(c => c.id === classroomId)?.name || 'Kelas'}`
            : 'QR Code Guru';

        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>${title}</title>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; padding: 20px; }
                    .card { border: 1px solid #ddd; padding: 15px; text-align: center; page-break-inside: avoid; }
                    .card img { width: 150px; height: 150px; }
                    .name { font-weight: bold; margin-top: 10px; }
                    .id { color: #666; font-size: 14px; }
                    @media print {
                        .grid { grid-template-columns: repeat(3, 1fr); }
                        .card { break-inside: avoid; }
                    }
                </style>
            </head>
            <body>
                <h1 style="text-align: center;">${title}</h1>
                <div class="grid">
                    ${qrCodes.map(qr => `
                        <div class="card">
                            <img src="${qr.qr_code}" alt="QR Code" />
                            <div class="name">${qr.name}</div>
                            <div class="id">${qr.nis || qr.nip || ''}</div>
                        </div>
                    `).join('')}
                </div>
            </body>
            </html>
        `);

        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
        }, 500);
    };

    const QrCard = ({ qr, type }: { qr: QrCodeData; type: 'student' | 'teacher' }) => (
        <Card className="overflow-hidden">
            <CardContent className="p-4">
                <div className="flex flex-col items-center">
                    <div
                        className="cursor-pointer"
                        onClick={() => setSelectedQr(qr)}
                    >
                        <img
                            src={qr.qr_code}
                            alt={`QR Code ${qr.name}`}
                            className="h-32 w-32 rounded-lg border"
                        />
                    </div>
                    <div className="mt-3 text-center">
                        <div className="font-medium">{qr.name}</div>
                        <div className="text-sm text-muted-foreground">
                            {qr.nis || qr.nip || '-'}
                        </div>
                    </div>
                    <div className="mt-3 flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDownload(qr)}
                        >
                            <Download className="h-4 w-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => type === 'student'
                                ? handleRegenerateStudent(qr.student_id!)
                                : handleRegenerateTeacher(qr.teacher_id!)
                            }
                            disabled={regenerating === (qr.student_id || qr.teacher_id)}
                        >
                            <RefreshCw className={`h-4 w-4 ${regenerating === (qr.student_id || qr.teacher_id) ? 'animate-spin' : ''}`} />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );

    return (
        <MainLayout title="QR Code">
            <Head title="QR Code" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">QR Code</h1>
                        <p className="text-muted-foreground">
                            Generate dan kelola QR Code untuk siswa dan guru
                        </p>
                    </div>
                </div>

                {/* Tabs */}
                <Tabs value={activeTab} onValueChange={setActiveTab}>
                    <div className="flex items-center justify-between">
                        <TabsList>
                            <TabsTrigger value="students" className="gap-2">
                                <Users className="h-4 w-4" />
                                Siswa
                            </TabsTrigger>
                            <TabsTrigger value="teachers" className="gap-2">
                                <GraduationCap className="h-4 w-4" />
                                Guru
                            </TabsTrigger>
                        </TabsList>

                        {((activeTab === 'students' && studentQrCodes.length > 0) ||
                          (activeTab === 'teachers' && teacherQrCodes.length > 0)) && (
                            <Button onClick={handlePrintAll}>
                                <Printer className="mr-2 h-4 w-4" />
                                Cetak Semua
                            </Button>
                        )}
                    </div>

                    {/* Students Tab */}
                    <TabsContent value="students" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Filter Kelas</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex gap-4">
                                    <div className="w-[250px]">
                                        <Label>Kelas</Label>
                                        <Select value={classroomId} onValueChange={setClassroomId}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Pilih kelas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {classrooms.map((c) => (
                                                    <SelectItem key={c.id} value={c.id}>
                                                        {c.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex items-end">
                                        <Button onClick={fetchStudentQrCodes} variant="outline" disabled={!classroomId || loading}>
                                            <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                            Refresh
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {!classroomId ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <QrCode className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">
                                        Pilih kelas untuk melihat QR Code siswa
                                    </p>
                                </CardContent>
                            </Card>
                        ) : loading ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <RefreshCw className="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Memuat...</p>
                                </CardContent>
                            </Card>
                        ) : studentQrCodes.length === 0 ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <p className="text-muted-foreground">
                                        Tidak ada siswa di kelas ini
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div ref={printRef} className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {studentQrCodes.map((qr) => (
                                    <QrCard key={qr.student_id} qr={qr} type="student" />
                                ))}
                            </div>
                        )}
                    </TabsContent>

                    {/* Teachers Tab */}
                    <TabsContent value="teachers" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>QR Code Guru</CardTitle>
                                <CardDescription>
                                    {teacherQrCodes.length} guru terdaftar
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button onClick={fetchTeacherQrCodes} variant="outline" disabled={loading}>
                                    <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                            </CardContent>
                        </Card>

                        {loading ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <RefreshCw className="mx-auto h-8 w-8 animate-spin text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">Memuat...</p>
                                </CardContent>
                            </Card>
                        ) : teacherQrCodes.length === 0 ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <p className="text-muted-foreground">
                                        Tidak ada data guru
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {teacherQrCodes.map((qr) => (
                                    <QrCard key={qr.teacher_id} qr={qr} type="teacher" />
                                ))}
                            </div>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            {/* QR Detail Dialog */}
            <Dialog open={!!selectedQr} onOpenChange={() => setSelectedQr(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{selectedQr?.name}</DialogTitle>
                        <DialogDescription>
                            {selectedQr?.nis || selectedQr?.nip || 'QR Code'}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="flex flex-col items-center py-4">
                        {selectedQr && (
                            <img
                                src={selectedQr.qr_code}
                                alt={`QR Code ${selectedQr.name}`}
                                className="h-64 w-64 rounded-lg border"
                            />
                        )}
                        <div className="mt-4 text-center text-sm text-muted-foreground">
                            Unique Code: {selectedQr?.unique_code}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setSelectedQr(null)}>
                            Tutup
                        </Button>
                        <Button onClick={() => selectedQr && handleDownload(selectedQr)}>
                            <Download className="mr-2 h-4 w-4" />
                            Download
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
