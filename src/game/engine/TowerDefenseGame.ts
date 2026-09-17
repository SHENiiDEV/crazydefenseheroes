import { Enemy } from '../entities/Enemy';
import {
  CELL_PATH,
  GridMap,
  TILE_SIZE,
} from '../entities/GridMap';
import { Tower, TOWER_CONFIGS } from '../entities/Tower';
import { Projectile } from '../entities/Projectile';
import { GameLoop } from './GameLoop';
import { TargetStrategy, TowerTypeId, type GridCell } from '../types';
import { CombatSystem } from '../systems/CombatSystem';
import { WaveManager } from '../systems/WaveManager';
import { WaypointPath } from '../systems/WaypointPath';

export type WaveCheckpointPayload = {
  runId: string;
  wave: number;
  defeatedEnemies: number;
  livesRemaining: number;
};

type TowerDefenseGameOptions = {
  onWaveCheckpoint?: (payload: WaveCheckpointPayload) => void;
  onSelectedTowerTypeChange?: (type: TowerTypeId) => void;
};

const DEFAULT_MATRIX = [
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 1, 1, 1, 1, 1, 1, 0, 0, 0],
  [0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0],
  [1, 1, 1, 1, 1, 1, 0, 0, 0, 0, 1, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 1, 1, 1],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
  [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
];

const DEFAULT_PATH: readonly GridCell[] = [
  { row: 3, col: 0 },
  { row: 3, col: 5 },
  { row: 1, col: 5 },
  { row: 1, col: 10 },
  { row: 4, col: 10 },
  { row: 4, col: 13 },
];

export class TowerDefenseGame {
  private readonly context: CanvasRenderingContext2D;
  private readonly map = new GridMap(DEFAULT_MATRIX);
  private readonly path = new WaypointPath(this.map, DEFAULT_PATH);
  private readonly enemies: Enemy[] = [];
  private readonly towers: Tower[] = [];
  private readonly projectiles: Projectile[] = [];
  private readonly combatSystem = new CombatSystem();
  private readonly waveManager = new WaveManager({
    initialEnemies: 6,
    enemiesPerWave: 2,
    spawnInterval: 0.8,
    restDuration: 5,
  });
  private readonly loop: GameLoop;
  private elapsedMilliseconds = 0;
  private gold = 150;
  private lives = 20;
  private defeated = 0;
  private selectedTowerId: string | null = null;
  private selectedBuildTowerType: TowerTypeId = 'ember-archer';
  private towerLevels: Record<TowerTypeId, number> = {
    'ember-archer': 1,
    'stone-warden': 1,
    'vault-mage': 1,
  };
  private statusMessage = 'Select a tower card and click a tile to build.';
  private readonly runId = createRunId();
  private lastCheckpointWave = 0;
  private isPaused = false;

  constructor(
    private readonly canvas: HTMLCanvasElement,
    private readonly options: TowerDefenseGameOptions = {},
  ) {
    const context = canvas.getContext('2d');

    if (!context) {
      throw new Error('CanvasRenderingContext2D is not available.');
    }

    this.context = context;
    canvas.width = this.map.width;
    canvas.height = this.map.height;
    canvas.addEventListener('click', this.handleCanvasClick);
    this.loop = new GameLoop(this.context, this.update, this.draw);
  }

  start(): void {
    this.loop.start();
  }

  stop(): void {
    this.loop.stop();
  }

  pause(): void {
    this.isPaused = true;
  }

  resume(): void {
    this.isPaused = false;
  }

  getPaused(): boolean {
    return this.isPaused;
  }

  setSelectedBuildTowerType(type: TowerTypeId): void {
    this.selectedBuildTowerType = type;
    const config = TOWER_CONFIGS[type];
    const lvl = this.towerLevels[type] ?? 1;
    this.statusMessage = `Ready to place ${config.name} (Lvl ${lvl}, ${config.cost}G).`;
  }

  setTowerLevels(levels: Record<TowerTypeId, number>): void {
    this.towerLevels = { ...this.towerLevels, ...levels };
  }

  getSelectedBuildTowerType(): TowerTypeId {
    return this.selectedBuildTowerType;
  }

  destroy(): void {
    this.stop();
    this.canvas.removeEventListener('click', this.handleCanvasClick);
  }

  private readonly update = (deltaTime: number): void => {
    if (this.isPaused) {
      return;
    }

    this.elapsedMilliseconds += deltaTime * 1000;
    this.waveManager.update(deltaTime, this.spawnEnemy, this.handleWaveComplete);

    for (let index = this.enemies.length - 1; index >= 0; index -= 1) {
      const enemy = this.enemies[index];

      if (enemy.move(deltaTime, this.path.points) === 'reached-end') {
        this.lives = Math.max(0, this.lives - 1);
        this.statusMessage = 'A monster breached the keep! Defend!';
        this.enemies.splice(index, 1);
      }
    }

    const combatResult = this.combatSystem.update(
      deltaTime,
      this.elapsedMilliseconds,
      this.towers,
      this.enemies,
      this.projectiles,
    );

    combatResult.defeated.forEach((enemy) => {
      this.gold += enemy.reward;
      this.defeated += 1;
    });

    if (combatResult.defeated.length > 0) {
      for (let index = this.enemies.length - 1; index >= 0; index -= 1) {
        if (!this.enemies[index].isAlive) {
          this.enemies.splice(index, 1);
        }
      }
    }
  };

