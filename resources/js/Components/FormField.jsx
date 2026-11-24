export default function FormField({ id, label, error, ...props }) {
  return <div>
    <label htmlFor={id} className="mb-2 block text-sm font-medium text-slate-200">{label}</label>
    <input id={id} name={id} aria-invalid={Boolean(error)} aria-describedby={error ? `${id}-error` : undefined}
      className="w-full rounded-lg border border-slate-600 bg-slate-950 px-3 py-2.5 text-slate-100 outline-none focus:border-emerald-400 focus:ring-2 focus:ring-emerald-400/30" {...props} />
    {error && <p id={`${id}-error`} role="alert" className="mt-1 text-sm text-rose-300">{error}</p>}
  </div>;
}
