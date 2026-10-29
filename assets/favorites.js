/** Explicit operations only; interrupted writes are reconciled by callers through GET. */
export async function requestFavorite(url, csrf, method, signal) {
    const timeout = AbortSignal.timeout(12000);
    const response = await fetch(url, {method, credentials: 'same-origin', redirect: 'error', signal: signal ? AbortSignal.any([timeout, signal]) : timeout, headers: {'Accept': 'application/json', 'X-CSRF-Token': csrf}});
    const data = await response.json();
    if (!response.ok) throw Object.assign(Error(data.message || 'Favorite request failed.'), {status: response.status});
    if (typeof data.favorite !== 'boolean') throw Error('Favorite status could not be confirmed.');
    return data;
}

export function favoriteEvent(name, detail) {
    document.dispatchEvent(new CustomEvent(`favorite:${name}`, {detail}));
}
