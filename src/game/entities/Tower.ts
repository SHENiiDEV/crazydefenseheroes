import { Enemy } from './Enemy';
import { Projectile } from './Projectile';
import { TargetStrategy, TowerTypeId } from '../types';

export const TOWER_COST = 50;

const strategyOrder = [
  TargetStrategy.FIRST,
  TargetStrategy.STRONG,
  TargetStrategy.CLOSE,
] as const;

export type TowerConfig = {
  type: TowerTypeId;
  name: string;
  nameRu: string;
  cost: number;
  range: number;
  damage: number;
  fireRate: number;
  color: string;
  bodyColor: string;
  projectileColor: string;
  projectileSpeed: number;
  projectileSize: number;
  defaultStrategy: TargetStrategy;
};

export const TOWER_CONFIGS: Record<TowerTypeId, TowerConfig> = {
  'ember-archer': {
    type: 'ember-archer',
    name: 'Ember Archer',
    nameRu: 'Лучник огня',
    cost: 50,
    range: 135,
    damage: 7,
    fireRate: 380,
    color: '#d86131',
    bodyColor: '#e37c38',
    projectileColor: '#f59e0b',
    projectileSpeed: 320,
    projectileSize: 3.5,
    defaultStrategy: TargetStrategy.FIRST,
  },
  'stone-warden': {
    type: 'stone-warden',
    name: 'Stone Warden',
    nameRu: 'Каменный страж',
    cost: 80,
    range: 115,
    damage: 24,
    fireRate: 950,
    color: '#52665b',
    bodyColor: '#6c8478',
    projectileColor: '#78716c',
    projectileSpeed: 210,
    projectileSize: 6,
    defaultStrategy: TargetStrategy.CLOSE,
  },
  'vault-mage': {
    type: 'vault-mage',
    name: 'Vault Mage',
    nameRu: 'Маг хранилища',
    cost: 110,
    range: 165,
    damage: 18,
    fireRate: 620,
    color: '#3b6e8c',
    bodyColor: '#5c8399',
    projectileColor: '#38bdf8',
    projectileSpeed: 270,
    projectileSize: 4.5,
    defaultStrategy: TargetStrategy.STRONG,
  },
};

export class Tower {
  public lastFired = Number.NEGATIVE_INFINITY;
  public readonly towerType: TowerTypeId;
  public readonly name: string;
  public readonly color: string;
  public readonly bodyColor: string;
  public readonly projectileColor: string;
  public readonly projectileSpeed: number;
  public readonly projectileSize: number;
  public readonly range: number;
  public readonly damage: number;
  public readonly fireRate: number;
  public readonly cost: number;

  constructor(
    public readonly id: string,
    public readonly x: number,
    public readonly y: number,
    type: TowerTypeId = 'ember-archer',
    public targetStrategy: TargetStrategy = TargetStrategy.FIRST,
    public readonly level: number = 1,
  ) {
    this.towerType = type;
    const config = TOWER_CONFIGS[type] ?? TOWER_CONFIGS['ember-archer'];
    this.name = config.name;
    this.color = config.color;
    this.bodyColor = config.bodyColor;
    this.projectileColor = config.projectileColor;
    this.projectileSpeed = config.projectileSpeed;
    this.projectileSize = config.projectileSize + (level > 1 ? 1 : 0);
    this.cost = config.cost;
    this.targetStrategy = targetStrategy || config.defaultStrategy;

    // Stat multipliers based on upgraded card level
    const levelMultiplier = 1 + (Math.max(1, level) - 1) * 0.25;
    const speedMultiplier = 1 + (Math.max(1, level) - 1) * 0.08;
    const rangeMultiplier = 1 + (Math.max(1, level) - 1) * 0.05;

    this.damage = Math.round(config.damage * levelMultiplier);
    this.fireRate = Math.max(100, Math.round(config.fireRate / speedMultiplier));
    this.range = Math.round(config.range * rangeMultiplier);
  }

  findTarget(enemies: readonly Enemy[]): Enemy | null {
    const targets = enemies.filter((enemy) => {
      if (!enemy.isAlive) {
        return false;
      }

      return Math.hypot(enemy.x - this.x, enemy.y - this.y) <= this.range;
    });

    if (targets.length === 0) {
      return null;
    }

    if (this.targetStrategy === TargetStrategy.STRONG) {
      return targets.reduce((strongest, target) => (
        target.currentHp > strongest.currentHp ? target : strongest
      ));
    }

    if (this.targetStrategy === TargetStrategy.CLOSE) {
      return targets.reduce((closest, target) => (
        this.distanceTo(target) < this.distanceTo(closest) ? target : closest
      ));
    }

    return targets.reduce((furthestAlongPath, target) => (
      target.pathProgress > furthestAlongPath.pathProgress ? target : furthestAlongPath
    ));
  }

  fire(target: Enemy, currentTime: number): Projectile | null {
    if (currentTime - this.lastFired < this.fireRate) {
      return null;
    }

    this.lastFired = currentTime;
    return new Projectile(
      this.x,
      this.y,
      this.projectileSpeed,
      this.damage,
      target.id,
      this.projectileColor,
      this.projectileSize,
    );
  }

  cycleTargetStrategy(): TargetStrategy {
    const currentIndex = strategyOrder.indexOf(this.targetStrategy);
    this.targetStrategy = strategyOrder[(currentIndex + 1) % strategyOrder.length];
    return this.targetStrategy;
  }

  draw(context: CanvasRenderingContext2D, selected = false): void {
    context.save();

    if (selected) {
      context.fillStyle = 'rgba(216, 97, 49, .15)';
      context.strokeStyle = 'rgba(216, 97, 49, .75)';
      context.lineWidth = 1.5;
      context.beginPath();
      context.arc(this.x, this.y, this.range, 0, Math.PI * 2);
      context.fill();
      context.stroke();
    }

    // Base body
    context.fillStyle = this.bodyColor;
    context.strokeStyle = '#30362f';
    context.lineWidth = 2;
    context.beginPath();
    context.arc(this.x, this.y + 4, 15, 0, Math.PI * 2);
    context.fill();
    context.stroke();

    // Turret / Crest
    const barrelAngle = this.targetStrategy === TargetStrategy.CLOSE
      ? Math.PI / 2
      : this.targetStrategy === TargetStrategy.STRONG
        ? -Math.PI / 4
        : 0;
    context.translate(this.x, this.y);
    context.rotate(barrelAngle);
    context.fillStyle = this.color;
    context.fillRect(-4, -18, 8, 20);
    context.restore();

    // Level & Strategy HUD text below tower
    context.fillStyle = '#f7e7b4';
    context.font = '700 8px monospace';
    context.textAlign = 'center';
    context.fillText(
      (this.level > 1 ? `L${this.level} ` : '') + this.targetStrategy,
      this.x,
      this.y + 27,
    );
    context.textAlign = 'start';
  }

  private distanceTo(enemy: Enemy): number {
    return Math.hypot(enemy.x - this.x, enemy.y - this.y);
  }
}
