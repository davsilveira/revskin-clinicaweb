# Opção 2 — validação local (baby steps)

**Ambiente:** local (`revski-main` / DDEV). **Nada foi para produção.**
Site: https://revski-main.ddev.site:33177

Atalho para rodar comandos no ambiente certo:
```bash
cd "$(ddev describe revski-main -j | jq -r .raw.approot)"
# depois: ddev exec php artisan <comando>
```

> O que já foi executado por mim no seu banco **local**: a migration do pivot e o
> `backfill-vinculos --force` (1153 vínculos criados) + `verify` (verde). Os comandos
> de limpeza de usuários rodaram só em **dry-run** (não apagaram nada). Os passos
> abaixo deixam você reproduzir e conferir tudo.

---

## Passo 0 — Migration e estado do banco
```bash
ddev exec php artisan migrate            # cria a tabela medico_paciente (já aplicada)
ddev exec php artisan tinker --execute='echo \App\Models\MedicoPaciente::count();'  # → 1153
```

## Passo 1 — Normalização de pacientes (idempotente, dry-run primeiro)
```bash
ddev exec php artisan pacientes:opcao2-preflight        # deve dizer "nenhuma colisão de CPF"
ddev exec php artisan pacientes:backfill-vinculos        # DRY-RUN: mostra quantos criaria
ddev exec php artisan pacientes:backfill-vinculos --force # aplica (idempotente: rodar de novo cria 0)
ddev exec php artisan pacientes:opcao2-verify            # tem de sair "OK: verificação passou."
```

## Passo 2 — Criar dois médicos de teste com senha conhecida
Cole no tinker (`ddev exec php artisan tinker`):
```php
$mk = function($email) {
    $m = \App\Models\Medico::create(['apelido' => $email]);
    return \App\Models\User::create([
        'name' => $email, 'email' => $email,
        'password' => \Illuminate\Support\Facades\Hash::make('senha123'),
        'role' => 'medico', 'medico_id' => $m->id, 'is_active' => true,
    ]);
};
$mk('medico.a@teste.local');
$mk('medico.b@teste.local');
```
Login dos dois: senha `senha123`.

## Passo 3 — O bug original resolvido (2 médicos, 1 paciente)
1. Entre como **medico.a@teste.local** → Pacientes → Novo Paciente.
   - Nome: `Paciente Compartilhado`; CPF **válido** `111.444.777-35`;
     nascimento, celular, e-mail quaisquer.
   - Nº Registro: `A-001`; Indicado por: `Indicação A`; Observações: `Notas A`.
   - Salvar. ✅ cadastra normalmente.
2. Saia e entre como **medico.b@teste.local** → Novo Paciente.
   - Use **o mesmo CPF** `111.444.777-35`. Nº Registro: `B-777`; Observações: `Notas B`.
   - Salvar. ✅ **Antes isso era bloqueado; agora salva** — vira o mesmo paciente com
     um vínculo novo para o médico B.
3. Confirme no tinker:
```php
$p = \App\Models\Paciente::where('cpf','111.444.777-35')->first();
echo "pacientes com esse CPF: ".\App\Models\Paciente::where('cpf','111.444.777-35')->count()."\n"; // 1
$p->medicos()->get()->each(fn($m)=>print("medico {$m->id} → codigo={$m->pivot->codigo} obs={$m->pivot->anotacoes}\n"));
// médico A → codigo=A-001 obs=Notas A ; médico B → codigo=B-777 obs=Notas B
```
   → **Um só paciente, dois vínculos, campos privados isolados.**

## Passo 4 — Isolamento na tela
- Como **A**: abra o paciente → vê `A-001` / `Notas A`. Não vê nada do B.
- Como **B**: abre o mesmo paciente → vê `B-777` / `Notas B`.
- Edite o telefone como A → entre como B e veja o telefone atualizado (dado
  **compartilhado**). Nº Registro/Observações continuam separados.

## Passo 5 — Arquivar é por vínculo
- Como **A**, arquive (desativar) o paciente. Ele some da lista do **A**.
- Entre como **B**: o paciente **continua** aparecendo para o B.
- (Admin continua podendo desativar globalmente pela tela de admin.)

