# Discovery and confirmed favorites feedback

Catalogue, Themes and Fonts share a compact filter layout: search and relevant fields,
Find, Clear all, matching total, active-filter chips, results and pagination. Each chip
removes that filter while keeping the others. Normal GET forms work without JavaScript;
URLs can be copied, opened directly and restored through Back/Forward. Filter forms
disable autocomplete so browser form-state restoration does not override URL values
after Back, including with JavaScript disabled.

| Listing | Query parameters | Matching and order |
| --- | --- | --- |
| `/catalogue` | `q`, `kind`, `tags`, `sort`, `favorites`, `page` | Existing name/title/description/tag search; kind; every comma-separated exact tag; updated or name order |
| `/themes` | `q`, `mode`, `sort`, `favorites`, `page` | Name/reference title/surface/family label; light/dark; updated or name order |
| `/fonts` | `q`, `category`, `page` | Family name; sans/serif/mono/display; alphabetical family order |

Catalogue tags are trimmed, case-insensitive and combined with AND. Search/type/tags/
favorites are applied before twelve-item pagination, as are theme search/mode/favorites.
Font metadata is filtered on the server before twelve-family pagination. Query DTOs
validate names, lengths, enums and page bounds. Invalid queries fail with HTTP 400.
An out-of-range page redirects once to the last existing page, or page 1 for no matches.

With JavaScript, search waits 350ms after input; composing characters never submit an
unfinished input-method sequence. Selects/checkboxes submit promptly. A Turbo Frame
replaces the matching total, chips, cards and pagination together using the ordinary SSR
response. The form remains outside the frame to preserve focus and caret. Detail links
leave the frame and retain the listing return URL. Font specimens remain independent:
all installed families are selectable, and licensed font bytes load only for Preview.

Typing replaces the current history entry. Explicit Find, selection changes, chips,
Clear all and pagination advance history when the URL changes. A confirmed favorite
refresh or Retry replaces the entry. Changes to the result set reset the form's page to
1; a favorite refresh keeps the current page unless it disappears, in which case the
server clamps it. Back/Forward loads filters and results from the URL through Turbo's
normal restoration visits. The application retains its existing no-cache page policy.

Each controller aborts obsolete requests on new input/disconnect. An instance-scoped
sequence is sent as `X-Discovery-Request`; the authenticated SSR response echoes that
bounded receipt in its header and frame. Response/render guards reject stale receipts,
including work queued before a newer edit. This receipt is only for ordering and grants
no access. Results are private/no-store, with the same authorization for frame/direct
requests. Framework/authentication failures without a receipt are reported as failures.

On a recoverable failure, the previous list stays visible with its previous total and
an explicit out-of-date message. **Retry results** repeats the requested GET filters.
The URL changes only for a successful result. Loading/result/failure messages are polite;
typing itself does not emit a message for each key.

Stars still call the existing explicit PUT/DELETE API and synchronize only after server
confirmation. A lost response is reconciled with GET. In Favorites only, a confirmed
removal refreshes the server result set; it does not remove a card in isolation. Totals,
pagination, last-page clamping and both empty states therefore remain consistent.
Regular listings keep the card and update its star.

A confirmed removal offers one **Undo** notice for 15 seconds. The notice remains while
it contains keyboard focus or a request is pending. Undo uses PUT to restore that exact
favorite, never a toggle. The API remains idempotent and applies current access/existence
checks. An interrupted Undo is reconciled with GET; failures show a retryable message.
Deleted or inaccessible targets are not recreated. Refreshes use current filters, so
Undo cannot insert a card excluded by another filter or force it onto the current page.
Only the most recent removal is retained, in memory; there is no persistent browser store.

When removing a focused card, focus moves to an adjacent favorite control if it remains
visible, otherwise to the result summary. Refreshes preserve focus in filters and the
Undo notice. Dismissing a focused notice returns focus to the summary.

`favorite:changed` keeps its existing `kind`, `id`, `favorite` fields. Confirmed button
operations also supply previousFavorite, operation, label, URL and whether the button
held focus, for the listing's feedback. Undo emits the same authoritative state with
operation=undo. Existing duplicate-button synchronization and pending events remain.

No new favorite model, migration, dependency or search engine is introduced. The
authenticated actor always supplies favorite identity; URL owner/user parameters do not.
The composer picker remains scoped to its separate prompt. Shared layout fields can be
reused without making unrelated domains adopt the same filters. See FAVORITES.md and
STATUS.md for persistence, commands, measured results and remaining manual checks.


The enhancement uses the installed Turbo frame/history actions and public lifecycle
hooks: [Frames handbook](https://turbo.hotwired.dev/handbook/frames) and
[request/render events](https://turbo.hotwired.dev/reference/events). The studio's
existing cache and editing-guard policies remain separate from discovery state.
