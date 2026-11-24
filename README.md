# EscalaSimples

Aplicação web em desenvolvimento para organizar escalas de trabalho e folgas. O cadastro de empresa, login por sessão e painel inicial estão disponíveis; as funcionalidades de escala ainda não foram implementadas.

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

Configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` no arquivo `.env` conforme sua instalação. Use um banco exclusivo para desenvolvimento, criado previamente por um administrador, e um usuário com permissões adequadas. Confirme o servidor, o banco e as migrations pendentes antes de executar `php artisan migrate`; não suponha que o banco esteja vazio. Nunca versione o `.env` ou compartilhe credenciais. Sessões e cache usam arquivos durante o desenvolvimento.

Se `node` ou `npm` não funcionar por conflito no PATH, verifique com `Get-Command node,npm -All` e coloque a pasta da instalação do Node antes dos atalhos conflitantes. Na sessão atual pode-se usar `$env:PATH = 'C:\Program Files\nodejs;' + $env:PATH`.

## Desenvolvimento e produção

Em terminais separados, execute `php artisan serve` e `npm run dev`, e abra `http://127.0.0.1:8000`. O segundo comando observa alterações no JavaScript e no CSS; atualize a página no navegador após a recompilação. `npm run build` gera os arquivos minificados em `public/build`. Gere o build antes de iniciar a aplicação ou executar os testes de integração.

O esbuild agrupa JSX e JavaScript; o Tailwind CLI gera o CSS. O Blade carrega os arquivos produzidos e um hash de ambos é usado para versionar os assets do Inertia e invalidar o cache. A resolução de páginas é explícita, adequada à base atual. Não há hot module replacement nem integração automática com o manifesto Vite; expansão para divisão de bundles e atualização em tempo real exigirá configuração adicional.

## Testes

Execute `php artisan test` e `vendor/bin/pint --test` (no Windows, `vendor\bin\pint.bat --test`). A suíte usa SQLite em memória, com `pdo_sqlite`, e define as variáveis de teste em `phpunit.xml`. A classe base valida o ambiente e a conexão antes dos recursos como `RefreshDatabase` e disponibiliza apenas a conexão SQLite aos testes Laravel. Variáveis externas conflitantes ou cache de configuração de outro ambiente podem causar uma interrupção de segurança: remova as variáveis conflitantes e, se necessário, o cache com `php artisan config:clear`.

Essa proteção cobre os testes Laravel que estendem `Tests\TestCase`; não protege comandos Artisan manuais, conexões criadas diretamente ou testes que modifiquem a configuração após a validação. Nunca execute `migrate:fresh`, `db:wipe` ou equivalentes em bancos existentes sem verificar o destino e obter autorização. SQLite não reproduz integralmente tipos, índices, constraints e comportamento do MySQL; uma futura suíte de integração deverá usar um banco MySQL de teste exclusivo, com proteção própria e credenciais limitadas. A suíte atual não valida a conexão MySQL. A renderização visual deve ser verificada também no navegador.

## Decisões e próximos passos

Cada empresa tem no máximo um usuário administrador no MVP; a restrição única em `users.company_id` deverá ser revista quando houver suporte a mais contas. A criação de empresa e administrador ocorre em transação no serviço `CreateCompanyWithAdministrator`, que recebe dados já validados. O painel obtém a empresa pelo usuário autenticado. O acesso usa sessão Laravel e a limitação de tentativas de login utiliza o cache configurado. Recuperação de acesso por e-mail ainda não está disponível.

As migrations de empresas recusam a execução se houver usuários existentes; nesses casos é necessário planejar a associação dos registros antes de aplicar as mudanças. Execute migrations apenas após confirmar o banco de destino e seu conteúdo.

O projeto é um monólito Laravel com React integrado por Inertia, sem API separada e sem Vite. O painel tem testes de isolamento entre duas empresas; os futuros recursos devem ter suas próprias consultas autorizadas e testes de acesso cruzado. A suíte ainda não valida a integração em MySQL e a aplicação permanece em desenvolvimento com dados fictícios.

Setores e colaboradores são associados à empresa autenticada. Nomes de setores são normalizados removendo espaços excedentes e comparando letras sem diferenciar maiúsculas de minúsculas; acentos permanecem significativos. Um setor inativo permanece nos colaboradores já vinculados, mas não pode receber novos vínculos. Colaboradores podem ficar sem setor e nomes repetidos são permitidos. A chave estrangeira composta impede vínculos entre empresas; não há exclusão de setores ou colaboradores na interface.

## Importação e alterações em lote

Na página de colaboradores, baixe o modelo CSV e use as colunas `nome,setor`, nessa ordem. O arquivo deve ser UTF-8, com ou sem BOM, separado por vírgula ou ponto e vírgula; não use quebras de linha dentro dos campos. Cada setor deve existir e estar ativo na empresa. A prévia mostra até 50 linhas e 50 erros e não cria colaboradores. Nomes repetidos no arquivo ou semelhantes aos encontrados nos registros existentes são apenas avisos de possíveis homônimos; a verificação de existentes depende da comparação de nomes do banco e não identifica todas as duplicidades.

Após revisar, confirme na mesma página: o navegador reenvia o arquivo, e o servidor confere o hash e revalida os dados e setores em transação. Cada prévia válida pode ser confirmada uma vez e expira em 60 minutos. Uma nova prévia do mesmo arquivo é uma nova operação e pode gerar colaboradores homônimos. O arquivo enviado não é armazenado permanentemente; a tabela `employee_imports` guarda o identificador, hash, empresa e situação da operação, sem nomes dos colaboradores. O modelo baixado contém apenas cabeçalhos fixos, sem dados fornecidos por usuários.

O limite inicial é 1.000 registros e 2.048 KiB por arquivo, ajustável pelas variáveis `EMPLOYEE_IMPORT_MAX_ROWS` e `EMPLOYEE_IMPORT_MAX_KB`. O PHP e o servidor web também precisam aceitar o tamanho configurado. A importação de 5.000 registros requer validação de desempenho antes de aumentar o limite. No Windows, se o PHP não conseguir escrever em `upload_tmp_dir`, configure um diretório temporário gravável na instalação local do PHP. Na listagem, é possível selecionar colaboradores da página e ativá-los, desativá-los ou alterar seu setor em lote, com confirmação e sem exclusão física.
