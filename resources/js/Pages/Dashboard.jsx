import { Link } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout.jsx';

export default function Dashboard({ company, administrator }) {
  return <AppLayout title="Painel"><p className="text-sm font-medium text-emerald-400">Painel da empresa</p>
    <h1 className="mt-3 break-words text-3xl font-bold sm:text-4xl">{company.name}</h1>
    <p className="mt-4 text-slate-300">Olá, {administrator.name}.</p>
    <div className="mt-10 flex flex-wrap gap-4"><Link className="rounded-lg border border-slate-700 bg-slate-900 p-5 hover:border-emerald-400" href="/setores">Gerenciar setores</Link><Link className="rounded-lg border border-slate-700 bg-slate-900 p-5 hover:border-emerald-400" href="/colaboradores">Gerenciar colaboradores</Link></div>
  </AppLayout>;
}
