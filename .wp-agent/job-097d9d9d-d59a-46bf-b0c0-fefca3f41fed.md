# Worklog — Pacientes perdem vínculo com a médica ao alternar papel (admin ↔ médico)

Job: `097d9d9d-d59a-46bf-b0c0-fefca3f41fed`
Env: `revskin-main` — https://revski-main.ddev.site:33177
Usuária do caso: **Giovana** — `giovana.naccarato@revskin.com.br` (user id 274)

## Resumo do problema
Ao usar o painel de usuários para transformar a médica em **admin** e depois voltá-la
para **médico**, os pacientes dela deixam de aparecer. Antes disso ela consegue criar
receitas e associar pacientes; depois some tudo. Não tem relação com clínica.

## Causa raiz (confirmada)
No fluxo de acesso, um paciente pertence a um `medico_id`, e a médica enxerga só os
pacientes cujo `paciente.medico_id === user.medico_id` (`User::canAccessPaciente`).

`app/Http/Controllers/UserController.php@update()`:

1. **médico → admin** (ramo `else`): fazia `'medico_id' => null`. Isso **desfazia o
   vínculo do usuário** com o registro em `medicos`, mas **não** apagava esse registro.
   O médico (e todos os pacientes apontando para ele) ficava **órfão**.
2. **admin → médico** (ramo `$isMedico`): como `user->medico_id` agora era `null`,
   `$user->medico` vinha `null`, então o código chamava `MedicoService::create(...)` e
   **criava um médico NOVO** (outro id). O usuário passava a apontar para esse médico
   novo (0 pacientes), enquanto os pacientes continuavam no médico antigo órfão.

Resultado: a médica fica ligada a um médico novo e vazio; os pacientes reais ficam
presos no médico antigo. `MedicoService::create()` não tem deduplicação por CRM, então
sempre gera um registro novo.

### Reprodução (rodada em transação, com rollback — nada persistido)
```
START: user 274 medico_id=930, pacientes=53
STEP1 (-> admin): user.medico_id=NULL ; medico 930 vira órfão com 53 pacientes
STEP2 (-> medico): criou NOVO medico id=944
  user.medico_id agora = 944 (antes 930) | pacientes VISÍVEIS = 0 | órfãos em 930 = 53
==> BUG REPRODUZIDO: SIM
```

## Correção
`app/Http/Controllers/UserController.php`, método `update()`, ramo não-médico:
**não zerar mais `medico_id`**. O vínculo com o registro de `medicos` (e seus pacientes)
é preservado quando o papel deixa de ser médico. Se o papel voltar a ser médico,
`$user->medico` é encontrado e **reaproveitado** (via `MedicoService::update`), em vez de
criar um médico novo. As checagens de acesso usam o papel (`isMedico()`), então um
`medico_id` remanescente num admin/secretária/callcenter é inofensivo.

Também removi um `'medico_id' => null` redundante que um patch automático havia inserido
no `store()` (criação de usuário novo já nasce com `medico_id` nulo por padrão).

### Verificação da correção (transação com rollback)
```
STEP1 (-> admin): user.medico_id=930 (preservado)
STEP2 (-> medico): REAPROVEITOU medico id=930
  user.medico_id=930 (orig 930) | pacientes VISÍVEIS = 53
==> FIX OK: SIM
```

## Arquivos alterados
- `app/Http/Controllers/UserController.php` — `update()` não zera `medico_id` ao trocar
  para papel não-médico; limpeza de linha redundante no `store()`.
- `tests/Feature/UserRoleToggleMedicoTest.php` — **novo** teste de regressão que dirige a
  rota real `PUT /users/{user}` (médico → admin → médico) e garante que o `medico_id` e
  os pacientes são preservados. Confirmei que o teste **falha** sem a correção e **passa**
  com ela.

## Como validar à mão
Pré-requisito: logar como admin no painel.

1. Acesse **/users** (Usuários) em https://revski-main.ddev.site:33177/users
2. Edite **Giovana** (`giovana.naccarato@revskin.com.br`), mude o perfil para
   **Administrador**, salve.
3. Edite de novo, volte o perfil para **Médico** (preencha CRM `283163` / UF `SP` e um
   telefone), salve.
4. Faça login como a Giovana e abra **/pacientes** — os 53 pacientes dela devem continuar
   aparecendo. Atualize também o perfil (CRM etc.) em **/perfil**: continua tudo visível.

Antes da correção, no passo 4 a lista de pacientes vinha **vazia**.

### Testes automatizados
```
ddev exec php artisan test --filter=UserRoleToggleMedicoTest   # PASS (novo)
ddev exec php artisan test                                     # ver nota abaixo
```
Nota: `ToolsIntegrationJobsTest` tem 2 falhas **pré-existentes** (relativas a `failed_jobs`
em SQLite), sem relação com esta mudança — confirmado rodando a suíte com o
`UserController.php` revertido ao estado do `main`.

## Situação dos dados (local)
No banco local a Giovana está **saudável**: user 274 → medico 930 → 53 pacientes. Não há
vítima a reparar. Havia 1 médico órfão com pacientes (id 878, "Luiz Fernando", CRM 46.365),
mas é médico **legado sem conta de usuário**, não vítima deste bug. A correção previne
novas ocorrências; nenhuma migração de dados foi necessária.

Se em produção houver uma médica já quebrada: identifique o médico órfão que contém os
pacientes (ex.: mesmo CRM/nome) e reatribua `users.medico_id` do usuário para o id desse
médico órfão (e, se necessário, apague o médico novo vazio). Sem uma referência de volta
guardada, a religação precisa ser feita manualmente por CRM/nome.
