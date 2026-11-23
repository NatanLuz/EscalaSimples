---
name: validar-alteracao
description: Valida funcionalidades, correções e refatorações do EscalaSimples antes de concluir uma tarefa.
---

# Validar alteração

1. Identifique os arquivos alterados e examine as mudanças e seu escopo, incluindo alterações existentes que possam afetar a validação.
2. Revise possíveis bugs, regressões e problemas de segurança. Quando aplicável, verifique autenticação, autorização, validação no backend e isolamento entre empresas, incluindo acesso indevido entre empresas.
3. Consulte o README e identifique e execute os testes existentes relevantes. Conforme o escopo, execute PHPUnit com `php artisan test`, Laravel Pint com `vendor/bin/pint --test` (no Windows, `vendor\bin\pint.bat --test`) e o build com `npm run build`. Gere o build antes dos testes de integração, conforme o README.
4. Não execute operações destrutivas no banco de dados. Se uma verificação depender delas ou não puder ser executada com segurança, registre a limitação.
5. Apresente um resumo objetivo da validação, indicando as verificações que passaram, falharam ou não puderam ser executadas, com os motivos e pendências relevantes. Não declare uma verificação como executada sem executá-la.

Não realize commit, push, merge ou deploy sem autorização explícita do usuário.
