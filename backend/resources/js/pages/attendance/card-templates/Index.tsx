import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { motion, type PanInfo } from 'framer-motion';
import { toast } from 'sonner';
import MainLayout from '@/layouts/MainLayout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Slider } from '@/components/ui/slider';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cardTemplateApi } from '@/services/attendance';
import type { CardElementKey, CardLayout } from '@/types/attendance';
import { ArrowLeft, Building2, Camera, QrCode, RotateCcw, Save, Users, GraduationCap } from 'lucide-react';

const CARD_WIDTH_MM = 85.6;
const CARD_HEIGHT_MM = 54;
const CANVAS_SCALE = 4; // px per mm
const CANVAS_WIDTH = CARD_WIDTH_MM * CANVAS_SCALE;
const CANVAS_HEIGHT = CARD_HEIGHT_MM * CANVAS_SCALE;

const TEXT_ELEMENTS: CardElementKey[] = ['schoolName', 'name', 'idNumber', 'subLine'];

const ELEMENT_LABELS: Record<CardElementKey, string> = {
    logo: 'Logo Sekolah',
    schoolName: 'Nama Sekolah',
    photo: 'Foto',
    qr: 'QR Code',
    name: 'Nama',
    idNumber: 'NIS / NIP',
    subLine: 'Kelas / Jabatan',
};

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function sampleContent(type: 'student' | 'teacher', key: CardElementKey): string {
    if (key === 'schoolName') return 'Nama Sekolah Anda';
    if (key === 'name') return type === 'student' ? 'Nama Siswa Contoh' : 'Nama Guru Contoh';
    if (key === 'idNumber') return type === 'student' ? '1234567890' : '198001012010011001';
    if (key === 'subLine') return type === 'student' ? 'Kelas XII IPA 1' : 'Guru Tetap';
    return '';
}

