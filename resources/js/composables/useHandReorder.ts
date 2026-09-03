import { computed, ref, type Ref } from 'vue';

const DRAG_THRESHOLD_PX = 6;

interface Options {
    /** How many cards are laid out, so a drag can be bounded. */
    count: Ref<number>;
    /** Reordering is off while the hand is not the player's to rearrange. */
    enabled: Ref<boolean>;
    /** Called once, on drop, with the positions the cards ended up in. */
    onReorder: (from: number, to: number) => void;
    /** Called when the pointer went down and up without really moving. */
    onTap: (index: number) => void;
    /** Somewhere outside the hand a card can be dropped, such as the pile. */
    dropZone?: Ref<HTMLElement | null>;
    /** Whether dropping there is allowed right now. */
    canDrop?: Ref<boolean>;
    /** Called when a card is released over the drop zone. */
    onDrop?: (index: number) => void;
}

interface Point {
    x: number;
    y: number;
}

/**
 * Drag-to-reorder for a fanned hand of overlapping cards.
 *
 * Built on pointer events so a finger and a mouse take exactly the same path,
 * and so a tap that never really moves still counts as a tap rather than a one
 * pixel drag.
 *
 * The cards are not reshuffled while a drag is in progress. Their positions are
 * measured once when the drag starts, and the cards in between simply slide
 * into their neighbour's place to open a gap; the array is rearranged once, on
 * drop. That keeps the layout from fighting the pointer as the order changes
 * underneath it.
 *
 * Positions are tracked in two dimensions, because a hand wraps onto a second
 * row on a phone. Sliding into a neighbour's place then correctly carries a
 * card from the start of one row to the end of the row above.
 */
export function useHandReorder({
    count,
    enabled,
    onReorder,
    onTap,
    dropZone,
    canDrop,
    onDrop,
}: Options) {
    const draggingIndex = ref<number | null>(null);
    const targetIndex = ref<number | null>(null);
    const offset = ref<Point>({ x: 0, y: 0 });
    const overDropZone = ref(false);

    /**
     * Reactive, because the lift and the layering of the dragged card are driven
     * from it: as a plain variable it would leave the computed below stale, and
     * the card would sit still while its neighbours moved around it.
     */
    const moved = ref(false);

    let pointerId: number | null = null;
    let start_: Point = { x: 0, y: 0 };
    let slots: Point[] = [];

    const isDragging = computed(
        () => draggingIndex.value !== null && moved.value,
    );

    function measure(row: HTMLElement): void {
        slots = Array.from(
            row.querySelectorAll<HTMLElement>('[data-card-slot]'),
        ).map((card) => {
            const box = card.getBoundingClientRect();

            return { x: box.left + box.width / 2, y: box.top + box.height / 2 };
        });
    }

    function isOverDropZone(x: number, y: number): boolean {
        if (canDrop?.value !== true || !dropZone?.value) {
            return false;
        }

        const box = dropZone.value.getBoundingClientRect();

        return (
            x >= box.left && x <= box.right && y >= box.top && y <= box.bottom
        );
    }

    /** The slot whose centre the pointer is closest to. */
    function slotFor(point: Point): number {
        let nearest = draggingIndex.value ?? 0;
        let shortest = Number.POSITIVE_INFINITY;

        slots.forEach((slot, index) => {
            const distance = (slot.x - point.x) ** 2 + (slot.y - point.y) ** 2;

            if (distance < shortest) {
                shortest = distance;
                nearest = index;
            }
        });

        return Math.min(nearest, count.value - 1);
    }

    function start(
        event: PointerEvent,
        index: number,
        row: HTMLElement | null,
    ): void {
        if (!enabled.value || !row || event.button > 0) {
            return;
        }

        pointerId = event.pointerId;
        start_ = { x: event.clientX, y: event.clientY };
        moved.value = false;
        draggingIndex.value = index;
        targetIndex.value = index;
        offset.value = { x: 0, y: 0 };

        measure(row);
        (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
    }

    function move(event: PointerEvent): void {
        if (draggingIndex.value === null || event.pointerId !== pointerId) {
            return;
        }

        const dx = event.clientX - start_.x;
        const dy = event.clientY - start_.y;

        if (!moved.value && Math.hypot(dx, dy) < DRAG_THRESHOLD_PX) {
            return;
        }

        moved.value = true;
        offset.value = { x: dx, y: dy };
        overDropZone.value = isOverDropZone(event.clientX, event.clientY);
        targetIndex.value = overDropZone.value
            ? draggingIndex.value
            : slotFor({ x: event.clientX, y: event.clientY });
    }

    function end(event: PointerEvent): void {
        if (draggingIndex.value === null || event.pointerId !== pointerId) {
            return;
        }

        const from = draggingIndex.value;
        const to = targetIndex.value ?? from;
        const wasDrag = moved.value;
        const onZone = overDropZone.value;

        reset();

        if (!wasDrag) {
            onTap(from);
        } else if (onZone) {
            onDrop?.(from);
        } else if (to !== from) {
            onReorder(from, to);
        }
    }

    function cancel(): void {
        reset();
    }

    function reset(): void {
        draggingIndex.value = null;
        targetIndex.value = null;
        offset.value = { x: 0, y: 0 };
        overDropZone.value = false;
        pointerId = null;
        moved.value = false;
    }

    /**
     * How far a card should slide to make room for the one being dragged. Cards
     * step into the place of the neighbour they are moving towards, which works
     * whether that neighbour sits beside them or on the row above.
     */
    function shiftFor(index: number): Point {
        const from = draggingIndex.value;
        const to = targetIndex.value;
        const still = { x: 0, y: 0 };

        if (from === null || to === null || !moved.value || index === from) {
            return still;
        }

        const neighbour =
            to > from && index > from && index <= to
                ? index - 1
                : to < from && index >= to && index < from
                  ? index + 1
                  : null;

        if (neighbour === null || slots[neighbour] === undefined) {
            return still;
        }

        return {
            x: slots[neighbour].x - slots[index].x,
            y: slots[neighbour].y - slots[index].y,
        };
    }

    return {
        draggingIndex,
        targetIndex,
        offset,
        isDragging,
        overDropZone,
        start,
        move,
        end,
        cancel,
        shiftFor,
    };
}

/**
 * Moves one item of a list to another position, without touching the original.
 */
export function moveItem<T>(items: T[], from: number, to: number): T[] {
    const next = [...items];
    const [item] = next.splice(from, 1);
    next.splice(to, 0, item);

    return next;
}
