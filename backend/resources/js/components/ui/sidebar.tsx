import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, VariantProps } from 'class-variance-authority';
import { PanelLeft } from 'lucide-react';

import { cn } from '@/lib/utils';
import { useIsMobile } from '@/hooks/use-mobile';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const SIDEBAR_WIDTH = '16rem';
const SIDEBAR_WIDTH_ICON = '3.5rem';

type SidebarContextType = {
    state: 'expanded' | 'collapsed';
    open: boolean;
    setOpen: (open: boolean | ((prev: boolean) => boolean)) => void;
    openMobile: boolean;
    setOpenMobile: (open: boolean) => void;
    isMobile: boolean;
    toggleSidebar: () => void;
};

const SidebarContext = React.createContext<SidebarContextType | null>(null);

function useSidebar() {
    const context = React.useContext(SidebarContext);
    if (!context) {
        throw new Error('useSidebar must be used within a SidebarProvider');
    }
    return context;
}

const SIDEBAR_STORAGE_KEY = 'sidebar-open';

const SidebarProvider = React.forwardRef<
    HTMLDivElement,
    React.ComponentProps<'div'> & { defaultOpen?: boolean }
>(({ defaultOpen = true, className, children, ...props }, ref) => {
    const isMobile = useIsMobile();

    // Initialize dari localStorage jika ada
    const [open, setOpenState] = React.useState(() => {
        if (typeof window === 'undefined') return defaultOpen;
        const stored = localStorage.getItem(SIDEBAR_STORAGE_KEY);
        if (stored !== null) return stored === 'true';
        return defaultOpen;
    });

    // Wrapper setOpen yang juga menyimpan ke localStorage
    const setOpen = React.useCallback((value: boolean | ((prev: boolean) => boolean)) => {
        setOpenState((prev) => {
            const newValue = typeof value === 'function' ? value(prev) : value;
            localStorage.setItem(SIDEBAR_STORAGE_KEY, String(newValue));
            return newValue;
        });
    }, []);

    // The mobile drawer must always start closed - it is a separate overlay
    // from the desktop sidebar's expanded/collapsed state.
    const [openMobile, setOpenMobile] = React.useState(false);

    const toggleSidebar = React.useCallback(() => {
        if (isMobile) {
            setOpenMobile((prev) => !prev);
        } else {
            setOpen((prev) => !prev);
        }
    }, [isMobile, setOpen]);

    const state = open ? 'expanded' : 'collapsed';

    const contextValue = React.useMemo<SidebarContextType>(
        () => ({ state, open, setOpen, openMobile, setOpenMobile, isMobile, toggleSidebar }),
        [state, open, setOpen, openMobile, isMobile, toggleSidebar]
    );

    return (
        <SidebarContext.Provider value={contextValue}>
            <TooltipProvider delayDuration={0}>
                <div
                    ref={ref}
                    className={cn('group/sidebar-wrapper flex min-h-svh w-full', className)}
                    style={
                        {
                            '--sidebar-width': SIDEBAR_WIDTH,
                            '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
                        } as React.CSSProperties
                    }
                    {...props}
                >
                    {children}
                </div>
            </TooltipProvider>
        </SidebarContext.Provider>
    );
});
SidebarProvider.displayName = 'SidebarProvider';

const Sidebar = React.forwardRef<
    HTMLDivElement,
    React.ComponentProps<'div'> & { variant?: 'sidebar' | 'floating' | 'inset' }
>(({ variant = 'sidebar', className, children, ...props }, ref) => {
    const { state, openMobile, setOpenMobile } = useSidebar();

    return (
        <>
            {/* Mobile */}
            <Sheet open={openMobile} onOpenChange={setOpenMobile}>
                <SheetContent side="left" className="w-[--sidebar-width] p-0 md:hidden">
                    <SheetTitle className="sr-only">Navigation Menu</SheetTitle>
                    <div className="flex h-full flex-col">{children}</div>
                </SheetContent>
            </Sheet>

            {/* Desktop */}
            <div
                ref={ref}
                data-state={state}
                className={cn(
                    'group hidden flex-col overflow-hidden border-r bg-sidebar text-sidebar-foreground md:flex',
                    'w-[--sidebar-width] transition-[width] duration-300 ease-in-out',
                    'data-[state=collapsed]:w-[--sidebar-width-icon]',
                    variant === 'inset' && 'rounded-lg border',
                    className
                )}
                {...props}
            >
                {children}
            </div>
        </>
    );
});
Sidebar.displayName = 'Sidebar';

const SidebarTrigger = React.forwardRef<
    React.ElementRef<typeof Button>,
    React.ComponentProps<typeof Button>
>(({ className, ...props }, ref) => {
    const { toggleSidebar } = useSidebar();

    return (
        <Button
            ref={ref}
            variant="ghost"
            size="icon"
            className={cn('h-8 w-8', className)}
            onClick={toggleSidebar}
            {...props}
        >
            <PanelLeft className="h-4 w-4" />
            <span className="sr-only">Toggle Sidebar</span>
        </Button>
    );
});
SidebarTrigger.displayName = 'SidebarTrigger';

const SidebarInset = React.forwardRef<HTMLDivElement, React.ComponentProps<'main'>>(
    ({ className, ...props }, ref) => {
        return (
            <main
                ref={ref}
                className={cn('relative flex min-h-svh flex-1 flex-col bg-background', className)}
                {...props}
            />
        );
    }
);
SidebarInset.displayName = 'SidebarInset';