## Passo 6 — Nº Registro é único por médico (não global)
- Como **A**, tente cadastrar um 2º paciente (CPF `529.982.247-25`) com Nº Registro
  `A-001` → **erro** "já existe para este médico".
- Como **B**, cadastre com Nº Registro `A-001` → **permitido** (é privado do B).

## Passo 7 — Lookup por CPF (endpoint da 2ª etapa do anexo)
```bash
# logado como médico B, no navegador/console, ou via curl com cookie de sessão:
GET /api/pacientes/lookup?cpf=111.444.777-35
# → { found:true, ja_vinculado:true, paciente:{ nome, endereço... } }  (só dados principais)
```

## Passo 8 — Limpeza de usuários duplicados (só dry-run; NÃO apliquei)
```bash
ddev exec php artisan usuarios:auditar-duplicados-legado          # DRY-RUN
# Esperado: 14 pares consolidados, 27 e-mails @legado a limpar, 0 dependências, 0 colisões,
#           11 "Secretaria Administrativa N" mantidas (existem no dump legado).
# Para aplicar de fato no LOCAL (apaga 14 shells vazias + renomeia e-mails):
# ddev exec php artisan usuarios:auditar-duplicados-legado --force
```

## Passo 9 — Testes automatizados
```bash
ddev exec php artisan test --filter=Opcao2PacienteMultiMedicoTest   # 5 passam
ddev exec php artisan test                                          # suíte cheia
```
> Observação: `ExampleTest` (GET /) e `ToolsIntegrationJobsTest` já falhavam **antes**
> desta implementação (confirmei revertendo minhas mudanças) — não têm relação com a
> Opção 2.

## Rollback local (se quiser voltar atrás)
```bash
ddev exec php artisan migrate:rollback --step=1   # dropa medico_paciente
```
As colunas antigas de `pacientes` (medico_id/codigo/anotacoes/indicado_por) foram
**mantidas** (Fase A), então reverter o pivot não perde dado.

---

## Passo 10 — UI: section separada + pré-preenchimento por CPF (novo)
No drawer "Novo Paciente":
- Os 3 campos privados agora ficam numa **section própria** "Dados exclusivos deste
  médico" (Indicado por, Nº Registro, Observações), com aviso de que são privados.
- Ao digitar um **CPF já cadastrado** (11 dígitos) e sair do campo, aparece um aviso
  verde e os **dados compartilhados são pré-preenchidos** automaticamente; a section
  privada permanece **em branco**. Se você já tem vínculo com o paciente, o aviso
  alerta que ele já está cadastrado para você.
- Reproduza: como **medico.b@teste.local**, Novo Paciente → digite o CPF
  `111.444.777-35` (já usado no Passo 3) → Tab. Os dados do "Paciente Compartilhado"
  aparecem; preencha só os campos exclusivos e Salve.

## Passo 11 — UI: múltiplos médicos na lista (admin/secretária/callcenter)
Entre como **admin** → Pacientes: há uma coluna **"Médico(s)"** listando todos os
médicos vinculados a cada paciente (ex.: o "Paciente Compartilhado" mostra os dois
médicos de teste). Para o papel **médico**, a coluna não aparece (ele só vê os seus).

> Rebuild do front já feito (`ddev exec npm run build`). Se editar de novo, rode o
> build outra vez.

## O que ficou pronto × o que ainda falta (para produção)
**Pronto e validável agora:** pivot + backfill/verify; acesso por vínculo; upsert por
CPF (resolve o bug do 2º médico pelo formulário); campos privados por médico numa
**section separada** com **pré-preenchimento por CPF**; Nº Registro único por médico;
arquivar por vínculo; vínculo criado também ao emitir receita/assistente; endpoint de
lookup; **coluna de múltiplos médicos** na lista para admin; comando de limpeza de
duplicados.

**Ainda falta (opcional):** nada bloqueante. Possíveis refinamentos: filtro da lista
por médico específico via chip, e um botão explícito "buscar por CPF" (hoje é no
blur do campo). O comando de dedup de usuários segue só em dry-run até você aprovar.
