import { useEffect, useState } from 'react';

export default function Drawer({ 
    isOpen, 
    onClose, 
    title, 
    children,
    width = 'w-[700px]'
}) {
    const [isVisible, setIsVisible] = useState(false);
    const [isAnimating, setIsAnimating] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setIsVisible(true);
            const timer = setTimeout(() => setIsAnimating(true), 50);
            return () => clearTimeout(timer);
        } else if (isVisible) {
            setIsAnimating(false);
            const timer = setTimeout(() => setIsVisible(false), 300);
            return () => clearTimeout(timer);
        }
    }, [isOpen, isVisible]);

    useEffect(() => {
        const handleEscape = (e) => {
            if (e.key === 'Escape' && isOpen) {
                onClose();
            }
        };

        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [isOpen, onClose]);

    useEffect(() => {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }

        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [isOpen]);

    if (!isVisible) return null;

    return (
        <>
            {/* Backdrop */}
            <div 
                className={`
                    fixed inset-0 bg-black/50 backdrop-blur-sm z-40 
                    transition-opacity duration-300 ease-out
                    ${isAnimating ? 'opacity-100' : 'opacity-0'}
                `}
                onClick={onClose}
            />

            {/* Drawer */}
            <div className="fixed inset-y-0 right-0 z-50 flex">
                <div 
                    className={`
                        ${width} bg-white shadow-2xl flex flex-col
                        transform transition-transform duration-300 ease-out
                        ${isAnimating ? 'translate-x-0' : 'translate-x-full'}
                    `}
                >
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h2 className="text-xl font-bold text-gray-900">
                            {title}
                        </h2>
                        <button
                            onClick={onClose}
                            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-200 rounded-lg transition-colors"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Content */}
                    <div className="flex-1 overflow-y-auto">
                        {children}
                    </div>
                </div>
            </div>
        </>
    );
}

