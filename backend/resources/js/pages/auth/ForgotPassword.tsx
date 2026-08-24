import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import { authApi } from '@/services/api';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            await authApi.loginWithOtp({ email, code });
            router.visit('/dashboard');
        } catch (err) {
            if (axios.isAxiosError(err) && err.response) {
                setError(err.response.data?.message ?? 'Kode akses tidak valid atau sudah kedaluwarsa.');
            } else {
                setError('Tidak dapat terhubung ke server. Silakan coba lagi.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Head title="Lupa Password" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 p-4 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                <Card className="w-full max-w-md">
                    <CardHeader className="space-y-1 text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <KeyRound className="h-7 w-7" />
                        </div>
                        <CardTitle className="text-2xl">Lupa Password</CardTitle>
                        <CardDescription>
                            Masuk memakai kode akses sekali-pakai, bukan reset password
                        </CardDescription>
                    </CardHeader>

                    <form onSubmit={submit}>
                        <CardContent className="space-y-4">
                            <Alert>
                                <ShieldCheck className="h-4 w-4" />
                                <AlertTitle>Hubungi administrator sekolah</AlertTitle>
                                <AlertDescription>
                                    Aplikasi ini tidak mengirim tautan reset password lewat email. Minta{' '}
                                    <strong>kode akses</strong> kepada administrator sekolah — kode berlaku
                                    15 menit dan hanya bisa dipakai sekali. Masukkan email dan kode tersebut
                                    di bawah untuk langsung masuk, lalu ganti password Anda dari halaman
                                    Profil.
                                </AlertDescription>
                            </Alert>

                            {error && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertDescription>{error}</AlertDescription>
                                </Alert>
                            )}

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="nama@sekolah.sch.id"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    autoComplete="username"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="otp_code">Kode Akses</Label>
                                <Input
                                    id="otp_code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    maxLength={6}
                                    placeholder="123456"
                                    value={code}
                                    onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                                    className="text-center font-mono text-xl tracking-[0.4em]"
                                />
                            </div>
                        </CardContent>

                        <CardFooter className="flex flex-col space-y-4">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={submitting || !email || code.length !== 6}
                            >
                                {submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Masuk dengan Kode
                            </Button>

                            <p className="text-center text-sm text-muted-foreground">
                                Ingat password Anda?{' '}
                                <Link href="/login" className="text-primary hover:underline">
                                    Kembali ke halaman masuk
                                </Link>
                            </p>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
