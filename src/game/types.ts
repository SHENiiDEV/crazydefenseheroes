export type Point = {
  x: number;
  y: number;
};

export type GridCell = {
  row: number;
  col: number;
};

export type WavePhase = 'spawning' | 'resting';

export type TowerTypeId = 'ember-archer' | 'stone-warden' | 'vault-mage';

export enum TargetStrategy {
  FIRST = 'FIRST',
  STRONG = 'STRONG',
  CLOSE = 'CLOSE',
}
