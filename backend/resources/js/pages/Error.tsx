import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    ArrowLeft,
    Home,
    FileQuestion,
    ShieldAlert,
    ServerCrash,
    Construction,
    TimerOff,
    Gauge,
    LockKeyhole,
} from 'lucide-react';

interface ErrorProps {
    status?: number;
}

const errorContent: Record<
    number,
    { title: string; description: string; icon: React.ElementType }
> = {
    401: {
        title: 'Tidak Terautentikasi',
        description: 'Silakan masuk terlebih dahulu untuk mengakses halaman ini.',
        icon: LockKeyhole,
    },
    403: {
        title: 'Akses Ditolak',
        description: 'Anda tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator jika Anda merasa ini adalah kesalahan.',
        icon: ShieldAlert,
    },
    404: {
        title: 'Halaman Tidak Ditemukan',
        description: 'Halaman yang Anda cari tidak ada, telah dipindahkan, atau masih dalam tahap pengembangan.',
        icon: FileQuestion,
    },
    419: {
        title: 'Sesi Berakhir',
        description: 'Sesi Anda telah berakhir. Silakan muat ulang halaman dan coba lagi.',
        icon: TimerOff,
    },
    429: {
        title: 'Terlalu Banyak Permintaan',
        description: 'Anda mengirim terlalu banyak permintaan. Mohon tunggu sebentar lalu coba lagi.',
        icon: Gauge,
    },
    500: {
        title: 'Kesalahan Server',
        description: 'Terjadi kesalahan pada server kami. Tim kami sedang menanganinya, silakan coba beberapa saat lagi.',
        icon: ServerCrash,
    },
    503: {
        title: 'Sedang Dalam Perbaikan',
        description: 'Sistem sedang dalam pemeliharaan. Silakan kembali beberapa saat lagi.',
        icon: Construction,
    },
};

const fallbackContent = {
    title: 'Segera Hadir',
    description: 'Halaman ini masih dalam tahap pengembangan. Nantikan pembaruan selanjutnya!',
    icon: Construction,
};

export default function ErrorPage({ status }: ErrorProps) {
    const { auth } = usePage().props as { auth?: { user?: unknown } };
    const content = (status && errorContent[status]) || fallbackContent;
    const Icon = content.icon;
    const homeHref = auth?.user ? '/dashboard' : '/login';

    return (
        <>
            <Head title={content.title} />
            <div className="relative flex min-h-svh items-center justify-center overflow-hidden bg-background p-6">
                {/* Decorative background */}
                <div className="pointer-events-none absolute inset-0">
                    <div className="absolute -left-32 -top-32 h-96 w-96 rounded-full bg-primary/10 blur-3xl" />
                    <div className="absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-primary/10 blur-3xl" />
                </div>

                <div className="relative w-full max-w-md text-center">
                    {/* Icon */}
                    <div className="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-2xl bg-primary/10 ring-8 ring-primary/5">
                        <Icon className="h-10 w-10 text-primary" />
                    </div>

                    {/* Status code */}
                    {status && (
                        <p className="bg-gradient-to-r from-primary to-primary/50 bg-clip-text text-7xl font-extrabold tracking-tight text-transparent">
                            {status}
                        </p>
                    )}

                    <h1 className="mt-3 text-2xl font-bold tracking-tight">{content.title}</h1>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        {content.description}
                    </p>

                    {/* Actions */}
                    <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Button
                            variant="outline"
                            className="w-full sm:w-auto"
                            onClick={() => window.history.back()}
                        >
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Kembali
                        </Button>
                        <Button asChild className="w-full sm:w-auto">
                            <Link href={homeHref}>
                                <Home className="mr-2 h-4 w-4" />
                                Ke Beranda
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
