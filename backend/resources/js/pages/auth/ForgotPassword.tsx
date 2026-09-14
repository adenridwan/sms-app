import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { AlertCircle, ArrowLeft, KeyRound, Loader2 } from 'lucide-react';
import { AuthPageFrame } from '@/components/auth/AuthPageFrame';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { authApi } from '@/services/api';

export default function ForgotPassword() {
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault(); setSubmitting(true); setError(null);
        try { await authApi.loginWithOtp({ email, code }); router.visit('/dashboard'); }
        catch (err) { setError(axios.isAxiosError(err) && err.response ? (err.response.data?.message ?? 'Kode akses tidak valid atau sudah kedaluwarsa.') : 'Tidak dapat terhubung ke server. Silakan coba lagi.'); }
        finally { setSubmitting(false); }
    };

    return <>
        <Head title="Masuk dengan Kode Akses" />
        <AuthPageFrame badge="Akses akun yang aman" title="Kembali mengakses ruang belajar Anda." description="Administrator sekolah dapat memberikan kode akses sementara agar Anda dapat masuk kembali dengan aman." footer={<><p className="text-sm font-medium">Kode akses sekali pakai</p><p className="mt-1 text-xs leading-5 text-blue-100">Berlaku 15 menit dan hanya dapat digunakan untuk satu kali masuk.</p></>}>
            <Card className="w-full max-w-md border-0 bg-transparent shadow-none">
                <CardHeader className="space-y-1 text-left">
                    <Link href="/login" className="mb-6 inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Kembali masuk</Link>
                    <div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-8 ring-blue-50/60 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/5"><KeyRound className="h-6 w-6" /></div>
                    <CardTitle className="text-3xl tracking-tight">Masuk dengan kode akses</CardTitle>
                    <CardDescription className="pt-1 leading-6">Gunakan kode sekali-pakai dari administrator untuk mengakses akun Anda.</CardDescription>
                </CardHeader>
                <form onSubmit={submit}>
                    <CardContent className="space-y-4">
                        <Alert><KeyRound className="h-4 w-4" /><AlertTitle>Hubungi administrator sekolah</AlertTitle><AlertDescription>Aplikasi ini tidak mengirim tautan reset password lewat email. Minta <strong>kode akses</strong> kepada administrator sekolah, lalu masukkan email dan kode tersebut di bawah.</AlertDescription></Alert>
                        {error && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{error}</AlertDescription></Alert>}
                        <div className="space-y-2"><Label htmlFor="email">Email</Label><Input id="email" type="email" placeholder="nama@sekolah.sch.id" value={email} onChange={(e) => setEmail(e.target.value)} autoComplete="username" className="h-11" /></div>
                        <div className="space-y-2"><Label htmlFor="otp_code">Kode Akses</Label><Input id="otp_code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} placeholder="123456" value={code} onChange={(e) => setCode(e.target.value.replace(/\D/g, '').slice(0, 6))} className="h-11 text-center font-mono text-xl tracking-[0.4em]" /></div>
                    </CardContent>
                    <CardFooter className="flex flex-col space-y-4 pt-2"><Button type="submit" className="h-11 w-full bg-blue-600 text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700" disabled={submitting || !email || code.length !== 6}>{submitting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Masuk dengan Kode</Button><p className="text-center text-sm text-muted-foreground">Ingat password Anda? <Link href="/login" className="text-primary hover:underline">Kembali ke halaman masuk</Link></p></CardFooter>
                </form>
            </Card>
        </AuthPageFrame>
    </>;
}
