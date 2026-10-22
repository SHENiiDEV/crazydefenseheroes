import { useEffect, useRef } from 'react';
import { syncEndlessCheckpoint, type EndlessCheckpointPayload, type PlayerState } from '../lib/api';
import { TowerDefenseGame } from './engine/TowerDefenseGame';

export function GameCanvas({
  paused = false,
  selectedTowerType = 'ember-archer',
  towerLevels,
  ownedTowerTypes,
  accountUserId,
  onWalletUpdated,
  onSyncStatus,
  onGameOver,
  boostCommand,
  onBattleStateChange,
}: {
  paused?: boolean;
  selectedTowerType?: import('./types').TowerTypeId;
  towerLevels?: Record<import('./types').TowerTypeId, number>;
  ownedTowerTypes?: import('./types').TowerTypeId[];
  accountUserId?: number;
  onWalletUpdated?: (wallet: NonNullable<PlayerState['wallet']>) => void;
  onSyncStatus?: (message: string) => void;
  onGameOver?: (wave: number) => void;
  boostCommand?: { id: number; code: string } | null;
  onBattleStateChange?: (state: { lives: number; enemies: number; towers: number }) => void;
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const gameRef = useRef<TowerDefenseGame | null>(null);
  const callbacks = useRef({ onWalletUpdated, onSyncStatus, onGameOver, onBattleStateChange });
  callbacks.current = { onWalletUpdated, onSyncStatus, onGameOver, onBattleStateChange };

  useEffect(() => {
    if (!canvasRef.current) {
      return;
    }

    const token = window.localStorage.getItem('cdh.token');
    const storageKey = `cdh.wave-checkpoints.${accountUserId}`;
    let pending: EndlessCheckpointPayload[] = [];
    let cancelled = false;
    let saving = false;
    if (accountUserId) {
      try { pending = JSON.parse(window.localStorage.getItem(storageKey) ?? '[]'); } catch { pending = []; }
      if (!Array.isArray(pending)) pending = [];
    }
    const persist = () => {
      try { window.localStorage.setItem(storageKey, JSON.stringify(pending)); } catch {
        callbacks.current.onSyncStatus?.('Browser storage is unavailable. Keep this tab open until rewards are saved.');
      }
    };
    const flush = async () => {
      if (!token || !accountUserId || saving || cancelled || pending.length === 0) return;
      saving = true;
      try {
        while (pending.length > 0 && !cancelled) {
          const result = await syncEndlessCheckpoint(token, pending[0]);
          pending.shift();
          persist();
          if (!cancelled) {
            callbacks.current.onWalletUpdated?.(result.wallet);
            callbacks.current.onSyncStatus?.(`Wave ${result.checkpoint.wave} saved. Account Gold retained for future use.`);
          }
        }
      } catch {
        if (!cancelled) callbacks.current.onSyncStatus?.('Reward save pending. Reconnecting automatically; do not clear browser storage.');
      } finally { saving = false; }
    };
    const game = new TowerDefenseGame(canvasRef.current, {
      onGameOver: (wave) => callbacks.current.onGameOver?.(wave),
      onBattleStateChange: (state) => callbacks.current.onBattleStateChange?.(state),
      onWaveCheckpoint: (checkpoint) => {
        if (!token || !accountUserId) {
          return;
        }

        pending.push({
          run_id: checkpoint.runId,
          wave: checkpoint.wave,
          defeated_enemies: checkpoint.defeatedEnemies,
          lives_remaining: checkpoint.livesRemaining,
        });
        persist();
        callbacks.current.onSyncStatus?.('Saving completed wave…');
        void flush();
      },
    });

    if (towerLevels) {
      game.setTowerLevels(towerLevels);
    }
    game.setSelectedBuildTowerType(selectedTowerType);
    if (ownedTowerTypes) game.setOwnedTowerTypes(ownedTowerTypes);
    game.start();
    gameRef.current = game;

    if (paused) {
      game.pause();
    }
    const retryTimer = accountUserId ? window.setInterval(() => { void flush(); }, 10000) : undefined;
    window.addEventListener('online', flush);
    void flush();

    return () => {
      cancelled = true;
      window.clearInterval(retryTimer);
      window.removeEventListener('online', flush);
      game.destroy();
      gameRef.current = null;
    };
  }, [accountUserId]);

  useEffect(() => {
    if (ownedTowerTypes) gameRef.current?.setOwnedTowerTypes(ownedTowerTypes);
  }, [ownedTowerTypes]);

  useEffect(() => {
    if (boostCommand) gameRef.current?.applyBoost(boostCommand.code);
  }, [boostCommand]);

  useEffect(() => {
    if (!gameRef.current) {
      return;
    }

    gameRef.current.setSelectedBuildTowerType(selectedTowerType);
  }, [selectedTowerType]);

  useEffect(() => {
    if (!gameRef.current || !towerLevels) {
      return;
    }

    gameRef.current.setTowerLevels(towerLevels);
  }, [towerLevels]);

  useEffect(() => {
    if (!gameRef.current) {
      return;
    }

    if (paused) {
      gameRef.current.pause();
    } else {
      gameRef.current.resume();
    }
  }, [paused]);

  return (
    <canvas
      ref={canvasRef}
      className="game-canvas"
      aria-label="Playable tower defense map preview. Click a pale tile to buy a tower. Click a tower to change its target strategy."
    />
  );
}
