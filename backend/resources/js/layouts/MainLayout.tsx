import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { cn } from '@/lib/utils';
import { roleLabel, roleSummary } from '@/lib/roles';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import AnnouncementBell from '@/components/AnnouncementBell';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarProvider,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import {
    LayoutDashboard,
    Users,
    GraduationCap,
    BookOpen,
    Calendar,
    ClipboardList,
    DollarSign,
    Library,
    FileText,
    Bell,
    Settings,
    ChevronRight,
    ChevronDown,
    Check,
    KeyRound,
    LogOut,
    User,
    Moon,
    Sun,
    Building2,
    Wallet,
    HelpCircle,
    Info,
} from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface MenuChild {
    /** Kunci stabil, sinkron dengan App\Support\MenuRegistry (backend) */
    key: string;
    title: string;
    href: string;
    permission?: string;
    /** Hanya tampil untuk super admin */
    superAdminOnly?: boolean;
}

interface MenuItem {
    /** Kunci stabil, sinkron dengan App\Support\MenuRegistry (backend) */
    key: string;
    title: string;
    icon: React.ElementType;
    href?: string;
    permission?: string;
    children?: MenuChild[];
}

const menuItems: MenuItem[] = [
    {
        key: 'dashboard',
        title: 'Dashboard',
        icon: LayoutDashboard,
        href: '/dashboard',
        permission: 'dashboard.view',
    },
    {
        key: 'academic',
        title: 'Akademik',
        icon: BookOpen,
        permission: 'academic.view',
        children: [
            { key: 'academic.years', title: 'Tahun Ajaran', href: '/academic/years', permission: 'academic-years.view' },
            { key: 'academic.curricula', title: 'Kurikulum', href: '/academic/curricula', permission: 'curricula.view' },
            { key: 'academic.subjects', title: 'Mata Pelajaran', href: '/academic/subjects', permission: 'subjects.view' },
            { key: 'academic.grade-levels', title: 'Tingkat Kelas', href: '/academic/grade-levels', permission: 'grade-levels.view' },
            { key: 'academic.majors', title: 'Jurusan', href: '/academic/majors', permission: 'majors.view' },
            { key: 'academic.classrooms', title: 'Kelas', href: '/academic/classrooms', permission: 'classrooms.view' },
            { key: 'academic.schedules', title: 'Jadwal', href: '/academic/schedules', permission: 'schedules.view' },
        ],
    },
    {
        key: 'students',
        title: 'Siswa',
        icon: Users,
        permission: 'students.view',
        children: [
            { key: 'students.data', title: 'Data Siswa', href: '/students' },
            { key: 'students.enrollment', title: 'Pendaftaran', href: '/students/enrollment', permission: 'students.enroll' },
            { key: 'students.achievements', title: 'Prestasi', href: '/students/achievements' },
        ],
    },
    {
        key: 'staff',
        title: 'Guru & Staff',
        icon: GraduationCap,
        permission: 'teachers.view',
        children: [
            { key: 'staff.teachers', title: 'Data Guru', href: '/teachers', permission: 'teachers.view' },
            { key: 'staff.staff', title: 'Data Staff', href: '/staff', permission: 'staff.view' },
            { key: 'staff.leave', title: 'Pengajuan Cuti', href: '/attendance/permissions', permission: 'attendance.manage' },
        ],
    },
    {
        key: 'attendance',
        title: 'Absensi',
        icon: Calendar,
        permission: 'attendance.view',
        children: [
            { key: 'attendance.me', title: 'Absensi Saya', href: '/attendance/me', permission: 'attendance.check-in' },
            { key: 'attendance.students', title: 'Absensi Siswa', href: '/attendance/students' },
            { key: 'attendance.teachers', title: 'Absensi Pegawai', href: '/attendance/teachers' },
            { key: 'attendance.reports', title: 'Rekap Absensi', href: '/attendance/reports' },
        ],
    },
    {
        key: 'grades',
        title: 'Nilai & Ujian',
        icon: ClipboardList,
        permission: 'grades.view',
        children: [
            { key: 'grades.exams', title: 'Ujian', href: '/exams', permission: 'exams.view' },
            { key: 'grades.input', title: 'Input Nilai', href: '/grades/input', permission: 'grades.input' },
            { key: 'grades.recap', title: 'Rekap Nilai', href: '/grades' },
        ],
    },
    {
        key: 'finance',
        title: 'Keuangan',
        icon: DollarSign,
        permission: 'finance.view',
        children: [
            { key: 'finance.fee-types', title: 'Jenis Biaya', href: '/finance/fee-types', permission: 'finance.manage' },
            { key: 'finance.fee-structures', title: 'Struktur Biaya', href: '/finance/fee-structures', permission: 'finance.manage' },
            { key: 'finance.payment-methods', title: 'Metode Pembayaran', href: '/finance/payment-methods', permission: 'finance.manage' },
            { key: 'finance.discounts', title: 'Potongan', href: '/finance/discounts', permission: 'finance.manage' },
            { key: 'finance.fees', title: 'Tagihan', href: '/finance/fees' },
            { key: 'finance.payments', title: 'Pembayaran', href: '/finance/payments' },
            { key: 'finance.reports', title: 'Laporan', href: '/finance/reports', permission: 'finance.report' },
        ],
    },
    {
        key: 'payroll',
        title: 'Penggajian',
        icon: Wallet,
        permission: 'payroll.view',
        children: [
            { key: 'payroll.periods', title: 'Proses Penggajian', href: '/payroll/periods', permission: 'payroll.manage' },
            { key: 'payroll.employee-salaries', title: 'Gaji Karyawan', href: '/payroll/employee-salaries', permission: 'payroll.manage' },
            { key: 'payroll.reports', title: 'Laporan', href: '/payroll/reports', permission: 'payroll.report' },
            { key: 'payroll.salary-grades', title: 'Golongan Gaji', href: '/payroll/salary-grades', permission: 'payroll.manage' },
            { key: 'payroll.salary-components', title: 'Komponen Gaji', href: '/payroll/salary-components', permission: 'payroll.manage' },
            { key: 'payroll.bpjs-rates', title: 'Tarif BPJS', href: '/payroll/bpjs-rates', permission: 'payroll.manage' },
            { key: 'payroll.tax-brackets', title: 'Tarif Pajak PPh 21', href: '/payroll/tax-brackets', permission: 'payroll.manage' },
            { key: 'payroll.tax-settings', title: 'Pengaturan Pajak', href: '/payroll/tax-settings', permission: 'payroll.manage' },
        ],
    },
    {
        key: 'library',
        title: 'Perpustakaan',
        icon: Library,
        permission: 'library.view',
        children: [
            { key: 'library.books', title: 'Katalog Buku', href: '/library/books' },
            { key: 'library.loans', title: 'Peminjaman', href: '/library/loans' },
            { key: 'library.members', title: 'Anggota', href: '/library/members' },
        ],
    },
    {
        key: 'reports',
        title: 'Laporan',
        icon: FileText,
        permission: 'reports.view',
        children: [
            { key: 'reports.expense', title: 'Pengeluaran', href: '/reports/expense', permission: 'finance.report' },
            { key: 'reports.report-cards', title: 'Rapor', href: '/reports/report-cards' },
            { key: 'reports.generate', title: 'Generate Laporan', href: '/reports/generate' },
        ],
    },
    {
        key: 'notifications',
        title: 'Notifikasi',
        icon: Bell,
        children: [
            { key: 'notifications.announcements', title: 'Pengumuman', href: '/notifications/announcements' },
        ],
    },
    {
        key: 'settings',
        title: 'Pengaturan',
        icon: Settings,
        // Tanpa permission di level group — visibilitas ditentukan anak-anaknya.
        // Group otomatis tersembunyi bila tak ada anak yang lolos.
        children: [
            { key: 'settings.general', title: 'Umum', href: '/settings', permission: 'settings.view' },
            { key: 'settings.attendance', title: 'Pengaturan Absensi', href: '/attendance/settings', permission: 'settings.attendance' },
            { key: 'settings.menu', title: 'Pengaturan Menu', href: '/settings/menu', permission: 'settings.manage' },
            { key: 'settings.users', title: 'Pengguna', href: '/settings/users', superAdminOnly: true },
            { key: 'settings.login-security', title: 'Keamanan Login', href: '/settings/login-security', permission: 'settings.manage' },
            { key: 'settings.devices', title: 'Monitor Device', href: '/settings/devices', permission: 'settings.manage' },
            { key: 'settings.backups', title: 'Backup Database', href: '/settings/backups', superAdminOnly: true },
        ],
    },
];

