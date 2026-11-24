import { Head, Link } from '@inertiajs/react';

export default function Dashboard({ company, administrator }) {
  return <div className="min-h-screen text-slate-100">
    <Head title="Painel" />
    <header className="border-b border-slate-800 bg-slate-900">
      <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-5">
        <span className="font-bold tracking-wide text-emerald-400">EscalaSimples</span>
        <nav aria-label="Navegação principal" className="flex items-center gap-5 text-sm">
          <Link href="/dashboard" aria-current="page" className="font-medium text-slate-100">Painel</Link>
          <Link href="/logout" method="post" as="button" className="rounded-md border border-slate-600 px-3 py-2 text-slate-200 hover:bg-slate-800">Sair</Link>
        </nav>
      </div>
    </header>
    <main className="mx-auto max-w-6xl px-5 py-12">
      <p className="text-sm font-medium text-emerald-400">Painel da empresa</p>
      <h1 className="mt-3 break-words text-3xl font-bold sm:text-4xl">{company.name}</h1>
      <p className="mt-4 text-slate-300">Olá, {administrator.name}.</p>
      <div className="mt-10 rounded-xl border border-slate-700 bg-slate-900 p-6">
        <h2 className="text-lg font-semibold">Seu espaço está pronto</h2>
        <p className="mt-2 text-sm leading-relaxed text-slate-400">As ferramentas para organizar sua equipe e suas escalas serão adicionadas nas próximas etapas.</p>
      </div>
    </main>
  </div>;
}
