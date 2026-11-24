import { Head, Link } from '@inertiajs/react';

export default function AuthLayout({ title, children }) {
  return <main className="flex min-h-screen items-center justify-center px-4 py-12 text-slate-100">
    <Head title={title} />
    <div className="w-full max-w-md">
      <Link href="/" className="text-sm font-semibold uppercase tracking-widest text-emerald-400">EscalaSimples</Link>
      <div className="mt-5 rounded-xl border border-slate-700 bg-slate-900 p-6 shadow-xl sm:p-8">
        <h1 className="text-2xl font-bold">{title}</h1>
        {children}
      </div>
    </div>
  </main>;
}
