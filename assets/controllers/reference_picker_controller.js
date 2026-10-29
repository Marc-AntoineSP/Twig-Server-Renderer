import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['dialog','query','kind','version','favorites','status','results','previous','next','current','selection','apply','defaults','defaultsLabel'];
    static values = {url: String};
    get composer() {return this.application.getControllerForElementAndIdentifier(this.element, 'composition');}
    connect() {this.serial = 0;this.favoritesTarget.checked = new URL(location.href).searchParams.get('favorites') === '1';}
    disconnect() {this.cancel();if (this.dialogTarget.open) this.dialogTarget.close();}
    cancel() {clearTimeout(this.timer);this.request?.abort();this.applyRequest?.abort();this.serial++;}

    open({detail}) {
        this.cancel();this.mode = detail.mode;this.opener = detail.opener;this.candidate = null;this.focusNode = false;
        this.searchContext = this.composer.pickerContext();
        const node = this.composer.node();
        this.currentTarget.textContent = this.mode === 'replace' ? `Pinned node: ${node.name}@${node.version}. Search filters do not change it.` : 'Insert after the selected node, or inside the selected container.';
        this.defaultsTarget.checked = false;this.defaultsLabelTarget.hidden = this.mode !== 'replace';
        this.selectionTarget.textContent = 'No candidate selected.';this.applyTarget.disabled = true;
        this.applyTarget.textContent = this.mode === 'replace' ? 'Replace with selected reference' : 'Insert selected reference';
        this.dialogTarget.showModal();this.queryTarget.focus();
        if (node.type === 'reference') this.composer.recheckReference();
        this.load(1);
    }
    close() {this.dialogTarget.close();}
    closed() {
        // A queued native close event can arrive after a rapid reopen.
        if (this.dialogTarget.open) return;
        this.cancel();this.resultsTarget.replaceChildren();this.candidate = null;
        // Shell restores modal focus on the next frame; the selected node wins
        // after a successful insertion, as permitted by the dialog pattern.
        requestAnimationFrame(() => {
            if (!this.element.isConnected || document.querySelector('dialog:modal')) return;
            if (this.focusNode) this.composer.focusSelected();
            else if (this.opener?.isConnected) this.opener.focus({preventScroll: true});
        });
    }
    compositionStart() {this.composing = true;this.cancel();}
    compositionEnd() {this.composing = false;this.schedule();}
    schedule() {
        this.cancel();if (this.composing) return;
        this.statusTarget.textContent = 'Search pending…';
        this.timer = setTimeout(() => this.load(1), 300);
    }
    filter() {
        const url = new URL(location.href);
        if (this.favoritesTarget.checked) url.searchParams.set('favorites','1');else url.searchParams.delete('favorites');
        history.replaceState(history.state, '', url);
        this.load(1);
    }
    clear() {this.queryTarget.value='';this.kindTarget.value='';this.versionTarget.value='';this.favoritesTarget.checked=false;this.filter();}
    submit(event) {event.preventDefault();this.load(1);}
    retry() {return this.load(this.page || 1);}
    previous() {return this.load(Math.max(1, this.page - 1));}
    next() {if (this.hasMore) return this.load(this.page + 1);}
    favoriteChanged({detail}) {if (detail.kind === 'catalogue' && this.dialogTarget.open) this.load(1);}

    async load(page) {
        this.cancel();if (!this.dialogTarget.open) return;
        const serial = this.serial;this.request = new AbortController();
        this.resultsTarget.setAttribute('aria-busy','true');this.statusTarget.textContent = 'Loading eligible references…';
        this.previousTarget.disabled = true;this.nextTarget.disabled = true;
        const query = {q:this.queryTarget.value, kind:this.kindTarget.value, version:this.versionTarget.value, favorites:this.favoritesTarget.checked, page};
        try {
            const results = await this.composer.json(this.urlValue, {method:'POST', signal:this.request.signal, body:JSON.stringify({...this.searchContext, mode:this.mode, query})});
            if (serial !== this.serial || !this.dialogTarget.open) return;
            const focusedResult = this.resultsTarget.contains(document.activeElement);
            this.page = results.page;this.hasMore = results.hasMore;this.render(results.items);
            this.previousTarget.disabled = this.page === 1;this.nextTarget.disabled = !this.hasMore;
            this.statusTarget.textContent = results.items.length ? `${results.items.length} eligible releases on page ${this.page}${this.hasMore ? '; more available.' : '.'}` : 'No eligible releases match. Clear filters or publish a valid component, block or layout.';
            if (focusedResult) this.statusTarget.focus();
        } catch (error) {
            if (serial !== this.serial || error.name === 'AbortError') return;
            // Retain candidate and unsaved tree, but never present old results as current.
            this.resultsTarget.replaceChildren();this.statusTarget.textContent = `${error.status === 412 ? 'The saved revision changed; your unsaved tree is kept.' : 'Reference search is unavailable.'} ${error.message} Retry search when ready.`;
        } finally {if (serial === this.serial) this.resultsTarget.setAttribute('aria-busy','false');}
    }

    render(items) {
        this.resultsTarget.replaceChildren();
        for (const item of items) {
            const row = document.createElement('li');row.className = 'min-w-0 rounded-box border border-base-300 p-3';
            const label = document.createElement('label');label.className = 'flex items-start gap-3';
            const radio = document.createElement('input');radio.type = 'radio';radio.name = 'reference-candidate';radio.className = 'radio radio-sm mt-1 shrink-0';
            radio.setAttribute('aria-label', `${item.name}@${item.version}`);
            radio.checked = this.candidate?.releaseId === item.releaseId;radio.addEventListener('change', () => this.select(item));
            const text = document.createElement('span');text.className = 'min-w-0';
            const heading = document.createElement('strong');heading.className = 'block break-words';heading.textContent = item.title;
            const identity = document.createElement('span');identity.className = 'block break-all font-mono text-sm';identity.textContent = `${item.name}@${item.version}`;
            const summary = document.createElement('span');summary.className = 'mt-1 block break-words text-sm';summary.textContent = `${item.kind}${item.favorite ? ' · Favorite' : ''} · ${item.summary}`;
            text.append(heading, identity, summary);label.append(radio,text);row.append(label);
            const preview = document.createElement('a');preview.className = 'link mt-2 inline-block max-w-full break-all text-sm';preview.href = item.previewUrl;preview.target = '_blank';preview.rel = 'noopener';preview.textContent = `Preview ${item.name}@${item.version} (new tab)`;row.append(preview);
            if (item.thumbnailUrl) row.append(this.thumbnail(item));
            this.resultsTarget.append(row);
        }
    }
    thumbnail(item) {
        const holder = document.createElement('div');holder.className = 'relative mt-2 flex aspect-video w-full max-w-xs items-center justify-center overflow-hidden rounded border border-base-300 bg-base-200';
        holder.dataset.controller = 'release-thumbnail';holder.dataset.releaseThumbnailUrlValue = item.thumbnailUrl;holder.dataset.releaseThumbnailTitleValue = `${item.name}@${item.version}`;
        const button = document.createElement('button');button.type = 'button';button.className = 'btn btn-ghost btn-sm';button.textContent = 'Show published preview';button.dataset.releaseThumbnailTarget = 'button';button.dataset.action = 'release-thumbnail#load';
        const status = document.createElement('p');status.className = 'text-xs';status.role = 'status';status.dataset.releaseThumbnailTarget = 'status';holder.append(button,status);return holder;
    }
    select(item) {this.candidate = item;this.selectionTarget.textContent = `Selected candidate: ${item.name}@${item.version}`;this.applyTarget.disabled = false;}

    async apply() {
        if (!this.candidate || this.applying) return;
        this.cancel();const serial=this.serial,candidate=this.candidate,mode=this.mode;
        this.applying = true;this.applyTarget.disabled = true;this.applyRequest=new AbortController();
        const controls=[...this.dialogTarget.querySelectorAll('input,select,button')].filter(el=>!el.closest('[data-appearance-recovery]') && !el.dataset.action?.includes('reference-picker#close')).map(el=>[el,el.disabled]);
        controls.forEach(([el])=>el.disabled=true);
        this.statusTarget.textContent = 'Checking selected release and applying the reference…';
        try {
            const url = new URL(this.composer.referenceReadUrlValue, location.href);url.searchParams.set('name',candidate.name);url.searchParams.set('version',candidate.version);
            const reference = await this.composer.json(url,{signal:this.applyRequest.signal});
            if (serial!==this.serial || !this.dialogTarget.open) return;
            const success = await this.composer.chooseReference(reference,mode,this.defaultsTarget.checked);
            if (success) {this.focusNode = true;this.close();}
            else this.statusTarget.textContent = 'Reference could not be applied. Your previous reference and props are kept. Check the composer diagnostic; choose a compatible release or explicitly use its defaults.';
        } catch (error) {if(serial!==this.serial || error.name==='AbortError')return;this.statusTarget.textContent = 'Reference unavailable or access changed. Your tree and props are kept. Choose another release or retry.';}
        finally {this.applying = false;controls.forEach(([el,disabled])=>{if(el.isConnected)el.disabled=disabled;});this.applyTarget.disabled = !this.candidate;}
    }
}
