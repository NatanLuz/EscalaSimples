import { Link, useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout.jsx';
import FormField from './FormField.jsx';

export default function DirectoryForm({ kind, item, departments = [] }) {
  const employee = kind === 'colaboradores';
  const label = employee ? 'colaborador' : 'setor';
  const form = useForm({ name: item?.name || '', is_active: item?.is_active ?? true, department: item?.department || '' });
  function submit(e) {
    e.preventDefault();
    if (item?.is_active && !form.data.is_active && !window.confirm(`Deseja desativar este ${label}?`)) return;
    if (item) form.put(`/${kind}/${item.id}`); else form.post(`/${kind}`);
  }
  return <AppLayout title={`${item ? 'Editar' : 'Adicionar'} ${label}`}><h1 className="text-3xl font-bold">{item ? 'Editar' : 'Adicionar'} {label}</h1>
    <form onSubmit={submit} className="mt-8 max-w-xl space-y-5 rounded-xl border border-slate-700 bg-slate-900 p-6">
      <FormField id="name" label="Nome" required maxLength={255} value={form.data.name} onChange={e => form.setData('name', e.target.value)} error={form.errors.name} />
      {employee && <div><label htmlFor="department" className="mb-2 block text-sm font-medium">Setor</label><select id="department" value={form.data.department} onChange={e => form.setData('department', e.target.value)} aria-invalid={Boolean(form.errors.department)} aria-describedby={form.errors.department ? 'department-error' : undefined} className="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2.5"><option value="">Sem setor</option>{departments.filter(d => d.is_active || d.id === item?.department).map(d => <option key={d.id} value={d.id}>{d.name}{d.is_active ? '' : ' (inativo)'}</option>)}</select>{form.errors.department && <p id="department-error" role="alert" className="mt-1 text-sm text-rose-300">{form.errors.department}</p>}</div>}
      <label className="flex items-center gap-2"><input type="checkbox" checked={form.data.is_active} onChange={e => form.setData('is_active', e.target.checked)} /> Ativo</label>
      {form.errors.is_active && <p role="alert" className="text-sm text-rose-300">{form.errors.is_active}</p>}
      <div className="flex gap-4"><button disabled={form.processing} className="rounded-lg bg-emerald-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">Salvar</button><Link href={`/${kind}`} className="px-4 py-2 text-slate-300 underline">Cancelar</Link></div>
    </form>
  </AppLayout>;
}
