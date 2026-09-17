import { Enemy } from '../entities/Enemy';
import { Projectile } from '../entities/Projectile';
import { Tower } from '../entities/Tower';

export type CombatUpdateResult = {
  defeated: Enemy[];
  fired: number;
};

export class CombatSystem {
  update(
    deltaTime: number,
    currentTime: number,
    towers: readonly Tower[],
    enemies: readonly Enemy[],
    projectiles: Projectile[],
  ): CombatUpdateResult {
    let fired = 0;

    towers.forEach((tower) => {
      const target = tower.findTarget(enemies);
      if (!target) {
        return;
      }

      const projectile = tower.fire(target, currentTime);
      if (projectile) {
        projectiles.push(projectile);
        fired += 1;
      }
    });

    for (let index = projectiles.length - 1; index >= 0; index -= 1) {
      const projectile = projectiles[index];
      const target = enemies.find((enemy) => enemy.id === projectile.targetId);

      if (projectile.update(deltaTime, target)) {
        projectiles.splice(index, 1);
      }
    }

    return {
      defeated: enemies.filter((enemy) => !enemy.isAlive),
      fired,
    };
  }
}
