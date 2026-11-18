import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';


// 419 = sessão expirada (token CSRF da página não vale mais): avisa a modal global
// (Components/SessaoExpiradaModal, no DashboardLayout). Quem chamou segue tratando o erro.
const avisarSessaoExpirada = (status) => {
    if (status === 419) window.dispatchEvent(new Event('sessao-expirada'));
};

const fetchOriginal = window.fetch.bind(window);
window.fetch = async (...args) => {
    const response = await fetchOriginal(...args);
    avisarSessaoExpirada(response.status);
    return response;
};

window.axios.interceptors.response.use(undefined, (error) => {
    avisarSessaoExpirada(error?.response?.status);
    return Promise.reject(error);
});
