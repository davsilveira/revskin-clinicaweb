# País “Estados Unidos” no Novo Paciente — o que acontece

## Resumo
**Não é o CW3** que escolhe EUA pelo locale do PC. O formulário já nasce com **Brasil** como padrão. O caso do anexo 2 é **autofill do navegador** (Chrome/Edge) com um endereço salvo nos EUA.

## Evidência no anexo 2
Além de País = “Estados Unidos”, o formulário veio com:
- CEP `34786`
- Cidade `Windermere`
- Estado `FL`

Isso é um endereço concreto de Flórida — típico de perfil de endereço salvo no browser, não de “idioma/região do Windows”. Locale do SO sozinho **não** preenche Windermere/FL/34786.

O anexo 1 (mesmo fluxo, sem autofill) mostra País = **Brasil**, que é o comportamento esperado da aplicação.

## O que a aplicação já fazia
| Camada | Comportamento |
|--------|----------------|
| Frontend (`PatientDrawer`) | `pais: 'Brasil'` no formulário novo |
| Lista de países | Brasil primeiro, demais A–Z |
| Banco | coluna `pais` com default `Brasil` |
| Anti-autofill anterior | `autocomplete="off"` + `name`s customizados (`revskin_paciente_*`) |

Chrome **ignora com frequência** `autocomplete="off"` em campos de endereço/e-mail e injeta o perfil salvo (daí EUA + Flórida).

## O que mudamos agora
No drawer de Novo Paciente:
1. Atributos mais agressivos anti-autofill (`one-time-code`, flags de password managers).
2. E-mail como `type="text"` + `inputMode="email"` (reduz vínculo com perfil de endereço do Chrome).
3. Nos primeiros ~2s após abrir **Novo Paciente**, se o browser mudar o país para algo ≠ Brasil, a app **restaura Brasil** e limpa CEP/endereço/cidade/UF, com aviso discreto.
4. Se o usuário alterar o País manualmente, o guard é desligado (não atrapalha cadastro internacional).

## Resposta ao cliente
> O CW3 já sugere Brasil por padrão. No seu print, o Chrome preencheu um endereço salvo nos EUA (Windermere, FL). Não vem do PC “estar em inglês”. Pode limpar/desativar o autofill do site, ou simplesmente mudar o país de volta para Brasil — e a partir desta correção o sistema também desfaz esse preenchimento automático no cadastro novo.
