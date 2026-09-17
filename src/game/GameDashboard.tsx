import { useState } from 'react';
import { GameCanvas } from './GameCanvas';
import { PlayerState, upgradeTowerApi, openChestApi } from '../lib/api';
import { TowerTypeId } from './types';
import { TOWER_CONFIGS } from './entities/Tower';
import { ChestArt } from './components/ChestArt';
import { TopUpModal } from './components/TopUpModal';
import { TowerEmblem } from './components/TowerEmblem';

type Tab = 'battle' | 'vault' | 'arsenal' | 'stats';

type ChestOption = {
  id: 'bronze' | 'silver' | 'gold';
  name: string;
  nameRu: string;
  costCoins: number;
  costDiamonds: number;
  badge: string;
  description: string;
  descriptionRu: string;
  color: string;
  possibleLoot: string[];
  possibleLootRu: string[];
};

const CHEST_OPTIONS: ChestOption[] = [
  {
    id: 'bronze',
    name: 'Bronze Stash',
    nameRu: 'Бронзовый сундук',
    costCoins: 50,
    costDiamonds: 0,
    badge: 'STARTER',
    description: 'A solid starter pack with essential tower upgrade cards and bonus coins.',
    descriptionRu: 'Базовый набор со стартовыми картами башен и бонусными монетами.',
    color: '#bc7853',
    possibleLoot: ['50-150 Soft Currency', '1x Ember Archer Card (+1 Lvl)', '1x Speed Boost'],
    possibleLootRu: ['50-150 Монет', '1x Карта Ember Archer (+1 Ур)', '1x Скоростной буст'],
  },
  {
    id: 'silver',
    name: 'Silver Cache',
    nameRu: 'Серебряный тайник',
    costCoins: 120,
    costDiamonds: 0,
    badge: 'TACTICAL',
    description: 'Tactical supply with high chance of rare tower cards and diamond rewards.',
    descriptionRu: 'Тактический набор с высоким шансом редких карт и кристаллами.',
    color: '#8f9a96',
    possibleLoot: ['150-300 Soft Currency', '5 Diamonds', '1x Stone Warden Card (+1 Lvl)', '2x Fortify Boost'],
    possibleLootRu: ['150-300 Монет', '5 Кристаллов', '1x Карта Stone Warden (+1 Ур)', '2x Буст защиты'],
  },
  {
    id: 'gold',
    name: 'Gold Legendary Chest',
    nameRu: 'Золотой легендарный сундук',
    costCoins: 250,
    costDiamonds: 10,
    badge: 'LEGENDARY',
    description: 'The ultimate royal vault. Guaranteed legendary drops, huge diamond sacks and cards.',
    descriptionRu: 'Королевская сокровищница. Гарантированный редкий дроп, кристаллы и карты.',
    color: '#d6a64d',
    possibleLoot: ['300-800 Soft Currency', '20 Diamonds', '1x Vault Mage Arcane Card (+1 Lvl)', '3x Meteor Charges'],
    possibleLootRu: ['300-800 Монет', '20 Кристаллов', '1x Карта Vault Mage (+1 Ур)', '3x Метеорных удара'],
  },
];

type RosterTowerInfo = {
  type: TowerTypeId;
  code: string;
  name: string;
  role: string;
  roleRu: string;
  copy: string;
  copyRu: string;
  color: 'ember' | 'stone' | 'mage';
};

const ROSTER_TOWERS: RosterTowerInfo[] = [
  {
    type: 'ember-archer',
    code: '01',
    name: 'EMBER ARCHER',
    role: 'FAST / FIRST',
    roleRu: 'СКОРОСТЬ / ПЕРВЫЙ',
    copy: 'Catches the scouts before they become a problem.',
    copyRu: 'Перехватывает лазутчиков до того, как они создадут проблемы.',
    color: 'ember',
  },
  {
    type: 'stone-warden',
    code: '02',
    name: 'STONE WARDEN',
    role: 'HEAVY / CLOSE',
    roleRu: 'ТЯЖЕЛЫЙ / БЛИЖНИЙ',
    copy: 'Holds the bend when the horde arrives in numbers.',
    copyRu: 'Держит поворот, когда орда наступает плотными рядами.',
    color: 'stone',
  },
  {
    type: 'vault-mage',
    code: '03',
    name: 'VAULT MAGE',
    role: 'ARCANE / STRONG',
    roleRu: 'МАГИЯ / СИЛЬНЫЙ',
    copy: 'Finds the strongest threat and makes it smaller.',
    copyRu: 'Находит самую грозную угрозу волны и уничтожает её.',
    color: 'mage',
  },
];

