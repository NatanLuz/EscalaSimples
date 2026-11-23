# Instruções para agentes

## Arquitetura e desenvolvimento

- Use Laravel 12, PHP 8.2+, React 19, Inertia.js 2, Tailwind CSS 4 e MySQL. Preserve o monólito Laravel + Inertia e use esbuild; não introduza Vite.
- Analise a implementação existente antes de alterar arquivos e siga as convenções do repositório. Mantenha as mudanças no escopo solicitado.
- Prefira código claro e sustentável; evite abstrações, dependências, complexidade e comentários redundantes.
- Valide os dados no backend e aplique autenticação, autorização e isolamento entre empresas. Não confie em identificadores de empresa fornecidos livremente pelo frontend.
- Use apenas dados fictícios, sem dados reais ou sensíveis, durante o desenvolvimento inicial.

## Qualidade e testes

- Crie ou atualize testes relevantes quando necessário e execute as verificações relacionadas às alterações, incluindo PHPUnit e Laravel Pint quando aplicável. Consulte o README para os comandos do projeto.
- Não afirme que algo foi testado sem executar a verificação. Informe resultados, falhas, limitações e verificações pendentes.

## Git e segurança

- Use Conventional Commits com mensagens em português brasileiro e revise as alterações antes de propor um commit.
- Nunca inclua credenciais, senhas, chaves ou arquivos `.env` em commits.
- Não execute commit, push, merge ou deploy sem autorização explícita do usuário.

## Comunicação

- Responda em português brasileiro, com explicações técnicas objetivas e profissionais.
- Ao concluir uma tarefa, informe o que foi implementado, os testes executados e as pendências.