export default function CardTemplateEditor() {
    const [type, setType] = useState<'student' | 'teacher'>('student');
    const [layout, setLayout] = useState<CardLayout | null>(null);
    const [isCustom, setIsCustom] = useState(false);
    const [selectedKey, setSelectedKey] = useState<CardElementKey | null>(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [resetting, setResetting] = useState(false);
    const canvasRef = useRef<HTMLDivElement>(null);

    const fetchLayout = async (targetType: 'student' | 'teacher') => {
        setLoading(true);
        setSelectedKey(null);
        try {
            const response = await cardTemplateApi.get(targetType);
            setLayout(response.data.data?.layout ?? null);
            setIsCustom(response.data.data?.is_custom ?? false);
        } catch {
            toast.error('Gagal memuat template kartu');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchLayout(type);
    }, [type]);

    const updateElement = (key: CardElementKey, patch: Partial<CardLayout['elements'][CardElementKey]>) => {
        setLayout((prev) => {
            if (!prev) return prev;
            return {
                ...prev,
                elements: {
                    ...prev.elements,
                    [key]: { ...prev.elements[key], ...patch },
                },
            };
        });
    };

    const handleDragEnd = (key: CardElementKey, info: PanInfo) => {
        const el = layout?.elements[key];
        if (!el) return;
        const deltaXPercent = (info.offset.x / CANVAS_WIDTH) * 100;
        const deltaYPercent = (info.offset.y / CANVAS_HEIGHT) * 100;
        updateElement(key, {
            x: clamp(el.x + deltaXPercent, 0, 100 - el.width),
            y: clamp(el.y + deltaYPercent, 0, 100 - el.height),
        });
    };

    const handleSave = async () => {
        if (!layout) return;
        setSaving(true);
        try {
            const response = await cardTemplateApi.update(type, layout);
            setLayout(response.data.data?.layout ?? layout);
            setIsCustom(true);
            toast.success('Template kartu berhasil disimpan');
        } catch {
            toast.error('Gagal menyimpan template kartu');
        } finally {
            setSaving(false);
        }
    };

    const handleReset = async () => {
        if (!confirm('Kembalikan template ke posisi default? Perubahan kustom akan hilang.')) return;
        setResetting(true);
        try {
            await cardTemplateApi.reset(type);
            await fetchLayout(type);
            toast.success('Template dikembalikan ke default');
        } catch {
            toast.error('Gagal mereset template');
        } finally {
            setResetting(false);
        }
    };

    const selectedElement = selectedKey && layout ? layout.elements[selectedKey] : null;
    const headerHeight = layout
        ? Math.max(
              layout.elements.logo ? layout.elements.logo.y + layout.elements.logo.height : 0,
              layout.elements.schoolName ? layout.elements.schoolName.y + layout.elements.schoolName.height : 0
          )
        : 0;

    return (
        <MainLayout title="Template Kartu ID">
            <Head title="Template Kartu ID" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center gap-4">
                    <Button variant="ghost" size="icon" asChild>
                        <Link href="/attendance">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div className="flex-1">
                        <h1 className="text-3xl font-bold tracking-tight">Template Kartu ID</h1>
                        <p className="text-muted-foreground">
                            Atur posisi dan ukuran elemen kartu dengan drag-and-drop
                        </p>
                    </div>
                </div>

                <Tabs value={type} onValueChange={(v) => setType(v as 'student' | 'teacher')}>
                    <TabsList>
                        <TabsTrigger value="student" className="gap-2">
                            <Users className="h-4 w-4" />
                            Siswa
                        </TabsTrigger>
                        <TabsTrigger value="teacher" className="gap-2">
                            <GraduationCap className="h-4 w-4" />
                            Guru
                        </TabsTrigger>
                    </TabsList>
                </Tabs>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0">
                                <div>
                                    <CardTitle>Kanvas Kartu</CardTitle>
                                    <CardDescription>
                                        Klik elemen untuk memilih, seret untuk memindah posisi
                                    </CardDescription>
                                </div>
                                <Badge variant={isCustom ? 'default' : 'outline'}>
                                    {isCustom ? 'Kustom' : 'Default'}
                                </Badge>
                            </CardHeader>
                            <CardContent>
                                {loading || !layout ? (
                                    <div className="flex h-[216px] items-center justify-center text-muted-foreground">
                                        Memuat...
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center gap-4">
                                        <div
                                            ref={canvasRef}
                                            className="relative overflow-hidden rounded-lg border-2 border-dashed border-muted-foreground/30"
                                            style={{
                                                width: CANVAS_WIDTH,
                                                height: CANVAS_HEIGHT,
                                                background: layout.cardBackground,
                                            }}
                                            onClick={() => setSelectedKey(null)}
                                        >
                                            <div
                                                className="pointer-events-none absolute left-0 top-0 w-full"
                                                style={{ height: `${headerHeight}%`, background: layout.headerBackground }}
                                            />

                                            {(Object.keys(layout.elements) as CardElementKey[]).map((key) => {
                                                const el = layout.elements[key];
                                                const isSelected = selectedKey === key;
                                                return (
                                                    <motion.div
                                                        key={`${key}-${Math.round(el.x * 10)}-${Math.round(el.y * 10)}`}
                                                        drag
                                                        dragMomentum={false}
                                                        dragElastic={0}
                                                        dragConstraints={canvasRef}
                                                        onDragEnd={(_, info) => handleDragEnd(key, info)}
                                                        onTap={(e) => {
                                                            e.stopPropagation();
                                                            setSelectedKey(key);
                                                        }}
                                                        className={`absolute flex cursor-move select-none items-center justify-center overflow-hidden border text-center leading-tight ${
                                                            isSelected
                                                                ? 'border-2 border-primary ring-2 ring-primary/40'
                                                                : 'border-dashed border-slate-400/70'
                                                        }`}
                                                        style={{
                                                            left: `${el.x}%`,
                                                            top: `${el.y}%`,
                                                            width: `${el.width}%`,
                                                            height: `${el.height}%`,
                                                            fontSize: el.fontSize ? `${el.fontSize * CANVAS_SCALE}px` : undefined,
                                                            color: key === 'schoolName' ? '#ffffff' : '#0f172a',
                                                            fontWeight: TEXT_ELEMENTS.includes(key) ? 600 : 400,
                                                            background:
                                                                key === 'photo' || key === 'qr' || key === 'logo'
                                                                    ? 'rgba(148, 163, 184, 0.35)'
                                                                    : 'transparent',
                                                        }}
                                                    >
                                                        {key === 'logo' && <Building2 className="h-1/2 w-1/2 opacity-60" />}
                                                        {key === 'photo' && <Camera className="h-1/3 w-1/3 opacity-60" />}
                                                        {key === 'qr' && <QrCode className="h-3/4 w-3/4 opacity-60" />}
                                                        {TEXT_ELEMENTS.includes(key) && (
                                                            <span className="px-0.5 uppercase tracking-tight">
                                                                {key === 'schoolName' ? 'Nama Sekolah' : sampleContent(type, key)}
                                                            </span>
                                                        )}
                                                    </motion.div>
                                                );
                                            })}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            Ukuran kartu sebenarnya: {CARD_WIDTH_MM}mm x {CARD_HEIGHT_MM}mm (CR80)
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Warna</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-6">
                                <div className="space-y-2">
                                    <Label>Latar Kartu</Label>
                                    <input
                                        type="color"
                                        className="h-9 w-16 cursor-pointer rounded border"
                                        value={layout?.cardBackground ?? '#ffffff'}
                                        onChange={(e) =>
                                            setLayout((prev) => (prev ? { ...prev, cardBackground: e.target.value } : prev))
                                        }
                                        disabled={!layout}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Latar Header</Label>
                                    <input
                                        type="color"
                                        className="h-9 w-16 cursor-pointer rounded border"
                                        value={layout?.headerBackground ?? '#1e3a5f'}
                                        onChange={(e) =>
                                            setLayout((prev) => (prev ? { ...prev, headerBackground: e.target.value } : prev))
                                        }
                                        disabled={!layout}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <div className="flex gap-2">
                            <Button onClick={handleSave} disabled={saving || !layout}>
                                <Save className="mr-2 h-4 w-4" />
                                {saving ? 'Menyimpan...' : 'Simpan Template'}
                            </Button>
                            <Button variant="outline" onClick={handleReset} disabled={resetting || !layout}>
                                <RotateCcw className="mr-2 h-4 w-4" />
                                {resetting ? 'Mereset...' : 'Reset ke Default'}
                            </Button>
                        </div>
                    </div>

                    <Card className="h-fit lg:col-span-1">
                        <CardHeader>
                            <CardTitle className="text-base">
                                {selectedKey ? ELEMENT_LABELS[selectedKey] : 'Pilih Elemen'}
                            </CardTitle>
                            <CardDescription>
                                {selectedKey
                                    ? 'Atur ukuran dan font elemen terpilih'
                                    : 'Klik salah satu elemen pada kanvas untuk mengatur ukurannya'}
                            </CardDescription>
                        </CardHeader>
                        {selectedKey && selectedElement && (
                            <CardContent className="space-y-6">
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <Label>Lebar</Label>
                                        <span className="text-muted-foreground">{selectedElement.width.toFixed(1)}%</span>
                                    </div>
                                    <Slider
                                        min={4}
                                        max={100}
                                        step={0.5}
                                        value={[selectedElement.width]}
                                        onValueChange={([v]) =>
                                            updateElement(selectedKey, {
                                                width: clamp(v, 4, 100 - selectedElement.x),
                                            })
                                        }
                                    />
                                </div>
                                <div className="space-y-2">
                                    <div className="flex justify-between text-sm">
                                        <Label>Tinggi</Label>
                                        <span className="text-muted-foreground">{selectedElement.height.toFixed(1)}%</span>
                                    </div>
                                    <Slider
                                        min={4}
                                        max={100}
                                        step={0.5}
                                        value={[selectedElement.height]}
                                        onValueChange={([v]) =>
                                            updateElement(selectedKey, {
                                                height: clamp(v, 4, 100 - selectedElement.y),
                                            })
                                        }
                                    />
                                </div>
                                {selectedElement.fontSize !== undefined && (
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <Label>Ukuran Font</Label>
                                            <span className="text-muted-foreground">{selectedElement.fontSize.toFixed(1)}mm</span>
                                        </div>
                                        <Slider
                                            min={2}
                                            max={8}
                                            step={0.1}
                                            value={[selectedElement.fontSize]}
                                            onValueChange={([v]) => updateElement(selectedKey, { fontSize: v })}
                                        />
                                    </div>
                                )}
                            </CardContent>
                        )}
                    </Card>
                </div>
            </div>
        </MainLayout>
    );
}