  private readonly handleWaveComplete = (wave: number): void => {
    if (wave <= this.lastCheckpointWave) {
      return;
    }

    this.lastCheckpointWave = wave;
    this.gold += 30 + wave * 5;
    this.statusMessage = `Wave ${wave} cleared! Checkpoint recorded.`;

    this.options.onWaveCheckpoint?.({
      runId: this.runId,
      wave,
      defeatedEnemies: this.defeated,
      livesRemaining: this.lives,
    });
  };

  private readonly draw = (context: CanvasRenderingContext2D): void => {
    context.clearRect(0, 0, this.canvas.width, this.canvas.height);
    this.map.draw(context);
    this.drawPath(context);
    this.towers.forEach((tower) => tower.draw(context, tower.id === this.selectedTowerId));
    this.projectiles.forEach((projectile) => projectile.draw(context));
    this.enemies.forEach((enemy) => enemy.draw(context));
    this.drawHud(context);
  };

  private readonly spawnEnemy = (wave: number, enemyNumber: number): void => {
    const spawnPoint = this.path.points[0];
    const maxHp = 30 * Math.pow(1.2, wave - 1);

    this.enemies.push(new Enemy(
      'enemy-' + wave + '-' + enemyNumber + '-' + Date.now(),
      spawnPoint.x,
      spawnPoint.y,
      24,
      24,
      54 + wave * 2,
      maxHp,
      10 + wave,
    ));
  };

  private readonly handleCanvasClick = (event: MouseEvent): void => {
    const bounds = this.canvas.getBoundingClientRect();
    const point = {
      x: (event.clientX - bounds.left) * (this.canvas.width / bounds.width),
      y: (event.clientY - bounds.top) * (this.canvas.height / bounds.height),
    };
    const cell = this.map.getCellAtPoint(point);

    if (cell) {
      const tower = this.towerAtCell(cell);

      if (tower) {
        this.selectedTowerId = tower.id;
        const strategy = tower.cycleTargetStrategy();
        this.statusMessage = `${tower.name} target: ${strategy}.`;
        return;
      }

      if (!this.map.isBuildable(cell)) {
        this.statusMessage = 'Road tile reserved for enemies.';
        return;
      }

      const config = TOWER_CONFIGS[this.selectedBuildTowerType];
      if (this.gold < config.cost) {
        this.statusMessage = `Need ${config.cost} gold for ${config.name}.`;
        return;
      }

      const center = this.map.getTileCenter(cell);
      const towerId = 'tower-' + cell.row + '-' + cell.col;
      const level = this.towerLevels[this.selectedBuildTowerType] ?? 1;
      this.map.toggleOccupied(cell);
      this.towers.push(new Tower(
        towerId,
        center.x,
        center.y,
        this.selectedBuildTowerType,
        undefined,
        level,
      ));
      this.gold -= config.cost;
      this.selectedTowerId = towerId;
      this.statusMessage = `${config.name} (Lvl ${level}) deployed. Click to cycle target mode.`;
    }
  };

  private drawPath(context: CanvasRenderingContext2D): void {
    context.save();
    context.strokeStyle = 'rgba(163, 101, 57, .45)';
    context.lineWidth = 3;
    context.setLineDash([6, 7]);
    context.beginPath();
    this.path.points.forEach((point, index) => {
      if (index === 0) {
        context.moveTo(point.x, point.y);
      } else {
        context.lineTo(point.x, point.y);
      }
    });
    context.stroke();
    context.restore();
  }

  private drawHud(context: CanvasRenderingContext2D): void {
    const state = this.waveManager.getState();
    const x = 18;
    const y = 17;

    context.fillStyle = 'rgba(53, 60, 48, .85)';
    context.fillRect(10, 10, 310, 43);
    context.fillStyle = '#fff5d4';
    context.font = '700 12px monospace';
    context.fillText('WAVE ' + String(state.wave).padStart(2, '0'), x, y);
    context.fillStyle = '#f2c864';
    context.fillText('GOLD ' + String(this.gold).padStart(3, '0'), 106, y);
    context.fillStyle = '#f2a8a0';
    context.fillText('LIVES ' + String(this.lives).padStart(2, '0'), 210, y);
    context.fillStyle = '#dbd3b2';
    context.font = '10px monospace';
    const phaseLabel = state.phase === 'resting'
      ? 'next wave in ' + Math.ceil(state.restRemaining) + 's'
      : state.spawned + '/' + state.total + ' enemies';
    context.fillText(phaseLabel, x, y + 17);

    // Bottom status bar
    context.fillStyle = '#4c3a2e';
    context.font = '600 10px monospace';
    context.fillText(this.statusMessage, 18, this.canvas.height - 12);
    context.fillText('DEFEATED: ' + String(this.defeated), this.canvas.width - 130, this.canvas.height - 12);

    if (this.isPaused) {
      context.save();
      context.fillStyle = 'rgba(216, 97, 49, .92)';
      context.fillRect(this.canvas.width / 2 - 55, 10, 110, 24);
      context.fillStyle = '#fff7dc';
      context.font = 'bold 15px sans-serif';
      context.textAlign = 'center';
      context.fillText('PAUSED', this.canvas.width / 2, 26);
      context.restore();
    }
  }

  private towerAtCell(cell: GridCell): Tower | undefined {
    return this.towers.find((tower) => (
      Math.floor(tower.x / this.map.tileSize) === cell.col
      && Math.floor(tower.y / this.map.tileSize) === cell.row
    ));
  }
}

function createRunId(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }

  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (character) => {
    const random = Math.floor(Math.random() * 16);
    const value = character === 'x' ? random : (random & 0x3) | 0x8;
    return value.toString(16);
  });
}
