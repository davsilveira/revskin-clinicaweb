/**
 * Mostra conteúdo desktop (ex.: tabela) só em lg+ e conteúdo mobile (ex.: lista de cartões) abaixo de lg.
 */
export default function ResponsiveEntityList({ desktop, mobile, className = '' }) {
    return (
        <>
            <div className={`hidden lg:block ${className}`.trim()}>{desktop}</div>
            <div className={`lg:hidden ${className}`.trim()}>{mobile}</div>
        </>
    );
}
