/**
 * Small JSON helpers for admin endpoints that don't return Inertia pages
 * (media picker, uploads). CSRF uses Laravel's XSRF-TOKEN cookie.
 */

export type JsonResponse<T> = {
    status: number;
    data: T;
};

export class HttpError extends Error {
    constructor(
        public status: number,
        public data: {
            message?: string;
            errors?: Record<string, string[]>;
        } & Record<string, unknown>,
    ) {
        super(data.message ?? `HTTP ${status}`);
    }

    /** First validation message, or the general message. */
    firstMessage(): string {
        const first = this.data.errors
            ? Object.values(this.data.errors)[0]?.[0]
            : undefined;

        return (
            first ??
            this.data.message ??
            'Došlo je do greške. Pokušajte ponovo.'
        );
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function headers(extra: Record<string, string> = {}): Record<string, string> {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrfToken(),
        ...extra,
    };
}

async function parse(response: Response): Promise<unknown> {
    if (response.status === 204) {
        return null;
    }

    const text = await response.text();

    return text ? JSON.parse(text) : null;
}

export async function requestJson<T>(
    method: 'get' | 'post' | 'patch' | 'put' | 'delete',
    url: string,
    body?: Record<string, unknown>,
): Promise<JsonResponse<T>> {
    const response = await fetch(url, {
        method: method.toUpperCase(),
        headers: headers(body ? { 'Content-Type': 'application/json' } : {}),
        body: body ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
    });

    const data = await parse(response);

    if (!response.ok) {
        throw new HttpError(response.status, (data ?? {}) as HttpError['data']);
    }

    return { status: response.status, data: data as T };
}

/**
 * Upload one file as multipart/form-data with progress (0–100).
 */
export function uploadFile<T>(
    url: string,
    file: File,
    onProgress?: (percent: number) => void,
    field = 'file',
): Promise<JsonResponse<T>> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const form = new FormData();
        form.append(field, file);

        xhr.open('POST', url);
        Object.entries(headers()).forEach(([k, v]) =>
            xhr.setRequestHeader(k, v),
        );

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && onProgress) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };

        xhr.onload = () => {
            let data: unknown = null;

            try {
                data = xhr.responseText ? JSON.parse(xhr.responseText) : null;
            } catch {
                data = { message: 'Server je vratio neočekivan odgovor.' };
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve({ status: xhr.status, data: data as T });
            } else if (xhr.status === 413) {
                reject(
                    new HttpError(413, {
                        message: 'Fotografija je prevelika za server.',
                    }),
                );
            } else {
                reject(
                    new HttpError(
                        xhr.status,
                        (data ?? {}) as HttpError['data'],
                    ),
                );
            }
        };

        xhr.onerror = () =>
            reject(new HttpError(0, { message: 'Nema veze sa serverom.' }));
        xhr.send(form);
    });
}
