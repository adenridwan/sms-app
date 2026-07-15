import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import { ArrowLeft, Upload, Loader2 } from 'lucide-react';
import { leavePermissionApi } from '@/services/attendance';
import type { LeaveType } from '@/types/attendance';
import type { Student, User } from '@/types';

interface Props {
    students?: Student[];
    teachers?: User[];
}

export default function CreateLeavePermission({ students = [], teachers = [] }: Props) {
    const [submitting, setSubmitting] = useState(false);
    const [personType, setPersonType] = useState<'student' | 'teacher'>('student');
    const [form, setForm] = useState({
        student_id: '',
        teacher_id: '',
        tanggal_mulai: new Date().toISOString().split('T')[0],
        tanggal_selesai: new Date().toISOString().split('T')[0],
        tipe_izin: 'sakit' as LeaveType,
        alasan: '',
        bukti: null as File | null,
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (personType === 'student' && !form.student_id) {
            toast.error('Pilih siswa terlebih dahulu');
            return;
        }
        if (personType === 'teacher' && !form.teacher_id) {
            toast.error('Pilih guru terlebih dahulu');
            return;
        }
        if (!form.tanggal_mulai || !form.tanggal_selesai) {
            toast.error('Tanggal harus diisi');
            return;
        }
        if (form.tanggal_selesai < form.tanggal_mulai) {
            toast.error('Tanggal selesai tidak boleh sebelum tanggal mulai');
            return;
        }

        setSubmitting(true);
        try {
            const data: Record<string, unknown> = {
                tanggal_mulai: form.tanggal_mulai,
                tanggal_selesai: form.tanggal_selesai,
                tipe_izin: form.tipe_izin,
                alasan: form.alasan,
            };

            if (personType === 'student') {
                data.student_id = form.student_id;
            } else {
                data.teacher_id = form.teacher_id;
            }

            if (form.bukti) {
                data.bukti = form.bukti;
            }

            await leavePermissionApi.create(data as any);
            toast.success('Perizinan berhasil diajukan');
            router.visit('/attendance/permissions');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal mengajukan perizinan');
        } finally {
            setSubmitting(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 5 * 1024 * 1024) {
                toast.error('Ukuran file maksimal 5MB');
                return;
            }
            setForm(prev => ({ ...prev, bukti: file }));
        }
    };

    return (
        <MainLayout title="Ajukan Izin">
            <Head title="Ajukan Izin" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="icon" onClick={() => router.visit('/attendance/permissions')}>
                        <ArrowLeft className="h-4 w-4" />
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Ajukan Izin</h1>
                        <p className="text-muted-foreground">
                            Buat pengajuan izin sakit atau izin lainnya
                        </p>
                    </div>
                </div>

                {/* Form */}
                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Form Perizinan</CardTitle>
                        <CardDescription>
                            Lengkapi data perizinan di bawah ini
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Person Type */}
                            <div className="space-y-2">
                                <Label>Tipe</Label>
                                <Select
                                    value={personType}
                                    onValueChange={(value: 'student' | 'teacher') => {
                                        setPersonType(value);
                                        setForm(prev => ({ ...prev, student_id: '', teacher_id: '' }));
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="student">Siswa</SelectItem>
                                        <SelectItem value="teacher">Guru/Karyawan</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Person Selection */}
                            {personType === 'student' ? (
                                <div className="space-y-2">
                                    <Label>Siswa</Label>
                                    <Select
                                        value={form.student_id}
                                        onValueChange={(value) => setForm(prev => ({ ...prev, student_id: value }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih siswa" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {students.map((student) => (
                                                <SelectItem key={student.id} value={student.id}>
                                                    {student.nis} - {student.user?.full_name || 'Unknown'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            ) : (
                                <div className="space-y-2">
                                    <Label>Guru/Karyawan</Label>
                                    <Select
                                        value={form.teacher_id}
                                        onValueChange={(value) => setForm(prev => ({ ...prev, teacher_id: value }))}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Pilih guru/karyawan" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {teachers.map((teacher) => (
                                                <SelectItem key={teacher.id} value={teacher.id}>
                                                    {teacher.full_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {/* Leave Type */}
                            <div className="space-y-2">
                                <Label>Jenis Izin</Label>
                                <Select
                                    value={form.tipe_izin}
                                    onValueChange={(value: LeaveType) => setForm(prev => ({ ...prev, tipe_izin: value }))}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="sakit">Sakit</SelectItem>
                                        <SelectItem value="izin">Izin</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            {/* Date Range */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Tanggal Mulai</Label>
                                    <Input
                                        type="date"
                                        value={form.tanggal_mulai}
                                        onChange={(e) => setForm(prev => ({ ...prev, tanggal_mulai: e.target.value }))}
                                        required
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Tanggal Selesai</Label>
                                    <Input
                                        type="date"
                                        value={form.tanggal_selesai}
                                        onChange={(e) => setForm(prev => ({ ...prev, tanggal_selesai: e.target.value }))}
                                        min={form.tanggal_mulai}
                                        required
                                    />
                                </div>
                            </div>

                            {/* Reason */}
                            <div className="space-y-2">
                                <Label>Alasan</Label>
                                <Textarea
                                    value={form.alasan}
                                    onChange={(e) => setForm(prev => ({ ...prev, alasan: e.target.value }))}
                                    placeholder="Jelaskan alasan perizinan..."
                                    rows={4}
                                />
                            </div>

                            {/* Evidence Upload */}
                            <div className="space-y-2">
                                <Label>Bukti (Opsional)</Label>
                                <div className="flex items-center gap-4">
                                    <Input
                                        type="file"
                                        accept="image/*,.pdf"
                                        onChange={handleFileChange}
                                        className="hidden"
                                        id="bukti-upload"
                                    />
                                    <Label
                                        htmlFor="bukti-upload"
                                        className="flex cursor-pointer items-center gap-2 rounded-md border border-dashed px-4 py-2 hover:bg-muted"
                                    >
                                        <Upload className="h-4 w-4" />
                                        {form.bukti ? form.bukti.name : 'Upload bukti'}
                                    </Label>
                                    {form.bukti && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setForm(prev => ({ ...prev, bukti: null }))}
                                        >
                                            Hapus
                                        </Button>
                                    )}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    Format: JPG, PNG, PDF. Maksimal 5MB
                                </p>
                            </div>

                            {/* Submit */}
                            <div className="flex justify-end gap-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => router.visit('/attendance/permissions')}
                                >
                                    Batal
                                </Button>
                                <Button type="submit" disabled={submitting}>
                                    {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Ajukan Izin
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}
