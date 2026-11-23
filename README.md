# EscalaSimples

Fundação de uma aplicação web para organizar escalas de trabalho e folgas. Esta etapa demonstra a integração técnica; autenticação, isolamento entre empresas e funcionalidades de escala ainda não foram implementados.

## Tecnologias e requisitos

- PHP 8.2 ou superior, com extensões exigidas pelo Laravel e PDO MySQL.
- Composer 2, Node.js 24 e npm; Laravel 12, React 19, Inertia 2, esbuild e Tailwind CSS 4.
- MySQL local. O MariaDB do XAMPP pode ser usado para desenvolvimento, mas a compatibilidade com MySQL deve ser testada separadamente.

O Laravel 12 recebe correções de segurança até 24/02/2027. Planeje atualizar o PHP para 8.3 ou superior e migrar para uma versão mais recente do Laravel. Consulte os requisitos da versão escolhida.

## Instalação no Windows

No PowerShell, na pasta do projeto:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
npm run build
```

Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no arquivo `.env` conforme sua instalação. Crie o banco local previamente e inicie o servidor MySQL; então execute `php artisan migrate`. Nunca versione o `.env`. Sessões e cache usam arquivos durante o desenvolvimento.

Se `node` ou `npm` não funcionar por conflito no PATH, verifique com `Get-Command node,npm -All` e coloque a pasta da instalação do Node antes dos atalhos conflitantes. Na sessão atual pode-se usar `$env:PATH = 'C:\Program Files\nodejs;' + $env:PATH`.

## Desenvolvimento e produção

Em terminais separados, execute `php artisan serve` e `npm run dev`, e abra `http://127.0.0.1:8000`. O segundo comando observa alterações no JavaScript e no CSS; atualize a página no navegador após a recompilação. `npm run build` gera os arquivos minificados em `public/build`. Gere o build antes de iniciar a aplicação ou executar os testes de integração.

O esbuild agrupa JSX e JavaScript; o Tailwind CLI gera o CSS. O Blade carrega os arquivos produzidos e um hash de ambos é usado para versionar os assets do Inertia e invalidar o cache. A resolução de páginas é explícita, adequada à base atual. Não há hot module replacement nem integração automática com o manifesto Vite; expansão para divisão de bundles e atualização em tempo real exigirá configuração adicional.

## Testes

Execute `php artisan test` e `vendor/bin/pint --test` (no Windows, `vendor\bin\pint.bat --test`). Os testes HTTP usam a configuração em `phpunit.xml` e não validam a conexão MySQL. A renderização visual deve ser verificada também no navegador.

## Decisões e próximos passos

O projeto é um monólito Laravel com React integrado por Inertia, sem API separada e sem Vite. Nenhum dado real deve ser usado enquanto autenticação e isolamento por empresa não forem implementados e testados. A próxima etapa recomendada é definir o modelo de empresa e conta administrativa, implementar autenticação e comprovar o isolamento entre empresas com testes automatizados.
