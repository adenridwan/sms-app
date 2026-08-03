import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

// Dropdown jam/menit, bukan <input type="time"> — format tampil input
// type="time" bawaan browser ikut locale OS (bisa jadi AM/PM), sementara
// sekolah selalu butuh 24 jam. Dua Select ini menjamin format 24 jam di
// semua device tanpa bergantung locale.
const HOURS = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
const MINUTES = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, '0'));

interface TimeInput24Props {
    /** Format "HH:MM" (24 jam), atau string kosong bila belum diisi. */
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
}

export function TimeInput24({ value, onChange, disabled }: TimeInput24Props) {
    const [hour, minute] = value ? value.split(':') : ['', ''];

    return (
        <div className="flex items-center gap-1">
            <Select value={hour || undefined} onValueChange={(h) => onChange(`${h}:${minute || '00'}`)} disabled={disabled}>
                <SelectTrigger className="w-[76px]">
                    <SelectValue placeholder="Jam" />
                </SelectTrigger>
                <SelectContent>
                    {HOURS.map((h) => (
                        <SelectItem key={h} value={h}>
                            {h}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <span className="text-muted-foreground">:</span>
            <Select value={minute || undefined} onValueChange={(m) => onChange(`${hour || '00'}:${m}`)} disabled={disabled}>
                <SelectTrigger className="w-[76px]">
                    <SelectValue placeholder="Menit" />
                </SelectTrigger>
                <SelectContent>
                    {MINUTES.map((m) => (
                        <SelectItem key={m} value={m}>
                            {m}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
