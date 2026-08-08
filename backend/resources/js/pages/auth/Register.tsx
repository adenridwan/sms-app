import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { AlertCircle, GraduationCap, Loader2, MailCheck, ShieldCheck } from 'lucide-react';
import { authApi } from '@/services/api';

export default function Register() {
    const [data, setData] = useState({
        username: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    // Diisi setelah pendaftaran berhasil — menandai layar berpindah ke tahap
    // aktivasi. Akun sudah ada di database tapi berstatus `pending`.
    const [pendingEmail, setPendingEmail] = useState<string | null>(null);
    const [activationCode, setActivationCode] = useState('');
    const [activationError, setActivationError] = useState<string | null>(null);
    const [activating, setActivating] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});
        setFormError(null);

        try {
            await authApi.register(data);
            setPendingEmail(data.email);
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
                    setFormError(message ?? 'Registrasi gagal. Silakan coba lagi.');
                }
            } else {
                setFormError('Tidak dapat terhubung ke server. Silakan coba lagi.');
            }
        } finally {
            setProcessing(false);
        }
    };

    const activate = async (e: FormEvent) => {
        e.preventDefault();
        if (!pendingEmail) return;

        setActivating(true);
        setActivationError(null);

        try {
            await authApi.activate({ email: pendingEmail, code: activationCode });
            // Aktivasi tidak menerbitkan token — akun sudah aktif, masuk seperti
            // biasa memakai password yang tadi dibuat sendiri oleh pendaftar.
            router.visit('/login');
        } catch (error) {
            if (axios.isAxiosError(error) && error.response) {
                const { message, errors: validationErrors } = error.response.data ?? {};
                const firstError = validationErrors
                    ? (Object.values(validationErrors as Record<string, string[]>)[0] ?? [])[0]
                    : null;
                setActivationError(firstError ?? message ?? 'Aktivasi gagal. Silakan coba lagi.');
            } else {
                setActivationError('Tidak dapat terhubung ke server. Silakan coba lagi.');
            }
        } finally {
            setActivating(false);
        }
    };

    if (pendingEmail) {
        return (
            <>
                <Head title="Aktivasi Akun" />

                <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 p-4 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                    <Card className="w-full max-w-md">
                        <CardHeader className="space-y-1 text-center">
                            <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-amber-500 text-white">
                                <MailCheck className="h-7 w-7" />
                            </div>
                            <CardTitle className="text-2xl">Menunggu Aktivasi</CardTitle>
                            <CardDescription>
                                Pendaftaran untuk <strong>{pendingEmail}</strong> berhasil disimpan.
                            </CardDescription>
                        </CardHeader>

                        <form onSubmit={activate}>
                            <CardContent className="space-y-4">
                                <Alert>
                                    <ShieldCheck className="h-4 w-4" />
                                    <AlertTitle>Hubungi administrator sekolah</AlertTitle>
                                    <AlertDescription>
                                        Akun Anda belum aktif dan belum bisa dipakai masuk. Minta{' '}
                                        <strong>kode OTP</strong> kepada administrator sekolah — kode dibuat
                                        dari menu <em>Pengaturan → Keamanan Login</em>, berlaku 15 menit dan
                                        hanya bisa dipakai sekali. Masukkan kode tersebut di bawah ini.
                                    </AlertDescription>
                                </Alert>

                                {activationError && (
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>{activationError}</AlertDescription>
                                    </Alert>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="activation_code">Kode OTP</Label>
                                    <Input
                                        id="activation_code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        placeholder="123456"
                                        value={activationCode}
                                        onChange={(e) =>
                                            setActivationCode(e.target.value.replace(/\D/g, '').slice(0, 6))
                                        }
                                        className="text-center font-mono text-xl tracking-[0.4em]"
                                    />
                                </div>
                            </CardContent>

                            <CardFooter className="flex flex-col space-y-4">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={activating || activationCode.length !== 6}
                                >
                                    {activating && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    Aktifkan Akun
                                </Button>

                                <p className="text-center text-sm text-muted-foreground">
                                    Sudah dapat kode nanti saja?{' '}
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

    return (
        <>
            <Head title="Daftar" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 p-4 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                <Card className="w-full max-w-md">
                    <CardHeader className="space-y-1 text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <GraduationCap className="h-7 w-7" />
                        </div>
                        <CardTitle className="text-2xl">Daftar Akun Baru</CardTitle>
                        <CardDescription>
                            Isi formulir di bawah untuk membuat akun. Akun baru berstatus menunggu
                            aktivasi — Anda perlu kode OTP dari administrator sekolah sebelum bisa masuk.
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

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="first_name">Nama Depan</Label>
                                    <Input
                                        id="first_name"
                                        type="text"
                                        placeholder="John"
                                        value={data.first_name}
                                        onChange={(e) => setData({ ...data, first_name: e.target.value })}
                                        className={errors.first_name ? 'border-destructive' : ''}
                                    />
                                    {errors.first_name && (
                                        <p className="text-sm text-destructive">{errors.first_name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="last_name">Nama Belakang</Label>
                                    <Input
                                        id="last_name"
                                        type="text"
                                        placeholder="Doe"
                                        value={data.last_name}
                                        onChange={(e) => setData({ ...data, last_name: e.target.value })}
                                        className={errors.last_name ? 'border-destructive' : ''}
                                    />
                                    {errors.last_name && (
                                        <p className="text-sm text-destructive">{errors.last_name}</p>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="username">Username</Label>
                                <Input
                                    id="username"
                                    type="text"
                                    placeholder="johndoe"
                                    value={data.username}
                                    onChange={(e) => setData({ ...data, username: e.target.value })}
                                    className={errors.username ? 'border-destructive' : ''}
                                />
                                {errors.username && (
                                    <p className="text-sm text-destructive">{errors.username}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    placeholder="nama@sekolah.sch.id"
                                    value={data.email}
                                    onChange={(e) => setData({ ...data, email: e.target.value })}
                                    className={errors.email ? 'border-destructive' : ''}
                                />
                                {errors.email && (
                                    <p className="text-sm text-destructive">{errors.email}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="phone">No. Telepon</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    placeholder="08123456789"
                                    value={data.phone}
                                    onChange={(e) => setData({ ...data, phone: e.target.value })}
                                    className={errors.phone ? 'border-destructive' : ''}
                                />
                                {errors.phone && (
                                    <p className="text-sm text-destructive">{errors.phone}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="••••••••"
                                    value={data.password}
                                    onChange={(e) => setData({ ...data, password: e.target.value })}
                                    className={errors.password ? 'border-destructive' : ''}
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">{errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">Konfirmasi Password</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    placeholder="••••••••"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData({ ...data, password_confirmation: e.target.value })}
                                    className={errors.password_confirmation ? 'border-destructive' : ''}
                                />
                                {errors.password_confirmation && (
                                    <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                                )}
                            </div>
                        </CardContent>

                        <CardFooter className="flex flex-col space-y-4">
                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Daftar
                            </Button>

                            <p className="text-center text-sm text-muted-foreground">
                                Sudah punya akun?{' '}
                                <Link href="/login" className="text-primary hover:underline">
                                    Masuk di sini
                                </Link>
                            </p>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
