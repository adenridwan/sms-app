import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    BookOpen,
    GraduationCap,
    Users,
    Calendar,
    DollarSign,
    Settings,
    HelpCircle,
    Keyboard,
    Mail,
    Phone,
    ChevronDown,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

const helpSections = [
    {
        icon: BookOpen,
        title: 'Akademik',
        description: 'Kelola data akademik sekolah',
        items: [
            {
                q: 'Bagaimana cara menambah tahun ajaran baru?',
                a: 'Buka menu Akademik > Tahun Ajaran, klik tombol "Tambah Tahun Ajaran", isi data yang diperlukan, lalu simpan.',
            },
            {
                q: 'Bagaimana cara mengatur jadwal pelajaran?',
                a: 'Buka menu Akademik > Jadwal. Pilih kelas dan semester, lalu atur jadwal per hari dengan memilih mata pelajaran dan guru pengampu.',
            },
            {
                q: 'Bagaimana cara menambah kurikulum?',
                a: 'Buka menu Akademik > Kurikulum, klik tombol "Tambah Kurikulum", isi nama dan kode kurikulum, lalu simpan.',
            },
        ],
    },
    {
        icon: Users,
        title: 'Siswa & Guru',
        description: 'Kelola data siswa, guru, dan staff',
        items: [
            {
                q: 'Bagaimana cara mendaftarkan siswa baru?',
                a: 'Buka menu Siswa > Data Siswa, klik "Tambah Siswa", isi formulir pendaftaran lengkap, lalu simpan. Akun login akan dibuat otomatis.',
            },
            {
                q: 'Bagaimana cara import data siswa massal?',
                a: 'Buka menu Siswa > Data Siswa, klik tombol "Import", unduh template Excel, isi data, lalu unggah file yang sudah diisi.',
            },
            {
                q: 'Bagaimana cara menambah guru baru?',
                a: 'Buka menu Guru & Staff > Data Guru, klik "Tambah Guru", isi data lengkap termasuk NIP dan status kepegawaian.',
            },
        ],
    },
    {
        icon: Calendar,
        title: 'Absensi',
        description: 'Sistem pencatatan kehadiran',
        items: [
            {
                q: 'Bagaimana cara mencatat absensi siswa?',
                a: 'Buka menu Absensi > Absensi Siswa, pilih kelas dan tanggal, lalu centang kehadiran masing-masing siswa.',
            },
            {
                q: 'Bagaimana cara menggunakan QR Code untuk absensi?',
                a: 'Siswa/guru dapat scan QR Code menggunakan aplikasi mobile SMS Absensi. QR Code dapat dicetak dari menu pengaturan perangkat.',
            },
            {
                q: 'Bagaimana cara melihat rekap absensi?',
                a: 'Buka menu Absensi > Rekap Absensi. Pilih rentang tanggal dan kelas untuk melihat ringkasan kehadiran.',
            },
        ],
    },
    {
        icon: DollarSign,
        title: 'Keuangan',
        description: 'Pengelolaan tagihan dan pembayaran',
        items: [
            {
                q: 'Bagaimana cara membuat tagihan SPP?',
                a: 'Buka menu Keuangan > Tagihan, klik "Buat Tagihan", pilih siswa atau kelas, tentukan jenis biaya dan nominal, lalu simpan.',
            },
            {
                q: 'Bagaimana cara mencatat pembayaran?',
                a: 'Buka menu Keuangan > Pembayaran, cari tagihan yang akan dibayar, klik "Bayar", pilih metode pembayaran, lalu konfirmasi.',
            },
            {
                q: 'Bagaimana cara melihat laporan keuangan?',
                a: 'Buka menu Keuangan > Laporan untuk melihat ringkasan penerimaan dan tunggakan per periode.',
            },
        ],
    },
    {
        icon: Settings,
        title: 'Pengaturan',
        description: 'Konfigurasi sistem',
        items: [
            {
                q: 'Bagaimana cara mengubah profil sekolah?',
                a: 'Buka menu Pengaturan > Umum. Di sini Anda dapat mengubah nama sekolah, logo, dan informasi kontak.',
            },
            {
                q: 'Bagaimana cara menambah pengguna baru?',
                a: 'Buka menu Pengaturan > Pengguna (khusus Super Admin), klik "Tambah Pengguna", isi data dan pilih role yang sesuai.',
            },
            {
                q: 'Bagaimana cara backup database?',
                a: 'Buka menu Pengaturan > Backup Database (khusus Super Admin), klik "Backup Sekarang" untuk membuat backup manual.',
            },
        ],
    },
];

