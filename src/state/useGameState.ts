import { useSyncExternalStore } from 'react';
import { gameStateStore } from './GameStateStore';

export function useGameState() {
  return useSyncExternalStore(
    gameStateStore.subscribe,
    gameStateStore.getSnapshot,
    gameStateStore.getSnapshot,
  );
}