type LootResult = {
  chestId: 'bronze' | 'silver' | 'gold';
  chestName: string;
  coins: number;
  diamonds: number;
  cardName: string;
  boost: string;
};

export function GameDashboard({
  player,
  language,
  onUpdatePlayer,
  onBackToLanding,
  onLogout,
  onToggleLanguage,
}: {
  player: PlayerState;
  language: 'EN' | 'RU';
  onUpdatePlayer: (updater: (prev: PlayerState) => PlayerState) => void;
  onBackToLanding: () => void;
  onLogout: () => void;
  onToggleLanguage: () => void;
}) {
  const [activeTab, setActiveTab] = useState<Tab>('battle');
  const [isManualPaused, setIsManualPaused] = useState(false);
  const [isTheaterMode, setIsTheaterMode] = useState(false);
  const [selectedBuildType, setSelectedBuildType] = useState<TowerTypeId>('ember-archer');
  const [openingChest, setOpeningChest] = useState<string | null>(null);
  const [lootResult, setLootResult] = useState<LootResult | null>(null);
  const [feedbackNotice, setFeedbackNotice] = useState('');
  const [showTopUp, setShowTopUp] = useState(false);

  const showFeedback = (msg: string) => {
    setFeedbackNotice(msg);
    setTimeout(() => setFeedbackNotice(''), 3500);
  };

  // Extract dynamic levels for each tower
  const getTowerLevel = (code: TowerTypeId): number => {
    const card = player.inventory?.tower_cards?.find((c) => c.code === code);
    return card?.level ?? 1;
  };

  const towerLevels: Record<TowerTypeId, number> = {
    'ember-archer': getTowerLevel('ember-archer'),
    'stone-warden': getTowerLevel('stone-warden'),
    'vault-mage': getTowerLevel('vault-mage'),
  };

  const handleOpenChest = async (chest: ChestOption) => {
    const currentCoins = player.wallet?.soft_currency ?? 0;
    const currentDiamonds = player.wallet?.diamonds ?? 0;

    if (chest.costDiamonds > 0 && currentDiamonds < chest.costDiamonds) {
      showFeedback(
        language === 'RU'
          ? `Недостаточно кристаллов (нужно ${chest.costDiamonds})! Нажмите «+ Пополнить».`
          : `Need ${chest.costDiamonds} diamonds! Open Top-Up to refill.`,
      );
      return;
    }
    if (chest.costCoins > 0 && currentCoins < chest.costCoins) {
      showFeedback(
        language === 'RU'
          ? `Недостаточно монет (нужно ${chest.costCoins})! Пополните баланс в Казне.`
          : `Need ${chest.costCoins} coins! Refill coins in Top-Up.`,
      );
      return;
    }

    setOpeningChest(chest.id);

    const token = window.localStorage.getItem('cdh.token');
    if (token) {
      try {
        const res = await openChestApi(token, chest.id);
        setTimeout(() => {
          onUpdatePlayer(() => res.player);
          setOpeningChest(null);

          const cardCode = res.loot.card_code as TowerTypeId;
          const config = TOWER_CONFIGS[cardCode];
          const tName = config ? (language === 'RU' ? config.nameRu : config.name) : cardCode;
          const cardLevel = res.loot.new_level ?? res.player.inventory?.tower_cards?.find((c) => c.code === cardCode)?.level ?? 1;
          const cardLabel = res.loot.was_new
            ? `${tName} (${language === 'RU' ? 'Разблокирована! LVL ' + cardLevel : 'Unlocked! LVL ' + cardLevel})`
            : `${tName} (+1 Level → LVL ${cardLevel})`;

          setLootResult({
            chestId: chest.id,
            chestName: language === 'RU' ? chest.nameRu : chest.name,
            coins: res.loot.coins,
            diamonds: res.loot.diamonds,
            cardName: cardLabel,
            boost:
              res.loot.boost_code === 'speed-burst'
                ? (language === 'RU' ? 'Ускорение стрельбы ×1' : 'Speed Burst ×1')
                : res.loot.boost_code === 'fortify'
                  ? (language === 'RU' ? 'Усиление брони ×2' : 'Armor Fortify ×2')
                  : (language === 'RU' ? 'Метеоритный удар ×3' : 'Meteor Strike ×3'),
          });
        }, 800);
        return;
      } catch {
        // Fallback to local state
      }
    }

    // Local optimistic update
    setTimeout(() => {
      let coinsGained = 0;
      let diamondsGained = 0;
      let cardCode: TowerTypeId = 'ember-archer';
      let boost = '';

      if (chest.id === 'bronze') {
        coinsGained = Math.floor(Math.random() * 80) + 60;
        cardCode = 'ember-archer';
        boost = language === 'RU' ? 'Ускорение стрельбы ×1' : 'Speed Burst ×1';
      } else if (chest.id === 'silver') {
        coinsGained = Math.floor(Math.random() * 150) + 120;
        diamondsGained = 5;
        cardCode = 'stone-warden';
        boost = language === 'RU' ? 'Усиление брони ×2' : 'Armor Fortify ×2';
      } else {
        coinsGained = Math.floor(Math.random() * 350) + 250;
        diamondsGained = 20;
        cardCode = 'vault-mage';
        boost = language === 'RU' ? 'Метеоритный удар ×3' : 'Meteor Strike ×3';
      }

      const existingCards = player.inventory?.tower_cards ?? [];
      const hasCard = existingCards.some((c) => c.code === cardCode);
      const existingLevel = existingCards.find((c) => c.code === cardCode)?.level ?? 0;
      const nextLevel = hasCard ? existingLevel + 1 : 1;

      const config = TOWER_CONFIGS[cardCode];
      const tName = config ? (language === 'RU' ? config.nameRu : config.name) : cardCode;
      const cardName = hasCard
        ? `${tName} (+1 Level → LVL ${nextLevel})`
        : `${tName} (${language === 'RU' ? 'Разблокирована! LVL 1' : 'Unlocked! LVL 1'})`;

      onUpdatePlayer((prev) => {
        const oldWallet = prev.wallet ?? { id: 1, diamonds: 0, soft_currency: 0 };
        const prevCards = prev.inventory?.tower_cards ?? [];
        const found = prevCards.some((c) => c.code === cardCode);
        const updatedCards = found
          ? prevCards.map((c) => (c.code === cardCode ? { ...c, level: c.level + 1, quantity: c.quantity + 1 } : c))
          : [...prevCards, { id: Date.now(), code: cardCode, level: 1, quantity: 1, is_equipped: true }];

        const oldSlots = prev.loadout?.slots ?? ['ember-archer'];
        const updatedSlots = oldSlots.includes(cardCode) ? oldSlots : [...oldSlots, cardCode];

        return {
          ...prev,
          wallet: {
            ...oldWallet,
            soft_currency: Math.max(0, oldWallet.soft_currency - chest.costCoins + coinsGained),
            diamonds: Math.max(0, oldWallet.diamonds - chest.costDiamonds + diamondsGained),
          },
          inventory: {
            ...prev.inventory,
            tower_cards: updatedCards,
          },
          loadout: {
            id: prev.loadout?.id ?? 1,
            slots: updatedSlots,
          },
        };
      });

      setOpeningChest(null);
      setLootResult({
        chestId: chest.id,
        chestName: language === 'RU' ? chest.nameRu : chest.name,
        coins: coinsGained,
        diamonds: diamondsGained,
        cardName,
        boost,
      });
    }, 800);
  };

  const handleUpgradeTower = async (code: TowerTypeId) => {
    const currentLevel = getTowerLevel(code);
    const cost = currentLevel * 80;
    const currentCoins = player.wallet?.soft_currency ?? 0;
    if (currentCoins < cost) {
      showFeedback(
        language === 'RU'
          ? `Недостаточно монет для улучшения (нужно ${cost})! Пополните баланс в Казне.`
          : `Need ${cost} coins to upgrade tower! Refill coins in Top-Up.`,
      );
      return;
    }

    const token = window.localStorage.getItem('cdh.token');
    if (token) {
      try {
        const updated = await upgradeTowerApi(token, code);
        onUpdatePlayer(() => updated);
        showFeedback(
          language === 'RU'
            ? `Башня «${TOWER_CONFIGS[code].nameRu}» успешно улучшена до LVL ${currentLevel + 1}! (+25% урона, +8% скорости)`
            : `Tower «${TOWER_CONFIGS[code].name}» upgraded to LVL ${currentLevel + 1}! (+25% damage, +8% speed)`,
        );
        return;
      } catch {
        // Fallback to local state
      }
    }

    // Local optimistic update
    onUpdatePlayer((prev) => {
      const oldWallet = prev.wallet ?? { id: 1, diamonds: 0, soft_currency: 0 };
      const existingCards = prev.inventory.tower_cards ?? [];
      const hasCard = existingCards.some((c) => c.code === code);
      const cards = hasCard
        ? existingCards.map((card) =>
            card.code === code ? { ...card, level: card.level + 1 } : card,
          )
        : [
            ...existingCards,
            { id: Date.now(), code, level: 2, quantity: 1, is_equipped: true },
          ];

      return {
        ...prev,
        wallet: {
          ...oldWallet,
          soft_currency: Math.max(0, oldWallet.soft_currency - cost),
        },
        inventory: {
          ...prev.inventory,
          tower_cards: cards,
        },
      };
    });

    showFeedback(
      language === 'RU'
        ? `Башня «${TOWER_CONFIGS[code].nameRu}» успешно улучшена до LVL ${currentLevel + 1}! (+25% урона, +8% скорости)`
        : `Tower «${TOWER_CONFIGS[code].name}» upgraded to LVL ${currentLevel + 1}! (+25% damage, +8% speed)`,
    );
  };

  return (
    <div className="game-arena-root">
      {/* Top Game Navigation Bar */}
      <header className="arena-topbar">
        <div className="arena-brand-group">
          <button className="button-back-realm" onClick={onBackToLanding}>
            ← {language === 'RU' ? 'На главную' : 'Back to Realm'}
          </button>
          <div className="arena-title">
            <span className="arena-badge">ARENA</span>
            <h2>Crazy Defense Hereoes</h2>
          </div>
        </div>

        <div className="arena-user-status">
          <div
            className="arena-wallet-badge clickable-wallet-badge"
            onClick={() => setShowTopUp(true)}
            title={language === 'RU' ? 'Нажмите для пополнения баланса' : 'Click to refill balance'}
          >
            <span className="wallet-item">
              <span className="wallet-val">{player.wallet?.diamonds ?? 0}</span>
              <span className="wallet-icon"><i className="fa-solid fa-gem" style={{ color: '#2980b9' }} /></span>
            </span>
            <span className="wallet-divider" />
            <span className="wallet-item">
              <span className="wallet-val">{player.wallet?.soft_currency ?? 0}</span>
              <span className="wallet-icon"><i className="fa-solid fa-coins" style={{ color: '#d4ac0d' }} /></span>
            </span>
            <span className="wallet-plus-btn">+</span>
          </div>

          <button className="button-topup-nav" onClick={() => setShowTopUp(true)}>
            <i className="fa-solid fa-gem" style={{ marginRight: 6, color: '#2980b9' }} /> {language === 'RU' ? 'Пополнить' : 'Top-Up'}
          </button>

          <div className="arena-player-card">
            <span className="player-avatar"><i className="fa-solid fa-crown" style={{ fontSize: '0.85em', color: '#f39c12' }} /></span>
            <div className="player-details">
              <span className="player-name">
                {player.user.name} {player.user.surname}
              </span>
              <span className="player-meta">{player.user.email}</span>
            </div>
          </div>

          <button className="language-button arena-lang-btn" onClick={onToggleLanguage}>
            {language} <i className="fa-solid fa-chevron-down" style={{ fontSize: '0.75em', marginLeft: 4 }} />
          </button>

          <button className="button-logout" onClick={onLogout}>
            <i className="fa-solid fa-arrow-right-from-bracket" style={{ marginRight: 6 }} />
            {language === 'RU' ? 'Выйти' : 'Sign Out'}
          </button>
        </div>
      </header>

      {/* Arena Tab Selector */}
      <nav className="arena-tabs-nav">
        <button
          className={`arena-tab-btn ${activeTab === 'battle' ? 'active' : ''}`}
          onClick={() => setActiveTab('battle')}
        >
          <i className="fa-solid fa-crosshairs" style={{ marginRight: 8, color: '#e74c3c' }} />
          {language === 'RU' ? 'Игровая арена' : 'Battle Arena'}
        </button>
        <button
          className={`arena-tab-btn ${activeTab === 'vault' ? 'active' : ''}`}
          onClick={() => setActiveTab('vault')}
        >
          <i className="fa-solid fa-box-open" style={{ marginRight: 8, color: '#f39c12' }} />
          {language === 'RU' ? 'Кейсы и сундуки' : 'Chest Vault'}
        </button>
        <button
          className={`arena-tab-btn ${activeTab === 'arsenal' ? 'active' : ''}`}
          onClick={() => setActiveTab('arsenal')}
        >
          <i className="fa-solid fa-shield-halved" style={{ marginRight: 8, color: '#3498db' }} />
          {language === 'RU' ? 'Башни и арсенал' : 'Towers & Arsenal'}
        </button>
      </nav>

      {/* Main Content Area */}
      <main className={`arena-main-content ${isTheaterMode ? 'theater-mode' : ''}`}>
        <section className={`battle-layout ${activeTab !== 'battle' ? 'tab-panel-hidden' : ''}`}>
          <div className="battle-canvas-container">
            <div className="battle-hud-header">
              <div className="hud-title">
                <span className="live-indicator">
                  {activeTab === 'battle' && !isManualPaused ? (
                    <><i className="fa-solid fa-circle" style={{ fontSize: '0.65em', marginRight: 4, color: '#2ecc71' }} /> LIVE</>
                  ) : (
                    <><i className="fa-solid fa-pause" style={{ marginRight: 4 }} /> PAUSED</>
                  )}
                </span>
                <h3>{language === 'RU' ? 'Оборона крепости' : 'Endless Fortress Defense'}</h3>
                <div className="hud-actions-group">
                  <button
                    type="button"
                    className="button-hud-control button-theater-toggle"
                    onClick={() => setIsTheaterMode((m) => !m)}
                    title={language === 'RU' ? 'Переключить размер экрана' : 'Toggle Screen Size'}
                  >
                    {isTheaterMode ? (
                      <><i className="fa-solid fa-compress" style={{ marginRight: 5 }} /> {language === 'RU' ? 'Обычный' : 'Standard'}</>
                    ) : (
                      <><i className="fa-solid fa-expand" style={{ marginRight: 5 }} /> {language === 'RU' ? 'Широкий экран' : 'Wide View'}</>
                    )}
                  </button>
                  <button
                    type="button"
                    className="button-hud-control button-pause-battle"
                    onClick={() => setIsManualPaused((p) => !p)}
                  >
                    {isManualPaused ? (
                      <><i className="fa-solid fa-play" style={{ marginRight: 5 }} /> {language === 'RU' ? 'Продолжить' : 'Resume'}</>
                    ) : (
                      <><i className="fa-solid fa-pause" style={{ marginRight: 5 }} /> {language === 'RU' ? 'Пауза' : 'Pause'}</>
                    )}
                  </button>
                </div>
              </div>

              {/* Tower Selection Bar */}
              <div className="tower-selector-bar">
                <span className="tower-selector-label">
                  {language === 'RU' ? 'Строить башню:' : 'Build Tower:'}
                </span>
                <div className="tower-selector-buttons">
                  {ROSTER_TOWERS.map((t) => {
                    const lvl = towerLevels[t.type];
                    const cfg = TOWER_CONFIGS[t.type];
                    const isActive = selectedBuildType === t.type;
                    return (
                      <button
                        key={t.type}
                        type="button"
                        className={`tower-select-btn tower-btn-${t.color} ${isActive ? 'active-tower' : ''}`}
                        onClick={() => setSelectedBuildType(t.type)}
                      >
                        <TowerEmblem type={t.color} mini />
                        <span className="tower-btn-text">
                          <b>
                            {cfg.name} <span className="tower-mini-lvl">LVL {lvl}</span>
                          </b>
                          <small>{cfg.cost} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', fontSize: '0.85em' }} /></small>
                        </span>
                        {isActive && <span className="tower-btn-badge">✓</span>}
                      </button>
                    );
                  })}
                </div>
              </div>

              <div className="hud-tips">
                <span>
                  <i className="fa-regular fa-lightbulb" style={{ color: '#f39c12', marginRight: 5 }} />
                  {language === 'RU'
                    ? `Выбрано: ${TOWER_CONFIGS[selectedBuildType].nameRu} (LVL ${towerLevels[selectedBuildType]}, ${TOWER_CONFIGS[selectedBuildType].cost} монет). Клик по светлой клетке строит башню!`
                    : `Selected: ${TOWER_CONFIGS[selectedBuildType].name} (LVL ${towerLevels[selectedBuildType]}, ${TOWER_CONFIGS[selectedBuildType].cost} coins). Click pale tile to build!`}
                </span>
                <span>
                  <i className="fa-solid fa-crosshairs" style={{ color: '#3498db', marginRight: 5 }} />
                  {language === 'RU'
                    ? 'Клик по башне: смена режима цели (First / Strong / Close)'
                    : 'Click tower: cycle target mode'}
                </span>
              </div>
            </div>

            <div className="game-canvas-wrapper">
              <GameCanvas
                paused={activeTab !== 'battle' || isManualPaused}
                selectedTowerType={selectedBuildType}
                towerLevels={towerLevels}
              />
            </div>
          </div>

          {/* Side deck & live info */}
          <aside className="battle-sidebar">
            <div className="side-card active-loadout-box">
              <h4>{language === 'RU' ? 'АКТИВНАЯ КОЛОДА БАШЕН' : 'ACTIVE TOWER DECK'}</h4>
              <p className="sidebar-hint">
                {language === 'RU'
                  ? 'Нажмите на карту башни для выбора перед стройкой:'
                  : 'Click a card to select which tower to build:'}
              </p>
              <div className="loadout-cards-list">
                {ROSTER_TOWERS.map((t) => {
                  const lvl = towerLevels[t.type];
                  const cfg = TOWER_CONFIGS[t.type];
                  const isActive = selectedBuildType === t.type;
                  const curDmg = Math.round(cfg.damage * (1 + (lvl - 1) * 0.25));

                  return (
                    <div
                      key={t.type}
                      className={`deck-card deck-card-${t.color} clickable-deck-card ${isActive ? 'selected-deck-card' : ''}`}
                      onClick={() => setSelectedBuildType(t.type)}
                      role="button"
                      tabIndex={0}
                    >
                      <TowerEmblem type={t.color} mini />
                      <div className="deck-card-info">
                        <div className="deck-card-title-row">
                          <b>{cfg.name}</b>
                          <span className="deck-card-lvl-pill">LVL {lvl}</span>
                        </div>
                        <small>{language === 'RU' ? t.roleRu : t.role}</small>
                        <span className="deck-card-stat">
                          {curDmg} dmg • {cfg.cost} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', fontSize: '0.85em' }} />
                        </span>
                      </div>
                      <span className="deck-card-tag">
                        {isActive
                          ? language === 'RU'
                            ? 'АКТИВНА ✓'
                            : 'ACTIVE ✓'
                          : language === 'RU'
                            ? 'ВЫБРАТЬ'
                            : 'SELECT'}
                      </span>
                    </div>
                  );
                })}
              </div>
            </div>

            <div className="side-card quick-chests-promo">
              <h4>{language === 'RU' ? 'НУЖНО БОЛЬШЕ СИЛЫ?' : 'NEED MORE FIREPOWER?'}</h4>
              <p>
                {language === 'RU'
                  ? 'Открывайте сундуки в сокровищнице, чтобы повышать уровни башен и пополнять кристаллы.'
                  : 'Open chests in the Vault to unlock card levels, boosts and currency.'}
              </p>
              <div className="side-card-btns">
                <button className="button button-orange" onClick={() => setActiveTab('vault')}>
                  <i className="fa-solid fa-box-open" style={{ marginRight: 6 }} />
                  {language === 'RU' ? 'Открыть сундуки' : 'Open Chests'} <span>↗</span>
                </button>
                <button className="button button-dark" onClick={() => setShowTopUp(true)}>
                  <i className="fa-solid fa-gem" style={{ marginRight: 6, color: '#2980b9' }} /> {language === 'RU' ? 'Пополнить баланс' : 'Top-Up'}
                </button>
              </div>
            </div>
          </aside>
        </section>

        {activeTab === 'vault' && (
          <section className="vault-layout">
            <div className="vault-header-text">
              <h2>{language === 'RU' ? 'Королевская сокровищница' : 'The Royal Chest Vault'}</h2>
              <p>
                {language === 'RU'
                  ? 'Открывайте сундуки, получайте золото, редкие кристаллы и повышайте уровень своих боевых башен.'
                  : 'Unlock tactical and legendary chests to claim card upgrades, boosts, and riches.'}
              </p>
            </div>

            <div className="chests-grid">
              {CHEST_OPTIONS.map((chest) => (
                <div className={`chest-card chest-card-${chest.id}`} key={chest.id}>
                  <div className="chest-badge" style={{ backgroundColor: chest.color }}>
                    {chest.badge}
                  </div>

                  {/* Landing page style ChestArt on glowing pedestal */}
                  <div className="chest-display arena-chest-display">
                    <div className="chest-pedestal" />
                    <ChestArt kind={chest.id} />
                  </div>

                  <h3>{language === 'RU' ? chest.nameRu : chest.name}</h3>
                  <p className="chest-desc">
                    {language === 'RU' ? chest.descriptionRu : chest.description}
                  </p>

                  <div className="loot-preview">
                    <b>{language === 'RU' ? 'Возможная добыча:' : 'Potential Drops:'}</b>
                    <ul>
                      {(language === 'RU' ? chest.possibleLootRu : chest.possibleLoot).map(
                        (item, idx) => (
                          <li key={idx}>✦ {item}</li>
                        ),
                      )}
                    </ul>
                  </div>

                  <div className="chest-price-row">
                    <span className="price-tag">
                      {chest.costCoins > 0 && (
                        <span>
                          {chest.costCoins} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', fontSize: '0.85em', marginRight: 4 }} />
                        </span>
                      )}
                      {chest.costDiamonds > 0 && (
                        <span>
                          {chest.costDiamonds} <i className="fa-solid fa-gem" style={{ color: '#2980b9', fontSize: '0.85em' }} />
                        </span>
                      )}
                    </span>
                  </div>

                  <button
                    className="button button-orange chest-open-btn"
                    disabled={openingChest !== null}
                    onClick={() => handleOpenChest(chest)}
                  >
                    {openingChest === chest.id
                      ? language === 'RU'
                        ? 'Открытие...'
                        : 'Opening...'
                      : language === 'RU'
                        ? 'Открыть сундук ↗'
                        : 'Unlock Chest ↗'}
                  </button>
                </div>
              ))}
            </div>
          </section>
        )}

        {activeTab === 'arsenal' && (
          <section className="arsenal-layout">
            <div className="vault-header-text">
              <h2>{language === 'RU' ? 'Арсенал башен и карты' : 'Towers & Card Arsenal'}</h2>
              <p>
                {language === 'RU'
                  ? 'Улучшайте башни за монеты. Каждый уровень увеличивает урон на +25%, скорострельность на +8% и дальность на +5%!'
                  : 'Upgrade your unlocked towers with soft currency. Each level grants +25% damage, +8% fire rate, and +5% range in battle!'}
              </p>
            </div>

            <div className="roster-grid">
              {ROSTER_TOWERS.map((tower) => {
                const lvl = towerLevels[tower.type];
                const config = TOWER_CONFIGS[tower.type];
                const curDmg = Math.round(config.damage * (1 + (lvl - 1) * 0.25));
                const nextDmg = Math.round(config.damage * (1 + lvl * 0.25));
                const curSpeed = (1000 / (config.fireRate / (1 + (lvl - 1) * 0.08))).toFixed(1);
                const nextSpeed = (1000 / (config.fireRate / (1 + lvl * 0.08))).toFixed(1);
                const curRange = Math.round(config.range * (1 + (lvl - 1) * 0.05));
                const cost = lvl * 80;

                return (
                  <article className={`roster-card roster-${tower.color} roster-arsenal-card`} key={tower.type}>
                    <div className="roster-card-topline">
                      <span>{tower.code}</span>
                      <div className="roster-topline-right">
                        <span className="roster-lvl-badge">LVL {lvl}</span>
                        <span className="roster-role-text">{language === 'RU' ? tower.roleRu : tower.role}</span>
                      </div>
                    </div>

                    <TowerEmblem type={tower.color} />

                    <h3>{tower.name}</h3>
                    <p>{language === 'RU' ? tower.copyRu : tower.copy}</p>

                    <div className="roster-stats-compact">
                      <div className="roster-stat-item">
                        <span>{language === 'RU' ? 'Урон' : 'Damage'}:</span>
                        <b>
                          {curDmg} dmg <small className="stat-next-pill">➔ {nextDmg} (+{nextDmg - curDmg})</small>
                        </b>
                      </div>
                      <div className="roster-stat-item">
                        <span>{language === 'RU' ? 'Скорострельность' : 'Fire rate'}:</span>
                        <b>
                          {curSpeed} / s <small className="stat-next-pill">➔ {nextSpeed}</small>
                        </b>
                      </div>
                      <div className="roster-stat-item">
                        <span>{language === 'RU' ? 'Радиус атаки' : 'Attack Range'}:</span>
                        <b>{curRange} px</b>
                      </div>
                    </div>

                    <div className="roster-card-footer">
                      <span>
                        {tower.type === 'ember-archer' && `${curSpeed} fire rate`}
                        {tower.type === 'stone-warden' && `${curRange} range`}
                        {tower.type === 'vault-mage' && `${curDmg} impact`}
                      </span>
                      <span className="card-arrow">↗</span>
                    </div>

                    <button
                      type="button"
                      className="button button-orange roster-upgrade-action-btn"
                      onClick={() => handleUpgradeTower(tower.type)}
                    >
                      <i className="fa-solid fa-arrow-up" style={{ marginRight: 6 }} />
                      {language === 'RU'
                        ? `Улучшить до LVL ${lvl + 1} (${cost})`
                        : `Upgrade to LVL ${lvl + 1} (${cost})`} <i className="fa-solid fa-coins" style={{ marginLeft: 4, color: '#d4ac0d' }} />
                    </button>
                  </article>
                );
              })}
            </div>
          </section>
        )}
      </main>

      {/* Loot Unbox Modal */}
      {lootResult && (
        <div
          className="modal-backdrop"
          role="presentation"
          onMouseDown={(e) => e.target === e.currentTarget && setLootResult(null)}
        >
          <div className="modal-card loot-modal-card" role="dialog">
            <span className="loot-sparkles">
              <i className="fa-solid fa-star" style={{ color: '#f1c40f', marginRight: 4 }} />
              <i className="fa-solid fa-star" style={{ color: '#f39c12', marginRight: 4 }} />
              <i className="fa-solid fa-star" style={{ color: '#e67e22' }} />
            </span>
            <p className="eyebrow">{language === 'RU' ? 'СОКРОВИЩЕ ОТКРЫТО' : 'CHEST UNBOXED'}</p>
            <h2>{lootResult.chestName}</h2>

            <div className="chest-display modal-chest-display">
              <div className="chest-pedestal" />
              <ChestArt kind={lootResult.chestId} large />
            </div>

            <div className="loot-rewards-grid">
              <div className="reward-box">
                <span className="reward-val">
                  +{lootResult.coins} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', fontSize: '0.85em' }} />
                </span>
                <small>{language === 'RU' ? 'Монеты' : 'Coins'}</small>
              </div>
              {lootResult.diamonds > 0 && (
                <div className="reward-box">
                  <span className="reward-val">
                    +{lootResult.diamonds} <i className="fa-solid fa-gem" style={{ color: '#2980b9', fontSize: '0.85em' }} />
                  </span>
                  <small>{language === 'RU' ? 'Кристаллы' : 'Diamonds'}</small>
                </div>
              )}
              <div className="reward-box full-width-reward">
                <span className="reward-val">
                  <i className="fa-solid fa-shield-halved" style={{ color: '#3498db', marginRight: 6 }} />
                  {lootResult.cardName}
                </span>
                <small>{language === 'RU' ? 'Улучшение башни' : 'Tower Card Upgrade'}</small>
              </div>
              <div className="reward-box full-width-reward">
                <span className="reward-val">
                  <i className="fa-solid fa-bolt" style={{ color: '#f1c40f', marginRight: 6 }} />
                  {lootResult.boost}
                </span>
                <small>{language === 'RU' ? 'Боевой буст' : 'Tactical Boost'}</small>
              </div>
            </div>
            <button className="button button-orange" onClick={() => setLootResult(null)}>
              {language === 'RU' ? 'Забрать награду ↗' : 'Claim Rewards ↗'}
            </button>
          </div>
        </div>
      )}

      {/* Top Up Modal */}
      {showTopUp && (
        <TopUpModal
          player={player}
          language={language}
          onClose={() => setShowTopUp(false)}
          onUpdatePlayer={onUpdatePlayer}
          onSuccessNotice={showFeedback}
        />
      )}

      {feedbackNotice && (
        <div className="toast" role="status">
          <span>✦</span>
          {feedbackNotice}
        </div>
      )}
    </div>
  );
}
