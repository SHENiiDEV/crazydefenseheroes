import type { WavePhase } from '../types';

export type WaveState = {
  wave: number;
  phase: WavePhase;
  spawned: number;
  total: number;
  restRemaining: number;
};

export type WaveConfig = {
  initialEnemies: number;
  enemiesPerWave: number;
  spawnInterval: number;
  restDuration: number;
};

export class WaveManager {
  private spawnTimer = 0;
  private restTimer = 0;
  private spawned = 0;
  private phase: WavePhase = 'spawning';
  private wave = 1;

  constructor(private readonly config: WaveConfig) {}

  getState(): WaveState {
    return {
      wave: this.wave,
      phase: this.phase,
      spawned: this.spawned,
      total: this.totalEnemies(),
      restRemaining: Math.max(this.restTimer, 0),
    };
  }

  update(
    deltaTime: number,
    onSpawn: (wave: number, enemyNumber: number) => void,
    onWaveComplete?: (wave: number) => void,
  ): void {
    if (this.phase === 'resting') {
      this.restTimer -= deltaTime;

      if (this.restTimer <= 0) {
        this.wave += 1;
        this.spawned = 0;
        this.spawnTimer = 0;
        this.phase = 'spawning';
      }

      return;
    }

    this.spawnTimer -= deltaTime;

    while (this.spawnTimer <= 0 && this.spawned < this.totalEnemies()) {
      this.spawned += 1;
      this.spawnTimer += this.config.spawnInterval;
      onSpawn(this.wave, this.spawned);
    }

    if (this.spawned >= this.totalEnemies()) {
      this.phase = 'resting';
      this.restTimer = this.config.restDuration;
      onWaveComplete?.(this.wave);
    }
  }

  private totalEnemies(): number {
    return this.config.initialEnemies + (this.wave - 1) * this.config.enemiesPerWave;
  }
}
