import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import TinySettings from '@/Pages/Settings/Integrations/Tiny';
import Toast from '@/Components/Toast';

export default function SettingsIndex({ tiny }) {
    const [activeTab, setActiveTab] = useState('tiny');
    const [toast, setToast] = useState(null);

    const tabs = [
        { key: 'tiny', label: 'Tiny ERP', enabled: true },
    ];

    const enabledTabs = tabs.filter(tab => tab.enabled);

    return (
        <>
            <Head title="Configuracoes" />

            <DashboardLayout>
                <div className="space-y-6 p-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Configuracoes</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Gerencie as integracoes e configuracoes do sistema.
                            </p>
                        </div>
                    </div>

                    <div className="bg-white rounded-xl shadow-sm border border-gray-200">
                        <div className="border-b border-gray-200 px-6">
                            <nav className="flex flex-wrap gap-2 py-4">
                                {enabledTabs.map((tab) => (
                                    <button
                                        key={tab.key}
                                        type="button"
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                            activeTab === tab.key
                                                ? 'bg-emerald-600 text-white shadow-sm'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </nav>
                        </div>

                        <div className="p-6">
                            {activeTab === 'tiny' && (
                                <TinySettings
                                    settings={tiny?.settings || {}}
                                    onToast={setToast}
                                />
                            )}
                        </div>
                    </div>
                </div>
            </DashboardLayout>

            {toast && (
                <Toast
                    message={toast.message}
                    type={toast.type}
                    onClose={() => setToast(null)}
                />
            )}
        </>
    );
}
