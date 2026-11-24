import { Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Layouts/AuthLayout.jsx';
import FormField from '../../Components/FormField.jsx';

export default function Register() {
  const { data, setData, post, processing, errors, reset } = useForm({ company_name: '', name: '', email: '', password: '', password_confirmation: '' });
  const submit = event => {
    event.preventDefault();
    post('/cadastro', { onFinish: () => reset('password', 'password_confirmation') });
  };
  return <AuthLayout title="Cadastrar empresa">
    <p className="mt-2 text-sm text-slate-400">Crie o acesso do administrador da sua empresa.</p>
    <form onSubmit={submit} className="mt-6 space-y-4">
      <FormField id="company_name" label="Nome da empresa" autoComplete="organization" required value={data.company_name} onChange={e => setData('company_name', e.target.value)} error={errors.company_name} />
      <FormField id="name" label="Nome do administrador" autoComplete="name" required value={data.name} onChange={e => setData('name', e.target.value)} error={errors.name} />
      <FormField id="email" label="E-mail" type="email" autoComplete="email" required value={data.email} onChange={e => setData('email', e.target.value)} error={errors.email} />
      <FormField id="password" label="Senha" type="password" autoComplete="new-password" required minLength={8} value={data.password} onChange={e => setData('password', e.target.value)} error={errors.password} />
      <FormField id="password_confirmation" label="Confirme a senha" type="password" autoComplete="new-password" required value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)} error={errors.password_confirmation} />
      <button disabled={processing} className="w-full rounded-lg bg-emerald-400 px-4 py-3 font-semibold text-slate-950 hover:bg-emerald-300 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-300 disabled:opacity-50">{processing ? 'Criando acesso…' : 'Criar acesso'}</button>
    </form>
    <p className="mt-6 text-sm text-slate-400">Já possui acesso? <Link href="/login" className="font-medium text-emerald-400 underline underline-offset-4">Entrar</Link></p>
  </AuthLayout>;
}
