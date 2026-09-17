import { Enemy } from './Enemy';

export class Projectile {
  constructor(
    public x: number,
    public y: number,
    public readonly speed: number,
    public readonly damage: number,
    public readonly targetId: string,
    public readonly color = '#f3c85b',
    public readonly radius = 4,
  ) {}

  /**
   * Moves toward the target's current position and applies damage on contact.
   * Returning true tells CombatSystem that the projectile should be removed.
   */
  update(deltaTime: number, target: Enemy | undefined): boolean {
    if (!target || !target.isAlive) {
      return true;
    }

    const dx = target.x - this.x;
    const dy = target.y - this.y;
    const distance = Math.hypot(dx, dy);
    const step = this.speed * deltaTime;

    if (distance <= Math.max(5, step)) {
      this.x = target.x;
      this.y = target.y;
      target.takeDamage(this.damage);
      return true;
    }

    this.x += (dx / distance) * step;
    this.y += (dy / distance) * step;
    return false;
  }

  draw(context: CanvasRenderingContext2D): void {
    context.save();
    context.fillStyle = this.color;
    context.strokeStyle = 'rgba(48, 54, 47, 0.6)';
    context.lineWidth = 1;
    context.beginPath();
    context.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
    context.fill();
    context.stroke();
    context.restore();
  }
}
