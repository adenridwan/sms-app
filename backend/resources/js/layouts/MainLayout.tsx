import { useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
    LogOut,
    User,
    Moon,
    Sun,
} from 'lucide-react';

interface MenuItem {
    title: string;
    icon: React.ElementType;
    href?: string;
    permission?: string;
    children?: {
        title: string;
        href: string;
        permission?: string;
    }[];
}

const menuItems: MenuItem[] = [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        href: '/dashboard',
        permission: 'dashboard.view',
    },
    {
        title: 'Akademik',
        icon: BookOpen,
        permission: 'academic.view',
        children: [
            { title: 'Tahun Ajaran', href: '/academic/years', permission: 'academic-years.view' },
            { title: 'Kurikulum', href: '/academic/curricula', permission: 'curricula.view' },
            { title: 'Mata Pelajaran', href: '/academic/subjects', permission: 'subjects.view' },
            { title: 'Kelas', href: '/academic/classrooms', permission: 'classrooms.view' },
            { title: 'Jadwal', href: '/academic/schedules', permission: 'schedules.view' },
        ],
    },
    {
        title: 'Siswa',
        icon: Users,
        permission: 'students.view',
        children: [
            { title: 'Data Siswa', href: '/students' },
            { title: 'Pendaftaran', href: '/students/enrollment', permission: 'students.enroll' },
            { title: 'Prestasi', href: '/students/achievements' },
        ],
    },
    {
        title: 'Guru & Staff',
        icon: GraduationCap,
        permission: 'teachers.view',
        children: [
            { title: 'Data Guru', href: '/teachers', permission: 'teachers.view' },
            { title: 'Data Staff', href: '/staff', permission: 'staff.view' },
            { title: 'Pengajuan Cuti', href: '/leave-requests' },
        ],
    },
    {
        title: 'Absensi',
        icon: Calendar,
        permission: 'attendance.view',
        children: [
            { title: 'Absensi Siswa', href: '/attendance/students' },
            { title: 'Absensi Pegawai', href: '/attendance/employees' },
            { title: 'Rekap Absensi', href: '/attendance/summary' },
        ],
    },
    {
        title: 'Nilai & Ujian',
        icon: ClipboardList,
        permission: 'grades.view',
        children: [
            { title: 'Ujian', href: '/exams', permission: 'exams.view' },
            { title: 'Input Nilai', href: '/grades/input', permission: 'grades.input' },
            { title: 'Rekap Nilai', href: '/grades' },
        ],
    },
    {
        title: 'Keuangan',
        icon: DollarSign,
        permission: 'finance.view',
        children: [
            { title: 'Tagihan', href: '/finance/fees' },
            { title: 'Pembayaran', href: '/finance/payments' },
            { title: 'Laporan', href: '/finance/reports', permission: 'finance.report' },
        ],
    },
    {
        title: 'Perpustakaan',
        icon: Library,
        permission: 'library.view',
        children: [
            { title: 'Katalog Buku', href: '/library/books' },
            { title: 'Peminjaman', href: '/library/loans' },
            { title: 'Anggota', href: '/library/members' },
        ],
    },
    {
        title: 'Laporan',
        icon: FileText,
        permission: 'reports.view',
        children: [
            { title: 'Rapor', href: '/reports/report-cards' },
            { title: 'Generate Laporan', href: '/reports/generate' },
        ],
    },
    {
        title: 'Pengaturan',
        icon: Settings,
        permission: 'settings.view',
        href: '/settings',
    },
];

interface MainLayoutProps {
    children: React.ReactNode;
    title?: string;
}

export default function MainLayout({ children, title }: MainLayoutProps) {
    const { auth, tenant, app } = usePage<PageProps>().props;
    const [isDark, setIsDark] = useState(false);

    const toggleTheme = () => {
        setIsDark(!isDark);
        document.documentElement.classList.toggle('dark');
    };

    const hasPermission = (permission?: string) => {
        if (!permission) return true;
        if (auth.user?.user_type === 'super_admin') return true;
        return auth.user?.permissions.includes(permission);
    };

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((n) => n[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    return (
        <SidebarProvider>
            <Sidebar variant="inset">
                <SidebarHeader className="border-b px-4 py-3">
                    <Link href="/dashboard" className="flex items-center gap-2">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                            <GraduationCap className="h-5 w-5" />
                        </div>
                        <div className="flex flex-col">
                            <span className="text-sm font-semibold">{tenant?.name || app.name}</span>
                            <span className="text-xs text-muted-foreground">School Management</span>
                        </div>
                    </Link>
                </SidebarHeader>

                <SidebarContent className="scrollbar-thin">
                    <SidebarMenu className="px-2 py-2">
                        {menuItems.map((item) => {
                            if (!hasPermission(item.permission)) return null;

                            if (item.children) {
                                const visibleChildren = item.children.filter((child) =>
                                    hasPermission(child.permission)
                                );
                                if (visibleChildren.length === 0) return null;

                                return (
                                    <SidebarMenuItem key={item.title}>
                                        <SidebarMenuButton>
                                            <item.icon className="h-4 w-4" />
                                            <span>{item.title}</span>
                                            <ChevronRight className="ml-auto h-4 w-4" />
                                        </SidebarMenuButton>
                                        <SidebarMenuSub>
                                            {visibleChildren.map((child) => (
                                                <SidebarMenuSubItem key={child.href}>
                                                    <SidebarMenuSubButton asChild>
                                                        <Link href={child.href}>{child.title}</Link>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </SidebarMenuItem>
                                );
                            }

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild>
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

                <SidebarFooter className="border-t p-2">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="w-full justify-start gap-2 px-2">
                                <Avatar className="h-8 w-8">
                                    <AvatarImage src={auth.user?.avatar} />
                                    <AvatarFallback>
                                        {auth.user?.full_name ? getInitials(auth.user.full_name) : 'U'}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex flex-col items-start text-left">
                                    <span className="text-sm font-medium">{auth.user?.full_name}</span>
                                    <span className="text-xs text-muted-foreground">{auth.user?.email}</span>
                                </div>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel>Akun Saya</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/profile">
                                    <User className="mr-2 h-4 w-4" />
                                    Profil
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem onClick={toggleTheme}>
                                {isDark ? (
                                    <Sun className="mr-2 h-4 w-4" />
                                ) : (
                                    <Moon className="mr-2 h-4 w-4" />
                                )}
                                {isDark ? 'Mode Terang' : 'Mode Gelap'}
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/logout" method="post" as="button" className="w-full">
                                    <LogOut className="mr-2 h-4 w-4" />
                                    Keluar
                                </Link>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarFooter>
            </Sidebar>

            <SidebarInset>
                {/* Header */}
                <header className="sticky top-0 z-10 flex h-14 items-center gap-4 border-b bg-background px-4">
                    <SidebarTrigger />
                    <div className="flex-1">
                        {title && <h1 className="text-lg font-semibold">{title}</h1>}
                    </div>
                    <Button variant="ghost" size="icon" className="relative">
                        <Bell className="h-5 w-5" />
                        <span className="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-destructive text-[10px] text-destructive-foreground">
                            3
                        </span>
                    </Button>
                </header>

                {/* Main Content */}
                <main className="flex-1 p-4 md:p-6">{children}</main>
            </SidebarInset>
        </SidebarProvider>
    );
}