const shortcuts = [
    { keys: ['Ctrl', 'K'], description: 'Pencarian cepat' },
    { keys: ['Esc'], description: 'Tutup dialog/modal' },
    { keys: ['Enter'], description: 'Konfirmasi/submit form' },
];

function FaqItem({ item }: { item: { q: string; a: string } }) {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
            <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg border px-4 py-3 text-left text-sm font-medium hover:bg-muted/50">
                {item.q}
                <ChevronDown
                    className={cn(
                        'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200',
                        isOpen && 'rotate-180'
                    )}
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="px-4 pb-3 pt-2 text-sm text-muted-foreground">
                {item.a}
            </CollapsibleContent>
        </Collapsible>
    );
}

function FaqSection({ section }: { section: typeof helpSections[0] }) {
    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <section.icon className="h-5 w-5 text-primary" />
                    {section.title}
                </CardTitle>
                <CardDescription>{section.description}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-2 pt-0">
                {section.items.map((item, idx) => (
                    <FaqItem key={idx} item={item} />
                ))}
            </CardContent>
        </Card>
    );
}

export default function Help() {
    const { app, tenant } = usePage<PageProps>().props;
    const schoolName = tenant?.name || app.name;

    return (
        <MainLayout title="Bantuan">
            <Head title="Bantuan" />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10">
                        <HelpCircle className="h-6 w-6 text-primary" />
                    </div>
                    <div>
                        <h1 className="text-2xl font-bold">Pusat Bantuan</h1>
                        <p className="text-muted-foreground">
                            Panduan penggunaan School Management System
                        </p>
                    </div>
                </div>

                {/* Quick Info */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <GraduationCap className="h-5 w-5" />
                            Tentang Aplikasi
                        </CardTitle>
                        <CardDescription>
                            Informasi sistem dan kontak dukungan
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-3">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Versi Aplikasi</span>
                                    <Badge variant="secondary">{app.version || '1.0.0'}</Badge>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Sekolah</span>
                                    <span className="font-medium">{schoolName}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Environment</span>
                                    <Badge variant="outline" className="font-mono text-xs">
                                        {app.env || 'production'}
                                    </Badge>
                                </div>
                            </div>
                            <div className="space-y-3">
                                <div className="flex items-center gap-2 text-sm">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <span>support@sms-app.id</span>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <span>+62 21 1234 5678</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Keyboard Shortcuts */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Keyboard className="h-5 w-5" />
                            Pintasan Keyboard
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-2 md:grid-cols-3">
                            {shortcuts.map((shortcut, idx) => (
                                <div key={idx} className="flex items-center justify-between rounded-lg border p-3">
                                    <span className="text-sm text-muted-foreground">{shortcut.description}</span>
                                    <div className="flex gap-1">
                                        {shortcut.keys.map((key, keyIdx) => (
                                            <kbd
                                                key={keyIdx}
                                                className="rounded bg-muted px-2 py-1 text-xs font-mono"
                                            >
                                                {key}
                                            </kbd>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* FAQ Sections */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold">Pertanyaan Umum</h2>
                    {helpSections.map((section, idx) => (
                        <FaqSection key={idx} section={section} />
                    ))}
                </div>

                {/* Footer */}
                <div className="rounded-lg border bg-muted/50 p-4 text-center text-sm text-muted-foreground">
                    <p>
                        Butuh bantuan lebih lanjut? Hubungi tim support kami melalui email atau telepon
                        yang tertera di atas.
                    </p>
                </div>
            </div>
        </MainLayout>
    );
}
