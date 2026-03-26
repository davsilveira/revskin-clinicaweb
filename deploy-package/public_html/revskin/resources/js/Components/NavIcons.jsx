/**
 * Ícones da sidebar (Heroicons outline) + chaves usadas no manual.
 * className padrão alinhado aos links da sidebar.
 */
const defaultNavClass = 'w-5 h-5 flex-shrink-0';

function Svg({ className = defaultNavClass, children, title }) {
    return (
        <svg
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            aria-hidden={title ? undefined : true}
        >
            {title ? <title>{title}</title> : null}
            {children}
        </svg>
    );
}

export function NavIconHome({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
            />
        </Svg>
    );
}

export function NavIconPacientes({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
        </Svg>
    );
}

export function NavIconReceitas({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
        </Svg>
    );
}

export function NavIconAssistente({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
            />
        </Svg>
    );
}

export function NavIconRelatorios({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
        </Svg>
    );
}

export function NavIconProdutos({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
            />
        </Svg>
    );
}

export function NavIconCallcenter({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
            />
        </Svg>
    );
}

export function NavIconClinicas({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
            />
        </Svg>
    );
}

export function NavIconUsers({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
            />
        </Svg>
    );
}

export function NavIconMedicos({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z"
            />
        </Svg>
    );
}

export function NavIconKarnaugh({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"
            />
        </Svg>
    );
}

export function NavIconRegras({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
            />
        </Svg>
    );
}

export function NavIconExport({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"
            />
        </Svg>
    );
}

export function NavIconSettings({ className } = {}) {
    return (
        <Svg className={className}>
            <circle cx="12" cy="12" r="3" strokeWidth={2} />
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 4V2m0 20v-2m8-8h2M2 12h2m13.657-6.343l1.414-1.414M4.929 19.071l1.414-1.414m0-11.314L4.93 4.93m14.142 14.142l-1.414-1.414"
            />
        </Svg>
    );
}

export function NavIconManual({ className } = {}) {
    return (
        <Svg className={className} title="Manual">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"
            />
        </Svg>
    );
}

export function NavIconPerfil({ className } = {}) {
    return (
        <Svg className={className}>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
            />
        </Svg>
    );
}

export function NavIconLogo({ className = 'w-5 h-5 text-white' } = {}) {
    return (
        <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden>
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
            />
        </svg>
    );
}

/* ---- Ícones de ação para o manual (inline), alinhados às telas ---- */

export function NavIconMenuHamburger({ className } = {}) {
    return (
        <Svg className={className} title="Menu">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
        </Svg>
    );
}

export function NavIconSearch({ className } = {}) {
    return (
        <Svg className={className} title="Buscar">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
            />
        </Svg>
    );
}

export function NavIconFilter({ className } = {}) {
    return (
        <Svg className={className} title="Filtrar">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
            />
        </Svg>
    );
}

export function NavIconPlus({ className } = {}) {
    return (
        <Svg className={className} title="Adicionar">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
        </Svg>
    );
}

export function NavIconChevronDown({ className } = {}) {
    return (
        <Svg className={className} title="Expandir menu">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </Svg>
    );
}

/** Estado “sidebar expandida” — mesmo traço do botão Recolher menu */
export function NavIconSidebarCollapse({ className } = {}) {
    return (
        <Svg className={className} title="Recolher menu lateral">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </Svg>
    );
}

export function NavIconLogout({ className } = {}) {
    return (
        <Svg className={className} title="Sair">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
            />
        </Svg>
    );
}

export function NavIconDownload({ className } = {}) {
    return (
        <Svg className={className} title="Download">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"
            />
        </Svg>
    );
}

/** Mesmo traço do botão remover em ReceitaFormItemRow */
export function NavIconTrash({ className } = {}) {
    return (
        <Svg className={className} title="Remover">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
            />
        </Svg>
    );
}

/** Caixa de seleção “incluir na receita impressa” (campo imprimir) */
export function NavIconCheckboxInclude({ className } = {}) {
    return (
        <Svg className={className} title="Incluir na receita final">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M6 4h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2z"
            />
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4" />
        </Svg>
    );
}

