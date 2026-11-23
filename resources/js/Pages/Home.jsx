import { Head, Link } from '@inertiajs/react';

export default function Home({ mensagem }) {
  return <main className="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 py-16 text-slate-100">
    <Head title="EscalaSimples" />
    <p className="mb-5 text-sm font-semibold uppercase tracking-widest text-emerald-400">EscalaSimples</p>
    <h1 className="max-w-3xl text-4xl font-bold leading-tight sm:text-6xl">Escalas mais simples para o seu negócio.</h1>
    <p className="mt-6 max-w-2xl text-lg leading-relaxed text-slate-300">Organize o trabalho e as folgas da sua equipe com clareza.</p>
    <div className="mt-10 rounded-xl border border-slate-700 bg-slate-800 p-6">
      <p className="font-medium text-emerald-300">Fundação em desenvolvimento</p>
      <p className="mt-2 text-slate-300">{mensagem}</p>
      <Link href="/sobre" className="mt-5 inline-block text-sm font-semibold text-emerald-400 underline underline-offset-4">Conheça esta etapa</Link>
    </div>
  </main>;
}
