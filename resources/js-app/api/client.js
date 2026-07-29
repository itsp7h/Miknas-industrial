function getCookie(name) {
    const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
    return match ? decodeURIComponent(match[2]) : null;
}

async function ensureCsrfCookie() {
    if (getCookie('XSRF-TOKEN')) return;
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

async function request(path, options = {}) {
    await ensureCsrfCookie();

    const response = await fetch(`/api/v1${path}`, {
        ...options,
        credentials: 'include',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') ?? '',
            ...options.headers,
        },
    });

    const body = await response.json().catch(() => null);

    if (!response.ok) {
        return Promise.reject(body ?? { message: 'Request failed.' });
    }

    return body;
}

export const apiGet = (path) => request(path);
export const apiPost = (path, data) => request(path, { method: 'POST', body: JSON.stringify(data) });
export const apiPut = (path, data) => request(path, { method: 'PUT', body: JSON.stringify(data) });
export const apiDelete = (path) => request(path, { method: 'DELETE' });
