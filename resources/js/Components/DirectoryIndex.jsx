import { Link, router, useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout.jsx';
import { useEffect, useState } from 'react';

export default function DirectoryIndex({ kind, items, filters, departments = [], flash }) {
  const employee = kind === 'colaboradores';
  const title = employee ? 'Colaboradores' : 'Setores';
  const form = useForm({ search: filters.search || '', status: filters.status || '', department: filters.department || '' });
  const [selected, setSelected] = useState([]);
  const bulk = useForm({ employees: [], action: 'activate', department: '' });
  useEffect(() => { setSelected([]); }, [items.current_page, filters.search, filters.status, filters.department]);
  return <AppLayout title={title}><div className="flex flex-wrap items-center justify-between gap-4"><h1 className="text-3xl font-bold">{title}</h1><div className="flex flex-wrap gap-4">{employee && <Link href="/colaboradores/importar" className="rounded-lg border border-slate-600 px-4 py-2">Importar CSV</Link>}<Link href={`/${kind}/create`} className="rounded-lg bg-emerald-400 px-4 py-2 font-semibold text-slate-950">Adicionar {employee ? 'colaborador' : 'setor'}</Link></div></div>
    {flash && <p role="status" className="mt-5 rounded-lg bg-emerald-950 p-3 text-emerald-200">{flash}</p>}
    <form className="mt-6 flex flex-wrap items-end gap-3" onSubmit={e => { e.preventDefault(); router.get(`/${kind}`, form.data, { preserveState: true, replace: true }); }}>
      <label className="text-sm">Pesquisar<input aria-label="Pesquisar por nome" className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.search} onChange={e => form.setData('search', e.target.value)} /></label>
      <label className="text-sm">Situação<select className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.status} onChange={e => form.setData('status', e.target.value)}><option value="">Todas</option><option value="active">Ativos</option><option value="inactive">Inativos</option></select></label>
      {employee && <label className="text-sm">Setor<select className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={form.data.department} onChange={e => form.setData('department', e.target.value)}><option value="">Todos</option>{departments.map(d => <option key={d.id} value={d.id}>{d.name}{!d.is_active ? ' (inativo)' : ''}</option>)}</select></label>}
      <button className="rounded border border-slate-600 px-4 py-2">Filtrar</button>
    </form>
    {employee && <form className="mt-6 flex flex-wrap items-end gap-3" onSubmit={e => { e.preventDefault(); if (!selected.length || !window.confirm(`Aplicar alteração a ${selected.length} colaborador(es)?`)) return; bulk.transform(data => ({ ...data, employees: selected })).post('/colaboradores/lote', { onSuccess: () => setSelected([]) }); }}>
      <label className="text-sm">Ação em lote<select className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={bulk.data.action} onChange={e => bulk.setData('action', e.target.value)}><option value="activate">Ativar</option><option value="deactivate">Desativar</option><option value="department">Alterar setor</option></select></label>
      {bulk.data.action === 'department' && <label className="text-sm">Novo setor<select required className="mt-1 block rounded border border-slate-600 bg-slate-900 px-3 py-2" value={bulk.data.department} onChange={e => bulk.setData('department', e.target.value)}><option value="">Selecione</option>{departments.filter(d => d.is_active).map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select></label>}
      <button disabled={!selected.length || bulk.processing} className="rounded border border-slate-600 px-4 py-2 disabled:opacity-50">Aplicar a {selected.length} selecionado(s)</button>
      {(bulk.errors.employees || bulk.errors.department || bulk.errors.action) && <p role="alert" className="w-full text-rose-300">{bulk.errors.employees || bulk.errors.department || bulk.errors.action}</p>}
    </form>}
    <div className="mt-6 overflow-x-auto rounded-xl border border-slate-700"><table className="w-full text-left text-sm"><thead className="bg-slate-800"><tr>{employee && <th className="p-4"><input type="checkbox" aria-label="Selecionar todos nesta página" checked={items.data.length > 0 && selected.length === items.data.length} onChange={e => setSelected(e.target.checked ? items.data.map(item => item.id) : [])} /></th>}<th className="p-4">Nome</th>{employee && <th className="p-4">Setor</th>}<th className="p-4">Situação</th><th className="p-4">Ações</th></tr></thead><tbody>
      {items.data.map(item => <tr key={item.id} className="border-t border-slate-700">{employee && <td className="p-4"><input type="checkbox" aria-label={`Selecionar ${item.name}`} checked={selected.includes(item.id)} onChange={e => setSelected(e.target.checked ? [...selected, item.id] : selected.filter(id => id !== item.id))} /></td>}<td className="p-4">{item.name}</td>{employee && <td className="p-4">{item.department ? `${item.department.name}${item.department.is_active ? '' : ' (inativo)'}` : 'Sem setor'}</td>}<td className="p-4">{item.is_active ? 'Ativo' : 'Inativo'}</td><td className="p-4"><Link className="text-emerald-400 underline" href={`/${kind}/${item.id}/edit`}>Editar</Link></td></tr>)}
    </tbody></table>{items.data.length === 0 && <p className="p-6 text-slate-400">Nenhum registro encontrado.</p>}</div>
    <nav aria-label="Paginação" className="mt-5 flex flex-wrap items-center gap-3 text-sm"><span>Página {items.current_page} de {items.last_page}</span>{items.prev_page_url && <Link href={items.prev_page_url} className="text-emerald-400 underline">Anterior</Link>}{items.next_page_url && <Link href={items.next_page_url} className="text-emerald-400 underline">Próxima</Link>}</nav>
  </AppLayout>;
}
