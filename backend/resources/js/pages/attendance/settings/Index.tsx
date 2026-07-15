import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Separator } from '@/components/ui/separator';
import { toast } from 'sonner';
import { Save, RefreshCw, Clock, MapPin, Bell, MessageSquare, Send, CheckCircle } from 'lucide-react';
import { attendanceSettingsApi } from '@/services/attendance';
import type { AttendanceSettings, NotificationSettings } from '@/types/attendance';

interface FormState {
    attendance: AttendanceSettings;
    notification: NotificationSettings & {
        wa_api_key: string;
        wa_sender_number: string;
        telegram_bot_token: string;
        telegram_default_chat_id: string;
    };
}

const defaultAttendance: AttendanceSettings = {
    check_in_start: '06:00',
    check_in_end: '07:30',
    check_out_start: '14:00',
    check_out_end: '17:00',
    late_tolerance_minutes: 15,
    require_location: false,
    require_photo: false,
    location_radius: 100,
    school_latitude: null,
    school_longitude: null,
    working_days: [1, 2, 3, 4, 5],
};

const defaultNotification = {
    wa_enabled: false,
    wa_provider: 'fonnte' as const,
    wa_configured: false,
    wa_api_key: '',
    wa_sender_number: '',
    telegram_enabled: false,
    telegram_configured: false,
    telegram_bot_token: '',
    telegram_default_chat_id: '',
    notify_check_in: true,
    notify_check_out: true,
    notify_late: true,
    notify_absent: true,
    notify_leave_approved: true,
    templates: {},
};

const weekdays = [
    { value: 1, label: 'Senin' },
    { value: 2, label: 'Selasa' },
    { value: 3, label: 'Rabu' },
    { value: 4, label: 'Kamis' },
    { value: 5, label: 'Jumat' },
    { value: 6, label: 'Sabtu' },
    { value: 0, label: 'Minggu' },
];

