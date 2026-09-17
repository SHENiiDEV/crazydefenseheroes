import type { Point } from '../types';

export type EnemyMoveResult = 'moving' | 'reached-end';

export class Enemy {
  public currentHp: number;
  public waypointIndex = 0;
  public pathProgress = 0;

  constructor(
    public readonly id: string,
    public x: number,
    public y: number,
    public readonly width: number,
    public readonly height: number,
    public readonly speed: number,
    public readonly maxHp: number,
    public readonly reward: number,
  ) {
    this.currentHp = maxHp;
  }

  get isAlive(): boolean {
    return this.currentHp > 0;
  }

  takeDamage(amount: number): void {
    this.currentHp = Math.max(0, this.currentHp - Math.max(amount, 0));
  }

  move(deltaTime: number, waypoints: readonly Point[]): EnemyMoveResult {
    const segmentStart = waypoints[this.waypointIndex];
    const target = waypoints[this.waypointIndex + 1];

    if (!segmentStart || !target) {
      return 'reached-end';
    }

    const dx = target.x - this.x;
    const dy = target.y - this.y;
    const distance = Math.hypot(dx, dy);
    const step = this.speed * deltaTime;

    if (distance <= step) {
      this.x = target.x;
      this.y = target.y;
      this.waypointIndex += 1;
      this.pathProgress = this.waypointIndex;

      return this.waypointIndex >= waypoints.length - 1 ? 'reached-end' : 'moving';
    }

    // The normalized vector keeps movement independent from the frame rate.
    this.x += (dx / distance) * step;
    this.y += (dy / distance) * step;
    const segmentLength = Math.hypot(target.x - segmentStart.x, target.y - segmentStart.y);
    const distanceFromStart = Math.hypot(this.x - segmentStart.x, this.y - segmentStart.y);
    this.pathProgress = this.waypointIndex + Math.min(distanceFromStart / segmentLength, 0.999);

    return 'moving';
  }

  draw(context: CanvasRenderingContext2D): void {
    const left = this.x - this.width / 2;
    const top = this.y - this.height / 2;
    const hpRatio = Math.max(this.currentHp / this.maxHp, 0);

    context.globalAlpha = this.isAlive ? 1 : 0;
    context.fillStyle = '#bd5138';
    context.fillRect(left, top, this.width, this.height);
    context.strokeStyle = '#6e382f';
    context.lineWidth = 2;
    context.strokeRect(left, top, this.width, this.height);

    context.fillStyle = '#4a3b32';
    context.fillRect(left + 5, top + 7, 4, 4);
    context.fillRect(left + this.width - 9, top + 7, 4, 4);

    context.fillStyle = 'rgba(57, 53, 42, .42)';
    context.fillRect(left - 4, top - 9, this.width + 8, 4);
    context.fillStyle = '#78a568';
    context.fillRect(left - 4, top - 9, (this.width + 8) * hpRatio, 4);
    context.globalAlpha = 1;
  }
}
