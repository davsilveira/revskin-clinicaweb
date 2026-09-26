# Job e3e3d575 — a "URL de Redirecionamento" do app Tiny precisa mudar?

## Pergunta

No painel do Tiny (Aplicativos API → app "ClinicaWeb"), a **URL de Redirecionamento** está
`http://localhost:9090/integracoes/tiny/callback`. Já estava assim com o domínio antigo.
Precisa alterar? Para qual valor?

## Resposta

**Sim, vale corrigir — mas não é urgente e não é o que está te servindo hoje.** O valor é:

```
https://plataforma.revskin.com.br/integracoes/tiny/callback
```

## Por que funcionou esse tempo todo com `localhost`

Duas razões independentes, as duas verificadas em produção:

**1. A produção não usa OAuth.** A configuração está em **API V2**, que autentica com um
**token estático**, não com o fluxo OAuth:

```
tiny_api_version    v2
tiny_token          (preenchido, 312 chars)   ← é este que está em uso
tiny_client_id      tiny-api-4d05075d…        ← só serviria para a V3
tiny_refresh_token  (preenchido, 1508 chars)
```

O log de produção confirma: as chamadas saem como `Tiny ERP V2 Request` para
`api2/contatos.pesquisa.php`. A URL de redirecionamento **nunca entra nesse caminho**.

**2. Mesmo na V3, o dia a dia não usaria a URL.** A renovação do token
(`renovarAccessTokenComRefreshToken`, `app/Services/TinyErpClient.php:287`) envia só
`grant_type=refresh_token`, `client_id`, `client_secret` e `refresh_token` — **sem
`redirect_uri`**.

A URL de redirecionamento só é usada em **dois momentos**, ambos no fluxo de autorização:

| Onde | Arquivo |
|---|---|
| Montar a URL de autorização | `TinyErpClient::gerarUrlAutorizacao()` (`:400`) |
| Trocar o code pelos tokens | `TinyErpClient::trocarCodigoPorTokens()` (`:342`) |

Ou seja: **só quando alguém clica em "Autorizar"** em Configurações → Tiny.

## Então por que mexer?

Porque é uma **mina terrestre dormente**. No dia em que:

- migrarem para a API V3, **ou**
- o token V2 for revogado / o Tiny descontinuar a V2, **ou**
- alguém simplesmente clicar em "Autorizar" para reconectar,

o app vai mandar `https://plataforma.revskin.com.br/integracoes/tiny/callback` e o Tiny vai
recusar com **`invalid_redirect_uri`**, porque o registrado é `localhost:9090`. E isso vai
acontecer justamente num momento de urgência (integração caída).

Custo de corrigir agora: 10 segundos. Custo de descobrir depois: uma integração parada e um
erro que não explica o que houve.

## O valor exato (gerado pela própria produção)

Rodei no servidor para não haver dúvida de barra final ou esquema:

```
APP_URL            : https://plataforma.revskin.com.br
redirect_uri TINY  : https://plataforma.revskin.com.br/integracoes/tiny/callback
```

Confirmação de que a rota existe e responde (302 → /login porque está atrás de auth,
comportamento esperado):

```
GET /integracoes/tiny/callback -> HTTP 302 -> https://plataforma.revskin.com.br/login
```

Rota declarada em `routes/web.php:249` (`Route::prefix('integracoes/tiny')` → `/callback`).

## Não precisa mudar nada no código

A tela **Configurações → Integrações → Tiny** já mostra a URL certa para copiar, montada
dinamicamente (`resources/js/Pages/Settings/Integrations/Tiny.jsx:460`):

```jsx
{window.location.origin}/integracoes/tiny/callback
```

Abrindo essa tela em produção, ela já exibe o endereço com `plataforma.revskin.com.br`.
**Pode copiar de lá** em vez de digitar.

## Ponto de atenção: o campo parece aceitar só uma URL

Na tela do Tiny o campo é único. Se você ainda autoriza o Tiny a partir do ambiente local,
trocar para produção **quebra o OAuth local** (e vice-versa). Não dá para saber pelo print se
o Tiny aceita múltiplas URLs — se aceitar (separadas por vírgula ou linha), vale deixar as
duas. Caso contrário, deixe a de produção: é a que importa quando algo quebrar de verdade.

O **nome do app** ("ClinicaWeb") é só rótulo na lista do Tiny, não afeta nada. Renomear é opcional.

## Enquanto isso, o RD Station tem exatamente o mesmo padrão

Também tem `client_id` e `refresh_token` configurados em produção. Vale conferir no painel do
RD, pelo mesmo motivo:

```
redirect_uri RD : https://plataforma.revskin.com.br/integracoes/rd-station/callback
```

## Resumo dos 4 endereços a acertar nos painéis

| Painel | Campo | Valor |
|---|---|---|
| Tiny | URL de Redirecionamento | `https://plataforma.revskin.com.br/integracoes/tiny/callback` |
| Tiny | Webhook de pedido | `https://plataforma.revskin.com.br/api/webhooks/tiny/pedido-finalizado` |
| RD Station | Redirect URI | `https://plataforma.revskin.com.br/integracoes/rd-station/callback` |
| RD Station | Webhook | `https://plataforma.revskin.com.br/api/webhooks/rd/crm-deal-updated` |

Todos gerados pela produção, não digitados à mão.

## Como validar depois de trocar no Tiny

1. Entre em **Configurações → Integrações → Tiny** em produção.
2. Confira que a URL exibida na caixa azul é idêntica à que você salvou no Tiny.
3. Clique em **Autorizar**: se o redirect estiver certo, o Tiny mostra a tela de consentimento
   e volta para o sistema. Se estiver errado, aparece `invalid_redirect_uri` antes mesmo do login.
4. Só faça esse teste se estiver disposto a refazer a conexão — hoje a integração roda na V2
   com token estático e **não precisa** desse fluxo.

## Alterações neste job

Nenhuma alteração de código ou de servidor — a pergunta era de diagnóstico e o app já monta a
URL corretamente sozinho. Só investigação + esta documentação.
