import { useCallback, useEffect, useState } from 'react';
import { Link, router } from '@inertiajs/react';
import { formatDistanceToNow, parseISO } from 'date-fns';
import { id as localeId } from 'date-fns/locale';
import { Bell, Pin } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { announcementApi, type Announcement } from '@/services/api';

const ANNOUNCEMENTS_URL = '/notifications/announcements';

/** Warna titik per prioritas — selaras dengan badge di halaman Pengumuman. */
const PRIORITY_DOT: Record<string, string> = {
    urgent: 'bg-destructive',
    high: 'bg-orange-500',
    normal: 'bg-primary',
    low: 'bg-muted-foreground',
};

function relativeTime(value: string | null): string {
    if (!value) return '';
    try {
        return formatDistanceToNow(parseISO(value), { addSuffix: true, locale: localeId });
    } catch {
        return '';
    }
}

/**
 * Ikon lonceng di navbar. Sebelumnya tombol mati dengan angka "3" yang
 * ditulis langsung di markup; sekarang menampilkan pengumuman yang benar-benar
 * tayang (endpoint `announcements/feed`, bukan `index` yang ikut membawa draft)
 * beserta jumlah yang belum dibaca oleh pengguna yang sedang login.
 */
export default function AnnouncementBell() {
    const [items, setItems] = useState<Announcement[]>([]);
    const [unread, setUnread] = useState(0);
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const res = await announcementApi.feed(5);
            const data = res.data.data;
            setItems(data?.items ?? []);
            setUnread(data?.unread_count ?? 0);
            setFailed(false);
        } catch {
            // Lonceng tidak boleh merusak seluruh layout kalau endpoint-nya
            // gagal — cukup tampil tanpa badge, dengan pesan di dalam dropdown.
            setFailed(true);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        load();
    }, [load]);

    const openAnnouncement = async (announcement: Announcement) => {
        if (!announcement.is_read) {
            try {
                await announcementApi.markAsRead(announcement.id);
                setUnread((n) => Math.max(0, n - 1));
                setItems((list) =>
                    list.map((a) => (a.id === announcement.id ? { ...a, is_read: true } : a))
                );
            } catch {
                // Gagal menandai bukan alasan untuk tidak membuka halamannya.
            }
        }

        router.visit(ANNOUNCEMENTS_URL);
    };

    return (
        <DropdownMenu onOpenChange={(open) => open && load()}>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="relative">
                    <Bell className="h-5 w-5" />
                    {unread > 0 && (
                        <span className="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-destructive px-1 text-[10px] text-destructive-foreground">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                    <span className="sr-only">
                        {unread > 0 ? `${unread} pengumuman belum dibaca` : 'Notifikasi'}
                    </span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>Pengumuman</span>
                    {unread > 0 && (
                        <span className="text-xs font-normal text-muted-foreground">
                            {unread} belum dibaca
                        </span>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                {loading && items.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">Memuat...</p>
                ) : failed ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Gagal memuat pengumuman.
                    </p>
                ) : items.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        Belum ada pengumuman.
                    </p>
                ) : (
                    <div className="max-h-80 overflow-y-auto">
                        {items.map((a) => (
                            <button
                                key={a.id}
                                type="button"
                                onClick={() => openAnnouncement(a)}
                                className="flex w-full gap-2 px-2 py-2 text-left text-sm hover:bg-accent focus:bg-accent focus:outline-none"
                            >
                                <span
                                    className={cn(
                                        'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                        a.is_read
                                            ? 'bg-transparent'
                                            : PRIORITY_DOT[a.priority] ?? 'bg-primary'
                                    )}
                                />
                                <span className="min-w-0 flex-1">
                                    <span
                                        className={cn(
                                            'flex items-center gap-1 truncate',
                                            !a.is_read && 'font-semibold'
                                        )}
                                    >
                                        {a.is_pinned && (
                                            <Pin className="h-3 w-3 shrink-0 text-muted-foreground" />
                                        )}
                                        <span className="truncate">{a.title}</span>
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {relativeTime(a.publish_at ?? a.created_at)}
                                    </span>
                                </span>
                            </button>
                        ))}
                    </div>
                )}

                <DropdownMenuSeparator />
                <Link
                    href={ANNOUNCEMENTS_URL}
                    className="block px-2 py-2 text-center text-sm font-medium text-primary hover:underline"
                >
                    Lihat semua pengumuman
                </Link>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