/** Visualizar tabela Karnaugh (listagem) */
export function NavIconEye({ className } = {}) {
    return (
        <Svg className={className} title="Visualizar">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
            />
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
            />
        </Svg>
    );
}

/** Definir como tabela padrão (listagem Karnaugh) */
export function NavIconStar({ className } = {}) {
    return (
        <Svg className={className} title="Definir como padrão">
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
            />
        </Svg>
    );
}

/** Check nas células da grade Karnaugh (produto marcado) */
export function NavIconCheckMark({ className = 'w-3.5 h-3.5 text-emerald-700 flex-shrink-0' } = {}) {
    return (
        <svg className={className} fill="currentColor" viewBox="0 0 20 20" aria-hidden>
            <path
                fillRule="evenodd"
                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                clipRule="evenodd"
            />
        </svg>
    );
}

/** Mapa de chave (API manual / sidebar) → componente */
export const navIconMap = {
    home: NavIconHome,
    pacientes: NavIconPacientes,
    receitas: NavIconReceitas,
    assistente: NavIconAssistente,
    relatorios: NavIconRelatorios,
    produtos: NavIconProdutos,
    callcenter: NavIconCallcenter,
    clinicas: NavIconClinicas,
    users: NavIconUsers,
    medicos: NavIconMedicos,
    karnaugh: NavIconKarnaugh,
    regras: NavIconRegras,
    export: NavIconExport,
    settings: NavIconSettings,
    manual: NavIconManual,
    perfil: NavIconPerfil,
};

export function NavIconByKey({ iconKey, className }) {
    const Cmp = navIconMap[iconKey] || NavIconManual;
    return <Cmp className={className} />;
}

/** Ícones referenciados em {{icon:chave}} no texto do manual */
export const manualInlineIconMap = {
    menu: NavIconMenuHamburger,
    search: NavIconSearch,
    filter: NavIconFilter,
    plus: NavIconPlus,
    chevrondown: NavIconChevronDown,
    sidebarcollapse: NavIconSidebarCollapse,
    logout: NavIconLogout,
    download: NavIconDownload,
    export: NavIconExport,
    settings: NavIconSettings,
    perfil: NavIconPerfil,
    receitas: NavIconReceitas,
    assistente: NavIconAssistente,
    relatorios: NavIconRelatorios,
    produtos: NavIconProdutos,
    trash: NavIconTrash,
    checkbox: NavIconCheckboxInclude,
    eye: NavIconEye,
    star: NavIconStar,
    check: NavIconCheckMark,
};

export const manualInlineIconLabels = {
    menu: 'Menu (três linhas)',
    search: 'Buscar',
    filter: 'Filtros',
    plus: 'Novo / adicionar',
    chevrondown: 'Menu da conta',
    sidebarcollapse: 'Recolher menu lateral',
    logout: 'Sair',
    download: 'Download / PDF',
    export: 'Exportar',
    settings: 'Configurações',
    perfil: 'Meu perfil',
    receitas: 'Receitas',
    assistente: 'Assistente de receita',
    relatorios: 'Relatórios',
    produtos: 'Produtos',
    trash: 'Remover item (lixeira)',
    checkbox: 'Incluir na receita final (PDF/impressão)',
    eye: 'Visualizar tabela',
    star: 'Definir como tabela padrão',
    check: 'Produto marcado na célula',
};

const inlineSvgClass = 'w-4 h-4 text-emerald-700 flex-shrink-0';
const inlineTrashClass = 'w-4 h-4 text-red-600 flex-shrink-0';
const inlineCheckClass = 'w-3.5 h-3.5 text-emerald-700 flex-shrink-0';

export function ManualInlineIconByKey({ iconKey }) {
    const key = String(iconKey || '')
        .toLowerCase()
        .replace(/_/g, '');
    const Cmp = manualInlineIconMap[key];
    if (!Cmp) {
        return null;
    }
    const title = manualInlineIconLabels[key];
    const svgClass =
        key === 'trash' ? inlineTrashClass : key === 'check' ? inlineCheckClass : inlineSvgClass;
    return (
        <span className="inline-flex items-center mx-0.5 align-middle" title={title}>
            <Cmp className={svgClass} />
        </span>
    );
}
