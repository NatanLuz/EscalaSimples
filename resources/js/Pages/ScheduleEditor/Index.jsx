import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';

const week = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
const field = 'w-full rounded-full border border-slate-700 bg-[#090f1c] px-4 py-2.5 text-sm text-slate-100 focus:border-emerald-400 focus:outline-none';
const newKey = () => globalThis.crypto?.randomUUID?.() || `${Date.now()}-${Math.random()}`;

export default function ScheduleEditor({ company, month, days, employees, employees_truncated, revision, snapshot, reused, holidays, operation_id, flash }) {
  const initial = snapshot || reused;
  const [model, setModel] = useState(initial?.model || '6x1');
  const [daysOff, setDaysOff] = useState(initial?.days_off || [6]);
  const [rows, setRows] = useState(() => (initial?.employees || []).map(row => ({ ...row, key: row.id, edited: row.edited || row.days.map(() => true) })));
  const [name, setName] = useState('');
  const [employeeId, setEmployeeId] = useState('');
  const [menu, setMenu] = useState(false);
  const [dirty, setDirty] = useState(Boolean(reused));
  const [showReuse, setShowReuse] = useState(Boolean(reused));
  const [notice, setNotice] = useState('');
  const [recurringKey, setRecurringKey] = useState(null);
  const form = useForm({});
  const daysOffSet = useMemo(() => new Set(daysOff), [daysOff]);
  const totals = useMemo(() => rows.reduce((total, row) => { row.days.forEach(day => { total[day]++; }); return total; }, { work: 0, off: 0 }), [rows]);
  const available = employees.filter(employee => !rows.some(row => row.id === employee.id));
  const holidayNames = date => holidays.filter(item => item.date === date).map(item => item.name).join(', ');
  const confirmLeave = () => !dirty || window.confirm('Há alterações não salvas. Deseja sair sem salvar?');
  useEffect(() => { const warn = e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } }; window.addEventListener('beforeunload', warn); return () => window.removeEventListener('beforeunload', warn); }, [dirty]);
  const defaultDays = selected => days.map(day => selected.includes(day.weekday) ? 'off' : 'work');
  const updateDefaults = selected => {
    setDaysOff(selected);
    if (selected.length !== 1) setModel('custom');
    setRows(current => current.map(row => ({ ...row, days: row.days.map((value, i) => row.edited[i] || row.recurring_days_off != null ? value : (selected.includes(days[i].weekday) ? 'off' : 'work')) })));
    setDirty(true); setNotice(rows.some(row => row.edited.some(Boolean)) ? 'Os ajustes individuais foram preservados.' : '');
  };
  const add = e => {
    e.preventDefault();
    const existing = employees.find(employee => employee.id === employeeId);
    if (!existing && !name.trim()) return;
    const defaults = defaultDays(daysOff);
    const values = existing ? existing.days.map((value, i) => value === 'unscheduled' ? defaults[i] : value) : defaults;
    setRows(current => [...current, { id: existing?.id || null, key: existing?.id || newKey(), name: existing?.name || name.trim(), days: values, edited: existing ? existing.days.map(value => value !== 'unscheduled') : defaults.map(() => false) }]);
    setName(''); setEmployeeId(''); setDirty(true); setNotice('');
  };
  const toggle = (key, index) => { setRows(current => current.map(row => row.key !== key ? row : { ...row, days: row.days.map((day, i) => i === index ? (day === 'work' ? 'off' : 'work') : day), edited: row.edited.map((value, i) => i === index || value) })); setDirty(true); };
  const updateRecurring = (key, selected) => {
    setRows(current => current.map(row => row.key !== key ? row : { ...row, recurring_days_off: selected, days: row.days.map((value, i) => row.edited[i] ? value : (selected.includes(days[i].weekday) ? 'off' : 'work')) }));
    setDirty(true);
  };
  const startOver = () => {
    if (!window.confirm('Começar do zero? As alterações deste rascunho serão descartadas.')) return;
    setRows([]); setModel('6x1'); setDaysOff([6]); setName(''); setEmployeeId(''); setNotice(''); setShowReuse(false); setDirty(true);
  };
  const submit = e => {
    e.preventDefault();
    form.transform(() => ({ month, model, days_off: daysOff, revision, operation_id, employees: rows.map(({ id, name: employeeName, days: employeeDays, edited, recurring_days_off }) => ({ id, name: employeeName, days: employeeDays, edited, recurring_days_off: recurring_days_off ?? null })) }));
    form.post('/minhas-escalas', { preserveState: false, onSuccess: () => setDirty(false) });
  };
  return <div className="min-h-screen bg-[#101010] px-3 py-3 text-slate-100 sm:px-6"><Head title="Nova escala" /><main className="mx-auto max-w-[760px] rounded-xl border border-slate-700 bg-[#090f1c] px-4 pb-5 pt-4 shadow-sm sm:px-5">
    <header className="flex items-center justify-between border-b border-slate-700 pb-4"><Link href="/minhas-escalas" onClick={e => { if (!confirmLeave()) e.preventDefault(); }} className="text-sm font-bold text-emerald-400">EscalaSimples</Link><div className="relative"><button type="button" onClick={() => setMenu(!menu)} aria-expanded={menu} aria-label="Abrir menu" className="rounded px-2 py-1 text-slate-300 hover:bg-slate-800">☰</button>{menu && <nav aria-label="Menu principal" className="absolute right-0 z-30 mt-2 w-48 rounded-lg border border-slate-700 bg-slate-900 p-2 shadow-xl">{[['Minhas escalas', '/minhas-escalas'], ['Colaboradores', '/colaboradores'], ['Minha empresa', '/dashboard'], ['Configurações', '/feriados']].map(([label, href]) => <Link key={href} href={href} onClick={e => { if (!confirmLeave()) e.preventDefault(); }} className="block rounded px-3 py-2 text-sm hover:bg-slate-800">{label}</Link>)}<button className="block w-full rounded px-3 py-2 text-left text-sm hover:bg-slate-800" onClick={() => { if (confirmLeave()) router.post('/logout'); }}>Sair</button></nav>}</div></header>
    <h1 className="mt-5 text-lg font-bold">Nova escala</h1><p className="mt-3 text-sm text-slate-300">Organize os dias de trabalho e folga da sua equipe.</p>
    {showReuse && <p role="status" className="mt-3 text-xs text-slate-400">Escala reaproveitada do mês anterior. <button type="button" onClick={startOver} className="text-emerald-400 underline hover:text-emerald-300">Começar do zero</button></p>}
    <div className="mt-4"><span className="text-xs font-medium text-slate-300">Empresa</span><div className={`${field} mt-2`} aria-label="Empresa autenticada">{company}</div></div>
    <div className="mt-4 grid gap-3 sm:grid-cols-2"><label className="text-xs font-medium text-slate-300">Mês da escala<input type="month" min="1900-01" max="2100-12" value={month} className={`${field} mt-2`} onChange={e => { if (e.target.value && confirmLeave()) router.get('/minhas-escalas', { month: e.target.value }, { preserveState: false }); }} /></label><label className="text-xs font-medium text-slate-300">Modelo inicial<select className={`${field} mt-2`} value={model} onChange={e => { const value = e.target.value; setModel(value); if (value === '6x1') updateDefaults([6]); else setDirty(true); }}><option value="6x1">6x1</option><option value="custom">Personalizada</option></select></label></div>
    <fieldset className="mt-5"><legend className="text-xs font-semibold text-slate-200">Dias de folga padrão</legend><div className="mt-2 grid grid-cols-7 gap-1">{week.map((day, index) => <button type="button" key={day} aria-pressed={daysOffSet.has(index)} onClick={() => updateDefaults(daysOffSet.has(index) ? daysOff.filter(i => i !== index) : [...daysOff, index].sort())} className={`rounded-md py-2 text-center text-xs ${daysOffSet.has(index) ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-800 text-slate-100'} hover:brightness-110 focus-visible:outline-2 focus-visible:outline-emerald-400`}><span className="block">{day}</span><span aria-hidden="true" className="mt-1 block">{daysOffSet.has(index) ? '✓' : '●'}</span></button>)}</div>{notice && <p role="status" className="mt-2 text-xs text-amber-300">{notice}</p>}</fieldset>
    <section className="mt-5 border-t border-slate-700 pt-5"><div className="flex justify-between text-xs"><h2 className="font-semibold">Colaboradores</h2><span>{rows.length} {rows.length === 1 ? 'pessoa' : 'pessoas'}</span></div><form onSubmit={add} className="mt-3 flex gap-2"><label className="sr-only" htmlFor="new-employee">Nome do colaborador</label><input id="new-employee" maxLength={255} className={field} placeholder="Nome do colaborador" value={name} disabled={!!employeeId} onChange={e => { setName(e.target.value); setDirty(true); }} /><button className="shrink-0 rounded-full border border-slate-700 px-3 text-xs hover:border-emerald-400">＋ Adicionar</button></form>{available.length > 0 && <label className="mt-2 block text-xs text-slate-400">Ou selecione alguém já cadastrado<select className={`${field} mt-1`} value={employeeId} onChange={e => { setEmployeeId(e.target.value); setName(''); setDirty(true); }}><option value="">Selecionar colaborador</option>{available.map(item => <option key={item.id} value={item.id}>{item.name} · {item.id.slice(-6)}</option>)}</select></label>}
      {employees_truncated && <p role="status" className="mt-2 text-xs text-amber-300">A seleção mostra os primeiros 1.000 colaboradores ativos.</p>}
      <div className="mt-4 overflow-x-auto" style={{ scrollbarColor: '#64748b #0f172a', scrollbarWidth: 'thin' }}><table className="border-separate border-spacing-1 text-xs"><thead><tr><th scope="col" className="sticky left-0 z-10 min-w-36 bg-[#090f1c] text-left font-normal text-slate-300">Colaborador</th>{days.map(day => <th key={day.date} scope="col" className={`min-w-9 w-9 text-center font-normal ${day.weekday >= 5 ? 'rounded bg-slate-800/60 text-slate-200' : 'text-slate-300'} ${day.weekday === 0 && day.day !== 1 ? 'border-l border-slate-700' : ''}`} title={holidayNames(day.date) || day.date}><span className="block text-[10px]">{week[day.weekday]}</span><span className="block text-xs font-semibold text-slate-100">{String(day.day).padStart(2, '0')}{holidayNames(day.date) && <span className="text-amber-300">•</span>}</span></th>)}</tr></thead><tbody>{rows.map(row => <tr key={row.key}><th scope="row" className="sticky left-0 z-10 bg-[#090f1c] pr-2 text-left font-semibold"><span className="flex items-center justify-between gap-2"><button type="button" className="max-w-28 truncate text-left underline decoration-slate-600 hover:text-emerald-300" title={row.name} aria-label={`Configurar folgas recorrentes de ${row.name}`} onClick={() => setRecurringKey(recurringKey === row.key ? null : row.key)}>{row.name}</button><button type="button" onClick={() => { setRows(current => current.filter(item => item.key !== row.key)); setDirty(true); }} aria-label={`Remover ${row.name} desta escala`} title="Remover da escala" className="text-slate-400 hover:text-rose-300">×</button></span></th>{row.days.map((value, index) => <td key={days[index].date} className={`${days[index].weekday >= 5 ? 'rounded bg-slate-800/40' : ''} ${days[index].weekday === 0 && days[index].day !== 1 ? 'border-l border-slate-700' : ''}`}><button type="button" onClick={() => toggle(row.key, index)} aria-label={`${row.name}, ${days[index].date}: ${value === 'work' ? 'trabalho' : 'folga'}. Alterar`} className={`h-8 w-9 rounded text-[10px] font-semibold focus-visible:outline-2 focus-visible:outline-emerald-300 ${value === 'off' ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-800 text-slate-100'}`}>{value === 'work' ? 'T' : 'F'}</button></td>)}</tr>)}</tbody></table>{!rows.length && <p className="py-4 text-xs text-slate-400">Adicione colaboradores para começar a montar a escala.</p>}</div>
      {rows.filter(row => row.key === recurringKey).map(row => <fieldset key={row.key} className="mt-3 rounded-lg border border-slate-700 p-3"><legend className="px-1 text-xs font-semibold">Folgas recorrentes - {row.name}</legend><p className="text-xs text-slate-400">Escolha os dias que se repetem. Os ajustes feitos nas células serão preservados.</p><div className="mt-2 grid grid-cols-7 gap-1">{week.map((day, i) => { const selected = row.recurring_days_off ?? daysOff; return <button type="button" key={day} aria-pressed={selected.includes(i)} onClick={() => updateRecurring(row.key, selected.includes(i) ? selected.filter(value => value !== i) : [...selected, i].sort())} className={`rounded-md py-2 text-xs ${selected.includes(i) ? 'bg-emerald-800 text-emerald-100' : 'bg-slate-800 text-slate-100'}`}>{day}<span aria-hidden="true" className="mt-1 block">{selected.includes(i) ? '✓' : '●'}</span></button>; })}</div><button type="button" onClick={() => setRecurringKey(null)} className="mt-2 text-xs text-emerald-400 underline">Concluído</button></fieldset>)}
      <p className="mt-3 text-xs text-slate-300"><span className="text-slate-500">●</span> Trabalho <span className="ml-3 text-emerald-600">●</span> Folga</p><p className="mt-3 text-xs text-slate-300">Clique em qualquer dia para alternar entre trabalho e folga.</p>
      <div className="mt-3 grid grid-cols-2 gap-2"><div className="rounded-lg bg-slate-800 p-3"><span className="block text-xs text-slate-300">Dias de trabalho</span><strong className="mt-1 block">{totals.work}</strong></div><div className="rounded-lg bg-slate-800 p-3"><span className="block text-xs text-slate-300">Folgas programadas</span><strong className="mt-1 block text-emerald-300">{totals.off}</strong></div></div>
      <form onSubmit={submit}><button disabled={form.processing} className="mt-3 w-full rounded-md bg-emerald-500 px-4 py-3 text-sm font-semibold text-slate-950 hover:bg-emerald-400 disabled:opacity-50">{form.processing ? 'Salvando…' : '▣ Salvar escala'}</button></form>{flash && !dirty && <p role="status" className="mt-3 text-xs text-emerald-400">{flash}</p>}{dirty && <p role="status" className="mt-3 text-xs text-slate-400">Alterações não salvas.</p>}{Object.values(form.errors).map((error, index) => <p role="alert" key={index} className="mt-2 text-xs text-rose-300">{error}</p>)}
    </section>
  </main></div>;
}
