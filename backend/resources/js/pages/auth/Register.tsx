import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { AlertCircle, ArrowLeft, Loader2, MailCheck, ShieldCheck, UserPlus } from 'lucide-react';
import { AuthPageFrame } from '@/components/auth/AuthPageFrame';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { authApi } from '@/services/api';

const Field = ({ id, label, type = 'text', placeholder, value, onChange, error }: { id: string; label: string; type?: string; placeholder: string; value: string; onChange: (value: string) => void; error?: string }) => (
    <div className="space-y-2"><Label htmlFor={id}>{label}</Label><Input id={id} type={type} placeholder={placeholder} value={value} onChange={(e) => onChange(e.target.value)} className={`h-11 ${error ? 'border-destructive' : ''}`} />{error && <p className="text-sm text-destructive">{error}</p>}</div>
);

export default function Register() {
    const [data, setData] = useState({ username: '', first_name: '', last_name: '', email: '', phone: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const [pendingEmail, setPendingEmail] = useState<string | null>(null);
    const [activationCode, setActivationCode] = useState('');
    const [activationError, setActivationError] = useState<string | null>(null);
    const [activating, setActivating] = useState(false);
    const change = (key: keyof typeof data) => (value: string) => setData({ ...data, [key]: value });

    const submit = async (e: FormEvent) => {
        e.preventDefault(); setProcessing(true); setErrors({}); setFormError(null);
        try { await authApi.register(data); setPendingEmail(data.email); }
        catch (error) {
            if (axios.isAxiosError(error) && error.response) {
                const { message, errors: validationErrors } = error.response.data ?? {};
                if (validationErrors) { const next: Record<string, string> = {}; Object.entries(validationErrors as Record<string, string[]>).forEach(([field, messages]) => { next[field] = messages[0]; }); setErrors(next); }
                else setFormError(message ?? 'Registrasi gagal. Silakan coba lagi.');
            } else setFormError('Tidak dapat terhubung ke server. Silakan coba lagi.');
        } finally { setProcessing(false); }
    };

    const activate = async (e: FormEvent) => {
        e.preventDefault(); if (!pendingEmail) return; setActivating(true); setActivationError(null);
        try { await authApi.activate({ email: pendingEmail, code: activationCode }); router.visit('/login'); }
        catch (error) {
            if (axios.isAxiosError(error) && error.response) { const { message, errors: validationErrors } = error.response.data ?? {}; const firstError = validationErrors ? (Object.values(validationErrors as Record<string, string[]>)[0] ?? [])[0] : null; setActivationError(firstError ?? message ?? 'Aktivasi gagal. Silakan coba lagi.'); }
            else setActivationError('Tidak dapat terhubung ke server. Silakan coba lagi.');
        } finally { setActivating(false); }
    };

    if (pendingEmail) return <>
        <Head title="Aktivasi Akun" />
        <AuthPageFrame badge="Langkah terakhir" title="Aktifkan akun Anda dengan aman." description="Pendaftaran telah tersimpan. Minta kode aktivasi dari administrator sekolah untuk menyelesaikan proses." footer={<><p className="text-sm font-medium">Hampir selesai</p><p className="mt-1 text-xs leading-5 text-blue-100">Kode OTP berlaku 15 menit dan hanya dapat digunakan satu kali.</p></>}>
            <Card className="w-full max-w-md border-0 bg-transparent shadow-none"><CardHeader className="space-y-1 text-left"><Link href="/login" className="mb-6 inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Kembali masuk</Link><div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-600 ring-8 ring-amber-50/60 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/5"><MailCheck className="h-6 w-6" /></div><CardTitle className="text-3xl tracking-tight">Menunggu aktivasi</CardTitle><CardDescription className="pt-1 leading-6">Pendaftaran untuk <strong>{pendingEmail}</strong> berhasil disimpan.</CardDescription></CardHeader>
                <form onSubmit={activate}><CardContent className="space-y-4"><Alert><ShieldCheck className="h-4 w-4" /><AlertTitle>Hubungi administrator sekolah</AlertTitle><AlertDescription>Akun belum aktif. Minta <strong>kode OTP</strong> kepada administrator melalui menu <em>Pengaturan → Keamanan Login</em>.</AlertDescription></Alert>{activationError && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{activationError}</AlertDescription></Alert>}<div className="space-y-2"><Label htmlFor="activation_code">Kode OTP</Label><Input id="activation_code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} placeholder="123456" value={activationCode} onChange={(e) => setActivationCode(e.target.value.replace(/\D/g, '').slice(0, 6))} className="h-11 text-center font-mono text-xl tracking-[0.4em]" /></div></CardContent><CardFooter className="flex flex-col space-y-4 pt-2"><Button type="submit" className="h-11 w-full bg-blue-600 text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700" disabled={activating || activationCode.length !== 6}>{activating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Aktifkan Akun</Button></CardFooter></form>
            </Card>
        </AuthPageFrame>
    </>;

    return <>
        <Head title="Daftar Akun Baru" />
        <AuthPageFrame badge="Mulai bersama sekolah Anda" title="Satu akun untuk semua kebutuhan sekolah." description="Buat akun Anda, lalu aktifkan dengan kode dari administrator untuk mulai terhubung." footer={<div className="space-y-3">{['Lengkapi identitas akun', 'Dapatkan kode aktivasi', 'Masuk dan mulai beraktivitas'].map((step, index) => <div key={step} className="flex items-center gap-3 text-sm"><span className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white/15 text-xs font-semibold">{index + 1}</span>{step}</div>)}</div>}>
            <Card className="w-full max-w-md border-0 bg-transparent shadow-none"><CardHeader className="space-y-1 text-left"><Link href="/login" className="mb-6 inline-flex w-fit items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Kembali masuk</Link><div className="mb-5 flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600 ring-8 ring-blue-50/60 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/5"><UserPlus className="h-6 w-6" /></div><CardTitle className="text-3xl tracking-tight">Buat akun baru</CardTitle><CardDescription className="pt-1 leading-6">Lengkapi data berikut. Administrator akan mengaktifkan akun Anda sebelum dapat digunakan.</CardDescription></CardHeader>
                <form onSubmit={submit}><CardContent className="space-y-4">{formError && <Alert variant="destructive"><AlertCircle className="h-4 w-4" /><AlertDescription>{formError}</AlertDescription></Alert>}<div className="grid grid-cols-2 gap-4"><Field id="first_name" label="Nama Depan" placeholder="John" value={data.first_name} onChange={change('first_name')} error={errors.first_name} /><Field id="last_name" label="Nama Belakang" placeholder="Doe" value={data.last_name} onChange={change('last_name')} error={errors.last_name} /></div><Field id="username" label="Username" placeholder="johndoe" value={data.username} onChange={change('username')} error={errors.username} /><Field id="email" label="Email" type="email" placeholder="nama@sekolah.sch.id" value={data.email} onChange={change('email')} error={errors.email} /><Field id="phone" label="No. Telepon" type="tel" placeholder="08123456789" value={data.phone} onChange={change('phone')} error={errors.phone} /><Field id="password" label="Password" type="password" placeholder="••••••••" value={data.password} onChange={change('password')} error={errors.password} /><Field id="password_confirmation" label="Konfirmasi Password" type="password" placeholder="••••••••" value={data.password_confirmation} onChange={change('password_confirmation')} error={errors.password_confirmation} /></CardContent><CardFooter className="flex flex-col space-y-4 pt-2"><Button type="submit" className="h-11 w-full bg-blue-600 text-white shadow-lg shadow-blue-500/20 hover:bg-blue-700" disabled={processing}>{processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Daftar</Button><p className="text-center text-sm text-muted-foreground">Sudah punya akun? <Link href="/login" className="text-primary hover:underline">Masuk di sini</Link></p></CardFooter></form>
            </Card>
        </AuthPageFrame>
    </>;
}
