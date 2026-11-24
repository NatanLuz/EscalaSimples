import { Head, Link } from '@inertiajs/react';

export default function AppLayout({ title, children }) {
  return <div className="min-h-screen text-slate-100"><Head title={title} />
    <header className="border-b border-slate-800 bg-slate-900"><div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5">
      <Link href="/dashboard" className="font-bold tracking-wide text-emerald-400">EscalaSimples</Link>
      <nav aria-label="Navegação principal" className="flex flex-wrap items-center gap-4 text-sm">
        <Link href="/dashboard">Painel</Link><Link href="/setores">Setores</Link><Link href="/colaboradores">Colaboradores</Link>
        <Link href="/logout" method="post" as="button" className="rounded-md border border-slate-600 px-3 py-2 hover:bg-slate-800">Sair</Link>
      </nav></div></header>
    <main className="mx-auto max-w-6xl px-5 py-10">{children}</main>
  </div>;
}
