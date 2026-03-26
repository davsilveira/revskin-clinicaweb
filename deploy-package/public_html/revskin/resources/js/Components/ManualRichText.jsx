import { Fragment } from 'react';
import { ManualInlineIconByKey } from '@/Components/NavIcons';

/**
 * Remove tokens {{icon:...}} e {{link:...|...}} para busca full-text no manual.
 */
export function plainTextForSearch(s) {
    if (!s || typeof s !== 'string') {
        return '';
    }
    return s
        .replace(/\{\{link:[^|]+\|([^}]+)\}\}/g, '$1')
        .replace(/\{\{icon:[^}]+\}\}/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/**
 * Partes de um trecho: texto, ícone ou link (href + rótulo, sem ícones aninhados no rótulo por ora).
 * @returns {Array<{type: 'text'|'icon'|'link', value?: string, key?: string, href?: string, label?: string}>}
 */
function parseSegmentWithIcons(segment) {
    if (!segment) {
        return [];
    }
    const parts = [];
    let last = 0;
    const re = /\{\{icon:([a-zA-Z0-9_-]+)\}\}/gi;
    let m;
    while ((m = re.exec(segment)) !== null) {
        if (m.index > last) {
            parts.push({ type: 'text', value: segment.slice(last, m.index) });
        }
        parts.push({ type: 'icon', key: m[1] });
        last = re.lastIndex;
    }
    if (last < segment.length) {
        parts.push({ type: 'text', value: segment.slice(last) });
    }
    return parts;
}

function parseRichText(text) {
    if (!text || typeof text !== 'string') {
        return [];
    }
    const linkRe = /\{\{link:([^|]+)\|([^}]+)\}\}/g;
    const out = [];
    let last = 0;
    let m;
    while ((m = linkRe.exec(text)) !== null) {
        if (m.index > last) {
            out.push(...parseSegmentWithIcons(text.slice(last, m.index)));
        }
        out.push({ type: 'link', href: m[1].trim(), label: m[2].trim() });
        last = m.lastIndex;
    }
    if (last < text.length) {
        out.push(...parseSegmentWithIcons(text.slice(last)));
    }
    if (out.length === 0) {
        return parseSegmentWithIcons(text);
    }
    return out;
}

function renderParts(parts) {
    return parts.map((p, i) => {
        if (p.type === 'text') {
            return <Fragment key={i}>{p.value}</Fragment>;
        }
        if (p.type === 'icon') {
            return <ManualInlineIconByKey key={i} iconKey={p.key} />;
        }
        if (p.type === 'link') {
            return (
                <a
                    key={i}
                    href={p.href}
                    className="text-emerald-700 underline underline-offset-2 hover:text-emerald-900 font-medium"
                >
                    {p.label}
                </a>
            );
        }
        return null;
    });
}

/**
 * Parágrafo ou bullet com ícones inline {{icon:chave}} e links {{link:url|rótulo}} (âncoras #mod-…, #id-sec).
 */
export default function ManualRichText({ text, className = '' }) {
    const parts = parseRichText(text);
    if (parts.length === 0) {
        return null;
    }
    return <span className={className}>{renderParts(parts)}</span>;
}
