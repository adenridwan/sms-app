import * as React from 'react';
import { Slot } from '@radix-ui/react-slot';
import { cva, VariantProps } from 'class-variance-authority';
import { PanelLeft } from 'lucide-react';

import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

const SIDEBAR_WIDTH = '16rem';
const SIDEBAR_WIDTH_ICON = '3rem';

type SidebarContextType = {
    state: 'expanded' | 'collapsed';
    open: boolean;
    setOpen: (open: boolean) => void;
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

const SidebarProvider = React.forwardRef<
    HTMLDivElement,
    React.ComponentProps<'div'> & { defaultOpen?: boolean }
>(({ defaultOpen = true, className, children, ...props }, ref) => {
    const [open, setOpen] = React.useState(defaultOpen);

    const toggleSidebar = React.useCallback(() => {
        setOpen((prev) => !prev);
    }, []);

    const state = open ? 'expanded' : 'collapsed';

    const contextValue = React.useMemo<SidebarContextType>(
        () => ({ state, open, setOpen, toggleSidebar }),
        [state, open, toggleSidebar]
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
    const { open, setOpen, state } = useSidebar();

    return (
        <>
            {/* Mobile */}
            <Sheet open={open} onOpenChange={setOpen}>
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
                    'group hidden flex-col border-r bg-sidebar text-sidebar-foreground md:flex',
                    'w-[--sidebar-width] transition-[width] duration-200 ease-linear',
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
                className={cn('flex min-h-0 flex-1 flex-col gap-2 overflow-auto', className)}
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
    'peer/menu-button flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-left text-sm outline-none ring-sidebar-ring transition-[width,height,padding] hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 group-has-[[data-sidebar=menu-action]]/menu-item:pr-8 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:bg-sidebar-accent data-[active=true]:font-medium data-[active=true]:text-sidebar-accent-foreground data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0',
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
                'flex h-8 min-w-0 -translate-x-px items-center gap-2 overflow-hidden rounded-md px-2 text-sidebar-foreground outline-none ring-sidebar-ring hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-2 active:bg-sidebar-accent active:text-sidebar-accent-foreground disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&>span:last-child]:truncate [&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:text-sidebar-accent-foreground',
                'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
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
