import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout.jsx';

export default function DirectoryIndex({ kind, items, filters, departments = [], flash }) {
  const employee = kind === 'colaboradores';
  const title = employee ? 'Colaboradores' : 'Setores';
  const form = useForm({ search: filters.search || '', status: filters.status || '', department: filters.department || '' });
  return <AppLayout title={title}><div className="flex flex-wrap items-center justify-between gap-4"><h1 className="text-3xl font-bold">{title}</h1><Link href={`/${kind}/create`} className="rounded-lg bg-emerald-400 px-4 py-2 font-semibold text-slate-950">Adicionar {employee ? 'colaborador' : 'setor'}</Link></div>
    {flash && <p role="status" className="mt-5 rounded-lg bg-emerald-950 p-3 text-emerald-200">{flash}</p>}
    <form className="mt-6 flex flex-wrap items-end gap-3" onSubmit={e => { e.preventDefault(); router.get(`/${kind}`, form.data, { preserveState: true, replace: true }); }}>
      <label className="text-sm">Pesquisar<input aria-label="Pesquisar por nome" className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.search} onChange={e => form.setData('search', e.target.value)} /></label>
      <label className="text-sm">Situação<select className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="">Todas</option><option value="active">Ativos</option><option value="inactive">Inativos</option></select></label>
      {employee && <label className="text-sm">Setor<select className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.department} onChange={e => form.setData('department', e.target.value)}><option value="">Todos</option>{departments.map(d => <option key={d.id} value={d.id}>{d.name}{!d.is_active ? ' (inativo)' : ''}</option>)}</select></label>}
      <button className="rounded border border-slate-600 px-4 py-2">Filtrar</button>
    </form>
    <div className="mt-6 overflow-x-auto rounded-xl border border-slate-700"><table className="w-full text-left text-sm"><thead className="bg-slate-800"><tr><th className="p-4">Nome</th>{employee && <th className="p-4">Setor</th>}<th className="p-4">Situação</th><th className="p-4">Ações</th></tr></thead><tbody>
      {items.data.map(item => <tr key={item.id} className="border-t border-slate-700"><td className="p-4">{item.name}</td>{employee && <td className="p-4">{item.department ? `${item.department.name}${item.department.is_active ? '' : ' (inativo)'}` : 'Sem setor'}</td>}<td className="p-4">{item.is_active ? 'Ativo' : 'Inativo'}</td><td className="p-4"><Link className="text-emerald-400 underline" href={`/${kind}/${item.id}/edit`}>Editar</Link></td></tr>)}
    </tbody></table>{items.data.length === 0 && <p className="p-6 text-slate-400">Nenhum registro encontrado.</p>}</div>
    <nav aria-label="Paginação" className="mt-5 flex flex-wrap items-center gap-3 text-sm"><span>Página {items.current_page} de {items.last_page}</span>{items.prev_page_url && <Link href={items.prev_page_url} className="text-emerald-400 underline">Anterior</Link>}{items.next_page_url && <Link href={items.next_page_url} className="text-emerald-400 underline">Próxima</Link>}</nav>
  </AppLayout>;
}
