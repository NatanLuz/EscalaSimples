import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../../Layouts/AppLayout.jsx';

export default function Index({ items, filters, flash }) {
  const form = useForm({ search: filters.search || '' });
  return <AppLayout title="Jornadas"><div className="flex flex-wrap justify-between gap-4"><h1 className="text-3xl font-bold">Padrões de jornada</h1><div className="flex gap-3"><Link href="/atribuicoes" className="rounded border border-slate-600 px-4 py-2">Atribuições e exceções</Link><Link href="/jornadas/create" className="rounded bg-emerald-400 px-4 py-2 font-semibold text-slate-950">Novo padrão</Link></div></div>
    {flash && <p role="status" className="mt-5 text-emerald-300">{flash}</p>}
    <form className="mt-6 flex gap-3" onSubmit={e => { e.preventDefault(); router.get('/jornadas', form.data); }}><label>Pesquisar <input className="rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.search} onChange={e => form.setData('search', e.target.value)} /></label><button className="rounded border border-slate-600 px-4">Buscar</button></form>
    <div className="mt-6 space-y-3">{items.data.map(item => <div key={item.id} className="rounded border border-slate-700 p-4"><div className="flex justify-between gap-3"><strong>{item.name}</strong><Link className="text-emerald-400 underline" href={`/jornadas/${item.id}/edit`}>Editar</Link></div><p>{item.is_active ? 'Ativo' : 'Inativo'} · {item.version?.kind === 'weekly' ? 'Semanal, a partir de segunda' : 'Ciclo contínuo'}</p><p className="text-sm text-slate-300">{item.version?.sequence.map((work, i) => `${i + 1}: ${work ? 'Trabalho' : 'Folga'}`).join(' · ')}</p></div>)}{!items.data.length && <p>Nenhum padrão encontrado.</p>}</div>
    <nav className="mt-5 flex gap-4" aria-label="Paginação">{items.prev_page_url && <Link href={items.prev_page_url}>Anterior</Link>}<span>Página {items.current_page} de {items.last_page}</span>{items.next_page_url && <Link href={items.next_page_url}>Próxima</Link>}</nav>
  </AppLayout>;
}
