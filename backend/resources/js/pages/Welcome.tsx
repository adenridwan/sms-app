import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { GraduationCap, ArrowRight, CheckCircle } from 'lucide-react';

export default function Welcome() {
    const features = [
        'Manajemen data siswa & guru',
        'Sistem absensi digital',
        'Penilaian & rapor otomatis',
        'Pembayaran SPP online',
        'Perpustakaan digital',
        'Laporan real-time',
    ];

    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                {/* Header */}
                <header className="container mx-auto flex items-center justify-between px-4 py-6">
                    <div className="flex items-center gap-2">
                        <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <GraduationCap className="h-6 w-6" />
                        </div>
                        <span className="text-xl font-bold">SMS Enterprise</span>
                    </div>
                    <nav className="flex items-center gap-4">
                        <Link href="/login">
                            <Button variant="ghost">Masuk</Button>
                        </Link>
                        <Link href="/register">
                            <Button>Daftar</Button>
                        </Link>
                    </nav>
                </header>

                {/* Hero */}
                <main className="container mx-auto px-4 py-20 text-center">
                    <div className="mx-auto max-w-3xl">
                        <h1 className="text-4xl font-bold tracking-tight text-gray-900 dark:text-white sm:text-6xl">
                            School Management System
                            <span className="text-primary"> Enterprise</span>
                        </h1>
                        <p className="mt-6 text-lg leading-8 text-gray-600 dark:text-gray-300">
                            Solusi lengkap untuk manajemen sekolah modern. Kelola siswa, guru, absensi,
                            nilai, keuangan, dan perpustakaan dalam satu platform terintegrasi.
                        </p>
                        <div className="mt-10 flex items-center justify-center gap-4">
                            <Link href="/login">
                                <Button size="lg" className="gap-2">
                                    Mulai Sekarang
                                    <ArrowRight className="h-4 w-4" />
                                </Button>
                            </Link>
                            <Button variant="outline" size="lg">
                                Lihat Demo
                            </Button>
                        </div>
                    </div>

                    {/* Features */}
                    <div className="mx-auto mt-20 max-w-4xl">
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Fitur Lengkap
                        </h2>
                        <div className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map((feature) => (
                                <div
                                    key={feature}
                                    className="flex items-center gap-3 rounded-lg border bg-white/50 p-4 backdrop-blur dark:bg-gray-800/50"
                                >
                                    <CheckCircle className="h-5 w-5 text-green-500" />
                                    <span className="text-sm font-medium">{feature}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </main>

                {/* Footer */}
                <footer className="container mx-auto px-4 py-8 text-center text-sm text-gray-500">
                    <p>&copy; 2024 SMS Enterprise. All rights reserved.</p>
                </footer>
            </div>
        </>
    );
}
