import { Head } from '@inertiajs/react';
import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import PageHeader from '@/Components/PageHeader';
import { NavIconByKey, NavIconSearch } from '@/Components/NavIcons';
import ManualRichText, { plainTextForSearch } from '@/Components/ManualRichText';

function normalizeText(s) {
    if (!s || typeof s !== 'string') {
        return '';
    }
    return s
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{M}/gu, '');
}

function sectionMatches(section, q) {
    if (!q) {
        return true;
    }
    if (normalizeText(section.title).includes(q)) {
        return true;
    }
    for (const p of section.paragraphs || []) {
        if (normalizeText(plainTextForSearch(p)).includes(q)) {
            return true;
        }
    }
    for (const b of section.bullets || []) {
        if (normalizeText(plainTextForSearch(b)).includes(q)) {
            return true;
        }
    }
    for (const b of section.numbered_bullets || []) {
        if (normalizeText(plainTextForSearch(b)).includes(q)) {
            return true;
        }
    }
    for (const p of section.paragraphs_after_numbered || []) {
        if (normalizeText(plainTextForSearch(p)).includes(q)) {
            return true;
        }
    }
    return false;
}

function filterModules(modules, rawQuery) {
    const q = normalizeText(rawQuery.trim());
    if (!q) {
        return modules;
    }

    return modules
        .map((mod) => {
            const titleHit = normalizeText(mod.title).includes(q);
            const matchingSections = (mod.sections || []).filter((sec) => sectionMatches(sec, q));
            if (titleHit) {
                return mod;
            }
            if (matchingSections.length > 0) {
                return { ...mod, sections: matchingSections };
            }
            return null;
        })
        .filter(Boolean);
}

/** Altura do header fixo do dashboard (h-16) + folga em px — índice nunca sobrepõe o header (z-index menor). */
const DASHBOARD_HEADER_OFFSET_PX = 64 + 12;

/**
 * Índice "Nesta página" no desktop: position fixed calculado em JS (scroll/resize),
 * porque sticky costuma falhar com ancestrais overflow no layout.
 */
