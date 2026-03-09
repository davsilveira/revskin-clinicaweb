import { Link } from '@inertiajs/react';

export default function Pagination({ links, preserveScroll = true }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 sm:px-6">
            <div className="flex flex-1 justify-between sm:hidden">
                {links[0].url && (
                    <Link
                        href={links[0].url}
                        preserveScroll={preserveScroll}
                        className="relative inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Anterior
                    </Link>
                )}
                {links[links.length - 1].url && (
                    <Link
                        href={links[links.length - 1].url}
                        preserveScroll={preserveScroll}
                        className="relative ml-3 inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Próximo
                    </Link>
                )}
            </div>
            <div className="hidden sm:flex sm:flex-1 sm:items-center sm:justify-center">
                <div>
                    <nav className="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                        {links.map((link, index) => {
                            if (index === 0) {
                                return (
                                    <Link
                                        key={`pagination-prev`}
                                        href={link.url || '#'}
                                        preserveScroll={preserveScroll}
                                        className={`relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 ${
                                            !link.url ? 'cursor-not-allowed opacity-50' : ''
                                        }`}
                                        disabled={!link.url}
                                    >
                                        <span className="sr-only">Anterior</span>
                                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fillRule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clipRule="evenodd" />
                                        </svg>
                                    </Link>
                                );
                            }

                            if (index === links.length - 1) {
                                return (
                                    <Link
                                        key={`pagination-next`}
                                        href={link.url || '#'}
                                        preserveScroll={preserveScroll}
                                        className={`relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:z-20 focus:outline-offset-0 ${
                                            !link.url ? 'cursor-not-allowed opacity-50' : ''
                                        }`}
                                        disabled={!link.url}
                                    >
                                        <span className="sr-only">Próximo</span>
                                        <svg className="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fillRule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clipRule="evenodd" />
                                        </svg>
                                    </Link>
                                );
                            }

                            return (
                                <Link
                                    key={`pagination-${index}`}
                                    href={link.url || '#'}
                                    preserveScroll={preserveScroll}
                                    className={`relative inline-flex items-center px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-gray-300 focus:z-20 focus:outline-offset-0 ${
                                        link.active
                                            ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600'
                                            : 'text-gray-900 hover:bg-gray-50'
                                    } ${!link.url ? 'cursor-not-allowed' : ''}`}
                                    disabled={!link.url}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            );
                        })}
                    </nav>
                </div>
            </div>
        </div>
    );
}

