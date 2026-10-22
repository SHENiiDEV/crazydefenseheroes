import { useEffect, useRef, useState } from 'react';
import { GameCanvas } from './GameCanvas';
import { PlayerState, CompanyInfo, GameRules, ChestOption, getGameRules, useBoostApi, upgradeTowerApi, openChestApi } from '../lib/api';
import { LegalModal, LegalDocType } from './components/LegalModal';
import { TowerTypeId } from './types';
import { TOWER_CONFIGS } from './entities/Tower';
import { ChestArt } from './components/ChestArt';
import { TreasuryPage } from './components/TreasuryPage';
import { TowerEmblem } from './components/TowerEmblem';

type Tab = 'battle' | 'vault' | 'arsenal' | 'treasury';


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
  company,
  initialTab = 'battle',
  onUpdatePlayer,
  onBackToLanding,
  onLogout,
}: {
  player: PlayerState;
  company: CompanyInfo;
  initialTab?: 'battle' | 'treasury';
  onUpdatePlayer: (updater: (prev: PlayerState) => PlayerState) => void;
  onBackToLanding: () => void;
  onLogout: () => void;
}) {
  const language: string = 'EN';
  const [activeTab, setActiveTab] = useState<Tab>(initialTab);
  const [isManualPaused, setIsManualPaused] = useState(false);
  const [isTheaterMode, setIsTheaterMode] = useState(false);
  const [selectedBuildType, setSelectedBuildType] = useState<TowerTypeId>('ember-archer');
  const [openingChest, setOpeningChest] = useState<string | null>(null);
  const [lootResult, setLootResult] = useState<LootResult | null>(null);
  const [feedbackNotice, setFeedbackNotice] = useState('');
  const [rules, setRules] = useState<GameRules | null>(null);
  const [rulesError, setRulesError] = useState('');
  const [actionBusy, setActionBusy] = useState(false);
  const actionLock = useRef(false);
  const [runKey, setRunKey] = useState(0);
  const [gameOverWave, setGameOverWave] = useState<number | null>(null);
  const [battleState, setBattleState] = useState({ lives: 20, enemies: 0, towers: 0 });
  const [syncStatus, setSyncStatus] = useState('Completed-wave rewards are saved to your account.');
  const [boostCommand, setBoostCommand] = useState<{ id: number; code: string } | null>(null);
  const [legalTab, setLegalTab] = useState<LegalDocType | null>(null);
  const ownedTowerTypes = player.inventory.tower_cards.filter((card) => card.quantity > 0).map((card) => card.code as TowerTypeId);
  useEffect(() => {
    getGameRules().then(setRules).catch((error: Error) => setRulesError(error.message));
  }, []);

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

  const requireToken = () => {
    const token = window.localStorage.getItem('cdh.token');
    if (!token) throw new Error('Please sign in again. No inventory changes have been made.');
    return token;
  };

  const handleOpenChest = async (chest: ChestOption) => {
    if (actionLock.current) return;
    actionLock.current = true;
    setActionBusy(true);
    setOpeningChest(chest.id);
    try {
      const res = await openChestApi(requireToken(), chest.id);
      onUpdatePlayer(() => res.player);
      setLootResult({
        chestId: chest.id, chestName: chest.name, coins: res.loot.coins, diamonds: res.loot.diamonds,
        cardName: chest.card_name + (res.loot.was_new ? ' unlocked (Level 1)' : ' upgraded to Level ' + res.loot.new_level),
        boost: res.loot.boost_quantity + ' × ' + chest.boost_name,
      });
    } catch (error) {
      showFeedback((error as Error).message);
    } finally {
      actionLock.current = false;
      setOpeningChest(null);
      setActionBusy(false);
    }
  };

  const handleUpgradeTower = async (code: TowerTypeId, currency: 'gold' | 'diamonds' = 'gold') => {
    if (actionLock.current) return;
    actionLock.current = true;
    setActionBusy(true);
    try {
      const updated = await upgradeTowerApi(requireToken(), code, currency);
      onUpdatePlayer(() => updated);
      showFeedback(TOWER_CONFIGS[code].name + ' upgraded. Account inventory saved.');
    } catch (error) {
      showFeedback((error as Error).message);
    } finally {
      actionLock.current = false;
      setActionBusy(false);
    }
  };

  const activateBoost = async (code: string) => {
    if (actionLock.current || gameOverWave !== null) return;
    actionLock.current = true;
    setActionBusy(true);
    try {
      const updated = await useBoostApi(requireToken(), code);
      onUpdatePlayer(() => updated);
      setBoostCommand({ id: Date.now(), code });
      showFeedback('One charge consumed. ' + (rules?.boosts[code]?.description ?? 'Boost activated.'));
    } catch (error) {
      showFeedback((error as Error).message);
    } finally {
      actionLock.current = false;
      setActionBusy(false);
    }
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
            onClick={() => setActiveTab('treasury')}
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

          <button className="button-topup-nav" onClick={() => setActiveTab('treasury')}>
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
        <button className={`arena-tab-btn ${activeTab === 'treasury' ? 'active' : ''}`} onClick={() => setActiveTab('treasury')}>
          <i className="fa-solid fa-coins" style={{ marginRight: 8, color: '#d6a64d' }} aria-hidden="true" /> Treasury & Top-Up
        </button>
      </nav>

      {/* Main Content Area */}
      <main className={`arena-main-content ${isTheaterMode && activeTab === 'battle' ? 'theater-mode' : ''}`}>
        {activeTab === 'treasury' && <TreasuryPage player={player} onUpdatePlayer={onUpdatePlayer} />}
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
                        disabled={!ownedTowerTypes.includes(t.type)}
                        title={!ownedTowerTypes.includes(t.type) ? 'Unlock in the Chest Vault first' : 'Deploy using battle Supply'}
                        onClick={() => setSelectedBuildType(t.type)}
                      >
                        <TowerEmblem type={t.color} mini />
                        <span className="tower-btn-text">
                          <b>
                            {cfg.name} <span className="tower-mini-lvl">LVL {lvl}</span>
                          </b>
                          <small>{cfg.cost} Supply</small>
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
                    : `Selected: ${TOWER_CONFIGS[selectedBuildType].name} (LVL ${towerLevels[selectedBuildType]}, ${TOWER_CONFIGS[selectedBuildType].cost} battle Supply). Click pale tile to build!`}
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
                key={runKey}
                paused={activeTab !== 'battle' || isManualPaused || actionBusy}
                selectedTowerType={selectedBuildType}
                towerLevels={towerLevels}
                ownedTowerTypes={ownedTowerTypes}
                accountUserId={player.user.id}
                onWalletUpdated={(wallet) => onUpdatePlayer((previous) => ({ ...previous, wallet }))}
                onSyncStatus={setSyncStatus}
                onGameOver={setGameOverWave}
                onBattleStateChange={setBattleState}
                boostCommand={boostCommand}
              />
            </div>
            <div className="battle-account-status"><p role="status">{syncStatus}</p>
              <p>Battle Supply is a temporary deployment budget. Account Gold is saved separately and is used in the Vault and Arsenal.</p>
              {gameOverWave !== null && <><p role="status">Run ended on wave {gameOverWave}. Survive longer next time; saved account items remain yours.</p>
                <button className="button button-orange" onClick={() => { setBoostCommand(null); setGameOverWave(null); setIsManualPaused(false); setRunKey((value) => value + 1); }}>Start a new run</button></>}
            </div>
            <div className="battle-boosts"><h3>Use your boosts</h3>{rules && Object.entries(rules.boosts).map(([code, boost]) => {
              const quantity = player.inventory.boosts.find((item) => item.code === code)?.quantity ?? 0;
              const notReady = (code === 'fortify' && battleState.lives >= 20) || (code === 'meteor-strike' && battleState.enemies === 0) || (code === 'speed-burst' && battleState.towers === 0);
              return <button key={code} type="button" title={boost.description} disabled={quantity < 1 || actionBusy || gameOverWave !== null || notReady} onClick={() => activateBoost(code)}>{boost.name} × {quantity}<small>{boost.description}{notReady ? ' Wait until this effect can be used.' : ''}</small></button>;
            })}</div>
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
                      onClick={() => { if (ownedTowerTypes.includes(t.type)) setSelectedBuildType(t.type); }}
                      role="button"
                      tabIndex={0}
                    >
                      <TowerEmblem type={t.color} mini />
                      <div className="deck-card-info">
                        <div className="deck-card-title-row">
                          <b>{cfg.name}</b>
                        <span className="deck-card-lvl-pill">{ownedTowerTypes.includes(t.type) ? `LVL ${lvl}` : 'LOCKED'}</span>
                        </div>
                        <small>{language === 'RU' ? t.roleRu : t.role}</small>
                        <span className="deck-card-stat">
                          {curDmg} dmg • {cfg.cost} Supply
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
                  : 'Open fixed-content chests to unlock towers, add card levels and receive usable boosts.'}
              </p>
              <div className="side-card-btns">
                <button className="button button-orange" onClick={() => setActiveTab('vault')}>
                  <i className="fa-solid fa-box-open" style={{ marginRight: 6 }} />
                  {language === 'RU' ? 'Открыть сундуки' : 'Open Chests'} <span>↗</span>
                </button>
                <button className="button button-dark" onClick={() => setActiveTab('treasury')}>
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
                  : 'Spend account currency on the exact card and boost charges shown below. Chests do not return Gold or Diamonds.'}
              </p>
            </div>

            <div className="chests-grid">
              {rulesError && <p role="alert">{rulesError}</p>}
              {!rules && !rulesError && <p role="status">Loading chest contents…</p>}
              {Object.values(rules?.chests ?? {}).map((chest) => (
                <div className={`chest-card chest-card-${chest.id}`} key={chest.id}>
                  <div className="chest-badge">
                    FIXED CONTENTS
                  </div>

                  {/* Landing page style ChestArt on glowing pedestal */}
                  <div className="chest-display arena-chest-display">
                    <div className="chest-pedestal" />
                    <ChestArt kind={chest.id} />
                  </div>

                  <h3>{chest.name}</h3>
                  <p className="chest-desc">
                    Unlock {chest.card_name}, or add one level if already owned. Includes {chest.boost_quantity} {chest.boost_name} charge(s).
                  </p>

                  <div className="loot-preview">
                    <b>Guaranteed contents — no random rewards</b>
                    <ul>
                      <li>1 {chest.card_name} card: unlock or +1 level</li>
                      <li>{chest.boost_quantity} × {chest.boost_name}</li>
                      <li>No Gold or Diamonds returned</li>
                    </ul>
                  </div>

                  <div className="chest-price-row">
                    <span className="price-tag">
                      {chest.cost_gold > 0 && (
                        <span>
                          {chest.cost_gold} Gold
                        </span>
                      )}
                      {chest.cost_diamonds > 0 && (
                        <span>
                          {chest.cost_gold > 0 ? ' + ' : ''}{chest.cost_diamonds} Diamonds
                        </span>
                      )}
                    </span>
                  </div>

                  <button
                    className="button button-orange chest-open-btn"
                    disabled={actionBusy}
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
                      disabled={actionBusy || !ownedTowerTypes.includes(tower.type)}
                      onClick={() => handleUpgradeTower(tower.type)}
                    >
                      <i className="fa-solid fa-arrow-up" style={{ marginRight: 6 }} />
                      {language === 'RU'
                        ? `Улучшить до LVL ${lvl + 1} (${cost})`
                        : `Upgrade to LVL ${lvl + 1} (${cost} Gold)`}
                    </button>
                    <button type="button" className="button button-dark roster-upgrade-action-btn" disabled={actionBusy || !ownedTowerTypes.includes(tower.type)} onClick={() => handleUpgradeTower(tower.type, 'diamonds')}>
                      {ownedTowerTypes.includes(tower.type) ? `Upgrade for ${lvl * (rules?.upgrade_diamonds_per_level ?? 5)} Diamonds` : 'Unlock this tower in the Chest Vault'}
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
              {lootResult.coins > 0 && <div className="reward-box">
                <span className="reward-val">
                  +{lootResult.coins} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d', fontSize: '0.85em' }} />
                </span>
                <small>{language === 'RU' ? 'Монеты' : 'Coins'}</small>
              </div>}
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

      <footer className="member-company-footer">
        <strong>{company.name}</strong><span>Company registration number: {company.number}</span><span>Registered address: {company.address}</span>
        <a href={`mailto:${company.email}`}>{company.email}</a><a href={`https://${company.domain}`}>{company.domain}</a>
        <nav aria-label="Member legal information">{(['terms', 'privacy', 'refunds', 'security'] as LegalDocType[]).map((tab) => <button key={tab} onClick={() => setLegalTab(tab)}>{({ terms: 'Terms & Conditions', privacy: 'Privacy Policy', refunds: 'Delivery & Refunds', security: 'Security' })[tab]}</button>)}</nav>
      </footer>
      {legalTab && <LegalModal initialTab={legalTab} company={company} onClose={() => setLegalTab(null)} />}

      {feedbackNotice && (
        <div className="toast" role="status">
          <span>✦</span>
          {feedbackNotice}
        </div>
      )}
    </div>
  );
}
