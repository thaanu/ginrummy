/**
 * A small wrapper around fetch for the game's action endpoints.
 *
 * Requests are same-origin so the identity cookie travels automatically; the
 * XSRF token Laravel sets is echoed back as a header to satisfy CSRF
 * protection. Errors always arrive as a human readable message.
 */
export class ApiError extends Error {
    public constructor(
        message: string,
        public readonly status: number,
    ) {
        super(message);
        this.name = 'ApiError';
    }
}

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export async function post<TResponse>(
    url: string,
    body: Record<string, unknown> = {},
): Promise<TResponse> {
    let response: Response;

    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(body),
        });
    } catch {
        throw new ApiError(
            'We could not reach the table. Check your connection and try again.',
            0,
        );
    }

    if (response.ok) {
        return (await response.json()) as TResponse;
    }

    throw new ApiError(await messageFor(response), response.status);
}

async function messageFor(response: Response): Promise<string> {
    if (response.status === 419) {
        return 'Your session expired. Refresh the page to carry on.';
    }

    if (response.status === 429) {
        return 'That was a lot of moves at once. Give it a moment.';
    }

    try {
        const payload = (await response.json()) as { message?: string };

        if (typeof payload.message === 'string' && payload.message !== '') {
            return payload.message;
        }
    } catch {
        // Fall through to the generic message below.
    }

    return response.status === 403
        ? 'You are not seated at this table.'
        : 'Something went wrong. Please try again.';
}
