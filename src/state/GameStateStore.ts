import { getPlayerState, type PlayerState } from '../lib/api';

export type GameState = {
  player: PlayerState | null;
  isLoading: boolean;
  error: string | null;
};

type Listener = () => void;

export class GameStateStore {
  private state: GameState = {
    player: null,
    isLoading: false,
    error: null,
  };

  private readonly listeners = new Set<Listener>();

  getSnapshot = (): GameState => this.state;

  subscribe = (listener: Listener): (() => void) => {
    this.listeners.add(listener);

    return () => this.listeners.delete(listener);
  };

  setState(next: Partial<GameState>): void {
    this.state = { ...this.state, ...next };
    this.listeners.forEach((listener) => listener());
  }

  clear(): void {
    this.setState({ player: null, isLoading: false, error: null });
  }

  async hydrate(token: string): Promise<void> {
    this.setState({ isLoading: true, error: null });

    try {
      const player = await getPlayerState(token);
      this.setState({ player, isLoading: false });
    } catch (error) {
      this.setState({
        isLoading: false,
        error: error instanceof Error ? error.message : 'Player state could not be loaded.',
      });
    }
  }
}

export const gameStateStore = new GameStateStore();
