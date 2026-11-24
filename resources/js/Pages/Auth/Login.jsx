import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout.jsx';
import FormField from '../../Components/FormField.jsx';

export default function Login() {
  const { data, setData, post, processing, errors, reset } = useForm({ email: '', password: '' });
  const submit = event => {
    event.preventDefault();
    post('/login', { onFinish: () => reset('password') });
  };
  return <AuthLayout title="Entrar">
    <p className="mt-2 text-sm text-slate-400">Acesse a área da sua empresa.</p>
    <form onSubmit={submit} className="mt-6 space-y-4">
      <FormField id="email" label="E-mail" type="email" autoComplete="email" required value={data.email} onChange={e => setData('email', e.target.value)} error={errors.email} />
      <FormField id="password" label="Senha" type="password" autoComplete="current-password" required value={data.password} onChange={e => setData('password', e.target.value)} error={errors.password} />
      <button disabled={processing} className="w-full rounded-lg bg-emerald-400 px-4 py-3 font-semibold text-slate-950 hover:bg-emerald-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300 disabled:opacity-50">{processing ? 'Entrando…' : 'Entrar'}</button>
    </form>
    <p className="mt-6 text-sm text-slate-400">Ainda não tem acesso? <Link href="/cadastro" className="font-medium text-emerald-400 underline underline-offset-4">Cadastrar empresa</Link></p>
  </AuthLayout>;
}
