---
paths:
    - 'resources/js/components/game/**'
    - resources/js/components/game/GameTable.vue
---

# Game

## Raise the slot, not the card, to bring a card to the front

Each `[data-card-slot]` wrapper in `CardHand.vue` carries an inline `transform`, which makes it a stacking context. A `z-index` on the `PlayingCard` inside therefore cannot lift it above the next slot, and the card stays buried under its neighbours. Set the z-index on the slot wrapper (`layerFor()`) instead.

Anything in `useHandReorder` that drives rendering must be a `ref`. `moved` was once a plain variable, so the `isDragging` computed never invalidated and the dragged card sat still while its neighbours shifted around it — the drag looked broken even though the reorder itself worked.

## Putting down the card that completes a hand is going gin

`play()` routes a discard through `declare` whenever `completesGin(card)` is true. Do not simplify this back into a plain discard: discarding the odd card and going gin are the same physical act, and treating them separately let a player end their turn on a winning hand and lose the win until the turn came round again (reported bug, revision 1 point 11).

Pile drags are followed on `window`, not on the pile element (`usePileDraw`). Taking a card ends the drawing phase, which re-renders the pile under the pointer — anything listening on the pile itself stops hearing the rest of the gesture and leaves the carried card stuck on screen.
