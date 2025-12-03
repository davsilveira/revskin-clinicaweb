import { Head } from '@inertiajs/react';
import { useState } from 'react';
import DashboardLayout from '@/Layouts/DashboardLayout';
import InfosimplesSettings from '@/Pages/Settings/Integrations/Infosimples';
import Toast from '@/Components/Toast';

export default function SettingsIndex({ infosimples }) {
    const [activeTab, setActiveTab] = useState('infosimples');
    const [toast, setToast] = useState(null);

    // Define tabs - add more integrations here
    const tabs = [
        { key: 'infosimples', label: 'Infosimples', enabled: true },
        // Add more tabs as needed:
        // { key: 'other', label: 'Other Integration', enabled: true },
    ];

    const enabledTabs = tabs.filter(tab => tab.enabled);

    return (
        <>
            <Head title="Configurações" />

            <DashboardLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Configurações</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Gerencie as integrações e configurações do sistema.
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
                                                ? 'bg-blue-600 text-white shadow-sm'
                                                : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </nav>
                        </div>

                        <div className="p-6">
                            {activeTab === 'infosimples' && (
                                <InfosimplesSettings
                                    settings={infosimples.settings}
                                    cache_ttl_options={infosimples.cache_ttl_options}
                                    default_timeout_options={infosimples.default_timeout_options}
                                    onToast={setToast}
                                />
                            )}

                            {/* Add more integration components here */}
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
