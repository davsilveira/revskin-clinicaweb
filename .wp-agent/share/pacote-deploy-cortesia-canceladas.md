# O que entraria no próximo deploy (21/10/2026)

`origin/main` = `HEAD` (e4ef312). **Não há commits locais.** Tudo o que está abaixo está **sem commit** no working tree.
Não há migration nova: a setting usa a tabela `settings`, que já existe.

## 1. Cortesia = desconto de 100% (jobs d3aba5c9 / af857077)
- No CLW3, a receita de cortesia fica com total R$ 0,00. O frete e a caixa continuam somando, e a linha "Cortesia (100%)"
  aparece no resumo da edição e na visualização.
- No oList, o pedido vai com os itens no preço de tabela e `valor_desconto` igual à soma deles. O frete continua sendo enviado.
- No RD Station, `one_time_price` passa a ser o total (0 numa cortesia sem frete).
- Arquivos: `app/Models/Receita.php` (`calcularTotais`), `app/Jobs/CriarPedidoTinyJob.php`,
  `resources/js/Pages/Receitas/Form.jsx` (só o trecho de desconto/resumo), `resources/js/Pages/Receitas/Show.jsx`,
  `tests/Feature/ReceitaCortesiaTinyTest.php` (novo).

## 2. Webhook do oList não marca vendido em receita cancelada (job af857077)
- O "faturado" ou a sincronização de preços de um pedido cuja receita já está cancelada no CLW3 passa a ser ignorado (fica um warning no log).
  Evita repetir o caso 17964-0001.
- Arquivo: `app/Jobs/ProcessWebhookTinyJob.php` (+ teste no `ReceitaCortesiaTinyTest`).

## 3. Ocultar receitas canceladas, atrás de setting DESLIGADA (jobs 3d354f2b, 48138323, 7c51ce45)
- Nova aba **Configurações → Receitas** com "Ocultar receitas canceladas". **Vem desligada**, então em produção nada muda
  até alguém ligar.
- Com ela ligada, as canceladas saem da lista geral, da lista do cliente, da ficha do paciente e do clique no paciente.
- Arquivos: `ReceitaController.php` (index), `PacienteController.php`, `SettingsController.php`, `routes/web.php`,
  `Receita.php` (`ocultarCanceladas()`), `Receitas/Index.jsx`, `Settings/Index.jsx`, `Settings/Receitas.jsx` (novo),
  `tests/Feature/PacienteCanceladasOcultasTest.php` (novo), `tests/Feature/ReceitaCopiarTest.php`.

## 4. Receita cancelada não pode ser duplicada (job 3d354f2b), SEMPRE ATIVO
- O botão "Duplicar Receita" some em receita cancelada (desktop e menu mobile), e a rota `copiar` recusa a cópia.
  **Esse item não depende da setting** e vai mudar o comportamento em produção no deploy.
- Arquivos: `ReceitaController.php` (`copiar`), `Receitas/Form.jsx` (trecho `isReceitaCancelada`), testes no `ReceitaCopiarTest`.
- "Cancelar e duplicar" numa receita válida continua funcionando.

## Não entra (lixo local, não commitar)
- `storage/logs/laravel.log`, `storage/framework/views/*`, `.DS_Store`, `storage/app/*`, `database/backups/`, `.env.pre-mysql-*`.
- `.wp-agent/**` (worklogs e relatórios; só se quiser versionar a documentação, como foi feito em outros deploys).
- `tests/Feature/PacienteCreateThenSearchTest.php`: teste avulso do job 22613ef7 (ago/2026), sem relação com este deploy.
  Opcional.

## Estado dos testes
- Suíte completa: 342 passaram e 1 foi pulado (intermitência conhecida de 1 teste por rodada, que passa sozinho).
- `npm run build` local OK (o deploy refaz o build no CI).

## Depois do deploy
- Receitas de cortesia **já finalizadas** continuam com o total antigo. Só recalcula quem for salva de novo.
- Conferir uma cortesia nova de ponta a ponta: total 0 no CLW3 e pedido no oList com desconto igual ao subtotal.
