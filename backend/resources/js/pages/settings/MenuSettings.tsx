import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';
import { Save, RefreshCw, RotateCcw, Lock, Info } from 'lucide-react';
import { menuSettingsApi, type MenuMatrix, type MenuCell } from '@/services/menu';

type CellMap = Record<string, Record<string, MenuCell>>;

export default function MenuSettings() {
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [matrix, setMatrix] = useState<MenuMatrix | null>(null);
    const [cells, setCells] = useState<CellMap>({});
    const [role, setRole] = useState<string>('');

    const fetchMatrix = async () => {
        setLoading(true);
        try {
            const res = await menuSettingsApi.get();
            const data = res.data.data;
            if (data) {
                setMatrix(data);
                setCells(structuredClone(data.cells));
                setRole(data.roles[0]?.name ?? '');
            }
        } catch {
            toast.error('Gagal memuat pengaturan menu');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchMatrix();
    }, []);

    const cell = (roleName: string, key: string): MenuCell =>
        cells[roleName]?.[key] ?? { visible: false, locked: true };

    const toggle = (key: string, next: boolean) => {
        if (!role) return;
        const c = cell(role, key);
        if (c.locked) return; // terkunci — tak bisa diubah
        setCells((prev) => ({
            ...prev,
            [role]: { ...prev[role], [key]: { ...c, visible: next } },
        }));
    };

    // Kembalikan semua menu (yang bisa di-toggle) role ini ke default = tampil.
    const resetRole = () => {
        if (!role) return;
        setCells((prev) => {
            const roleCells = { ...prev[role] };
            for (const key of Object.keys(roleCells)) {
                if (!roleCells[key].locked) {
                    roleCells[key] = { ...roleCells[key], visible: true };
                }
            }
            return { ...prev, [role]: roleCells };
        });
        toast.info('Menu role ini dikembalikan ke default. Klik Simpan untuk menerapkan.');
    };

    const handleSave = async () => {
        setSaving(true);
        try {
            // hidden[role] = key yang bisa di-toggle tapi OFF
            const hidden: Record<string, string[]> = {};
            for (const [roleName, roleCells] of Object.entries(cells)) {
                const off = Object.entries(roleCells)
                    .filter(([, c]) => !c.locked && !c.visible)
                    .map(([key]) => key);
                if (off.length > 0) hidden[roleName] = off;
            }
            await menuSettingsApi.update(hidden);
            toast.success('Pengaturan menu berhasil disimpan');
        } catch {
            toast.error('Gagal menyimpan pengaturan menu');
        } finally {
            setSaving(false);
        }
    };

    const hiddenCount = useMemo(() => {
        if (!role) return 0;
        return Object.values(cells[role] ?? {}).filter((c) => !c.locked && !c.visible).length;
    }, [cells, role]);

    if (loading) {
        return (
            <MainLayout title="Pengaturan Menu">
                <Head title="Pengaturan Menu" />
                <div className="flex h-64 items-center justify-center">
                    <RefreshCw className="h-8 w-8 animate-spin text-muted-foreground" />
                </div>
            </MainLayout>
        );
    }

    if (!matrix) {
        return (
            <MainLayout title="Pengaturan Menu">
                <Head title="Pengaturan Menu" />
                <Card>
                    <CardContent className="py-10 text-center text-muted-foreground">
                        Data tidak tersedia. Pastikan konteks sekolah (tenant) aktif.
                    </CardContent>
                </Card>
            </MainLayout>
        );
    }

    const renderSwitch = (key: string) => {
        const c = cell(role, key);
        return (
            <div className="flex items-center gap-2">
                {c.locked && (
                    <span
                        className="text-muted-foreground"
                        title={c.visible ? 'Wajib tampil (tidak bisa disembunyikan)' : 'Role ini tidak punya akses'}
                    >
                        <Lock className="h-3.5 w-3.5" />
                    </span>
                )}
                <Switch
                    checked={c.visible}
                    disabled={c.locked}
                    onCheckedChange={(v) => toggle(key, v)}
                />
            </div>
        );
    };

    return (
        <MainLayout title="Pengaturan Menu">
            <Head title="Pengaturan Menu" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Pengaturan Menu</h1>
                        <p className="text-muted-foreground">
                            Atur menu mana yang tampil untuk tiap role. Menyembunyikan menu tidak
                            mencabut hak akses — hanya menyembunyikan tautannya.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={resetRole}>
                            <RotateCcw className="mr-2 h-4 w-4" />
                            Reset Role Ini
                        </Button>
                        <Button onClick={handleSave} disabled={saving}>
                            <Save className="mr-2 h-4 w-4" />
                            {saving ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </div>

                {/* Role selector */}
                <Card>
                    <CardHeader>
                        <CardTitle>Pilih Role</CardTitle>
                        <CardDescription>
                            Konfigurasi berlaku per role. Super Admin selalu melihat semua menu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap items-center gap-3">
                            <Select value={role} onValueChange={setRole}>
                                <SelectTrigger className="w-64">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {matrix.roles.map((r) => (
                                        <SelectItem key={r.name} value={r.name}>
                                            {r.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {hiddenCount > 0 && (
                                <Badge variant="secondary">{hiddenCount} menu disembunyikan</Badge>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Menu tree */}
                <Card>
                    <CardHeader>
                        <CardTitle>Daftar Menu</CardTitle>
                        <CardDescription className="flex items-center gap-1.5">
                            <Info className="h-3.5 w-3.5" />
                            Ikon gembok = terkunci (wajib tampil, atau role tanpa akses sehingga tak
                            bisa ditampilkan).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="divide-y">
                        {matrix.tree.map((group) => (
                            <div key={group.key} className="py-1.5">
                                {/* Group row */}
                                <div className="flex items-center justify-between py-1.5">
                                    <span className="font-semibold">{group.title}</span>
                                    {renderSwitch(group.key)}
                                </div>
                                {/* Children */}
                                {group.children.map((child) => (
                                    <div
                                        key={child.key}
                                        className="flex items-center justify-between py-1.5 pl-4"
                                    >
                                        <span className="text-sm text-muted-foreground">
                                            {child.title}
                                        </span>
                                        {renderSwitch(child.key)}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </MainLayout>
    );
}
