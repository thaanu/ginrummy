<script setup lang="ts">
import GameLobby from '@/components/game/GameLobby.vue';
import GameTable from '@/components/game/GameTable.vue';
import AppAlert from '@/components/ui/AppAlert.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppShell from '@/components/ui/AppShell.vue';
import { useGame } from '@/composables/useGame';
import { leave } from '@/routes/games';
import type { PrivateGameState, PublicGameState } from '@/types/game';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    state: PublicGameState;
    private: PrivateGameState;
    playerId: number;
    invitationUrl: string;
}>();

const game = useGame(props.state, props.private, props.playerId);

const subtitle = computed(() => {
    if (game.state.value.status === 'waiting') {
        return 'Game created';
    }

    return game.state.value.status === 'playing' ? 'Game started' : 'Game over';
});

const connectionLabel = computed(() =>
    game.connection.value === 'connected' ? null : 'Live updates reconnecting…',
);

function leaveGame(): void {
    router.post(leave.url(game.code.value));
}
</script>

<template>
    <Head :title="`Game ${game.code.value}`" />

    <AppShell :subtitle="subtitle">
        <div
            class="flex items-center justify-center gap-3 text-xs text-slate-500"
        >
            <span class="font-mono tracking-[0.2em] tabular-nums">
                {{ game.code.value }}
            </span>
            <span v-if="connectionLabel" class="text-amber-600">
                · {{ connectionLabel }}
            </span>
        </div>

        <AppAlert v-if="game.errorMessage.value">
            {{ game.errorMessage.value }}
        </AppAlert>

        <GameLobby
            v-if="game.state.value.status === 'waiting'"
            :code="game.code.value"
            :invitation-url="invitationUrl"
            :players="game.players.value"
            :is-host="game.isHost.value"
            :can-start="game.canStart.value"
            :busy="game.busy.value"
            @start="game.startGame"
        />

        <GameTable
            v-else
            :state="game.state.value"
            :hand="game.hand.value"
            :opponents="game.opponents.value"
            :me="game.me.value"
            :current-player="game.currentPlayer.value"
            :winner="game.winner.value"
            :selected-card="game.selectedCard.value"
            :spotlit-card="game.justDrawn.value"
            :meld-group-of="game.meldGroupOf"
            :busy="game.busy.value"
            :is-my-turn="game.isMyTurn.value"
            :is-completed="game.isCompleted.value"
            :can-draw="game.canDraw.value"
            :is-discard-phase="game.isDiscardPhase.value"
            :can-declare="game.canDeclare.value"
            :gin-is-waiting-on-selection="game.ginIsWaitingOnSelection.value"
            :gin-is-waiting-on-turn="game.ginIsWaitingOnTurn.value"
            :completes-gin="game.completesGin"
            @select="game.selectCard"
            @reorder="game.sortHand"
            @draw="game.draw"
            @discard="game.discard"
            @declare="game.declareGin"
        />

        <div class="flex justify-center">
            <AppButton variant="ghost" @click="leaveGame">
                {{ game.isCompleted.value ? 'Leave game' : 'Leave table' }}
            </AppButton>
        </div>
    </AppShell>
</template>
