import { Head, Link, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, ArrowRight, GraduationCap, Loader2, ShieldCheck } from 'lucide-react';
import { authApi } from '@/services/api';

interface Branding {
    name: string;
    logo_url: string | null;
}

interface PageProps {
    branding: Branding | null;
}

function BrandLogo({ branding, size = 'md', className = '' }: { branding: Branding | null; size?: 'sm' | 'md'; className?: string }) {
    const sizeClasses = size === 'sm' ? 'h-5 w-5' : 'h-6 w-6';
    const containerClasses = size === 'sm'
        ? 'flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-cyan-200 to-blue-100 text-blue-700 overflow-hidden'
        : 'flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25 overflow-hidden';

    const [imgError, setImgError] = useState(false);

    if (branding?.logo_url && !imgError) {
        return (
            <div className={`${containerClasses} ${className}`}>
                <img
                    src={branding.logo_url}
                    alt={branding.name}
                    className="h-full w-full object-cover"
                    onError={() => setImgError(true)}
                />
            </div>
        );
    }

    return (
        <div className={`${containerClasses} ${className}`}>
            <GraduationCap className={sizeClasses} />
        </div>
    );
}

function FormLogo({ branding }: { branding: Branding | null }) {
    const [imgError, setImgError] = useState(false);

    if (branding?.logo_url && !imgError) {
        return (
            <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 ring-8 ring-blue-50/60 dark:bg-blue-500/10 dark:ring-blue-500/5 overflow-hidden">
                <img
                    src={branding.logo_url}
                    alt={branding.name}
                    className="h-full w-full object-cover"
                    onError={() => setImgError(true)}
                />
            </div>
        );
    }

    return (
        <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-8 ring-blue-50/60 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/5">
            <GraduationCap className="h-6 w-6" />
        </div>
    );
}

export default function Login() {
    const { branding } = usePage<PageProps>().props;
    const [data, setData] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const brandName = branding?.name ?? 'SMS Enterprise';

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setFormError(null);

        try {
            await authApi.login(data);
            router.visit('/dashboard');
        } catch (error) {
            if (axios.isAxiosError(error) && error.response) {
                const { message, errors: validationErrors } = error.response.data ?? {};

                if (validationErrors) {
                    const fieldErrors: Record<string, string> = {};
                    Object.entries(validationErrors as Record<string, string[]>).forEach(([field, messages]) => {
                        fieldErrors[field] = messages[0];
                    });
                    setErrors(fieldErrors);
                } else {
                    setFormError(message ?? 'Email atau password salah.');
                }
            } else {
                setFormError('Tidak dapat terhubung ke server. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Login" />

            <div className="relative isolate min-h-screen overflow-hidden bg-slate-950 p-4 sm:p-6 lg:p-8">
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.35),_transparent_33%),radial-gradient(circle_at_bottom_right,_rgba(20,184,166,0.2),_transparent_38%)]" />
                <div className="absolute -left-24 top-1/4 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl" />
                <div className="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl" />

                <div className="relative mx-auto grid min-h-[calc(100vh-2rem)] max-w-6xl overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl shadow-slate-950/40 backdrop-blur-sm lg:grid-cols-[1.08fr_0.92fr]">
                    <section className="relative hidden min-h-full overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-slate-950 p-10 text-white lg:flex lg:flex-col">
                        <div className="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:42px_42px]" />
                        <div className="relative flex items-center gap-3 text-lg font-semibold tracking-tight">
                            <BrandLogo branding={branding} size="md" />
                            {brandName}
                        </div>

                        <div className="relative my-auto max-w-md">
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium">
                                <ShieldCheck className="h-3.5 w-3.5" /> Sistem manajemen sekolah
                            </span>
                            <h1 className="mt-6 text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">
                                Semua aktivitas sekolah, dalam satu tempat.
                            </h1>
                            <p className="mt-5 max-w-sm text-base leading-7 text-blue-100">
                                Kelola data akademik, kehadiran, keuangan, dan kegiatan sekolah dengan lebih terhubung.
                            </p>
                        </div>

                        <div className="relative flex items-center gap-4 rounded-2xl border border-white/15 bg-slate-950/20 p-4 backdrop-blur">
                            <BrandLogo branding={branding} size="sm" />
                            <div>
                                <p className="text-sm font-medium">Ruang belajar yang lebih teratur</p>
                                <p className="mt-0.5 text-xs text-blue-100">Aman, cepat, dan mudah diakses.</p>
                            </div>
                        </div>
                    </section>

                    <section className="flex items-center justify-center bg-background/95 px-5 py-10 sm:px-10 lg:px-12 dark:bg-slate-950/95">
                <Card className="w-full max-w-md border-0 bg-transparent shadow-none">
                    <CardHeader className="space-y-1 text-left">
                        <FormLogo branding={branding} />
                        <CardTitle className="text-3xl tracking-tight">Selamat datang</CardTitle>
                        <CardDescription className="pt-1 leading-6">
                            Masuk dengan akun sekolah Anda untuk melanjutkan.
                        </CardDescription>
                    </CardHeader>

                    <form onSubmit={submit}>
                        <CardContent className="space-y-4">
                            {formError && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{formError}</AlertDescription>
                                </Alert>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="nama@sekolah.sch.id"
                                    value={data.email}
                                    onChange={(e) => setData({ ...data, email: e.target.value })}
                                    className={`h-11 ${errors.email ? 'border-destructive' : ''}`}
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between">
                                    <Label htmlFor="password">Password</Label>
                                    <Link
                                        href="/forgot-password"
                                        className="text-sm text-primary hover:underline"
                                    >
                                        Lupa password?
                                    </Link>
                                </div>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="••••••••"
                                    value={data.password}
                                    onChange={(e) => setData({ ...data, password: e.target.value })}
                                    className={`h-11 ${errors.password ? 'border-destructive' : ''}`}
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">{errors.password}</p>
                                )}
                            </div>
                        </CardContent>

                        <CardFooter className="flex flex-col space-y-4 pt-2">
                            <Button type="submit" className="h-11 w-full gap-2 bg-blue-600 text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700" disabled={processing}>
                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Masuk
                                {!processing && <ArrowRight className="h-4 w-4" />}
                            </Button>

                            <p className="text-center text-sm text-muted-foreground">
                                Belum punya akun?{' '}
                                <Link href="/register" className="text-primary hover:underline">
                                    Daftar sekarang
                                </Link>
                            </p>
                            <p className="text-center text-xs text-muted-foreground">
                                Dilindungi dengan autentikasi aman.
                            </p>
                        </CardFooter>
                    </form>
                </Card>
                    </section>
                </div>
            </div>
        </>
    );
}
