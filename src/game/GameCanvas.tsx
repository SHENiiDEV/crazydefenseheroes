import { useEffect, useRef } from 'react';
import { syncEndlessCheckpoint } from '../lib/api';
import { TowerDefenseGame } from './engine/TowerDefenseGame';

export function GameCanvas({
  paused = false,
  selectedTowerType = 'ember-archer',
  towerLevels,
}: {
  paused?: boolean;
  selectedTowerType?: import('./types').TowerTypeId;
  towerLevels?: Record<import('./types').TowerTypeId, number>;
}) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const gameRef = useRef<TowerDefenseGame | null>(null);

  useEffect(() => {
    if (!canvasRef.current) {
      return;
    }

    const token = window.localStorage.getItem('cdh.token');
    const game = new TowerDefenseGame(canvasRef.current, {
      onWaveCheckpoint: (checkpoint) => {
        if (!token) {
          return;
        }

        void syncEndlessCheckpoint(token, {
          run_id: checkpoint.runId,
          wave: checkpoint.wave,
          defeated_enemies: checkpoint.defeatedEnemies,
          lives_remaining: checkpoint.livesRemaining,
        }).catch(() => {
          // The next checkpoint can retry after a reconnect without stopping the local run.
        });
      },
    });

    if (towerLevels) {
      game.setTowerLevels(towerLevels);
    }
    game.setSelectedBuildTowerType(selectedTowerType);
    game.start();
    gameRef.current = game;

    if (paused) {
      game.pause();
    }

    return () => {
      game.destroy();
      gameRef.current = null;
    };
  }, []);

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
