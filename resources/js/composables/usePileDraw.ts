import type { DrawSource } from '@/types/game';
import { onBeforeUnmount, ref, type Ref } from 'vue';

const DRAG_THRESHOLD_PX = 6;

interface Options {
    /** Whether a card may be taken from this pile right now. */
    enabled: Ref<boolean>;
    /** Takes the card. Called once, the moment the drag begins. */
    onDraw: (source: DrawSource) => void;
}

/**
 * Lets a card be dragged out of a pile and into the hand.
 *
 * The card is taken as soon as the drag starts rather than when it is dropped,
 * which is what makes it possible to turn a face-down stock card over: the
 * server has to hand it out before anyone can be shown what it is. Committing
 * at that moment also settles what happens if the player changes their mind —
 * nothing does. The card is theirs and cannot go back, which is the same rule
 * that applies to picking one up at a real table.
 *
 * The rest of the gesture is followed on the window rather than on the pile.
 * Taking a card ends the drawing phase, which re-renders the pile underneath
 * the pointer; anything listening to the pile itself would simply stop hearing
 * about the drag halfway through, and the card would be left hanging.
 */
export function usePileDraw({ enabled, onDraw }: Options) {
    const source = ref<DrawSource | null>(null);
    const position = ref<{ x: number; y: number } | null>(null);
    const drawn = ref(false);

    let pointerId: number | null = null;
    let origin = { x: 0, y: 0 };
    let tookOnLastGesture = false;

    function start(event: PointerEvent, from: DrawSource): void {
        if (!enabled.value || event.button > 0 || source.value !== null) {
            return;
        }

        pointerId = event.pointerId;
        origin = { x: event.clientX, y: event.clientY };
        source.value = from;
        drawn.value = false;
        position.value = null;

        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
    }

    function move(event: PointerEvent): void {
        if (source.value === null || event.pointerId !== pointerId) {
            return;
        }

        const travelled = Math.hypot(
            event.clientX - origin.x,
            event.clientY - origin.y,
        );

        if (!drawn.value && travelled < DRAG_THRESHOLD_PX) {
            return;
        }

        if (!drawn.value) {
            drawn.value = true;
            onDraw(source.value);
        }

        position.value = { x: event.clientX, y: event.clientY };
    }

    function end(event: PointerEvent): void {
        if (source.value === null || event.pointerId !== pointerId) {
            return;
        }

        reset();
    }

    /**
     * True when the gesture just ended actually took a card, so the click the
     * browser fires afterwards can be ignored rather than drawing again.
     */
    function consumedClick(): boolean {
        const took = tookOnLastGesture;
        tookOnLastGesture = false;

        return took;
    }

    function reset(): void {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        window.removeEventListener('pointercancel', end);

        tookOnLastGesture = drawn.value;
        source.value = null;
        position.value = null;
        drawn.value = false;
        pointerId = null;
    }

    onBeforeUnmount(reset);

    return { source, position, drawn, start, consumedClick };
}
