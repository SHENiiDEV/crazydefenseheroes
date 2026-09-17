export type GameUpdate = (deltaTime: number) => void;
export type GameDraw = (context: CanvasRenderingContext2D) => void;

export class GameLoop {
  private animationFrame: number | null = null;
  private previousTimestamp: number | null = null;

  constructor(
    private readonly context: CanvasRenderingContext2D,
    private readonly update: GameUpdate,
    private readonly draw: GameDraw,
  ) {}

  start(): void {
    if (this.animationFrame !== null) {
      return;
    }

    this.previousTimestamp = null;
    this.animationFrame = window.requestAnimationFrame(this.frame);
  }

  stop(): void {
    if (this.animationFrame !== null) {
      window.cancelAnimationFrame(this.animationFrame);
    }

    this.animationFrame = null;
    this.previousTimestamp = null;
  }

  private readonly frame = (timestamp: number): void => {
    const deltaTime = this.previousTimestamp === null
      ? 0
      : Math.min((timestamp - this.previousTimestamp) / 1000, 0.1);

    this.previousTimestamp = timestamp;
    this.update(deltaTime);
    this.draw(this.context);
    this.animationFrame = window.requestAnimationFrame(this.frame);
  };
}
