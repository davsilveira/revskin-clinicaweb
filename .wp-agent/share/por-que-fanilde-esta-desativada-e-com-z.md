# Por que a Fanilde está "desativada" e com `z-` no nome aqui, se no ClinicaWeb ela aparece ativa

**Resposta curta:** o ClinicaWeb está certo — a ficha que vocês abriram (a das fotos) é a ficha **#594**, ativa e com o nome limpo. O que veio errado foi **qual das 4 fichas dela sobreviveu na migração**: ficamos com a `z-` desativada, e nenhuma etapa posterior tem permissão para corrigir nome nem status.

---

## 1. No ClinicaWeb ela não tem 1 ficha — tem 4

Todas com o mesmo CPF/celular, no dump de 06/08/2026:

| CLW2 | Nome como está lá | Ativo no CLW2 | Criada em |
| --- | --- | --- | --- |
| #573 | `z-Fanilde Pirro Viana Paquer` | **não** | 23/06/2025 |
| #577 | `zzzFANILDE PIRRO VIANA PAQUER` | **não** | 24/06/2025 |
| #579 | `zzzFanilde Paquer` | **não** | 24/06/2025 |
| **#594** | **`Fanilde Pirro Viana Paquer`** | **sim** | 26/06/2025 |

É o hábito de sempre no ClinicaWeb: em vez de apagar a ficha repetida, renomeia com `z`/`zzz` na frente (para afundar no fim da lista) e desmarca "ativo". No dia a dia vocês só veem a #594 — por isso, para vocês, ela "é ativa e tem nome normal". As outras três continuam lá, e foram elas que a migração encontrou primeiro.

As fotos confirmam que é a #594: o endereço mostrado é `Rua Dom Antônio Malam, 631 — Residencial Bell Mont, Apto 202`, grafia que só existe na #594 (nas outras está `R. Dom Antonio Malan`).

## 2. Aqui as 4 viraram 1 ficha, e a escolhida foi a pior

A migração junta fichas pelo CPF e **mantém a mais antiga** como a ficha que fica. A mais antiga é a #573 — a `z-`, desativada. Então a ficha #16742 do sistema novo nasceu, em 16/06/2026, já com o nome `z-Fanilde Pirro Viana Paquer` e com "ativo = não", copiados da linha errada. As receitas da ficha boa (#594) foram todas penduradas nela.

## 3. Por que as importações seguintes não corrigiram

Duas regras se somam — as duas existem por bons motivos, mas juntas travam exatamente estes dois campos:

- **"Ativo" nunca é copiado do ClinicaWeb numa atualização.** Arquivar/reativar aqui é decisão do médico, e um dump não deve reativar quem vocês arquivaram de propósito. O status só é copiado **no momento de criar** a ficha — e a ficha foi criada a partir da linha errada.
- **O nome só é sobrescrito se a ficha do ClinicaWeb tiver alteração mais recente que a nossa.** A #594 nunca foi editada no ClinicaWeb (só tem data de inclusão, 26/06/2025). Na importação de 07/08/2026, nosso registro (de junho/2026) contava como "mais novo", então o nome limpo foi tratado como desatualizado e descartado. Fora disso, o merge só preenche campo **vazio** — e o nome não estava vazio.

**A prova está no relatório da própria importação de 07/08.** A única alteração registrada para a ficha #16742 veio da linha **#577** (`zzzFANILDE PIRRO VIANA PAQUER`), e preencheu só sexo e fototipo:

```
antes:  nome "z-Fanilde Pirro Viana Paquer"   ativo "não"
depois: nome "z-Fanilde Pirro Viana Paquer"   ativo "não"
diff:   sexo → F ; fototipo → 1
```

A linha **#594 não gerou nenhuma linha no relatório** — entrou como "sem mudanças" (um dos 355 skips daquela rodada). E a receita de 11/06/2026 (a nº 3 das fotos: BIOMASSANCE, AQUELINE II, SYNCHRON, TONALITE…) entrou na mesma rodada como "Nova", **sem nenhum aviso**, dentro da ficha arquivada. Ninguém tinha como perceber olhando a tela de conferência.

## 4. Por que endereço e e-mail parecem certos, mas o nome não

O endereço que está aqui hoje é o da ficha boa (`Rua Dom Antônio Malam` + `Apto 202`) — no relatório de 07/08 ele ainda era o da `z-` (`R. Dom Antonio Malan`, sem apto). Quem atualizou foi a **sincronização com o oList**, às 19:10 daquele mesmo dia (`tiny_sync_at = 2026-08-07 19:10:51`, igual ao último `updated_at` da ficha).

Só que essa sincronização, também de propósito, **não reescreve o nome de uma ficha que já existe** (o nome local é o que o médico reconhece na busca) e só marca "ativo" quando **cria** um contato novo.

Resultado: tudo o que é endereço, e-mail, sexo e telefone foi sendo atualizado ao longo do tempo — e os **dois únicos campos que escondem a paciente da busca (nome com `z-` e "ativo") são justamente os dois que nada, em nenhuma etapa, reescreve.**

## 5. E a segunda tranca

Em 21/07/2026, o comando que criou os vínculos médico↔paciente copiou o "ativo" da ficha (que era 0) para o vínculo com a Dra Sullege. Então a ficha ficou invisível duas vezes: arquivada para todos, e arquivada para ela.

---

## O que já mudou por causa disso

Na tela de conferência da importação do ClinicaWeb, cada receita que estiver entrando numa ficha arquivada — ou arquivada para aquele médico — passa a aparecer marcada em amarelo: *"Ficha do paciente está arquivada no CLW3 — o médico não vai encontrá-la na busca"*. E quando a ficha está ativa no ClinicaWeb e arquivada aqui, fica registrado no relatório em vez de virar um skip silencioso. Coberto por 4 testes novos.

Isso não conserta as fichas que já entraram assim — para essas continua valendo a reativação descrita no relatório anterior (`caso-fanilde-paciente-invisivel.md`), que está pronta e aguardando o "pode aplicar" da clínica: **8 fichas**, sendo 2 urgentes (Fanilde, da Dra Sullege, e uma da Dra Renata).
