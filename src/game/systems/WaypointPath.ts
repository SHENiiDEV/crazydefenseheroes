import type { GridCell, Point } from '../types';
import { GridMap } from '../entities/GridMap';

export class WaypointPath {
  public readonly points: readonly Point[];

  constructor(map: GridMap, cells: readonly GridCell[]) {
    if (cells.length < 2) {
      throw new Error('WaypointPath requires at least two cells.');
    }

    this.points = cells.map((cell) => map.getTileCenter(cell));
  }
}