const getInitials = (name: string) =>
    name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);

/**
 * Menu group with expandable/collapsible submenu.
 * When the sidebar is collapsed (icon-only), clicking the group
 * re-expands the sidebar and opens the submenu.
 */
function NavGroup({
    item,
    childrenItems,
    currentPath,
}: {
    item: MenuItem;
    childrenItems: MenuChild[];
    currentPath: string;
}) {
    const { state, setOpen } = useSidebar();
    const collapsed = state === 'collapsed';

    const isPathActive = (href: string) =>
        currentPath === href || currentPath.startsWith(href + '/');

    // Only the deepest (longest) matching child is marked active
    const activeChildHref = childrenItems
        .filter((c) => isPathActive(c.href))
        .sort((a, b) => b.href.length - a.href.length)[0]?.href;

    const [isGroupOpen, setIsGroupOpen] = useState(!!activeChildHref);

    const handleTriggerClick = () => {
        if (collapsed) {
            setOpen(true);
            setIsGroupOpen(true);
        }
    };

    return (
        <Collapsible
            open={isGroupOpen && !collapsed}
            onOpenChange={(open) => {
                if (!collapsed) setIsGroupOpen(open);
            }}
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={item.title}
                        isActive={!!activeChildHref}
                        onClick={handleTriggerClick}
                    >
                        <item.icon className="h-4 w-4" />
                        <span>{item.title}</span>
                        <ChevronRight
                            className={cn(
                                'ml-auto h-4 w-4 shrink-0 transition-transform duration-200',
                                isGroupOpen && !collapsed && 'rotate-90',
                                'group-data-[state=collapsed]:hidden'
                            )}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {childrenItems.map((child) => (
                            <SidebarMenuSubItem key={child.href}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={child.href === activeChildHref}
                                >
                                    <Link href={child.href}>
                                        <span>{child.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

interface MainLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function MainLayout({ children, title }: MainLayoutProps) {
    const { auth, tenant, tenants, app, menu } = usePage<PageProps>().props;
    const { url } = usePage();
    const currentPath = url.split('?')[0];

    // Set menu_key yang boleh tampil (resolusi permission + config per-role dari
    // server). Digabung dengan hasPermission sebagai lapis pertahanan tambahan.
    const menuVisibleSet = new Set(menu?.visible ?? []);

    // Tenant switcher for super admin: the selected tenant is persisted in
    // localStorage and sent as the X-Tenant-ID header on every API request.
    const isSuperAdmin = auth.user?.user_type === 'super_admin';
    const tenantList = isSuperAdmin && tenants ? tenants : [];
    const [activeTenantId] = useState<string | null>(() => {
        if (!isSuperAdmin || tenantList.length === 0) return null;
        const stored = localStorage.getItem('active_tenant_id');
        if (stored && tenantList.some((t) => t.id === stored)) return stored;
        // Default to the first tenant so tenant-scoped API calls always work
        localStorage.setItem('active_tenant_id', tenantList[0].id);
        return tenantList[0].id;
    });
    const activeTenant = tenantList.find((t) => t.id === activeTenantId) ?? null;

    const switchTenant = (id: string) => {
        if (id === activeTenantId) return;
        localStorage.setItem('active_tenant_id', id);
        // Full reload so every page refetches data under the new tenant context
        window.location.reload();
    };

    const [isDark, setIsDark] = useState(
        () => typeof document !== 'undefined' && document.documentElement.classList.contains('dark')
    );

    const setTheme = (dark: boolean) => {
        setIsDark(dark);
        document.documentElement.classList.toggle('dark', dark);
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    };

    const hasPermission = (permission?: string) => {
        if (!permission) return true;
        if (auth.user?.user_type === 'super_admin') return true;
        return auth.user?.permissions?.includes(permission) ?? false;
    };

    // Visibilitas menu dari config server (Opsi A: hanya bisa menyembunyikan).
    // Super admin selalu lihat semua. Item tanpa key → jatuh ke hasPermission.
    const isMenuVisible = (key?: string) => {
        if (auth.user?.user_type === 'super_admin') return true;
        if (!key) return true;
        return menuVisibleSet.has(key);
    };

    const isPathActive = (href: string) =>
        currentPath === href || currentPath.startsWith(href + '/');

    const schoolName = tenant?.name || activeTenant?.name || app.name;
    // Fallback ke activeTenant dengan alasan yang sama seperti nama di atas:
    // prop `tenant` selalu null untuk super admin (akunnya tidak terikat satu
    // sekolah), sehingga tanpa ini logo sekolah yang baru diunggah tidak pernah
    // muncul untuknya — yang tampil selalu ikon topi wisuda bawaan.
    const schoolLogo = tenant?.logo || activeTenant?.logo || null;
    const userName = auth.user?.full_name || 'User';
    const userRoles = auth.user?.roles ?? [];

    return (
        <SidebarProvider>
            <Sidebar>
                {/* Sidebar Header: school logo & name */}
                <SidebarHeader className="border-b border-sidebar-border bg-gradient-to-b from-primary/[0.04] to-transparent px-3 py-3">
                    <Link
                        href="/dashboard"
                        className="flex items-center gap-2.5 rounded-xl p-1.5 transition-colors hover:bg-sidebar-accent group-data-[state=collapsed]:justify-center"
                    >
                        {schoolLogo ? (
                            <img
                                src={schoolLogo}
                                alt={schoolName}
                                className="h-9 w-9 shrink-0 rounded-xl object-cover ring-1 ring-sidebar-border shadow-sm"
                            />
                        ) : (
                            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary to-primary/70 text-primary-foreground shadow-sm ring-1 ring-primary/20">
                                <GraduationCap className="h-5 w-5" />
                            </div>
                        )}
                        <div className="flex min-w-0 flex-col group-data-[state=collapsed]:hidden">
                            <span className="truncate text-sm font-semibold">{schoolName}</span>
                            <span className="truncate text-xs text-muted-foreground">
                                School Management
                            </span>
                        </div>
                    </Link>
                </SidebarHeader>

                {/* Sidebar Menu: vertically scrollable */}
                <SidebarContent className="scrollbar-thin">
                    <SidebarMenu className="px-2 py-3">
                        {menuItems.map((item) => {
                            if (!hasPermission(item.permission) || !isMenuVisible(item.key)) return null;

                            if (item.children) {
                                const visibleChildren = item.children.filter(
                                    (child) =>
                                        hasPermission(child.permission) &&
                                        (!child.superAdminOnly || isSuperAdmin) &&
                                        isMenuVisible(child.key)
                                );
                                if (visibleChildren.length === 0) return null;

                                return (
                                    <NavGroup
                                        key={item.title}
                                        item={item}
                                        childrenItems={visibleChildren}
                                        currentPath={currentPath}
                                    />
                                );
                            }

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        tooltip={item.title}
                                        isActive={isPathActive(item.href!)}
                                    >
                                        <Link href={item.href!}>
                                            <item.icon className="h-4 w-4" />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarContent>

                {/* Sidebar Footer: user info + logout */}
                <SidebarFooter className="border-t border-sidebar-border bg-gradient-to-t from-primary/[0.025] to-transparent p-2">
                    <div className="flex items-center gap-2 rounded-xl border border-transparent p-1.5 transition-colors hover:border-sidebar-border hover:bg-sidebar-accent/60 group-data-[state=collapsed]:flex-col group-data-[state=collapsed]:gap-1.5 group-data-[state=collapsed]:border-0 group-data-[state=collapsed]:bg-transparent group-data-[state=collapsed]:p-0">
                        <Link href="/profile" title="Profil" className="shrink-0">
                            <Avatar className="h-8 w-8 ring-1 ring-border transition-shadow hover:ring-2 hover:ring-primary">
                                <AvatarImage src={auth.user?.avatar_url ?? undefined} />
                                <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                    {getInitials(userName)}
                                </AvatarFallback>
                            </Avatar>
                        </Link>
                        <div className="flex min-w-0 flex-1 flex-col group-data-[state=collapsed]:hidden">
                            <span className="truncate text-sm font-medium">{userName}</span>
                            <span className="truncate text-xs text-muted-foreground">
                                {auth.user?.email}
                            </span>
                        </div>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            title="Keluar"
                            className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                        >
                            <LogOut className="h-4 w-4" />
                            <span className="sr-only">Keluar</span>
                        </Link>
                    </div>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset>
                {/* Header / Navbar */}
                <header className="sticky top-0 z-10 flex h-14 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                    <SidebarTrigger />
                    <div className="min-w-0 flex-1">
                        {title && (
                            <h1 className="truncate text-lg font-semibold">{title}</h1>
                        )}
                    </div>

                    {/* Tenant switcher (super admin only) */}
                    {isSuperAdmin && tenantList.length > 0 && (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" className="h-9 gap-2 px-2.5">
                                    <Building2 className="h-4 w-4 text-muted-foreground" />
                                    <span className="hidden max-w-[12rem] truncate text-sm md:inline">
                                        {activeTenant?.name ?? 'Pilih Sekolah'}
                                    </span>
                                    <ChevronDown className="h-4 w-4 text-muted-foreground" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-64">
                                <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                    Kelola data sekolah
                                </DropdownMenuLabel>
                                {tenantList.map((t) => (
                                    <DropdownMenuItem key={t.id} onClick={() => switchTenant(t.id)}>
                                        <Building2 className="mr-2 h-4 w-4 text-muted-foreground" />
                                        <span className="truncate">{t.name}</span>
                                        {t.id === activeTenantId && (
                                            <Check className="ml-auto h-4 w-4 text-primary" />
                                        )}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}

                    {/* Notifikasi — pengumuman yang sudah tayang, lihat AnnouncementBell */}
                    <AnnouncementBell />

                    {/* User dropdown: theme, name, logout */}
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                className="h-9 gap-2 rounded-full px-1.5 md:pr-2.5"
                            >
                                <Avatar className="h-7 w-7">
                                    <AvatarImage src={auth.user?.avatar_url ?? undefined} />
                                    <AvatarFallback className="bg-primary/10 text-xs font-semibold text-primary">
                                        {getInitials(userName)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="hidden max-w-[10rem] truncate text-sm font-medium md:inline">
                                    {userName}
                                </span>
                                <ChevronDown className="hidden h-4 w-4 text-muted-foreground md:inline" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-60">
                            <DropdownMenuLabel className="flex flex-col gap-1.5">
                                <div className="flex flex-col">
                                    <span className="truncate">{userName}</span>
                                    <span className="truncate text-xs font-normal text-muted-foreground">
                                        {auth.user?.email}
                                    </span>
                                </div>
                                {/* Peran yang sedang dipakai — satu akun bisa memegang
                                    beberapa peran sekaligus (mis. guru + wali kelas),
                                    jadi ditampilkan semua, bukan yang pertama saja. */}
                                <div className="flex flex-wrap gap-1">
                                    {userRoles.length > 0 ? (
                                        userRoles.map((role) => (
                                            <Badge key={role} variant="secondary" className="font-normal">
                                                {roleLabel(role)}
                                            </Badge>
                                        ))
                                    ) : (
                                        <Badge variant="outline" className="font-normal">
                                            {roleSummary([], auth.user?.user_type)}
                                        </Badge>
                                    )}
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/profile">
                                    <User className="mr-2 h-4 w-4" />
                                    Profil Saya
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <Link href="/profile?tab=security">
                                    <KeyRound className="mr-2 h-4 w-4" />
                                    Ganti Password
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                Tampilan
                            </DropdownMenuLabel>
                            <DropdownMenuItem onClick={() => setTheme(false)}>
                                <Sun className="mr-2 h-4 w-4 text-amber-500" />
                                Mode Terang
                                {!isDark && <Check className="ml-auto h-4 w-4 text-primary" />}
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={() => setTheme(true)}>
                                <Moon className="mr-2 h-4 w-4 text-indigo-400" />
                                Mode Gelap
                                {isDark && <Check className="ml-auto h-4 w-4 text-primary" />}
                            </DropdownMenuItem>
                            {isSuperAdmin && (
                                <>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                                        Informasi
                                    </DropdownMenuLabel>
                                    <DropdownMenuItem asChild>
                                        <Link href="/help">
                                            <HelpCircle className="mr-2 h-4 w-4" />
                                            Bantuan
                                        </Link>
                                    </DropdownMenuItem>
                                    <Dialog>
                                        <DialogTrigger asChild>
                                            <DropdownMenuItem onSelect={(e) => e.preventDefault()}>
                                                <Info className="mr-2 h-4 w-4" />
                                                Tentang Aplikasi
                                            </DropdownMenuItem>
                                        </DialogTrigger>
                                        <DialogContent className="sm:max-w-md">
                                            <DialogHeader>
                                                <DialogTitle className="flex items-center gap-2">
                                                    {schoolLogo ? (
                                                        <img
                                                            src={schoolLogo}
                                                            alt={schoolName}
                                                            className="h-8 w-8 rounded-lg object-cover"
                                                        />
                                                    ) : (
                                                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                                                            <GraduationCap className="h-5 w-5" />
                                                        </div>
                                                    )}
                                                    School Management System
                                                </DialogTitle>
                                                <DialogDescription asChild>
                                                    <div className="space-y-3 pt-2">
                                                        <div className="rounded-lg border bg-muted/50 p-3">
                                                            <div className="grid gap-2 text-sm">
                                                                <div className="flex justify-between">
                                                                    <span className="text-muted-foreground">Versi</span>
                                                                    <span className="font-medium">{app.version || '1.0.0'}</span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span className="text-muted-foreground">Sekolah</span>
                                                                    <span className="font-medium">{schoolName}</span>
                                                                </div>
                                                                <div className="flex justify-between">
                                                                    <span className="text-muted-foreground">Environment</span>
                                                                    <Badge variant="outline" className="font-mono text-xs">
                                                                        {app.env || 'production'}
                                                                    </Badge>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            Sistem informasi manajemen sekolah terpadu untuk
                                                            pengelolaan data akademik, siswa, guru, keuangan,
                                                            dan absensi.
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            &copy; {new Date().getFullYear()} {schoolName}. Hak cipta dilindungi.
                                                        </p>
                                                    </div>
                                                </DialogDescription>
                                            </DialogHeader>
                                        </DialogContent>
                                    </Dialog>
                                </>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                asChild
                                className="text-destructive focus:bg-destructive/10 focus:text-destructive"
                            >
                                <Link href="/logout" method="post" as="button" className="w-full">
                                    <LogOut className="mr-2 h-4 w-4" />
                                    Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </header>

                {/* Main Content */}
                <main className="flex-1 p-4 md:p-6">{children}</main>

                {/* Section Footer */}
                <footer className="border-t bg-background px-4 py-3 md:px-6">
                    <div className="flex flex-col items-center justify-between gap-1 text-xs text-muted-foreground md:flex-row">
                        <p>
                            &copy; {new Date().getFullYear()} {schoolName}. Hak cipta dilindungi.
                        </p>
                        <p>
                            School Management System
                            {app.version ? ` · v${app.version}` : ''}
                        </p>
                    </div>
                </footer>
            </SidebarInset>
        </SidebarProvider>
    );
}
