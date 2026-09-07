import { Head, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { printAttendanceCards, printAttendanceCardsWithTemplate } from '@/lib/attendanceCardPrint';
import type { PageProps } from '@/types';
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
import { Checkbox } from '@/components/ui/checkbox';
import { toast } from 'sonner';
import { Download, RefreshCw, Printer, QrCode, Users, GraduationCap, Briefcase, FileDown, FileSpreadsheet, FileArchive, FileText } from 'lucide-react';
import { qrCodeApi } from '@/services/attendance';
import type { QrCodeData } from '@/types/attendance';
import type { ClassRoom } from '@/types';
import { usePermissions } from '@/hooks/usePermissions';

interface Props {
    classrooms: ClassRoom[];
}

export default function QrCodeIndex({ classrooms }: Props) {
    const { tenant } = usePage<PageProps>().props;
    const { can } = usePermissions();
    // Guru buka halaman ini lewat attendance.scan-students (tab "Siswa"
    // saja, kelas yang diampu saja) — tab "Guru" dan aksi admin (export,
    // regenerate kode) tetap khusus admin/TU/super_admin.
    const canManageAll = can('settings.attendance');
    const [activeTab, setActiveTab] = useState('students');
    const [classroomId, setClassroomId] = useState('');
    const [studentQrCodes, setStudentQrCodes] = useState<QrCodeData[]>([]);
    const [teacherQrCodes, setTeacherQrCodes] = useState<QrCodeData[]>([]);
    const [staffQrCodes, setStaffQrCodes] = useState<QrCodeData[]>([]);
    const [loading, setLoading] = useState(false);
    const [regenerating, setRegenerating] = useState<string | null>(null);
    const [selectedQr, setSelectedQr] = useState<QrCodeData | null>(null);
    const printRef = useRef<HTMLDivElement>(null);

    // Export QR/RFID
    const [exportOpen, setExportOpen] = useState(false);
    const [exportFormat, setExportFormat] = useState<'excel' | 'zip' | 'pdf'>('excel');
    const [exportImage, setExportImage] = useState<'svg' | 'png' | 'both'>('both');
    const [exportScope, setExportScope] = useState<'class' | 'all'>('class');
    const [generateRfid, setGenerateRfid] = useState(true);
    const [exporting, setExporting] = useState(false);

    const triggerBlobDownload = (blob: Blob, fallbackName: string, disposition?: string) => {
        let filename = fallbackName;
        const match = disposition?.match(/filename="?([^"]+)"?/);
        if (match) filename = match[1];
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    };

    const handleExport = async () => {
        if (activeTab === 'students' && exportScope === 'class' && !classroomId) {
            toast.error('Pilih kelas terlebih dahulu');
            return;
        }
        setExporting(true);
        try {
            const params = {
                type: activeTab === 'teachers' ? ('teacher' as const) : ('student' as const),
                format: exportFormat,
                image: exportImage,
                generate_rfid: generateRfid,
                ...(activeTab === 'students'
                    ? { scope: exportScope, classroom_id: exportScope === 'class' ? classroomId : undefined }
                    : {}),
            };
            const res = await qrCodeApi.export(params);
            const ext = exportFormat === 'excel' ? 'xlsx' : exportFormat;
            triggerBlobDownload(
                res.data as unknown as Blob,
                `qr-${params.type}.${ext}`,
                (res.headers as Record<string, string>)['content-disposition'],
            );
            toast.success('Export berhasil diunduh');
            setExportOpen(false);
            // Refresh tampilan agar kode RFID/QR baru ikut terlihat
            if (activeTab === 'students' && classroomId) fetchStudentQrCodes();
            if (activeTab === 'teachers') fetchTeacherQrCodes();
        } catch (error: any) {
            // Response error bertipe blob — coba baca pesannya
            let msg = 'Gagal export';
            try {
                const text = await (error.response?.data as Blob)?.text?.();
                if (text) msg = JSON.parse(text).message || msg;
            } catch { /* abaikan */ }
            toast.error(msg);
        } finally {
            setExporting(false);
        }
    };

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

    const fetchStaffQrCodes = async () => {
        setLoading(true);
        try {
            const response = await qrCodeApi.bulkStaff();
            if (response.data.data) {
                setStaffQrCodes(response.data.data.staff);
            }
        } catch (error) {
            toast.error('Gagal memuat QR Code staf');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (activeTab === 'students' && classroomId) {
            fetchStudentQrCodes();
        } else if (activeTab === 'teachers') {
            fetchTeacherQrCodes();
        } else if (activeTab === 'staff') {
            fetchStaffQrCodes();
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

    const handleRegenerateStaff = async (staffId: string) => {
        setRegenerating(staffId);
        try {
            await qrCodeApi.regenerateStaff(staffId);
            toast.success('QR Code berhasil diperbarui');
            fetchStaffQrCodes();
        } catch (error) {
            toast.error('Gagal regenerate QR Code');
        } finally {
            setRegenerating(null);
        }
    };

    /**
     * QR dari server berupa **SVG** (`data:image/svg+xml;base64,...`) meskipun
     * field-nya bernama `qr_code` dan method pembuatnya bernama
     * `generateQrImage`. Menyimpannya apa adanya dengan ekstensi `.png`
     * menghasilkan berkas berisi SVG — Windows Photos menolaknya dengan
     * "we don't support this file format". Jadi digambar dulu ke canvas, lalu
     * diekspor sebagai PNG sungguhan supaya isinya cocok dengan namanya.
     */
    const svgDataUriToPng = (dataUri: string, size = 600): Promise<string> =>
        new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    reject(new Error('Canvas tidak tersedia'));
                    return;
                }
                // Latar putih eksplisit: PNG transparan sulit dipindai kalau
                // tercetak di atas kertas berwarna atau ditempel di layar gelap.
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, size, size);
                ctx.drawImage(img, 0, 0, size, size);
                resolve(canvas.toDataURL('image/png'));
            };
            img.onerror = () => reject(new Error('QR tidak bisa dimuat'));
            img.src = dataUri;
        });

    const handleDownload = async (qrCode: QrCodeData) => {
        const base = `qr-${qrCode.nis || qrCode.nip || qrCode.employee_id || qrCode.name}`;
        const link = document.createElement('a');

        try {
            link.href = await svgDataUriToPng(qrCode.qr_code);
            link.download = `${base}.png`;
        } catch {
            // Konversi gagal — simpan SVG aslinya dengan ekstensi yang benar,
            // jangan berkas .png yang tak bisa dibuka siapa pun.
            link.href = qrCode.qr_code;
            link.download = `${base}.svg`;
            toast.message('QR diunduh sebagai SVG', {
                description: 'Konversi ke PNG gagal di peramban ini.',
            });
        }

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handlePrintAll = async () => {
        const options = {
            title: activeTab === 'students'
                ? `Kartu Siswa - ${classrooms.find(c => c.id === classroomId)?.name || 'Kelas'}`
                : activeTab === 'teachers' ? 'Kartu Guru' : 'Kartu Staf',
            schoolName: tenant?.name,
            schoolLogo: tenant?.logo,
        };

        // Template Editor (card_templates.type) baru mengenal 'student' dan
        // 'teacher' — kolomnya enum, jadi 'staff' belum bisa disimpan di sana.
        // Kartu staf memakai layout fixed bawaan supaya tetap bisa dicetak,
        // bukan menembak endpoint template yang pasti 404.
        const opened = activeTab === 'staff'
            ? printAttendanceCards(staffQrCodes, { ...options, layout: null })
            : await printAttendanceCardsWithTemplate(
                activeTab === 'students' ? 'student' : 'teacher',
                activeTab === 'students' ? studentQrCodes : teacherQrCodes,
                options,
            );

        if (!opened) {
            toast.error('Gagal menyiapkan halaman cetak. Coba lagi.');
        }
    };

    const QrCard = ({ qr, type }: { qr: QrCodeData; type: 'student' | 'teacher' | 'staff' }) => (
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
                            {qr.nis || qr.nip || qr.employee_id || '-'}
                        </div>
                        {qr.position && (
                            <div className="text-xs text-muted-foreground">{qr.position}</div>
                        )}
                    </div>
                    <div className="mt-3 flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => handleDownload(qr)}
                        >
                            <Download className="h-4 w-4" />
                        </Button>
                        {canManageAll && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (type === 'student') return handleRegenerateStudent(qr.student_id!);
                                    if (type === 'teacher') return handleRegenerateTeacher(qr.teacher_id!);
                                    return handleRegenerateStaff(qr.staff_id!);
                                }}
                                disabled={regenerating === (qr.student_id || qr.teacher_id || qr.staff_id)}
                            >
                                <RefreshCw className={`h-4 w-4 ${regenerating === (qr.student_id || qr.teacher_id || qr.staff_id) ? 'animate-spin' : ''}`} />
                            </Button>
                        )}
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
                            Generate dan kelola QR Code untuk siswa, guru, dan staf
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
                            {canManageAll && (
                                <TabsTrigger value="teachers" className="gap-2">
                                    <GraduationCap className="h-4 w-4" />
                                    Guru
                                </TabsTrigger>
                            )}
                            {canManageAll && (
                                <TabsTrigger value="staff" className="gap-2">
                                    <Briefcase className="h-4 w-4" />
                                    Staf
                                </TabsTrigger>
                            )}
                        </TabsList>

                        <div className="flex gap-2">
                            {/* Export QR/RFID backend baru menerima type student|teacher
                                (QrExportController), jadi tombolnya disembunyikan di tab Staf
                                daripada memunculkan tombol yang pasti gagal 422. */}
                            {canManageAll && activeTab !== 'staff' && (
                                <Button variant="outline" onClick={() => setExportOpen(true)}>
                                    <FileDown className="mr-2 h-4 w-4" />
                                    Export
                                </Button>
                            )}
                            {((activeTab === 'students' && studentQrCodes.length > 0) ||
                              (activeTab === 'teachers' && teacherQrCodes.length > 0) ||
                              (activeTab === 'staff' && staffQrCodes.length > 0)) && (
                                <Button onClick={handlePrintAll}>
                                    <Printer className="mr-2 h-4 w-4" />
                                    Cetak Semua
                                </Button>
                            )}
                        </div>
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

                    {/* Staff Tab — sumber datanya master data Staff (menu Data
                        Staff), terpisah dari guru: pegawai non-pengajar seperti
                        TU, keamanan, kebersihan. */}
                    <TabsContent value="staff" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>QR Code Staf</CardTitle>
                                <CardDescription>
                                    {staffQrCodes.length} staf aktif terdaftar
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button onClick={fetchStaffQrCodes} variant="outline" disabled={loading}>
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
                        ) : staffQrCodes.length === 0 ? (
                            <Card>
                                <CardContent className="py-16 text-center">
                                    <Briefcase className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-muted-foreground">
                                        Belum ada data staf aktif
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        Tambahkan lewat menu Data Staff terlebih dahulu.
                                    </p>
                                </CardContent>
                            </Card>
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                                {staffQrCodes.map((qr) => (
                                    <QrCard key={qr.staff_id} qr={qr} type="staff" />
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
                            {selectedQr?.nis || selectedQr?.nip || selectedQr?.employee_id || 'QR Code'}
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

            {/* Export QR/RFID Dialog */}
            <Dialog open={exportOpen} onOpenChange={setExportOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Export QR / RFID</DialogTitle>
                        <DialogDescription>
                            Export {activeTab === 'teachers' ? 'guru' : 'siswa'} untuk pembuatan kartu di luar aplikasi.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-2">
                        {/* Format */}
                        <div className="space-y-2">
                            <Label>Format</Label>
                            <div className="grid grid-cols-3 gap-2">
                                {([
                                    { v: 'excel', label: 'Excel', icon: FileSpreadsheet },
                                    { v: 'zip', label: 'ZIP Gambar', icon: FileArchive },
                                    { v: 'pdf', label: 'PDF Kartu', icon: FileText },
                                ] as const).map((f) => (
                                    <Button
                                        key={f.v}
                                        type="button"
                                        variant={exportFormat === f.v ? 'default' : 'outline'}
                                        className="flex-col h-auto py-3"
                                        onClick={() => setExportFormat(f.v)}
                                    >
                                        <f.icon className="mb-1 h-5 w-5" />
                                        <span className="text-xs">{f.label}</span>
                                    </Button>
                                ))}
                            </div>
                        </div>

                        {/* Scope (siswa saja) */}
                        {activeTab === 'students' && (
                            <div className="space-y-2">
                                <Label>Cakupan</Label>
                                <Select value={exportScope} onValueChange={(v) => setExportScope(v as 'class' | 'all')}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="class">Per kelas (yang dipilih)</SelectItem>
                                        <SelectItem value="all">Semua siswa</SelectItem>
                                    </SelectContent>
                                </Select>
                                {exportScope === 'class' && !classroomId && (
                                    <p className="text-xs text-destructive">Pilih kelas dulu di filter atas.</p>
                                )}
                            </div>
                        )}

                        {/* Format gambar (ZIP saja) */}
                        {exportFormat === 'zip' && (
                            <div className="space-y-2">
                                <Label>Format Gambar</Label>
                                <Select value={exportImage} onValueChange={(v) => setExportImage(v as 'svg' | 'png' | 'both')}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="both">SVG + PNG</SelectItem>
                                        <SelectItem value="svg">SVG saja</SelectItem>
                                        <SelectItem value="png">PNG saja</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {/* Generate RFID */}
                        <div className="flex items-start gap-2">
                            <Checkbox
                                id="gen-rfid"
                                checked={generateRfid}
                                onCheckedChange={(c) => setGenerateRfid(!!c)}
                            />
                            <div className="grid gap-1 leading-none">
                                <Label htmlFor="gen-rfid">Generate kode RFID otomatis</Label>
                                <p className="text-xs text-muted-foreground">
                                    Isi kode RFID untuk yang belum punya (untuk kartu writable).
                                </p>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setExportOpen(false)} disabled={exporting}>
                            Batal
                        </Button>
                        <Button onClick={handleExport} disabled={exporting}>
                            <FileDown className={`mr-2 h-4 w-4 ${exporting ? 'animate-spin' : ''}`} />
                            {exporting ? 'Memproses...' : 'Export'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </MainLayout>
    );
}
