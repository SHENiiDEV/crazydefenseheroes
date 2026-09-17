import type { GridCell, Point } from '../types';

export const TILE_SIZE = 48;

export const CELL_BUILDABLE = 0;
export const CELL_PATH = 1;
export const CELL_OCCUPIED = 2;

export class GridMap {
  public readonly rows: number;
  public readonly columns: number;
  private readonly cells: number[][];

  constructor(matrix: number[][], public readonly tileSize = TILE_SIZE) {
    if (matrix.length === 0 || matrix.some((row) => row.length !== matrix[0].length)) {
      throw new Error('GridMap requires a non-empty rectangular matrix.');
    }

    this.cells = matrix.map((row) => [...row]);
    this.rows = matrix.length;
    this.columns = matrix[0].length;
  }

  get width(): number {
    return this.columns * this.tileSize;
  }

  get height(): number {
    return this.rows * this.tileSize;
  }

  getCell(cell: GridCell): number | undefined {
    return this.cells[cell.row]?.[cell.col];
  }

  getCellAtPoint(point: Point): GridCell | null {
    const row = Math.floor(point.y / this.tileSize);
    const col = Math.floor(point.x / this.tileSize);

    return this.isInside({ row, col }) ? { row, col } : null;
  }

  getTileCenter(cell: GridCell): Point {
    return {
      x: cell.col * this.tileSize + this.tileSize / 2,
      y: cell.row * this.tileSize + this.tileSize / 2,
    };
  }

  isInside(cell: GridCell): boolean {
    return cell.row >= 0 && cell.row < this.rows && cell.col >= 0 && cell.col < this.columns;
  }

  isBuildable(cell: GridCell): boolean {
    return this.getCell(cell) === CELL_BUILDABLE;
  }

  toggleOccupied(cell: GridCell): boolean {
    const current = this.getCell(cell);

    if (current === CELL_BUILDABLE) {
      this.cells[cell.row][cell.col] = CELL_OCCUPIED;
      return true;
    }

    if (current === CELL_OCCUPIED) {
      this.cells[cell.row][cell.col] = CELL_BUILDABLE;
      return true;
    }

    return false;
  }

  draw(context: CanvasRenderingContext2D): void {
    context.fillStyle = '#f4edcf';
    context.fillRect(0, 0, this.width, this.height);

    for (let row = 0; row < this.rows; row += 1) {
      for (let col = 0; col < this.columns; col += 1) {
        const cell = { row, col };
        const value = this.getCell(cell);
        const x = col * this.tileSize;
        const y = row * this.tileSize;

        context.fillStyle = this.cellColor(value);
        context.fillRect(x + 1, y + 1, this.tileSize - 2, this.tileSize - 2);
        context.strokeStyle = 'rgba(88, 83, 62, .16)';
        context.lineWidth = 1;
        context.strokeRect(x + .5, y + .5, this.tileSize - 1, this.tileSize - 1);

        if (value === CELL_OCCUPIED) {
          this.drawOccupiedMarker(context, x, y);
        }
      }
    }
  }

  private cellColor(value: number | undefined): string {
    if (value === CELL_PATH) {
      return '#d4c19f';
    }

    if (value === CELL_OCCUPIED) {
      return '#a6c6ad';
    }

    return '#f8f3dd';
  }

  private drawOccupiedMarker(context: CanvasRenderingContext2D, x: number, y: number): void {
    const center = this.tileSize / 2;
    context.strokeStyle = 'rgba(61, 102, 75, .65)';
    context.lineWidth = 2;
    context.beginPath();
    context.moveTo(x + center - 8, y + center);
    context.lineTo(x + center + 8, y + center);
    context.moveTo(x + center, y + center - 8);
    context.lineTo(x + center, y + center + 8);
    context.stroke();
  }
}