const SidebarHeader = React.forwardRef<HTMLDivElement, React.ComponentProps<'div'>>(
    ({ className, ...props }, ref) => {
        return <div ref={ref} className={cn('flex flex-col gap-2', className)} {...props} />;
    }
);
SidebarHeader.displayName = 'SidebarHeader';

const SidebarFooter = React.forwardRef<HTMLDivElement, React.ComponentProps<'div'>>(
    ({ className, ...props }, ref) => {
        return <div ref={ref} className={cn('flex flex-col gap-2', className)} {...props} />;
    }
);
SidebarFooter.displayName = 'SidebarFooter';

const SidebarContent = React.forwardRef<HTMLDivElement, React.ComponentProps<'div'>>(
    ({ className, ...props }, ref) => {
        return (
            <div
                ref={ref}
                className={cn(
                    'flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto overflow-x-hidden',
                    className
                )}
                {...props}
            />
        );
    }
);
SidebarContent.displayName = 'SidebarContent';

const SidebarMenu = React.forwardRef<HTMLUListElement, React.ComponentProps<'ul'>>(
    ({ className, ...props }, ref) => (
        <ul ref={ref} className={cn('flex w-full flex-col gap-1', className)} {...props} />
    )
);
SidebarMenu.displayName = 'SidebarMenu';

const SidebarMenuItem = React.forwardRef<HTMLLIElement, React.ComponentProps<'li'>>(
    ({ className, ...props }, ref) => (
        <li ref={ref} className={cn('group/menu-item relative', className)} {...props} />
    )
);
SidebarMenuItem.displayName = 'SidebarMenuItem';

const sidebarMenuButtonVariants = cva(
    'peer/menu-button flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-left text-sm outline-none ring-sidebar-ring transition-colors duration-150 hover:bg-primary/10 hover:text-primary focus-visible:ring-2 active:bg-primary/15 active:text-primary disabled:pointer-events-none disabled:opacity-50 group-has-[[data-sidebar=menu-action]]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:bg-primary data-[active=true]:font-medium data-[active=true]:text-primary-foreground data-[active=true]:shadow-sm data-[active=true]:hover:bg-primary data-[active=true]:hover:text-primary-foreground [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0 group-data-[state=collapsed]:justify-center group-data-[state=collapsed]:gap-0 group-data-[state=collapsed]:[&>span]:hidden',
    {
        variants: {
            size: {
                default: 'h-9 text-sm',
                sm: 'h-8 text-xs',
                lg: 'h-12 text-sm',
            },
        },
        defaultVariants: {
            size: 'default',
        },
    }
);

const SidebarMenuButton = React.forwardRef<
    HTMLButtonElement,
    React.ComponentProps<'button'> & {
        asChild?: boolean;
        isActive?: boolean;
        tooltip?: string | React.ComponentProps<typeof TooltipContent>;
    } & VariantProps<typeof sidebarMenuButtonVariants>
>(({ asChild = false, isActive = false, size, tooltip, className, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';
    const { state } = useSidebar();

    const button = (
        <Comp
            ref={ref}
            data-active={isActive}
            className={cn(sidebarMenuButtonVariants({ size }), className)}
            {...props}
        />
    );

    if (!tooltip || state !== 'collapsed') {
        return button;
    }

    const tooltipProps = typeof tooltip === 'string' ? { children: tooltip } : tooltip;

    return (
        <Tooltip>
            <TooltipTrigger asChild>{button}</TooltipTrigger>
            <TooltipContent side="right" align="center" {...tooltipProps} />
        </Tooltip>
    );
});
SidebarMenuButton.displayName = 'SidebarMenuButton';

const SidebarMenuSub = React.forwardRef<HTMLUListElement, React.ComponentProps<'ul'>>(
    ({ className, ...props }, ref) => (
        <ul
            ref={ref}
            className={cn(
                'mx-3.5 flex min-w-0 translate-x-px flex-col gap-1 border-l border-sidebar-border px-2.5 py-0.5',
                'group-data-[state=collapsed]:hidden',
                className
            )}
            {...props}
        />
    )
);
SidebarMenuSub.displayName = 'SidebarMenuSub';

const SidebarMenuSubItem = React.forwardRef<HTMLLIElement, React.ComponentProps<'li'>>(
    ({ ...props }, ref) => <li ref={ref} {...props} />
);
SidebarMenuSubItem.displayName = 'SidebarMenuSubItem';

const SidebarMenuSubButton = React.forwardRef<
    HTMLAnchorElement,
    React.ComponentProps<'a'> & { asChild?: boolean; isActive?: boolean }
>(({ asChild = false, isActive, className, ...props }, ref) => {
    const Comp = asChild ? Slot : 'a';

    return (
        <Comp
            ref={ref}
            data-active={isActive}
            className={cn(
                'flex h-8 min-w-0 -translate-x-px items-center gap-2 overflow-hidden rounded-md px-2 text-sidebar-foreground outline-none ring-sidebar-ring transition-colors duration-150 hover:bg-primary/10 hover:text-primary focus-visible:ring-2 active:bg-primary/15 active:text-primary disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0',
                'data-[active=true]:bg-primary/15 data-[active=true]:font-medium data-[active=true]:text-primary',
                'text-sm',
                className
            )}
            {...props}
        />
    );
});
SidebarMenuSubButton.displayName = 'SidebarMenuSubButton';

export {
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
};
