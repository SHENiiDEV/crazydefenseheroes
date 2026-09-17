import { useState } from 'react';
import { PlayerState, topupWalletApi } from '../../lib/api';

type TopUpModalProps = {
  player: PlayerState;
  language: 'EN' | 'RU';
  onClose: () => void;
  onUpdatePlayer: (updater: (prev: PlayerState) => PlayerState) => void;
  onSuccessNotice: (msg: string) => void;
};

type DiamondPack = {
  id: string;
  name: string;
  nameRu: string;
  diamonds: number;
  bonusText?: string;
  bonusTextRu?: string;
  price: string;
  badge?: string;
  isFreeDemo?: boolean;
};

const DIAMOND_PACKS: DiamondPack[] = [
  {
    id: 'free-demo-100',
    name: 'Sandbox Demo Refill',
    nameRu: 'Тестовое пополнение',
    diamonds: 100,
    bonusText: '+200 Soft Currency included',
    bonusTextRu: '+200 Монет в подарок',
    price: '0.00 € (FREE)',
    badge: 'FREE DEMO',
    isFreeDemo: true,
  },
  {
    id: 'diamonds-500',
    name: 'Pouch of Diamonds',
    nameRu: 'Мешок кристаллов',
    diamonds: 500,
    bonusText: 'Starter balance boost',
    bonusTextRu: 'Стартовый запас',
    price: '4.99 €',
  },
  {
    id: 'diamonds-1200',
    name: 'Chest of Diamonds',
    nameRu: 'Сундук кристаллов',
    diamonds: 1200,
    bonusText: '+20% Extra value',
    bonusTextRu: '+20% Больше выгоды',
    price: '9.99 €',
    badge: 'POPULAR',
  },
  {
    id: 'diamonds-2500',
    name: 'Royal Treasury of Diamonds',
    nameRu: 'Королевская сокровищница',
    diamonds: 2500,
    bonusText: '+35% Maximum value',
    bonusTextRu: '+35% Максимальный бонус',
    price: '19.99 €',
    badge: 'BEST VALUE',
  },
];

type CoinExchange = {
  id: string;
  coins: number;
  costDiamonds: number;
  name: string;
  nameRu: string;
};

const COIN_EXCHANGES: CoinExchange[] = [
  {
    id: 'exchange-coins-500',
    name: 'Pouch of Gold Coins',
    nameRu: 'Кошель золотых монет',
    coins: 500,
    costDiamonds: 25,
  },
  {
    id: 'exchange-coins-2000',
    name: 'Treasury Vault of Gold',
    nameRu: 'Хранилище золота',
    coins: 2000,
    costDiamonds: 80,
  },
];

