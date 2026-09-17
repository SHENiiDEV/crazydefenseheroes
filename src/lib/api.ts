export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';

export type PlayerState = {
  user: {
    id: number;
    name: string;
    surname: string;
    email: string;
    email_verified: boolean;
    address: {
      street: string;
      city: string;
      country: string;
      postcode: string;
    } | null;
  };
  wallet: {
    id: number;
    diamonds: number;
    soft_currency: number;
  } | null;
  inventory: {
    tower_cards: Array<{
      id: number;
      code: string;
      level: number;
      quantity: number;
      is_equipped: boolean;
    }>;
    items: Array<{
      id: number;
      item_type: string;
      item_key: string;
      quantity: number;
      metadata: Record<string, unknown> | null;
    }>;
    boosts: Array<{
      id: number;
      code: string;
      quantity: number;
      expires_at: string | null;
    }>;
  };
  loadout: {
    id: number;
    slots: string[];
  } | null;
  endless_progress: EndlessRun | null;
};

export type EndlessRun = {
  client_run_id: string;
  highest_wave: number;
  defeated_enemies: number;
  lives_remaining: number;
  soft_currency_earned: number;
  created_at: string;
  updated_at: string;
};

export type EndlessCheckpointPayload = {
  run_id: string;
  wave: number;
  defeated_enemies: number;
  lives_remaining: number;
};

export type EndlessCheckpointResponse = {
  run: EndlessRun;
  checkpoint: {
    wave: number;
    defeated_enemies: number;
    lives_remaining: number;
    reward: number;
  };
  wallet: {
    id: number;
    diamonds: number;
    soft_currency: number;
  };
  idempotent: boolean;
};

export type CatalogProduct = {
  code: string;
  name: string;
  product_type: 'diamonds' | 'soft_currency' | 'boost' | 'tower_card' | 'item';
  grant_key: string | null;
  grant_quantity: number;
  price_minor: number;
  currency: string;
  metadata: Record<string, unknown> | null;
};

export type Order = {
  id: number;
  public_id: string;
  status: 'pending' | 'paid' | 'failed' | 'refunded';
  provider: string | null;
  total_minor: number;
  currency: string;
  paid_at: string | null;
  created_at: string;
  items: Array<{
    product_code: string;
    product_name: string;
    product_type: string;
    quantity: number;
    grant_quantity: number;
    unit_price_minor: number;
    currency: string;
  }>;
};

type ApiEnvelope<T> = {
  success: boolean;
  data: T;
  error?: {
    code: string;
    message: string;
    details: Record<string, string[]> | null;
  };
};

export async function getPlayerState(token: string): Promise<PlayerState> {
  const response = await fetch(API_BASE_URL + '/me', {
    headers: {
      Accept: 'application/json',
      Authorization: 'Bearer ' + token,
    },
  });
  const payload = await response.json() as ApiEnvelope<PlayerState>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Player state could not be loaded.');
  }

  return payload.data;
}

export async function getEndlessProgress(token: string): Promise<{
  run: EndlessRun | null;
  wallet: PlayerState['wallet'];
}> {
  const response = await fetch(API_BASE_URL + '/me/game/progress', {
    headers: {
      Accept: 'application/json',
      Authorization: 'Bearer ' + token,
    },
  });
  const payload = await response.json() as ApiEnvelope<{
    run: EndlessRun | null;
    wallet: PlayerState['wallet'];
  }>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Endless progress could not be loaded.');
  }

  return payload.data;
}

export async function syncEndlessCheckpoint(
  token: string,
  checkpoint: EndlessCheckpointPayload,
): Promise<EndlessCheckpointResponse> {
  const response = await fetch(API_BASE_URL + '/me/game/checkpoints', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify(checkpoint),
  });
  const payload = await response.json() as ApiEnvelope<EndlessCheckpointResponse>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Endless progress could not be saved.');
  }

  return payload.data;
}

export async function getCatalog(): Promise<CatalogProduct[]> {
  const response = await fetch(API_BASE_URL + '/catalog', {
    headers: { Accept: 'application/json' },
  });
  const payload = await response.json() as ApiEnvelope<{ products: CatalogProduct[] }>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Catalog could not be loaded.');
  }

  return payload.data.products;
}

export async function createOrder(
  token: string,
  productCode: string,
  idempotencyKey: string,
  quantity = 1,
): Promise<{ order: Order; idempotent: boolean; checkout: { provider: string; status: string } }> {
  const response = await fetch(API_BASE_URL + '/me/orders', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({
      product_code: productCode,
      idempotency_key: idempotencyKey,
      quantity,
    }),
  });
  const payload = await response.json() as ApiEnvelope<{
    order: Order;
    idempotent: boolean;
    checkout: { provider: string; status: string };
  }>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Order could not be created.');
  }

  return payload.data;
}

export async function sandboxPayOrder(token: string, orderId: number): Promise<Order> {
  const response = await fetch(API_BASE_URL + '/me/orders/' + orderId + '/sandbox-pay', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: 'Bearer ' + token,
    },
  });
  const payload = await response.json() as ApiEnvelope<{ order: Order }>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Sandbox payment could not be completed.');
  }

  return payload.data.order;
}

export async function upgradeTowerApi(token: string, code: string): Promise<PlayerState> {
  const response = await fetch(API_BASE_URL + '/me/towers/upgrade', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({ code }),
  });
  const payload = await response.json() as ApiEnvelope<PlayerState>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Tower upgrade failed.');
  }

  return payload.data;
}

export async function openChestApi(
  token: string,
  chestId: string,
): Promise<{
  player: PlayerState;
  loot: {
    coins: number;
    diamonds: number;
    card_code: string;
    boost_code: string;
    was_new?: boolean;
    new_level?: number;
  };
}> {
  const response = await fetch(API_BASE_URL + '/me/chests/open', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({ chest_id: chestId }),
  });
  const payload = await response.json() as ApiEnvelope<{
    player: PlayerState;
    loot: {
      coins: number;
      diamonds: number;
      card_code: string;
      boost_code: string;
      was_new?: boolean;
      new_level?: number;
    };
  }>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Chest opening failed.');
  }

  return payload.data;
}

export async function topupWalletApi(token: string, packageCode: string): Promise<PlayerState> {
  const response = await fetch(API_BASE_URL + '/me/wallet/topup', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({ package_code: packageCode }),
  });
  const payload = await response.json() as ApiEnvelope<PlayerState>;

  if (!response.ok || !payload.success) {
    throw new Error(payload.error?.message ?? 'Wallet top-up failed.');
  }

  return payload.data;
}
