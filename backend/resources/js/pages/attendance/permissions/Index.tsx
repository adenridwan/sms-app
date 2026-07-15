import { Head, Link } from '@inertiajs/react';
import { useState, useEffect } from 'react';
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
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { toast } from 'sonner';
import { Plus, RefreshCw, CheckCircle, XCircle, Eye, FileText, Image } from 'lucide-react';
import { leavePermissionApi } from '@/services/attendance';
import type { LeavePermission, LeaveStatus, LeaveType } from '@/types/attendance';

const getStatusBadge = (status: LeaveStatus) => {
    const config: Record<LeaveStatus, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
        pending: { variant: 'secondary', label: 'Menunggu' },
        approved: { variant: 'default', label: 'Disetujui' },
        rejected: { variant: 'destructive', label: 'Ditolak' },
    };
    const { variant, label } = config[status] || { variant: 'outline', label: status };
    return <Badge variant={variant}>{label}</Badge>;
};

const getTypeBadge = (type: LeaveType) => {
    const config: Record<LeaveType, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
        sakit: { variant: 'destructive', label: 'Sakit' },
        izin: { variant: 'outline', label: 'Izin' },
    };
    const { variant, label } = config[type] || { variant: 'outline', label: type };
    return <Badge variant={variant}>{label}</Badge>;
};

