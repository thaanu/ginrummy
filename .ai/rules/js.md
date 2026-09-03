---
paths:
    - 'resources/js/**'
---

# Js

## Hand order and meld hints come from the server

The browser holds no game rules. Meld highlighting and whether the Gin button is enabled both come from the private payload (`melds`, `canGoGin`, `ginDiscards`), computed by `MeldValidator`. Never reimplement meld detection in TypeScript — the hint and the verdict must not be able to disagree.

Card order is persisted server-side via `POST /game/{code}/sort`, not in localStorage, so it survives a refresh and needs no client-side merging with server hand updates. `useGame.sortHand()` applies it optimistically then posts; a failure is swallowed because the heartbeat restores the saved order.

`useHandReorder` tracks slot positions in 2D because the hand wraps to two rows on phones. Keep `touch-action: pan-y` on card slots so vertical page scroll still works while horizontal drags belong to the app.
