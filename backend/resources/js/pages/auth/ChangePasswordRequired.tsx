import { Head, router, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertCircle, KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import { authApi } from '@/services/api';
import type { PageProps } from '@/types';

export default function ChangePasswordRequired() {
    const { auth } = usePage<PageProps>().props;

    const [data, setData] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [formError, setFormError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const submit = async (e: FormEvent) => {
        e.preventDefault();

        if (data.password !== data.password_confirmation) {
            setErrors({ password_confirmation: 'Konfirmasi password tidak cocok.' });
            return;
        }

        setProcessing(true);
        setErrors({});
        setFormError(null);

        try {
            await authApi.changePassword(data);
            // Reload penuh: shared prop must_change_password akan hilang dari server
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
                    setFormError(message ?? 'Gagal mengganti password.');
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
            <Head title="Ganti Password" />

            <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 p-4 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900">
                <Card className="w-full max-w-md">
                    <CardHeader className="space-y-1 text-center">
                        <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <ShieldCheck className="h-7 w-7" />
                        </div>
                        <CardTitle className="text-2xl">Ganti Password Anda</CardTitle>
                        <CardDescription>
                            {auth.user?.full_name ? `Halo, ${auth.user.full_name}. ` : ''}
                            Demi keamanan, password awal/hasil reset wajib diganti sebelum
                            melanjutkan.
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
                                <Label htmlFor="current_password">Password Saat Ini *</Label>
                                <Input
                                    id="current_password"
                                    type="password"
                                    placeholder="Password awal / hasil reset admin"
                                    value={data.current_password}
                                    onChange={(e) => setData({ ...data, current_password: e.target.value })}
                                    className={errors.current_password ? 'border-destructive' : ''}
                                />
                                {errors.current_password && (
                                    <p className="text-sm text-destructive">{errors.current_password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password">Password Baru *</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    placeholder="Minimal 8 karakter"
                                    value={data.password}
                                    onChange={(e) => setData({ ...data, password: e.target.value })}
                                    className={errors.password ? 'border-destructive' : ''}
                                />
                                {errors.password && (
                                    <p className="text-sm text-destructive">{errors.password}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="password_confirmation">Konfirmasi Password Baru *</Label>
                                <Input
                                    id="password_confirmation"
                                    type="password"
                                    placeholder="Ulangi password baru"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData({ ...data, password_confirmation: e.target.value })}
                                    className={errors.password_confirmation ? 'border-destructive' : ''}
                                />
                                {errors.password_confirmation && (
                                    <p className="text-sm text-destructive">{errors.password_confirmation}</p>
                                )}
                            </div>
                        </CardContent>

                        <CardFooter>
                            <Button type="submit" className="w-full" disabled={processing}>
                                {processing ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <KeyRound className="mr-2 h-4 w-4" />
                                )}
                                Ganti Password & Lanjutkan
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </>
    );
}