export default function LeavePermissionIndex() {
    const [permissions, setPermissions] = useState<LeavePermission[]>([]);
    const [loading, setLoading] = useState(false);
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [typeFilter, setTypeFilter] = useState<string>('all');
    const [viewingPermission, setViewingPermission] = useState<LeavePermission | null>(null);
    const [rejectingPermission, setRejectingPermission] = useState<LeavePermission | null>(null);
    const [rejectionReason, setRejectionReason] = useState('');
    const [approvingId, setApprovingId] = useState<string | null>(null);

    const fetchPermissions = async () => {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (statusFilter !== 'all') params.status = statusFilter;
            if (typeFilter !== 'all') params.tipe_izin = typeFilter;

            const response = await leavePermissionApi.list(params);
            if (response.data.data) {
                setPermissions(response.data.data);
            }
        } catch (error) {
            toast.error('Gagal memuat data perizinan');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchPermissions();
    }, [statusFilter, typeFilter]);

    const handleApprove = async (id: string) => {
        setApprovingId(id);
        try {
            await leavePermissionApi.approve(id);
            toast.success('Perizinan berhasil disetujui');
            fetchPermissions();
        } catch (error) {
            toast.error('Gagal menyetujui perizinan');
        } finally {
            setApprovingId(null);
        }
    };

    const handleReject = async () => {
        if (!rejectingPermission || !rejectionReason.trim()) {
            toast.error('Alasan penolakan harus diisi');
            return;
        }

        try {
            await leavePermissionApi.reject(rejectingPermission.id, rejectionReason);
            toast.success('Perizinan berhasil ditolak');
            setRejectingPermission(null);
            setRejectionReason('');
            fetchPermissions();
        } catch (error) {
            toast.error('Gagal menolak perizinan');
        }
    };

    const handleDelete = async (id: string) => {
        try {
            await leavePermissionApi.delete(id);
            toast.success('Perizinan berhasil dihapus');
            fetchPermissions();
        } catch (error) {
            toast.error('Gagal menghapus perizinan');
        }
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
    };

    const getPersonName = (permission: LeavePermission) => {
        if (permission.student) {
            return permission.student.user?.full_name || permission.student.nis;
        }
        if (permission.teacher) {
            return permission.teacher.user?.full_name || permission.teacher.nip || 'Guru';
        }
        return '-';
    };

    const getPersonType = (permission: LeavePermission) => {
        if (permission.student_id) return 'Siswa';
        if (permission.teacher_id) return 'Guru';
        return '-';
    };

    return (
        <MainLayout title="Perizinan">
            <Head title="Perizinan" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Perizinan</h1>
                        <p className="text-muted-foreground">
                            Kelola izin sakit dan perizinan siswa/guru
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/attendance/permissions/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Ajukan Izin
                        </Link>
                    </Button>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle>Filter</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4">
                            <div className="w-[180px]">
                                <Label>Status</Label>
                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Status</SelectItem>
                                        <SelectItem value="pending">Menunggu</SelectItem>
                                        <SelectItem value="approved">Disetujui</SelectItem>
                                        <SelectItem value="rejected">Ditolak</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-[180px]">
                                <Label>Tipe Izin</Label>
                                <Select value={typeFilter} onValueChange={setTypeFilter}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Semua Tipe</SelectItem>
                                        <SelectItem value="sakit">Sakit</SelectItem>
                                        <SelectItem value="izin">Izin</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-end">
                                <Button onClick={fetchPermissions} variant="outline" disabled={loading}>
                                    <RefreshCw className={`mr-2 h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
                                    Refresh
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Perizinan</CardTitle>
                        <CardDescription>
                            {permissions.length} perizinan ditemukan
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {loading ? (
                            <div className="py-8 text-center text-muted-foreground">Memuat...</div>
                        ) : permissions.length === 0 ? (
                            <div className="py-8 text-center text-muted-foreground">
                                Tidak ada data perizinan
                            </div>
                        ) : (
                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Nama</TableHead>
                                            <TableHead>Tipe</TableHead>
                                            <TableHead>Jenis Izin</TableHead>
                                            <TableHead>Tanggal</TableHead>
                                            <TableHead>Durasi</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="w-[200px]">Aksi</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {permissions.map((permission) => (
                                            <TableRow key={permission.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{getPersonName(permission)}</div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {getPersonType(permission)}
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{getTypeBadge(permission.tipe_izin)}</TableCell>
                                                <TableCell className="max-w-[200px] truncate">
                                                    {permission.alasan || '-'}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="text-sm">
                                                        {formatDate(permission.tanggal_mulai)}
                                                        {permission.tanggal_mulai !== permission.tanggal_selesai && (
                                                            <> - {formatDate(permission.tanggal_selesai)}</>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>{permission.total_days} hari</TableCell>
                                                <TableCell>{getStatusBadge(permission.status)}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-1">
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() => setViewingPermission(permission)}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Button>
                                                        {permission.status === 'pending' && (
                                                            <>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-green-600 hover:text-green-700"
                                                                    onClick={() => handleApprove(permission.id)}
                                                                    disabled={approvingId === permission.id}
                                                                >
                                                                    <CheckCircle className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="text-red-600 hover:text-red-700"
                                                                    onClick={() => setRejectingPermission(permission)}
                                                                >
                                                                    <XCircle className="h-4 w-4" />
                                                                </Button>
                                                            </>
                                                        )}
                                                    </div>
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

            {/* View Dialog */}
            <Dialog open={!!viewingPermission} onOpenChange={() => setViewingPermission(null)}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Detail Perizinan</DialogTitle>
                        <DialogDescription>
                            {viewingPermission && getPersonName(viewingPermission)}
                        </DialogDescription>
                    </DialogHeader>
                    {viewingPermission && (
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="text-muted-foreground">Tipe</Label>
                                    <div>{getTypeBadge(viewingPermission.tipe_izin)}</div>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">Status</Label>
                                    <div>{getStatusBadge(viewingPermission.status)}</div>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label className="text-muted-foreground">Tanggal Mulai</Label>
                                    <div className="font-medium">{formatDate(viewingPermission.tanggal_mulai)}</div>
                                </div>
                                <div>
                                    <Label className="text-muted-foreground">Tanggal Selesai</Label>
                                    <div className="font-medium">{formatDate(viewingPermission.tanggal_selesai)}</div>
                                </div>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Durasi</Label>
                                <div className="font-medium">{viewingPermission.total_days} hari</div>
                            </div>
                            <div>
                                <Label className="text-muted-foreground">Alasan</Label>
                                <div className="mt-1 rounded-md bg-muted p-3">
                                    {viewingPermission.alasan || 'Tidak ada alasan'}
                                </div>
                            </div>
                            {viewingPermission.bukti && (
                                <div>
                                    <Label className="text-muted-foreground">Bukti</Label>
                                    <div className="mt-1">
                                        <a
                                            href={viewingPermission.bukti}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-center gap-2 text-blue-600 hover:underline"
                                        >
                                            <Image className="h-4 w-4" />
                                            Lihat Bukti
                                        </a>
                                    </div>
                                </div>
                            )}
                            {viewingPermission.rejection_reason && (
                                <div>
                                    <Label className="text-muted-foreground">Alasan Penolakan</Label>
                                    <div className="mt-1 rounded-md bg-red-50 p-3 text-red-700 dark:bg-red-900/20">
                                        {viewingPermission.rejection_reason}
                                    </div>
                                </div>
                            )}
                            {viewingPermission.approver && (
                                <div>
                                    <Label className="text-muted-foreground">Diproses Oleh</Label>
                                    <div className="font-medium">{viewingPermission.approver.full_name}</div>
                                </div>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setViewingPermission(null)}>
                            Tutup
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Reject Dialog */}
            <AlertDialog open={!!rejectingPermission} onOpenChange={() => setRejectingPermission(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Tolak Perizinan</AlertDialogTitle>
                        <AlertDialogDescription>
                            Masukkan alasan penolakan untuk {rejectingPermission && getPersonName(rejectingPermission)}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="py-4">
                        <Textarea
                            value={rejectionReason}
                            onChange={(e) => setRejectionReason(e.target.value)}
                            placeholder="Alasan penolakan..."
                            rows={3}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => {
                            setRejectingPermission(null);
                            setRejectionReason('');
                        }}>
                            Batal
                        </AlertDialogCancel>
                        <AlertDialogAction onClick={handleReject} className="bg-red-600 hover:bg-red-700">
                            Tolak
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </MainLayout>
    );
}