export default function SettingsIndex() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [testingWa, setTestingWa] = useState(false);
    const [testingTelegram, setTestingTelegram] = useState(false);
    const [testPhone, setTestPhone] = useState('');
    const [testChatId, setTestChatId] = useState('');
    const [form, setForm] = useState<FormState>({
        attendance: defaultAttendance,
        notification: defaultNotification,
    });

    const fetchSettings = async () => {
        setLoading(true);
        try {
            const response = await attendanceSettingsApi.get();
            if (response.data.data) {
                setForm({
                    attendance: { ...defaultAttendance, ...response.data.data.attendance },
                    notification: { ...defaultNotification, ...response.data.data.notification },
                });
            }
        } catch (error) {
            toast.error('Gagal memuat pengaturan');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchSettings();
    }, []);

    const handleSave = async () => {
        setSaving(true);
        try {
            await attendanceSettingsApi.update({
                ...form.attendance,
                ...form.notification,
            });
            toast.success('Pengaturan berhasil disimpan');
        } catch (error) {
            toast.error('Gagal menyimpan pengaturan');
        } finally {
            setSaving(false);
        }
    };

    const handleTestWhatsApp = async () => {
        if (!testPhone.trim()) {
            toast.error('Masukkan nomor telepon untuk testing');
            return;
        }

        setTestingWa(true);
        try {
            await attendanceSettingsApi.testWhatsApp(testPhone);
            toast.success('Pesan WhatsApp berhasil dikirim');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal mengirim pesan WhatsApp');
        } finally {
            setTestingWa(false);
        }
    };

    const handleTestTelegram = async () => {
        if (!testChatId.trim()) {
            toast.error('Masukkan Chat ID untuk testing');
            return;
        }

        setTestingTelegram(true);
        try {
            await attendanceSettingsApi.testTelegram(testChatId);
            toast.success('Pesan Telegram berhasil dikirim');
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Gagal mengirim pesan Telegram');
        } finally {
            setTestingTelegram(false);
        }
    };

    const updateAttendance = (key: keyof AttendanceSettings, value: unknown) => {
        setForm(prev => ({
            ...prev,
            attendance: { ...prev.attendance, [key]: value },
        }));
    };

    const updateNotification = (key: string, value: unknown) => {
        setForm(prev => ({
            ...prev,
            notification: { ...prev.notification, [key]: value },
        }));
    };

    const toggleWorkingDay = (day: number) => {
        const current = form.attendance.working_days;
        const updated = current.includes(day)
            ? current.filter(d => d !== day)
            : [...current, day].sort();
        updateAttendance('working_days', updated);
    };

    if (loading) {
        return (
            <MainLayout title="Pengaturan Absensi">
                <Head title="Pengaturan Absensi" />
                <div className="flex h-64 items-center justify-center">
                    <RefreshCw className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            </MainLayout>
        );
    }

    return (
        <MainLayout title="Pengaturan Absensi">
            <Head title="Pengaturan Absensi" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pengaturan Absensi</h1>
                        <p className="text-muted-foreground">
                            Konfigurasi jam absensi dan notifikasi
                        </p>
                    </div>
                    <Button onClick={handleSave} disabled={saving}>
                        <Save className="mr-2 h-4 w-4" />
                        {saving ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>

                {/* Tabs */}
                <Tabs defaultValue="attendance">
                    <TabsList>
                        <TabsTrigger value="attendance" className="gap-2">
                            <Clock className="h-4 w-4" />
                            Jam Absensi
                        </TabsTrigger>
                        <TabsTrigger value="location" className="gap-2">
                            <MapPin className="h-4 w-4" />
                            Lokasi
                        </TabsTrigger>
                        <TabsTrigger value="notification" className="gap-2">
                            <Bell className="h-4 w-4" />
                            Notifikasi
                        </TabsTrigger>
                    </TabsList>

                    {/* Attendance Settings */}
                    <TabsContent value="attendance" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Jam Masuk</CardTitle>
                                <CardDescription>
                                    Konfigurasi jam masuk dan toleransi keterlambatan
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label>Jam Mulai Scan Masuk</Label>
                                        <Input
                                            type="time"
                                            value={form.attendance.check_in_start}
                                            onChange={(e) => updateAttendance('check_in_start', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Batas Jam Masuk</Label>
                                        <Input
                                            type="time"
                                            value={form.attendance.check_in_end}
                                            onChange={(e) => updateAttendance('check_in_end', e.target.value)}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Lewat dari jam ini dihitung terlambat
                                        </p>
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Toleransi Terlambat (menit)</Label>
                                        <Input
                                            type="number"
                                            min={0}
                                            max={60}
                                            value={form.attendance.late_tolerance_minutes}
                                            onChange={(e) => updateAttendance('late_tolerance_minutes', parseInt(e.target.value) || 0)}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Jam Pulang</CardTitle>
                                <CardDescription>
                                    Konfigurasi jam scan pulang
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label>Jam Mulai Scan Pulang</Label>
                                        <Input
                                            type="time"
                                            value={form.attendance.check_out_start}
                                            onChange={(e) => updateAttendance('check_out_start', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Batas Jam Pulang</Label>
                                        <Input
                                            type="time"
                                            value={form.attendance.check_out_end}
                                            onChange={(e) => updateAttendance('check_out_end', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Hari Kerja</CardTitle>
                                <CardDescription>
                                    Pilih hari-hari aktif sekolah
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="flex flex-wrap gap-2">
                                    {weekdays.map((day) => (
                                        <Button
                                            key={day.value}
                                            variant={form.attendance.working_days.includes(day.value) ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => toggleWorkingDay(day.value)}
                                        >
                                            {day.label}
                                        </Button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Location Settings */}
                    <TabsContent value="location" className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>Pengaturan Lokasi</CardTitle>
                                <CardDescription>
                                    Konfigurasi validasi lokasi saat scan
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <Label>Wajib Lokasi</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Scan harus dalam radius lokasi sekolah
                                        </p>
                                    </div>
                                    <Switch
                                        checked={form.attendance.require_location}
                                        onCheckedChange={(checked) => updateAttendance('require_location', checked)}
                                    />
                                </div>

                                <Separator />

                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label>Latitude Sekolah</Label>
                                        <Input
                                            type="number"
                                            step="any"
                                            placeholder="-6.2088"
                                            value={form.attendance.school_latitude || ''}
                                            onChange={(e) => updateAttendance('school_latitude', parseFloat(e.target.value) || null)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Longitude Sekolah</Label>
                                        <Input
                                            type="number"
                                            step="any"
                                            placeholder="106.8456"
                                            value={form.attendance.school_longitude || ''}
                                            onChange={(e) => updateAttendance('school_longitude', parseFloat(e.target.value) || null)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>Radius (meter)</Label>
                                        <Input
                                            type="number"
                                            min={10}
                                            max={1000}
                                            value={form.attendance.location_radius || 100}
                                            onChange={(e) => updateAttendance('location_radius', parseInt(e.target.value) || 100)}
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Notification Settings */}
                    <TabsContent value="notification" className="space-y-4">
                        {/* WhatsApp */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <MessageSquare className="h-5 w-5" />
                                    WhatsApp
                                </CardTitle>
                                <CardDescription>
                                    Konfigurasi notifikasi WhatsApp
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <Label>Aktifkan WhatsApp</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Kirim notifikasi via WhatsApp
                                        </p>
                                    </div>
                                    <Switch
                                        checked={form.notification.wa_enabled}
                                        onCheckedChange={(checked) => updateNotification('wa_enabled', checked)}
                                    />
                                </div>

                                {form.notification.wa_enabled && (
                                    <>
                                        <Separator />

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Provider</Label>
                                                <Select
                                                    value={form.notification.wa_provider}
                                                    onValueChange={(value) => updateNotification('wa_provider', value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="fonnte">Fonnte</SelectItem>
                                                        <SelectItem value="wablas">Wablas</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="space-y-2">
                                                <Label>API Key</Label>
                                                <Input
                                                    type="password"
                                                    value={form.notification.wa_api_key}
                                                    onChange={(e) => updateNotification('wa_api_key', e.target.value)}
                                                    placeholder="API Key dari provider"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex gap-4">
                                            <div className="flex-1 space-y-2">
                                                <Label>Test Nomor</Label>
                                                <Input
                                                    value={testPhone}
                                                    onChange={(e) => setTestPhone(e.target.value)}
                                                    placeholder="628123456789"
                                                />
                                            </div>
                                            <div className="flex items-end">
                                                <Button
                                                    variant="outline"
                                                    onClick={handleTestWhatsApp}
                                                    disabled={testingWa}
                                                >
                                                    <Send className={`mr-2 h-4 w-4 ${testingWa ? 'animate-spin' : ''}`} />
                                                    Test
                                                </Button>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Telegram */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Send className="h-5 w-5" />
                                    Telegram
                                </CardTitle>
                                <CardDescription>
                                    Konfigurasi notifikasi Telegram
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <Label>Aktifkan Telegram</Label>
                                        <p className="text-sm text-muted-foreground">
                                            Kirim notifikasi via Telegram Bot
                                        </p>
                                    </div>
                                    <Switch
                                        checked={form.notification.telegram_enabled}
                                        onCheckedChange={(checked) => updateNotification('telegram_enabled', checked)}
                                    />
                                </div>

                                {form.notification.telegram_enabled && (
                                    <>
                                        <Separator />

                                        <div className="grid gap-4 md:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label>Bot Token</Label>
                                                <Input
                                                    type="password"
                                                    value={form.notification.telegram_bot_token}
                                                    onChange={(e) => updateNotification('telegram_bot_token', e.target.value)}
                                                    placeholder="Token dari @BotFather"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label>Default Chat ID</Label>
                                                <Input
                                                    value={form.notification.telegram_default_chat_id}
                                                    onChange={(e) => updateNotification('telegram_default_chat_id', e.target.value)}
                                                    placeholder="Chat ID untuk notifikasi"
                                                />
                                            </div>
                                        </div>

                                        <div className="flex gap-4">
                                            <div className="flex-1 space-y-2">
                                                <Label>Test Chat ID</Label>
                                                <Input
                                                    value={testChatId}
                                                    onChange={(e) => setTestChatId(e.target.value)}
                                                    placeholder="123456789"
                                                />
                                            </div>
                                            <div className="flex items-end">
                                                <Button
                                                    variant="outline"
                                                    onClick={handleTestTelegram}
                                                    disabled={testingTelegram}
                                                >
                                                    <Send className={`mr-2 h-4 w-4 ${testingTelegram ? 'animate-spin' : ''}`} />
                                                    Test
                                                </Button>
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Notification Events */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Event Notifikasi</CardTitle>
                                <CardDescription>
                                    Pilih kapan notifikasi dikirim
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <Label>Notifikasi Check-in</Label>
                                    <Switch
                                        checked={form.notification.notify_check_in}
                                        onCheckedChange={(checked) => updateNotification('notify_check_in', checked)}
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <Label>Notifikasi Check-out</Label>
                                    <Switch
                                        checked={form.notification.notify_check_out}
                                        onCheckedChange={(checked) => updateNotification('notify_check_out', checked)}
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <Label>Notifikasi Terlambat</Label>
                                    <Switch
                                        checked={form.notification.notify_late}
                                        onCheckedChange={(checked) => updateNotification('notify_late', checked)}
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <Label>Notifikasi Tidak Hadir</Label>
                                    <Switch
                                        checked={form.notification.notify_absent}
                                        onCheckedChange={(checked) => updateNotification('notify_absent', checked)}
                                    />
                                </div>
                                <div className="flex items-center justify-between">
                                    <Label>Notifikasi Izin Disetujui</Label>
                                    <Switch
                                        checked={form.notification.notify_leave_approved}
                                        onCheckedChange={(checked) => updateNotification('notify_leave_approved', checked)}
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </MainLayout>
    );
}
