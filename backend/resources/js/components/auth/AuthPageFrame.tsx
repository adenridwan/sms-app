import { ReactNode } from 'react';
import { ShieldCheck, Sparkles } from 'lucide-react';

interface AuthPageFrameProps {
    badge: string;
    title: string;
    description: string;
    footer: ReactNode;
    children: ReactNode;
}

export function AuthPageFrame({ badge, title, description, footer, children }: AuthPageFrameProps) {
    return (
        <div className="relative isolate min-h-screen overflow-hidden bg-slate-950 p-4 sm:p-6 lg:p-8">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_rgba(59,130,246,0.35),_transparent_33%),radial-gradient(circle_at_bottom_right,_rgba(20,184,166,0.2),_transparent_38%)]" />
            <div className="absolute -left-24 top-1/4 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl" />
            <div className="absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl" />
            <div className="relative mx-auto grid min-h-[calc(100vh-2rem)] max-w-6xl overflow-hidden rounded-[2rem] border border-white/10 bg-white/5 shadow-2xl shadow-slate-950/40 backdrop-blur-sm lg:grid-cols-[1.08fr_0.92fr]">
                <section className="relative hidden overflow-hidden bg-gradient-to-br from-blue-600 via-blue-700 to-slate-950 p-10 text-white lg:flex lg:flex-col">
                    <div className="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,0.12)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.12)_1px,transparent_1px)] [background-size:42px_42px]" />
                    <div className="relative flex items-center gap-3 text-lg font-semibold tracking-tight"><div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/25"><Sparkles className="h-5 w-5" /></div>SMS Enterprise</div>
                    <div className="relative my-auto max-w-md"><span className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-medium"><ShieldCheck className="h-3.5 w-3.5" /> {badge}</span><h1 className="mt-6 text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">{title}</h1><p className="mt-5 text-base leading-7 text-blue-100">{description}</p></div>
                    <div className="relative rounded-2xl border border-white/15 bg-slate-950/20 p-5 backdrop-blur">{footer}</div>
                </section>
                <section className="flex items-center justify-center bg-background/95 px-5 py-10 sm:px-10 lg:px-12 dark:bg-slate-950/95">{children}</section>
            </div>
        </div>
    );
}
