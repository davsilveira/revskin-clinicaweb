<?php

namespace App\Manual;

use App\Models\User;

/**
 * Conteúdo estático do manual de uso, filtrado por perfil em forUser().
 */
class ManualContent
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forUser(User $user): array
    {
        $out = [];
        foreach (self::allModules() as $module) {
            if (! self::moduleVisible($user, $module)) {
                continue;
            }
            $sections = [];
            foreach ($module['sections'] as $section) {
                if (! self::sectionVisible($user, $section)) {
                    continue;
                }
                $sections[] = [
                    'slug' => $section['slug'],
                    'level' => $section['level'],
                    'title' => $section['title'],
                    'paragraphs' => $section['paragraphs'] ?? [],
                    'paragraphs_after_numbered' => $section['paragraphs_after_numbered'] ?? [],
                    'bullets' => $section['bullets'] ?? [],
                    'numbered_bullets' => $section['numbered_bullets'] ?? [],
                ];
            }
            $out[] = [
                'id' => $module['id'],
                'title' => $module['title'],
                'iconKey' => $module['iconKey'],
                'sections' => $sections,
            ];
        }

        return $out;
    }

    private static function moduleVisible(User $user, array $module): bool
    {
        if ($user->isCallcenter()) {
            return false;
        }
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($user->role, $module['roles'], true);
    }

    private static function sectionVisible(User $user, array $section): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $roles = $section['roles'] ?? null;
        if ($roles === null) {
            return true;
        }

        return in_array($user->role, $roles, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function allModules(): array
    {
        return [
            self::moduleDashboard(),
            self::modulePacientes(),
            self::modulePerfil(),
            self::moduleReceitas(),
            self::moduleAssistente(),
            self::moduleRelatorios(),
            self::moduleProdutos(),
            self::moduleCatalogo(),
            self::moduleClinicas(),
            self::moduleUsuarios(),
            self::moduleKarnaugh(),
            self::moduleRegras(),
            self::moduleExports(),
            self::moduleSettings(),
        ];
    }

    private static function moduleDashboard(): array
    {
        return [
            'id' => 'dashboard',
            'title' => 'Tela inicial',
            'iconKey' => 'home',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO, User::ROLE_SECRETARIA],
            'sections' => [
                [
                    'slug' => 'visao-geral',
                    'level' => 2,
                    'title' => 'Visão geral',
                    'paragraphs' => [
                        'A tela inicial é o ponto de entrada após o login. Pelo menu lateral você navega entre as áreas do sistema — as seções deste manual são as mesmas que o seu perfil enxerga no menu.',
                        'No topo da página, o cabeçalho mostra uma saudação com o seu nome e, à direita, o menu da sua conta {{icon:chevronDown}} (perfil e saída).',
                    ],
                    'bullets' => [
                        'Em telas menores, use {{icon:menu}} (três linhas) no canto superior para abrir ou fechar a barra lateral.',
                        'No desktop, use {{icon:sidebarCollapse}} na parte inferior do menu para recolher ou expandir a sidebar.',
                    ],
                ],
            ],
        ];
    }

    private static function modulePacientes(): array
    {
        return [
            'id' => 'pacientes',
            'title' => 'Pacientes',
            'iconKey' => 'pacientes',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO, User::ROLE_SECRETARIA],
            'sections' => [
                [
                    'slug' => 'lista-pacientes',
                    'level' => 2,
                    'title' => 'Lista e busca',
                    'paragraphs' => [
                        'Em Pacientes você vê a listagem cadastrada no sistema. Use o campo {{icon:search}} e o filtro de status {{icon:filter}} para localizar alguém rapidamente; em seguida use o botão Buscar.',
                    ],
                    'bullets' => [
                        'Clique em um paciente para abrir a ficha completa (painel lateral ou página de detalhes, conforme a tela).',
                        'Para incluir um novo cadastro, use {{icon:plus}} Novo Paciente no topo da página.',
                    ],
                ],
                [
                    'slug' => 'cadastro-paciente',
                    'level' => 2,
                    'title' => 'Cadastro e edição',
                    'paragraphs' => [
                        'O formulário reúne dados pessoais, contato e vínculo com o médico responsável. Campos obrigatórios são validados antes de salvar.',
                        'Ao informar o CEP e sair do campo, o sistema consulta a base e preenche logradouro, bairro, cidade e UF quando o retorno for válido.',
                    ],
                    'bullets' => [
                        'No painel de cadastro, o autosave grava várias alterações automaticamente enquanto você edita, sem precisar clicar em salvar a cada campo.',
                    ],
                ],
                [
                    'slug' => 'secretaria-escopo',
                    'level' => 2,
                    'title' => 'Secretária e escopo da clínica',
                    'paragraphs' => [
                        'Usuários com perfil Secretária trabalham no contexto da clínica vinculada à conta: só visualizam e editam pacientes cujo médico pertence àquela clínica.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_SECRETARIA],
                ],
                [
                    'slug' => 'medico-pacientes',
                    'level' => 2,
                    'title' => 'Médico e seus pacientes',
                    'paragraphs' => [
                        'O perfil Médico enxerga os pacientes associados ao seu cadastro, mantendo o foco na própria carteira.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_MEDICO],
                ],
                [
                    'slug' => 'admin-pacientes',
                    'level' => 2,
                    'title' => 'Administrador',
                    'paragraphs' => [
                        'Você tem visão global de pacientes e pode atuar em qualquer registro; no cadastro, use o bloco Médico responsável para definir ou trocar o profissional vinculado ao paciente.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_ADMIN],
                ],
            ],
        ];
    }

    private static function modulePerfil(): array
    {
        return [
            'id' => 'perfil',
            'title' => 'Meu perfil e conta',
            'iconKey' => 'perfil',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO, User::ROLE_SECRETARIA],
            'sections' => [
                [
                    'slug' => 'menu-conta',
                    'level' => 2,
                    'title' => 'Menu do usuário',
                    'paragraphs' => [
                        'No canto superior direito, clique no seu nome e na seta {{icon:chevronDown}} para abrir o menu da conta.',
                    ],
                    'bullets' => [
                        '{{icon:perfil}} Meu Perfil: abre o painel para atualizar dados da conta, e-mail e senha.',
                        '{{icon:logout}} Sair: encerra a sessão com segurança.',
                    ],
                ],
                [
                    'slug' => 'perfil-medico',
                    'level' => 2,
                    'title' => 'Dados profissionais (médico)',
                    'paragraphs' => [
                        'No menu da conta {{icon:chevronDown}}, use Meu Perfil para abrir o painel onde você altera senha, dados profissionais (CRM, especialidade etc.) e envia a imagem de assinatura usada em receitas em PDF — formatos e tamanho máximo aparecem no próprio upload.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_MEDICO],
                ],
            ],
        ];
    }

    private static function moduleReceitas(): array
    {
        return [
            'id' => 'receitas',
            'title' => 'Receitas',
            'iconKey' => 'receitas',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO],
            'sections' => [
                [
                    'slug' => 'lista-receitas',
                    'level' => 2,
                    'title' => 'Listagem',
                    'paragraphs' => [
                        'A lista de receitas mostra os registros aos quais você tem acesso. Use filtros e ordenação para encontrar uma receita específica.',
                    ],
                    'bullets' => [
                        'Abra uma receita para ver o detalhamento. O PDF {{icon:download}} só fica disponível depois que a receita foi finalizada (veja a seção PDF e impressão).',
                    ],
                ],
                [
                    'slug' => 'nova-receita',
                    'level' => 2,
                    'title' => 'Criar e editar',
                    'paragraphs' => [
                        'Para criar uma nova receita, escolha o paciente e monte a lista de itens: selecione os produtos, preencha anotações opcionais em cada linha e altere a quantidade quando necessário.',
                        'Para remover um produto da lista, use o ícone de lixeira {{icon:trash}} na linha. Para manter o item no rascunho mas excluí-lo da receita final (PDF/impressão), desmarque a caixa de seleção {{icon:checkbox}} ao lado do produto.',
                        'Há também campos de anotações gerais (uso interno e texto destinado ao paciente) além dos itens da receita.',
                    ],
                    'bullets' => [
                        'Com paciente e médico já definidos no formulário, o rascunho é salvo automaticamente enquanto você edita.',
                        'Na edição de uma receita existente, use Duplicar receita para criar uma nova com os mesmos produtos.',
                    ],
                ],
                [
                    'slug' => 'receitas-admin-medico',
                    'level' => 2,
                    'title' => 'Escolha do médico (administrador)',
                    'paragraphs' => [
                        'Na criação ou edição da receita você pode selecionar o médico responsável quando houver mais de um vínculo possível; caso o paciente já tenha um vínculo único, o sistema mantém esse profissional.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_ADMIN],
                ],
                [
                    'slug' => 'pdf-receita',
                    'level' => 2,
                    'title' => 'PDF e impressão',
                    'paragraphs' => [
                        'A geração de PDF e o download {{icon:download}} só são habilitados após você finalizar a receita: use o botão Finalizar no formulário. Enquanto a receita estiver em edição (rascunho), essas opções permanecem indisponíveis para evitar documentos incompletos.',
                        'Depois de finalizada, o layout do PDF reúne os dados do médico (incluindo assinatura em imagem, quando houver). Se a clínica vinculada ao médico tiver logo cadastrada no sistema (envio feito na gestão de clínicas, por um administrador), essa logo aparece no topo do documento. Sem logo na clínica, o cabeçalho mostra apenas as informações do profissional, sem recuo lateral reservado para imagem.',
                    ],
                    'bullets' => [],
                ],
            ],
        ];
    }

    private static function moduleAssistente(): array
    {
        return [
            'id' => 'assistente-receita',
            'title' => 'Assistente de receita',
            'iconKey' => 'assistente',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO],
            'sections' => [
                [
                    'slug' => 'fluxo-assistente',
                    'level' => 2,
                    'title' => 'Como usar o assistente',
                    'paragraphs' => [
                        'O {{icon:assistente}} Assistente de Receita guia você por perguntas e ajuda cruzando os parâmetros informados com os casos clínicos previstos, o que resulta em recomendações de produtos e na montagem da receita de forma estruturada.',
                    ],
                    'bullets' => [
                        'Inicie um novo fluxo, responda às etapas e revise o resultado antes de gerar ou vincular à {{icon:receitas}} receita.',
                    ],
                ],
                [
                    'slug' => 'assistente-admin-medico',
                    'level' => 2,
                    'title' => 'Administrador: médico do assistente',
                    'paragraphs' => [
                        'No topo da tela do assistente, o seletor de médico aparece para você escolher em qual cadastro profissional o fluxo será registrado (obrigatório antes de avançar quando há médicos cadastrados).',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_ADMIN],
                ],
            ],
        ];
    }

    private static function moduleRelatorios(): array
    {
        return [
            'id' => 'relatorios',
            'title' => 'Relatórios',
            'iconKey' => 'relatorios',
            'roles' => [User::ROLE_ADMIN, User::ROLE_MEDICO],
            'sections' => [
                [
                    'slug' => 'indice-relatorios',
                    'level' => 2,
                    'title' => 'Índice de relatórios',
                    'paragraphs' => [
                        'Na página {{icon:relatorios}} Relatórios cada card abre o relatório com filtros {{icon:filter}}, tabela ou resumo na tela e botões para exportar {{icon:export}} (Excel e/ou PDF, conforme o relatório).',
                    ],
                    'bullets' => [],
                ],
                [
                    'slug' => 'aquisicao-produtos',
                    'level' => 2,
                    'title' => 'Aquisição de produtos',
                    'paragraphs' => [
                        'Consolida informações sobre aquisição de produtos no período e filtros escolhidos (médicos, pacientes, produtos, datas) {{icon:filter}}.',
                        'Use os botões da tela para exportar em Excel ou solicitar PDF {{icon:export}}.',
                    ],
                    'bullets' => [],
                ],
                [
                    'slug' => 'receitas-medico',
                    'level' => 2,
                    'title' => 'Receitas por médico',
                    'paragraphs' => [
                        'Relatório administrativo que agrupa receitas por médico, com filtros de período {{icon:filter}} e exportação Excel/PDF {{icon:download}} para análise gerencial.',
                    ],
                    'bullets' => [],
                    'roles' => [User::ROLE_ADMIN],
                ],
            ],
        ];
    }

    private static function moduleProdutos(): array
    {
        return [
            'id' => 'produtos',
            'title' => 'Produtos (Somente administradores)',
            'iconKey' => 'produtos',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'sincronizar-tiny',
                    'level' => 2,
                    'title' => 'Sincronizar produtos (Tiny ERP)',
                    'paragraphs' => [
                        'O botão Sincronizar Produtos atualiza o catálogo a partir do Tiny ERP. Na API v2, por padrão entram só produtos com a tag clinicaweb; em {{icon:settings}} Configurações (integração Tiny) dá para desativar esse filtro ou tratar cenários em API legada (v3).',
                        'Use {{icon:search}} na listagem para localizar itens por código ou nome após a sincronização.',
                    ],
                    'bullets' => [],
                ],
                [
                    'slug' => 'edicao-clinicaweb',
                    'level' => 2,
                    'title' => 'Campos editáveis no ClinicaWeb',
                    'paragraphs' => [
                        'No painel de edição, código, unidade e preço de venda aparecem como dados vindos do Tiny (somente leitura). A seção Dados ClinicaWeb (editáveis) concentra o que você pode alterar: Nome, Descrição / Fórmula, Modo de uso, Anotações dos especialistas, Anotações internas e o Status (Ativo/Inativo) do produto.',
                        'Quando você sincroniza de novo, o sistema atualiza do Tiny itens como preço, custo, unidade e o vínculo com o produto no ERP; o nome só é preenchido automaticamente pelo Tiny se ainda estiver vazio no ClinicaWeb. Já descrição/fórmula, modo de uso, anotações, anotações internas e o texto do nome que você já salvou não são sobrescritos por novas sincronizações.',
                    ],
                    'bullets' => [
                        'Observação: o indicativo de ativo/inativo também pode ser alinhado ao Tiny em cada sincronização; ajustes locais podem ser refletidos de novo na próxima carga.',
                    ],
                ],
                [
                    'slug' => 'acoes-exportacao-importacao',
                    'level' => 2,
                    'title' => 'Menu + Ações: exportar e atualização em massa',
                    'paragraphs' => [
                        'No topo da página, + Ações abre o painel Outras ações. Em Exportar produtos {{icon:export}} você gera uma planilha (Excel ou CSV) com os registros conforme filtros de status e, se quiser, apenas itens pendentes.',
                        'Essa planilha serve para alterar em lote os campos editáveis (as mesmas colunas do modelo de importação — descrição/fórmula, modo de uso, anotações etc., identificadas pelo código do produto). Depois, use Importar alterações em massa: envie o arquivo, confira o preview e execute a importação para aplicar as mudanças.',
                    ],
                    'bullets' => [
                        'Baixe o modelo sugerido na área de importação para obter só as colunas aceitas pelo processamento em massa.',
                    ],
                ],
                [
                    'slug' => 'status-pendente',
                    'level' => 2,
                    'title' => 'Status “Pendente”',
                    'paragraphs' => [
                        'O selo Pendente aparece na lista quando o produto ainda não tem Descrição / Fórmula nem Modo de uso preenchidos (os dois campos vazios ao mesmo tempo). O filtro Pendentes do status usa o mesmo critério.',
                        'Para sair dessa condição, abra o produto pelo ícone de edição e preencha pelo menos um desses campos — o ideal é completar Descrição / Fórmula e Modo de uso para o catálogo e as receitas ficarem consistentes.',
                    ],
                    'bullets' => [],
                ],
            ],
        ];
    }

    private static function moduleCatalogo(): array
    {
        return [
            'id' => 'catalogo-produtos',
            'title' => 'Catálogo de produtos',
            'iconKey' => 'produtos',
            'roles' => [User::ROLE_MEDICO],
            'sections' => [
                [
                    'slug' => 'catalogo-leitura',
                    'level' => 2,
                    'title' => 'Consulta somente leitura',
                    'paragraphs' => [
                        'Esta tela existe apenas para usuários com perfil Médico: o item no menu e a rota /catalogo-produtos não aparecem para administrador, secretária nem demais perfis. Quem gerencia o cadastro mestre usa a área administrativa de produtos.',
                        'O médico acessa o catálogo para consultar produtos disponíveis sem alterar o cadastro mestre.',
                    ],
                    'bullets' => [
                        'Use {{icon:search}} e {{icon:filter}} para localizar itens durante o atendimento ou elaboração de receitas.',
                        'Os botões PDF e Excel no topo disparam exportação assíncrona do catálogo {{icon:download}}; você recebe e-mail com o link quando o arquivo estiver pronto.',
                    ],
                ],
            ],
        ];
    }

    private static function moduleClinicas(): array
    {
        return [
            'id' => 'clinicas',
            'title' => 'Clínicas',
            'iconKey' => 'clinicas',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'gestao-clinicas',
                    'level' => 2,
                    'title' => 'Gestão de clínicas',
                    'paragraphs' => [
                        'Crie e edite clínicas, endereços e vínculos com médicos (incluindo associação de vários profissionais à mesma unidade).',
                        'No formulário de nova clínica ou edição, use o campo Logo da clínica (imagem) para enviar um arquivo (tipos e tamanho máximo indicados ao lado do upload). Na edição, é possível trocar a imagem ou removê-la antes de salvar.',
                        'Quando a clínica possui logo cadastrada, essa imagem aparece no cabeçalho do PDF da receita gerado para receitas cujo médico esteja ligado àquela clínica (vínculo direto no cadastro do médico ou participação na lista de médicos da clínica). Se não houver logo, o PDF não reserva espaço nem mostra marcador: os dados do médico ficam alinhados à margem esquerda do documento.',
                    ],
                    'bullets' => [
                        'Secretárias vinculadas a uma clínica enxergam apenas pacientes dos médicos daquela clínica.',
                    ],
                ],
            ],
        ];
    }

    private static function moduleUsuarios(): array
    {
        return [
            'id' => 'usuarios',
            'title' => 'Usuários',
            'iconKey' => 'users',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'usuarios-listagem',
                    'level' => 2,
                    'title' => 'Listagem e novo usuário',
                    'paragraphs' => [
                        'A página Gerenciamento de Usuários concentra todas as contas que podem acessar o sistema. Cada linha mostra Nome, E-mail, Perfil (etiqueta colorida), Status (Ativo ou Inativo) e, em Ações, o botão de editar (ícone de lápis).',
                        'Use {{icon:plus}} Novo Usuário no topo para abrir o painel lateral de cadastro. Quando houver muitos registros, a listagem é paginada na parte inferior da tabela.',
                        'Em telas menores, cada usuário aparece como um cartão: toque no cartão ou no atalho Editar para abrir o mesmo painel de criação ou alteração.',
                        'Novos acessos costumam seguir o processo da sua implantação (e-mail de convite, link para definir senha ou senha inicial repassada pela equipe). O administrador pode criar o usuário aqui e combinar com esse fluxo.',
                    ],
                    'bullets' => [
                        'Para bloquear o login sem apagar dados, prefira colocar o usuário como Inativo na edição (veja a seção seguinte) em vez de usar {{icon:trash}} Excluir.',
                    ],
                ],
                [
                    'slug' => 'usuarios-formulario',
                    'level' => 2,
                    'title' => 'Formulário, perfis e médico',
                    'paragraphs' => [
                        'O painel Novo Usuário ou Editar Usuário divide o conteúdo em blocos. Em Dados de acesso você define perfil, nome, e-mail e senha. A senha é obrigatória na criação; na edição, deixe em branco para manter a atual.',
                        'O campo Perfil é obrigatório. Os perfis oferecidos no formulário podem variar conforme a configuração do ambiente. Em geral você trabalha com Administrador (gestão ampla do sistema), Médico (atendimento e receitas ligados ao cadastro profissional) e Secretária (atua na {{link:#clinicas-gestao-clinicas|clínica}} à qual for vinculada, com escopo de pacientes alinhado a essa unidade).',
                        'Para Secretária, o sistema exige escolher uma Clínica na lista cadastrada. Garanta que as unidades existam em {{link:#clinicas-gestao-clinicas|Gestão de clínicas}} antes de criar o acesso.',
                        'Ao editar um usuário já existente, aparece o campo Status da conta (Ativo/Inativo), que controla se o login é permitido.',
                        'Se o perfil for Médico, abre a seção Dados profissionais: CRM e UF do CRM obrigatórios, celular obrigatório, telefone fixo e especialidade opcionais, upload de assinatura (imagem para receitas em PDF) com opção de remover a imagem atual, várias clínicas vinculadas (adicionar e retirar pela lista), endereços com nome, CEP (o sistema pode completar logradouro, bairro, cidade e UF ao sair do campo) e demais campos de endereço. Na edição, o bloco médico também traz Status do cadastro profissional (Ativo/Inativo). Os médicos são cadastrados como usuários com esse perfil neste mesmo fluxo.',
                        'No rodapé do painel estão {{icon:trash}} Excluir (somente na edição, com confirmação Sim/Não), Cancelar e Salvar. O botão Salvar só habilita quando os campos obrigatórios do perfil escolhido estão preenchidos.',
                    ],
                    'numbered_bullets' => [
                        'Administrador — acesso administrativo à configuração, cadastros e relatórios conforme as telas do sistema.',
                        'Médico — usuário atrelado ao profissional: CRM, contatos, clínicas, endereços e assinatura; usado no fluxo de pacientes e receitas.',
                        'Secretária — usuário obrigatoriamente ligado a uma clínica; enxerga e opera no contexto dos médicos e pacientes daquela unidade.',
                    ],
                    'bullets' => [
                        'Encerrar acesso sem perder histórico: altere Status para Inativo em vez de excluir o registro.',
                    ],
                ],
            ],
        ];
    }

    private static function moduleKarnaugh(): array
    {
        return [
            'id' => 'tabelas-karnaugh',
            'title' => 'Tabelas Karnaugh',
            'iconKey' => 'karnaugh',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'karnaugh-admin',
                    'level' => 2,
                    'title' => 'Listagem e importação',
                    'paragraphs' => [
                        'As tabelas Karnaugh alimentam a lógica do {{link:#assistente-receita|Assistente de receita}}: cada caso clínico da planilha é cruzado com as categorias de produto para montar as sugestões no atendimento.',
                        'Use o botão Importar no topo para abrir o painel: envie um arquivo CSV ou XLSX, informe o nome da tabela (obrigatório), opcionalmente uma descrição e marque a opção para definir aquela versão como tabela padrão quando for a base principal do assistente.',
                        'Na importação o sistema valida o arquivo; se houver erros de estrutura ou conteúdo, as mensagens aparecem no formulário. Corrija a planilha e tente de novo antes de usar a tabela em produção.',
                        'A tabela na tela mostra as colunas Nome (com a etiqueta Padrão na linha que está marcada como padrão), Casos (quantidade de casos clínicos distintos), Status (Ativa ou Inativa), Arquivo original e Ações.',
                        'Em telas menores, cada cartão concentra as mesmas informações e, além dos atalhos abaixo, oferece ativar ou desativar a versão com os ícones de reprodução e pausa.',
                    ],
                    'bullets' => [
                        '{{icon:eye}} Visualizar: abre a {{link:#tabelas-karnaugh-karnaugh-visualizacao|grade completa da tabela}} (casos, categorias e produtos sugeridos).',
                        '{{icon:download}} na coluna Arquivo original: baixa a planilha que foi enviada na importação (o mesmo ícone aparece no detalhe da tabela).',
                        '{{icon:star}} Definir como padrão: só aparece para tabelas ativas que ainda não são a padrão; a linha que já é padrão não exibe a estrela.',
                        '{{icon:trash}} Excluir: só para tabelas que não são a padrão; o sistema pede confirmação (Sim/Não) antes de remover o cadastro.',
                    ],
                ],
                [
                    'slug' => 'karnaugh-visualizacao',
                    'level' => 2,
                    'title' => 'Visualização da tabela',
                    'paragraphs' => [
                        'Ao abrir uma tabela pela {{link:#tabelas-karnaugh-karnaugh-admin|listagem}}, use a seta ao lado do título para voltar à lista de versões.',
                        'No topo há o campo {{icon:search}} “Buscar caso ou produto…” para filtrar a matriz pelo código do caso, pelo produto ou pela categoria.',
                        'Os cartões exibem Total de casos, Tipos (quantidade de categorias/colunas da planilha) e Arquivo original; neste último, {{icon:download}} baixa de novo o arquivo importado.',
                    ],
                    'bullets' => [
                        'A legenda indica o primeiro grupo (pré-selecionado no assistente) e o segundo grupo (opcional), com o mesmo esquema de cores da grade.',
                        'Em cada célula aparece o código do produto quando há sugestão para aquela categoria; quando houver marcação correspondente na planilha, o ícone {{icon:check}} acompanha o texto, como na interface.',
                    ],
                ],
            ],
        ];
    }

    private static function moduleRegras(): array
    {
        return [
            'id' => 'regras-condicionais',
            'title' => 'Regras condicionais',
            'iconKey' => 'regras',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'regras-como-funciona',
                    'level' => 2,
                    'title' => 'Como as regras entram no Assistente de receita',
                    'paragraphs' => [
                        'As regras condicionais orientam o {{link:#assistente-receita|Assistente de receita}} depois que o profissional responde à avaliação clínica. Elas não substituem a {{link:#tabelas-karnaugh|tabela Karnaugh}}: primeiro o sistema decide qual tabela usar (ou usa a tabela padrão), depois lê aquela planilha para o caso e, em seguida, aplica ajustes vindos das regras de modificação.',
                        'O fluxo é sempre o mesmo, nesta ordem:',
                    ],
                    'paragraphs_after_numbered' => [
                        'Na seleção de tabela, se mais de uma regra tiver condições atendidas na mesma avaliação, vale a última que definir qual Karnaugh usar, na ordem em que as regras aparecem na lista administrativa. Por isso a ordem importa tanto quanto o texto da condição.',
                    ],
                    'numbered_bullets' => [
                        'Avaliar, em ordem, as regras do tipo Seleção de tabela que estiverem ativas.',
                        'Se nenhuma definir uma tabela, usar a tabela Karnaugh marcada como padrão no cadastro de tabelas.',
                        'Avaliar, em ordem, as regras do tipo Modificação de tabela vinculadas à tabela que ficou escolhida.',
                        'Montar a lista de produtos sugeridos a partir da célula do caso na tabela, já respeitando inclusões, exclusões, quantidades e marcações impostas pelas regras.',
                    ],
                    'bullets' => [
                        'Se uma regra de modificação remove um produto e outra inclui o mesmo item, a remoção prevalece: ele não entra na sugestão final.',
                    ],
                ],
                [
                    'slug' => 'regras-condicoes-e-acoes',
                    'level' => 2,
                    'title' => 'Tipos de regra, condições (SE) e ações (ENTÃO)',
                    'paragraphs' => [
                        'Cada regra combina um bloco SE (uma ou mais condições) com um bloco ENTÃO (uma ou mais ações). Todas as condições da regra precisam ser verdadeiras ao mesmo tempo (lógica “e”). Use Adicionar condição ou Adicionar ação no formulário para montar regras mais específicas.',
                    ],
                    'bullets' => [
                        'Seleção de tabela: quando o SE for atendido, a única ação possível é Usar tabela Karnaugh, ou seja, passar a usar aquela planilha naquele atendimento. Serve para trocar o fluxo inteiro (por exemplo, tabela específica para rosácea) conforme dados da avaliação.',
                        'Modificação de tabela: a regra fica atrelada a uma tabela Karnaugh escolhida no campo Tabela alvo. Só entra em cena quando a tabela selecionada no passo anterior for exatamente essa. As ações disponíveis são: Adicionar item (inclui um produto extra na sugestão, com marcação recomendado/opcional e tipo do produto obrigatório no formulário), Remover item (tira da lista um produto que viria da tabela), Modificar quantidade (substitui a quantidade padrão daquele produto) e Alterar marcação (força o produto como recomendado ou opcional na receita).',
                        'Campos que você pode usar nas condições: Gravidez, Rosácea, Fototipo, Tipo de pele, Manchas, Rugas, Acne e Flacidez — sempre comparados ao que foi informado na avaliação do assistente.',
                        'Operadores: Igual a, Diferente de e Qualquer valor (útil quando a condição deve ignorar o conteúdo do campo e só “passar” para combinar com outras linhas do SE).',
                    ],
                ],
                [
                    'slug' => 'regras-tela',
                    'level' => 2,
                    'title' => 'Tela Regras condicionais',
                    'paragraphs' => [
                        'Administradores configuram tudo na página de regras do assistente (menu do Assistente de receita).',
                    ],
                    'bullets' => [
                        'Use Nova regra para abrir o painel: nome obrigatório, descrição opcional, escolha do tipo (Seleção de tabela ou Modificação de tabela), condições, ações e caixa Regra ativa.',
                        'Para Modificação de tabela, preencha Tabela alvo; sem isso a regra não sabe a qual planilha ela pertence.',
                        'As abas no topo filtram a lista (todas as regras, só Seleção de tabela ou regras ligadas a uma tabela Karnaugh específica).',
                        'O número à esquerda de cada card (1, 2, 3…) indica a ordem em que as regras são avaliadas; regras novas entram ao final da lista até serem reordenadas.',
                        'Cada linha tem ícones para editar, desativar sem apagar e excluir (com confirmação).',
                    ],
                ],
            ],
        ];
    }

    private static function moduleExports(): array
    {
        return [
            'id' => 'exports',
            'title' => 'Exportar dados',
            'iconKey' => 'export',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'exports-admin',
                    'level' => 2,
                    'title' => 'Exportações em lote',
                    'paragraphs' => [
                        'Na tela de exportar dados você escolhe o tipo de conjunto (pacientes, médicos, produtos, entre outros listados), dispara o pedido e acompanha o processamento.',
                    ],
                    'bullets' => [
                        'Quando o status indicar conclusão, use o download {{icon:download}} do arquivo gerado.',
                        'Use Limpar histórico de exportações para esvaziar a lista de pedidos antigos na própria página.',
                    ],
                ],
            ],
        ];
    }

    private static function moduleSettings(): array
    {
        return [
            'id' => 'settings',
            'title' => 'Configurações e integrações',
            'iconKey' => 'settings',
            'roles' => [User::ROLE_ADMIN],
            'sections' => [
                [
                    'slug' => 'tiny-aba-conexao',
                    'level' => 2,
                    'title' => 'Na aba Tiny: conexão e webhook',
                    'paragraphs' => [
                        'Depois de informar o token e salvar, use o botão Testar conexão no topo da tela para confirmar que o ClinicaWeb fala com a API do Tiny. Se o teste falhar, corrija credenciais ou versão da API (V2 ou V3) antes de contar com sincronização ou pedidos.',
                        'Na mesma aba aparece a URL do webhook. Copie-a e cadastre no Tiny no caminho indicado no painel (em geral Configurações > E-commerce > Integrações > Webhook). Sem esse cadastro, o sistema não recebe avisos quando o pedido muda de situação no ERP. O endereço do ClinicaWeb precisa ser acessível pela internet; em ambiente local o Tiny não consegue acionar o webhook.',
                    ],
                    'bullets' => [],
                ],
                [
                    'slug' => 'integracao-tiny',
                    'level' => 2,
                    'title' => 'Integração Tiny ERP',
                    'paragraphs' => [
                        'Com a integração Tiny ligada em {{icon:settings}} Configurações, os fluxos abaixo passam a rodar em conjunto com o ERP.',
                    ],
                    'bullets' => [
                        'Produtos: na área Produtos, use Sincronizar produtos para trazer itens do Tiny (em geral os com a tag clinicaweb, conforme a configuração). Campos editáveis, importação em massa e o que uma nova sincronização preserva ou atualiza estão no {{link:#mod-produtos|módulo Produtos deste manual}}.',
                        'Pacientes: em rotina automática (uma vez ao dia), o sistema busca contatos no Tiny e alinha com pacientes no ClinicaWeb, gravando o vínculo com o ERP. Contatos sem CPF válido com 11 dígitos costumam ser ignorados nessa carga.',
                        'Receitas: ao usar Finalizar em uma receita com o Tiny ativo, os dados são enviados ao ERP: o cliente é criado ou atualizado no Tiny conforme o paciente, e é gerado um pedido de venda em aberto com os itens da receita e referência ao número da receita.',
                        'Retorno do pedido (webhook): quando o pedido no Tiny avança para situações como finalizado ou enviado, o ClinicaWeb recebe o aviso, localiza a receita ligada ao pedido e atualiza a tela: os itens efetivamente comercializados aparecem marcados em verde e registra-se a data de aquisição daquele produto para aquele paciente. Situações de cancelamento também são tratadas automaticamente.',
                    ],
                ],
                [
                    'slug' => 'integracao-rd-station',
                    'level' => 2,
                    'title' => 'Integração RD Station CRM',
                    'paragraphs' => [
                        'A aba RD Station concentra credenciais da API (Client ID e Client Secret), o interruptor de integração e os identificadores que o CRM exige para criar negócios automaticamente.',
                        'Depois de salvar Client ID e Secret, use Autorizar aplicativo para concluir o OAuth: o RD Station redireciona de volta ao ClinicaWeb e os tokens ficam armazenados. O botão Testar conexão confirma se a API responde com o token atual.',
                        'Com a integração ativada, ao Finalizar uma receita que ainda não tinha atendimento vinculado, o sistema enfileira a criação no CRM: organização e contato a partir do paciente (reaproveitando IDs já salvos quando existirem), depois uma negociação (deal) na etapa configurada, com campos customizados para médico e receita e linha de produto padrão.',
                        'Na mesma aba você informa o Owner (obrigatório para o job rodar), o Stage da negociação, os IDs dos campos customizados de médico e receita e o produto padrão da linha do negócio — todos devem existir no seu pipeline RD Station.',
                    ],
                    'bullets' => [
                        'Sem Owner configurado, a criação da negociação falha: preencha antes de produção.',
                        'Se desconectar ou revogar tokens, será necessário autorizar de novo.',
                    ],
                ],
            ],
        ];
    }
}