function ManualTocNav({ filtered, gridRef }) {
    const columnRef = useRef(null);
    const panelRef = useRef(null);
    const [panelStyle, setPanelStyle] = useState({});

    const layoutPanel = useCallback(() => {
        if (typeof window === 'undefined' || window.innerWidth < 1024) {
            setPanelStyle({});
            return;
        }
        const col = columnRef.current;
        if (!col) {
            return;
        }
        const r = col.getBoundingClientRect();
        const top = DASHBOARD_HEADER_OFFSET_PX;
        setPanelStyle({
            position: 'fixed',
            top,
            left: r.left,
            width: r.width,
            maxHeight: `calc(100vh - ${top + 16}px)`,
            overflowY: 'auto',
            zIndex: 5,
            boxSizing: 'border-box',
        });
    }, []);

    useLayoutEffect(() => {
        layoutPanel();
        let raf = 0;
        const onWin = () => {
            cancelAnimationFrame(raf);
            raf = requestAnimationFrame(() => layoutPanel());
        };
        window.addEventListener('scroll', onWin, { passive: true });
        window.addEventListener('resize', onWin, { passive: true });
        const ro = new ResizeObserver(() => layoutPanel());
        if (gridRef?.current) {
            ro.observe(gridRef.current);
        }
        if (columnRef.current) {
            ro.observe(columnRef.current);
        }
        return () => {
            cancelAnimationFrame(raf);
            window.removeEventListener('scroll', onWin);
            window.removeEventListener('resize', onWin);
            ro.disconnect();
        };
    }, [layoutPanel, filtered, gridRef]);

    return (
        <nav aria-label="Índice do manual" className="hidden lg:block relative w-full min-w-0">
            <div ref={columnRef} className="w-full min-h-px" aria-hidden />
            <div
                ref={panelRef}
                className="rounded-xl border border-gray-200 bg-white shadow-sm p-4 overscroll-contain"
                style={Object.keys(panelStyle).length ? panelStyle : undefined}
            >
                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3 px-1">
                    Nesta página
                </p>
                <ul className="space-y-3 text-sm">
                    {filtered.map((mod) => (
                        <li key={mod.id}>
                            <a
                                href={`#mod-${mod.id}`}
                                className="flex items-start gap-2 rounded-md px-2 py-1.5 -mx-2 text-gray-800 hover:bg-emerald-50 hover:text-emerald-800 font-medium transition-colors"
                            >
                                <span className="mt-0.5 text-emerald-700 flex-shrink-0">
                                    <NavIconByKey iconKey={mod.iconKey} className="w-4 h-4" />
                                </span>
                                <span className="leading-snug">{mod.title}</span>
                            </a>
                            <ul className="mt-1.5 ml-2 space-y-0.5 border-l-2 border-emerald-100 pl-3">
                                {(mod.sections || []).map((sec) => (
                                    <li key={`${mod.id}-${sec.slug}`}>
                                        <a
                                            href={`#${mod.id}-${sec.slug}`}
                                            className={`block rounded px-1.5 py-0.5 text-gray-600 hover:bg-emerald-50/80 hover:text-emerald-800 transition-colors ${
                                                sec.level === 3 ? 'text-xs' : 'text-sm'
                                            }`}
                                        >
                                            {sec.title}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </nav>
    );
}

export default function ManualIndex({ modules = [] }) {
    const manualGridRef = useRef(null);
    const [query, setQuery] = useState('');
    const filtered = useMemo(() => filterModules(modules, query), [modules, query]);
    const trimmed = query.trim();
    const emptySearch = trimmed.length > 0 && filtered.length === 0;

    return (
        <>
            <Head title="Manual de uso" />

            <DashboardLayout>
                <div className="py-4 lg:py-6 px-0">
                    <div
                        ref={manualGridRef}
                        className="lg:grid lg:grid-cols-[1fr_minmax(220px,280px)] lg:gap-8 xl:gap-10 items-start"
                    >
                        <div className="min-w-0 space-y-6">
                            <PageHeader
                                title="Manual de uso"
                                description="Documentação por módulo. Em telas grandes o índice fica à direita; no celular use os atalhos rápidos. A busca filtra tópicos e trechos."
                            />

                            <div className="rounded-xl border border-gray-200 bg-white shadow-sm p-4 sm:p-5">
                                <div className="flex flex-col sm:flex-row sm:items-center gap-3">
                                    <label htmlFor="manual-search" className="sr-only">
                                        Buscar no manual
                                    </label>
                                    <div className="relative flex-1 min-w-0">
                                        <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                                            <NavIconSearch className="w-5 h-5" />
                                        </span>
                                        <input
                                            id="manual-search"
                                            type="search"
                                            value={query}
                                            onChange={(e) => setQuery(e.target.value)}
                                            placeholder="Buscar no manual…"
                                            className="w-full rounded-lg border border-gray-300 pl-11 pr-4 py-2.5 text-sm text-gray-900 shadow-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 outline-none"
                                            autoComplete="off"
                                        />
                                    </div>
                                    {trimmed && (
                                        <button
                                            type="button"
                                            onClick={() => setQuery('')}
                                            className="text-sm font-medium text-emerald-700 hover:text-emerald-800 self-start sm:self-center shrink-0"
                                        >
                                            Limpar busca
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div className="lg:hidden rounded-xl border border-gray-200 bg-white shadow-sm p-4">
                                <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                                    Atalhos rápidos
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {filtered.map((mod) => (
                                        <a
                                            key={mod.id}
                                            href={`#mod-${mod.id}`}
                                            className="inline-flex items-center gap-1.5 rounded-full border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-medium text-gray-800 hover:border-emerald-200 hover:bg-emerald-50 hover:text-emerald-900 transition-colors"
                                        >
                                            <NavIconByKey iconKey={mod.iconKey} className="w-3.5 h-3.5 text-emerald-700" />
                                            {mod.title}
                                        </a>
                                    ))}
                                </div>
                            </div>

                            <div className="space-y-10 lg:space-y-12">
                                {emptySearch && (
                                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        Nenhum trecho encontrado para &quot;{trimmed}&quot;. Tente outras palavras ou
                                        limpe a busca.
                                    </div>
                                )}

                                {filtered.map((mod) => (
                                    <article
                                        key={mod.id}
                                        id={`mod-${mod.id}`}
                                        className="scroll-mt-24 rounded-xl border border-gray-200 bg-white shadow-md overflow-hidden ring-1 ring-gray-900/5"
                                    >
                                        <header className="flex items-center gap-4 px-5 py-4 sm:px-6 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
                                            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-800 shadow-sm">
                                                <NavIconByKey iconKey={mod.iconKey} className="w-6 h-6" />
                                            </span>
                                            <h2 className="text-xl font-semibold text-gray-900 tracking-tight">
                                                {mod.title}
                                            </h2>
                                        </header>
                                        <div className="px-5 py-6 sm:px-8 sm:py-8 max-w-none">
                                            {(mod.sections || []).map((sec, secIndex) => {
                                                const HeadingTag = sec.level === 3 ? 'h4' : 'h3';
                                                const showDivider = secIndex > 0;
                                                return (
                                                    <section
                                                        key={sec.slug}
                                                        id={`${mod.id}-${sec.slug}`}
                                                        className={`scroll-mt-28 ${showDivider ? 'pt-8 mt-8 border-t border-gray-100' : ''}`}
                                                    >
                                                        <HeadingTag
                                                            className={
                                                                sec.level === 3
                                                                    ? 'text-base font-semibold text-gray-900 mb-3'
                                                                    : 'text-lg font-semibold text-gray-900 mb-4'
                                                            }
                                                        >
                                                            {sec.title}
                                                        </HeadingTag>
                                                        {(sec.paragraphs || []).map((p, i) => (
                                                            <p
                                                                key={i}
                                                                className="text-gray-700 text-base leading-relaxed mb-4 last:mb-0"
                                                            >
                                                                <ManualRichText text={p} />
                                                            </p>
                                                        ))}
                                                        {(sec.numbered_bullets || []).length > 0 && (
                                                            <ol className="mt-4 list-decimal pl-5 space-y-3 text-base text-gray-700 marker:text-gray-600">
                                                                {sec.numbered_bullets.map((item, i) => (
                                                                    <li key={i} className="leading-relaxed pl-1">
                                                                        <ManualRichText text={item} />
                                                                    </li>
                                                                ))}
                                                            </ol>
                                                        )}
                                                        {(sec.paragraphs_after_numbered || []).map((p, i) => (
                                                            <p
                                                                key={`after-num-${i}`}
                                                                className="text-gray-700 text-base leading-relaxed mt-4 mb-0"
                                                            >
                                                                <ManualRichText text={p} />
                                                            </p>
                                                        ))}
                                                        {(sec.bullets || []).length > 0 && (
                                                            <ul className="mt-4 list-disc pl-5 space-y-3 text-base text-gray-700">
                                                                {sec.bullets.map((item, i) => (
                                                                    <li key={i} className="leading-relaxed">
                                                                        <ManualRichText text={item} />
                                                                    </li>
                                                                ))}
                                                            </ul>
                                                        )}
                                                    </section>
                                                );
                                            })}
                                        </div>
                                    </article>
                                ))}
                            </div>
                        </div>

                        <ManualTocNav filtered={filtered} gridRef={manualGridRef} />
                    </div>
                </div>
            </DashboardLayout>
        </>
    );
}
