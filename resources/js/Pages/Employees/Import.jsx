import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../Layouts/AppLayout.jsx';

export default function Import({ preview }) {
  const form = useForm({ file: null });
  const [confirming, setConfirming] = useState(false);
  const [confirmErrors, setConfirmErrors] = useState({});
  function confirm() {
    if (!form.data.file || !window.confirm('Confirmar a importação dos colaboradores?')) return;
    setConfirming(true); setConfirmErrors({});
    router.post('/colaboradores/importar/confirmar', { file: form.data.file, token: preview.token }, { forceFormData: true, onError: setConfirmErrors, onFinish: () => setConfirming(false) });
  }
  return <AppLayout title="Importar colaboradores"><h1 className="text-3xl font-bold">Importar colaboradores</h1>
    <p className="mt-3 text-slate-300">Envie um CSV UTF-8 com as colunas nome e setor. Os setores devem estar ativos.</p>
    <p className="mt-2 text-sm text-slate-400">Nomes repetidos podem pertencer a pessoas diferentes; revise possíveis homônimos antes de confirmar.</p>
    <a href="/colaboradores/modelo-csv" className="mt-3 inline-block text-emerald-400 underline">Baixar modelo CSV</a>
    <form onSubmit={e => { e.preventDefault(); form.post('/colaboradores/importar/previa', { forceFormData: true, preserveState: true }); }} className="mt-6 space-y-4 rounded-xl border border-slate-700 bg-slate-900 p-6">
      <label htmlFor="csv" className="block text-sm font-medium">Arquivo CSV</label>
      <input id="csv" type="file" accept=".csv,text/csv" required onChange={e => form.setData('file', e.target.files[0] || null)} aria-describedby={form.errors.file || confirmErrors.file || confirmErrors.token ? 'csv-error' : undefined} />
      {(form.errors.file || confirmErrors.file || confirmErrors.token) && <p id="csv-error" role="alert" className="text-sm text-rose-300">{form.errors.file || confirmErrors.file || confirmErrors.token}</p>}
      <div><button disabled={form.processing || confirming} className="rounded bg-emerald-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">{form.processing ? 'Validando…' : 'Validar arquivo'}</button></div>
    </form>
    {preview && <section className="mt-6" aria-labelledby="preview-title"><h2 id="preview-title" className="text-xl font-bold">Prévia</h2>
      <p className="mt-2 text-slate-300">{preview.total} linhas válidas; {preview.error_count} com erro. Exibindo até 50 de cada.</p>
      {preview.duplicates > 0 && <p className="mt-2 text-amber-300">{preview.duplicates} nomes semelhantes no arquivo ou em registros existentes: podem ser homônimos.</p>}
      {preview.errors.length > 0 && <ul className="mt-4 space-y-1 text-rose-300">{preview.errors.map((e, i) => <li key={i}>Linha {e.line}: {e.message}</li>)}</ul>}
      <div className="mt-4 overflow-x-auto"><table className="w-full text-left text-sm"><thead><tr><th className="p-2">Linha</th><th className="p-2">Nome</th><th className="p-2">Setor</th><th className="p-2">Aviso</th></tr></thead><tbody>{preview.rows.map((row, i) => <tr key={i} className="border-t border-slate-700"><td className="p-2">{row.line}</td><td className="p-2">{row.name}</td><td className="p-2">{row.department}</td><td className="p-2">{row.possible_duplicate ? 'Nome repetido' : '—'}</td></tr>)}</tbody></table></div>
      {preview.token && <button type="button" disabled={confirming || form.processing} onClick={confirm} className="mt-5 rounded bg-emerald-400 px-4 py-2 font-semibold text-slate-950 disabled:opacity-50">{confirming ? 'Importando…' : 'Confirmar importação'}</button>}
    </section>}
    <Link href="/colaboradores" className="mt-8 inline-block text-emerald-400 underline">Voltar aos colaboradores</Link>
  </AppLayout>;
}
