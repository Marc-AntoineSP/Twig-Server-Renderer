// Blocking, local head script: resolve the server's saved state before colour styles paint.
(() => {
    if (window.StyleguideAppearance) return;
    const effectiveMode = state => {
        const requested = state.mode === 'system' ? (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light') : state.mode;
        return state.variants[requested] ? requested : (state.variants.light ? 'light' : 'dark');
    };
    const render = state => {
        const mode = effectiveMode(state), variant = state.variants[mode], root = document.documentElement;
        root.dataset.theme = variant.cssName;
        root.dataset.colorMode = mode;
        root.dataset.appearanceFamily = state.familyId;
        root.dataset.appearancePreference = state.mode;
        for (const key of ['light', 'dark']) {
            if (state.variants[key]) root.dataset[`appearance${key[0].toUpperCase()}${key.slice(1)}`] = state.variants[key].id;
            else delete root.dataset[`appearance${key[0].toUpperCase()}${key.slice(1)}`];
        }
        root.dataset.appearanceTrial = state.trial ? 'true' : 'false';
        return mode;
    };
    window.StyleguideAppearance = {render, effectiveMode};
    const parse = value => {try {const state = JSON.parse(value);if (state && state.variants && Object.keys(state.variants).length) render(state);} catch {}};
    parse(document.documentElement.dataset.appearanceState);
    document.addEventListener('turbo:before-render', event => parse(event.detail.newBody.dataset.appearanceStateValue));
})();
