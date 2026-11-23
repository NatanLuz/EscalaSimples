import { Head, Link } from '@inertiajs/react';

export default function Sobre() {
  return <main className="mx-auto flex min-h-screen max-w-5xl flex-col justify-center px-6 text-slate-100">
    <Head title="Sobre" />
    <h1 className="text-3xl font-bold">Uma base para organizar escalas</h1>
    <p className="mt-4 text-slate-300">Esta é a primeira etapa do EscalaSimples.</p>
    <Link href="/" className="mt-6 text-emerald-400 underline underline-offset-4">Voltar ao início</Link>
  </main>;
}