export function TopUpModal({
  player,
  language,
  onClose,
  onUpdatePlayer,
  onSuccessNotice,
}: TopUpModalProps) {
  const [activeSubTab, setActiveSubTab] = useState<'diamonds' | 'coins'>('diamonds');
  const [loadingPkg, setLoadingPkg] = useState<string | null>(null);

  const handlePurchase = async (pkgId: string, diamondsGain: number, coinsGain = 0, costDiamonds = 0) => {
    const currentDiamonds = player.wallet?.diamonds ?? 0;
    if (costDiamonds > 0 && currentDiamonds < costDiamonds) {
      onSuccessNotice(
        language === 'RU'
          ? `Недостаточно кристаллов! Нужно ${costDiamonds}`
          : `Not enough diamonds! Need ${costDiamonds}`,
      );
      return;
    }

    setLoadingPkg(pkgId);

    const token = window.localStorage.getItem('cdh.token');
    if (token) {
      try {
        const updated = await topupWalletApi(token, pkgId);
        onUpdatePlayer(() => updated);
        onSuccessNotice(
          language === 'RU'
            ? `Баланс успешно пополнен: +${diamondsGain > 0 ? `${diamondsGain} кр. ` : ''}${coinsGain > 0 ? `+${coinsGain} монет` : ''}`
            : `Balance replenished: +${diamondsGain > 0 ? `${diamondsGain} diamonds ` : ''}${coinsGain > 0 ? `+${coinsGain} coins` : ''}`,
        );
        setLoadingPkg(null);
        return;
      } catch {
        // Fallback to local state if offline/sandbox
      }
    }

    // Local optimistic update
    onUpdatePlayer((prev) => {
      const oldWallet = prev.wallet ?? { id: 1, diamonds: 0, soft_currency: 0 };
      return {
        ...prev,
        wallet: {
          ...oldWallet,
          diamonds: Math.max(0, oldWallet.diamonds - costDiamonds + diamondsGain),
          soft_currency: oldWallet.soft_currency + coinsGain,
        },
      };
    });

    onSuccessNotice(
      language === 'RU'
        ? `Баланс успешно пополнен: +${diamondsGain > 0 ? `${diamondsGain} кр. ` : ''}${coinsGain > 0 ? `+${coinsGain} монет` : ''}`
        : `Balance replenished: +${diamondsGain > 0 ? `${diamondsGain} diamonds ` : ''}${coinsGain > 0 ? `+${coinsGain} coins` : ''}`,
    );
    setLoadingPkg(null);
  };

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(e) => e.target === e.currentTarget && onClose()}
    >
      <div className="modal-card topup-modal-card" role="dialog" aria-modal="true">
        <button className="topup-close-btn" onClick={onClose} aria-label="Close">
          <i className="fa-solid fa-xmark" />
        </button>

        <div className="topup-header">
          <span className="topup-badge"><i className="fa-solid fa-gem" style={{ marginRight: 5 }} /> THE ROYAL BANK & TREASURY <i className="fa-solid fa-gem" style={{ marginLeft: 5 }} /></span>
          <h2>{language === 'RU' ? 'Пополнение баланса' : 'Replenish Treasury'}</h2>
          <p className="topup-subtitle">
            {language === 'RU'
              ? 'Приобретайте кристаллы для покупки сундуков или обменивайте их на игровое золото.'
              : 'Acquire rare diamonds for legendary chest unboxings or exchange for fortress gold.'}
          </p>

          <div className="topup-current-balance">
            <span className="topup-bal-label">{language === 'RU' ? 'Текущий баланс:' : 'Current Balance:'}</span>
            <span className="topup-bal-item">
              <b>{player.wallet?.diamonds ?? 0}</b> <i className="fa-solid fa-gem" style={{ color: '#2980b9', marginLeft: 2 }} />
            </span>
            <span className="topup-bal-item">
              <b>{player.wallet?.soft_currency ?? 0}</b> <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', marginLeft: 2 }} />
            </span>
          </div>
        </div>

        <div className="topup-subnav">
          <button
            type="button"
            className={`topup-subnav-btn ${activeSubTab === 'diamonds' ? 'active' : ''}`}
            onClick={() => setActiveSubTab('diamonds')}
          >
            <i className="fa-solid fa-gem" style={{ marginRight: 6, color: '#2980b9' }} />
            {language === 'RU' ? 'Кристаллы (Diamonds)' : 'Diamond Packages'}
          </button>
          <button
            type="button"
            className={`topup-subnav-btn ${activeSubTab === 'coins' ? 'active' : ''}`}
            onClick={() => setActiveSubTab('coins')}
          >
            <i className="fa-solid fa-coins" style={{ marginRight: 6, color: '#d4ac0d' }} />
            {language === 'RU' ? 'Обмен на золото (Coins)' : 'Gold Coin Exchange'}
          </button>
        </div>

        {activeSubTab === 'diamonds' && (
          <div className="topup-grid">
            {DIAMOND_PACKS.map((pack) => (
              <div className={`topup-card ${pack.isFreeDemo ? 'topup-free-card' : ''}`} key={pack.id}>
                {pack.badge && (
                  <span className="topup-card-badge">
                    {pack.badge === 'POPULAR' && <i className="fa-solid fa-fire" style={{ marginRight: 3 }} />}
                    {pack.badge}
                  </span>
                )}
                <div className="topup-card-icon"><i className="fa-solid fa-gem" style={{ color: '#2980b9' }} /></div>
                <h3>+{pack.diamonds} <i className="fa-solid fa-gem" style={{ fontSize: '0.85em', color: '#2980b9' }} /></h3>
                <p className="topup-card-name">{language === 'RU' ? pack.nameRu : pack.name}</p>
                <small className="topup-card-bonus">
                  {language === 'RU' ? pack.bonusTextRu : pack.bonusText}
                </small>

                <button
                  type="button"
                  className={`button ${pack.isFreeDemo ? 'button-dark' : 'button-orange'} topup-buy-btn`}
                  disabled={loadingPkg !== null}
                  onClick={() =>
                    handlePurchase(
                      pack.id,
                      pack.diamonds,
                      pack.isFreeDemo ? 200 : 0,
                    )
                  }
                >
                  {loadingPkg === pack.id
                    ? (language === 'RU' ? 'Пополнение...' : 'Processing...')
                    : (pack.isFreeDemo
                        ? (language === 'RU' ? 'Получить бесплатно ↗' : 'Claim Free ↗')
                        : `${pack.price} ↗`)}
                </button>
              </div>
            ))}
          </div>
        )}

        {activeSubTab === 'coins' && (
          <div className="topup-grid topup-grid-coins">
            {COIN_EXCHANGES.map((exch) => (
              <div className="topup-card" key={exch.id}>
                <div className="topup-card-icon"><i className="fa-solid fa-coins" style={{ color: '#d4ac0d' }} /></div>
                <h3>+{exch.coins} <i className="fa-solid fa-coins" style={{ fontSize: '0.85em', color: '#d4ac0d' }} /></h3>
                <p className="topup-card-name">{language === 'RU' ? exch.nameRu : exch.name}</p>
                <small className="topup-card-bonus">
                  {language === 'RU' ? `Стоимость: ${exch.costDiamonds}` : `Cost: ${exch.costDiamonds}`} <i className="fa-solid fa-gem" style={{ color: '#2980b9' }} />
                </small>

                <button
                  type="button"
                  className="button button-orange topup-buy-btn"
                  disabled={loadingPkg !== null}
                  onClick={() => handlePurchase(exch.id, 0, exch.coins, exch.costDiamonds)}
                >
                  {loadingPkg === exch.id
                    ? (language === 'RU' ? 'Обмен...' : 'Exchanging...')
                    : (
                        language === 'RU' ? (
                          <>Обменять за {exch.costDiamonds} <i className="fa-solid fa-gem" style={{ fontSize: '0.85em', marginLeft: 2 }} /> ↗</>
                        ) : (
                          <>Exchange for {exch.costDiamonds} <i className="fa-solid fa-gem" style={{ fontSize: '0.85em', marginLeft: 2 }} /> ↗</>
                        )
                      )}
                </button>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
