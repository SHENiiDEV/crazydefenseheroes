import { FormEvent, useEffect, useState } from 'react';
import { GameCanvas } from './game/GameCanvas';
import { GameDashboard } from './game/GameDashboard';
import { getPlayerState, PlayerState } from './lib/api';
import { LegalModal, LegalDocType } from './game/components/LegalModal';
import { TopUpModal } from './game/components/TopUpModal';

type ChestId = 'silver' | 'gold' | 'bronze';
type AuthTab = 'register' | 'login';
type ApiErrorDetails = Record<string, string[]>;

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? '/api/v1';
const LEGAL_BASE_URL = API_BASE_URL.replace(/\/api\/v1\/?$/, '') || (typeof window !== 'undefined' ? window.location.origin : '');

type Chest = {
  id: ChestId;
  name: string;
  eyebrow: string;
  description: string;
  loot: string;
  color: string;
};

const roster = [
  { code: '01', name: 'Ember Archer', role: 'FAST / FIRST', copy: 'Catches the scouts before they become a problem.', color: 'ember', stat: '8.4 fire rate' },
  { code: '02', name: 'Stone Warden', role: 'HEAVY / CLOSE', copy: 'Holds the bend when the horde arrives in numbers.', color: 'stone', stat: '145 range' },
  { code: '03', name: 'Vault Mage', role: 'ARCANE / STRONG', copy: 'Finds the strongest threat and makes it smaller.', color: 'mage', stat: '24 impact' },
];

const watchPhases = [
  { label: 'SCOUT', title: 'Read the route.', copy: 'The road is fixed. Your answer never has to be.' },
  { label: 'BUILD', title: 'Spend with intent.', copy: 'Place a tower, watch its range, and make every tile count.' },
  { label: 'ADAPT', title: 'Stay for the next wave.', copy: 'The horde scales up. Your loadout learns with it.' },
];

const chests: Chest[] = [
  {
    id: 'silver',
    name: 'Silver cache',
    eyebrow: 'TACTICAL START',
    description: 'A fast route into the defense. Grab reliable common cards and build your first line.',
    loot: '3 common tower cards',
    color: 'silver',
  },
  {
    id: 'gold',
    name: 'Gold chest',
    eyebrow: 'LEGENDARY DROP',
    description: 'Three tower game cards, ranging from Common to Legendary. Every wave deserves a little chaos.',
    loot: '3 tower cards · Common → Legendary',
    color: 'gold',
  },
  {
    id: 'bronze',
    name: 'Bronze stash',
    eyebrow: 'BRAVE BEGINNINGS',
    description: 'A sturdy starter stash with the gear you need to turn a quiet hill into a fortress.',
    loot: '3 starter tower cards',
    color: 'bronze',
  },
];

const fallbackCountries = [
  'Albania', 'Algeria', 'Andorra', 'Argentina', 'Armenia', 'Australia', 'Austria', 'Belgium', 'Belize', 'Brazil',
  'Bulgaria', 'Canada', 'Chile', 'China', 'Colombia', 'Costa Rica', 'Croatia', 'Cyprus', 'Czechia', 'Denmark',
  'Dominican Republic', 'Ecuador', 'Egypt', 'Estonia', 'Finland', 'France', 'Georgia', 'Germany', 'Ghana', 'Greece',
  'Hungary', 'Iceland', 'India', 'Indonesia', 'Ireland', 'Israel', 'Italy', 'Japan', 'Jordan', 'Kazakhstan', 'Kenya',
  'Latvia', 'Lebanon', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Malaysia', 'Malta', 'Mexico', 'Monaco', 'Mongolia',
  'Montenegro', 'Morocco', 'Nepal', 'Netherlands', 'New Zealand', 'Nigeria', 'North Macedonia', 'Norway', 'Pakistan',
  'Panama', 'Peru', 'Philippines', 'Poland', 'Portugal', 'Qatar', 'Romania', 'San Marino', 'Saudi Arabia', 'Serbia',
  'Singapore', 'Slovakia', 'Slovenia', 'South Africa', 'South Korea', 'Spain', 'Sweden', 'Switzerland', 'Thailand',
  'Tunisia', 'Türkiye', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Vatican City',
  'Vietnam', 'Zambia',
];

const legalCopy = {
  terms: 'By playing Crazy Defense Hereoes, you agree to use the service lawfully, keep your account secure, and treat other players fairly. Purchases are made by the account holder and digital items have no cash value outside the service.',
  privacy: 'We use the details you provide to create your account, deliver receipts, protect the game, and answer support requests. We do not sell personal information.',
};

function ShieldMark() {
  return (
    <span className="shield-mark" aria-hidden="true">
      <span className="shield-cut" />
      <span className="shield-crown" />
    </span>
  );
}

function ChestArt({ kind, large = false }: { kind: ChestId; large?: boolean }) {
  return (
    <div className={`chest-art chest-${kind} ${large ? 'chest-art-large' : ''}`} aria-hidden="true">
      <div className="chest-glint glint-one" />
      <div className="chest-glint glint-two" />
      <div className="chest-lid"><span className="lid-rivet" /></div>
      <div className="chest-body">
        <span className="chest-band band-top" />
        <span className="chest-band band-bottom" />
        <span className="chest-lock"><i /></span>
        <span className="chest-gem gem-left" />
        <span className="chest-gem gem-right" />
      </div>
      <span className="chest-foot foot-left" />
      <span className="chest-foot foot-right" />
    </div>
  );
}

function Figure({ side, type }: { side: 'left' | 'right'; type: 'ranger' | 'mage' }) {
  return (
    <div className={`figure figure-${side} figure-${type}`} aria-hidden="true">
      <div className="figure-glow" />
      <div className="figure-head"><span className="figure-hair" /></div>
      <div className="figure-body"><span className="figure-collar" /><span className="figure-gem" /></div>
      <div className="figure-prop"><span /></div>
    </div>
  );
}

function AuthModal({
  language,
  initialTab = 'register',
  onClose,
  onAuthSuccess,
}: {
  language: string;
  initialTab?: AuthTab;
  onClose: () => void;
  onAuthSuccess: (token: string, user: { id: number; name: string; surname: string; email: string }) => void;
}) {
  const [tab, setTab] = useState<AuthTab>(initialTab);
  const [availableCountries, setAvailableCountries] = useState(fallbackCountries);
  const [countriesLoading, setCountriesLoading] = useState(true);
  const [formError, setFormError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<ApiErrorDetails>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    let cancelled = false;

    fetch(API_BASE_URL + '/registration/countries', { headers: { Accept: 'application/json' } })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error('Countries could not be loaded.');
        }
        return response.json() as Promise<{ data?: { countries?: string[] } }>;
      })
      .then((payload) => {
        if (!cancelled && payload.data?.countries?.length) {
          setAvailableCountries(payload.data.countries);
        }
      })
      .catch(() => {
        // Fallback silently
      })
      .finally(() => {
        if (!cancelled) {
          setCountriesLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  const handleRegisterSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    setFieldErrors({});
    setIsSubmitting(true);

    const formData = new FormData(event.currentTarget);
    const payload = {
      email: String(formData.get('email') ?? ''),
      password: String(formData.get('password') ?? ''),
      name: String(formData.get('name') ?? ''),
      surname: String(formData.get('surname') ?? ''),
      phone: String(formData.get('phone') ?? ''),
      date_of_birth: String(formData.get('date_of_birth') ?? ''),
      address: {
        street: String(formData.get('street') ?? ''),
        city: String(formData.get('city') ?? ''),
        country: String(formData.get('country') ?? ''),
        postcode: String(formData.get('postcode') ?? ''),
      },
      terms_accepted: formData.get('terms_accepted') === '1',
    };

    try {
      const response = await fetch(API_BASE_URL + '/register', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const result = await response.json() as {
        success?: boolean;
        data?: { token: string; user: { id: number; name: string; surname: string; email: string } };
        error?: { message?: string; details?: ApiErrorDetails };
      };

      if (!response.ok || !result.success || !result.data?.token) {
        setFormError(result.error?.message ?? (language === 'RU' ? 'Ошибка регистрации. Проверьте данные.' : 'Registration could not be completed.'));
        setFieldErrors(result.error?.details ?? {});
        return;
      }

      onAuthSuccess(result.data.token, result.data.user);
    } catch {
      setFormError(language === 'RU' ? 'Сервер временно недоступен. Попробуйте снова.' : 'Game server is currently unreachable. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleLoginSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setFormError('');
    setFieldErrors({});
    setIsSubmitting(true);

    const formData = new FormData(event.currentTarget);
    const payload = {
      email: String(formData.get('email') ?? ''),
      password: String(formData.get('password') ?? ''),
    };

    try {
      const response = await fetch(API_BASE_URL + '/login', {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      const result = await response.json() as {
        success?: boolean;
        data?: { token: string; user: { id: number; name: string; surname: string; email: string } };
        error?: { message?: string; details?: ApiErrorDetails };
      };

      if (!response.ok || !result.success || !result.data?.token) {
        setFormError(result.error?.message ?? (language === 'RU' ? 'Неверный email или пароль.' : 'Invalid email or password.'));
        setFieldErrors(result.error?.details ?? {});
        return;
      }

      onAuthSuccess(result.data.token, result.data.user);
    } catch {
      setFormError(language === 'RU' ? 'Сервер временно недоступен. Попробуйте снова.' : 'Game server is currently unreachable. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <section className="modal-card" role="dialog" aria-modal="true" aria-labelledby="auth-title">
        <button className="modal-close" onClick={onClose} aria-label="Close dialog">×</button>

        <p className="eyebrow">{tab === 'register' ? (language === 'RU' ? 'ВСТУПИТЬ В СТРАЖУ' : 'JOIN THE WATCH') : (language === 'RU' ? 'ДОБРО ПОЖАЛОВАТЬ' : 'WELCOME BACK')}</p>
        <h2 id="auth-title">{tab === 'register' ? (language === 'RU' ? 'Создать аккаунт' : 'Create your account') : (language === 'RU' ? 'Вход в аккаунт' : 'Sign in to the watch')}</h2>
        <p className="modal-intro">
          {tab === 'register'
            ? (language === 'RU' ? 'Сохраняйте башни, открывайте сундуки и развивайте базу в каждом походе.' : 'Save your towers, unlock boosts, and keep every endless run.')
            : (language === 'RU' ? 'Войдите, чтобы загрузить ваши башни, прогресс и кошелёк.' : 'Enter your credentials to load your saved towers, wallet, and run progress.')}
        </p>

        <div className="auth-tabs" role="tablist">
          <button
            type="button"
            className={`auth-tab ${tab === 'register' ? 'active' : ''}`}
            onClick={() => { setTab('register'); setFormError(''); setFieldErrors({}); }}
            role="tab"
            aria-selected={tab === 'register'}
          >
            {language === 'RU' ? 'Регистрация' : 'Create Account'}
          </button>
          <button
            type="button"
            className={`auth-tab ${tab === 'login' ? 'active' : ''}`}
            onClick={() => { setTab('login'); setFormError(''); setFieldErrors({}); }}
            role="tab"
            aria-selected={tab === 'login'}
          >
            {language === 'RU' ? 'Вход' : 'Sign In'}
          </button>
        </div>

        {formError && <p className="form-error" role="alert">{formError}</p>}
        {Object.values(fieldErrors).flat().map((error) => <p className="field-error" key={error}>{error}</p>)}

        {tab === 'register' ? (
          <form className="register-form" onSubmit={handleRegisterSubmit}>
            <div className="form-grid">
              <label>Email<input type="email" name="email" required placeholder="you@realm.com" /></label>
              <label>{language === 'RU' ? 'Пароль' : 'Password'}<input type="password" name="password" required minLength={8} placeholder="8+ characters" /></label>
              <label>{language === 'RU' ? 'Имя' : 'Name'}<input name="name" required placeholder="Aria" /></label>
              <label>{language === 'RU' ? 'Фамилия' : 'Surname'}<input name="surname" required placeholder="Stormwatch" /></label>
              <label>{language === 'RU' ? 'Телефон' : 'Phone number'}<input type="tel" name="phone" required placeholder="+371 ..." /></label>
              <label>{language === 'RU' ? 'Дата рождения' : 'Date of birth'}<input type="date" name="date_of_birth" required /></label>
            </div>
            <label>{language === 'RU' ? 'Улица, дом, квартира' : 'Street, house number, apartment'}<input name="street" required placeholder="14 Lantern Row, apt. 3" /></label>
            <div className="form-grid address-grid">
              <label>{language === 'RU' ? 'Город' : 'City'}<input name="city" required placeholder="Riga" /></label>
              <label>
                {language === 'RU' ? 'Страна' : 'Country'}
                <select name="country" required defaultValue="" disabled={countriesLoading}>
                  <option value="" disabled>{countriesLoading ? (language === 'RU' ? 'Загрузка...' : 'Loading countries…') : (language === 'RU' ? 'Выберите страну' : 'Select country')}</option>
                  {availableCountries.map((country) => <option key={country}>{country}</option>)}
                </select>
              </label>
              <label>{language === 'RU' ? 'Индекс' : 'Post code'}<input name="postcode" required placeholder="LV-1010" /></label>
            </div>
            <label className="check-label">
              <input type="checkbox" name="terms_accepted" value="1" required />
              <span>
                {language === 'RU' ? 'Я согласен с ' : 'I agree to the '}
                <a className="inline-link" href={LEGAL_BASE_URL + '/legal/terms'} target="_blank" rel="noreferrer">
                  {language === 'RU' ? 'Правилами сервиса' : 'Terms & Conditions'}
                </a>
                {language === 'RU' ? ' и ' : ' and '}
                <a className="inline-link" href={LEGAL_BASE_URL + '/legal/privacy'} target="_blank" rel="noreferrer">
                  {language === 'RU' ? 'Политикой конфиденциальности' : 'Privacy Policy'}
                </a>.
              </span>
            </label>
            <button className="button button-orange form-submit" type="submit" disabled={isSubmitting}>
              {isSubmitting ? (language === 'RU' ? 'Создание...' : 'Creating account…') : <>{language === 'RU' ? 'Создать аккаунт' : 'Create account'} <span>↗</span></>}
            </button>
            <button type="button" className="switch-auth-link" onClick={() => { setTab('login'); setFormError(''); setFieldErrors({}); }}>
              {language === 'RU' ? 'Уже есть аккаунт? Войти' : 'Already have an account? Sign in'}
            </button>
          </form>
        ) : (
          <form className="register-form" onSubmit={handleLoginSubmit}>
            <label>Email<input type="email" name="email" required placeholder="you@realm.com" autoFocus /></label>
            <label>{language === 'RU' ? 'Пароль' : 'Password'}<input type="password" name="password" required placeholder="••••••••" /></label>
            <button className="button button-orange form-submit" type="submit" disabled={isSubmitting}>
              {isSubmitting ? (language === 'RU' ? 'Вход...' : 'Signing in…') : <>{language === 'RU' ? 'Войти в аккаунт' : 'Sign in to the watch'} <span>↗</span></>}
            </button>
            <button type="button" className="switch-auth-link" onClick={() => { setTab('register'); setFormError(''); setFieldErrors({}); }}>
              {language === 'RU' ? 'Нет аккаунта? Зарегистрироваться' : 'Need an account? Create one'}
            </button>
          </form>
        )}
      </section>
    </div>
  );
}

export function App() {
  const [activeChest, setActiveChest] = useState<ChestId>('gold');
  const [language, setLanguage] = useState<'EN' | 'RU'>('EN');
  const [authModalOpen, setAuthModalOpen] = useState(false);
  const [authInitialTab, setAuthInitialTab] = useState<AuthTab>('register');
  const [legalModalTab, setLegalModalTab] = useState<LegalDocType | null>(null);
  const [showTopUp, setShowTopUp] = useState(false);
  const [notice, setNotice] = useState('');
  const [player, setPlayer] = useState<PlayerState | null>(null);
  const [currentView, setCurrentView] = useState<'landing' | 'game'>('landing');

  const active = chests.find((chest) => chest.id === activeChest) ?? chests[1];

  const showNotice = (message: string) => {
    setNotice(message);
    window.setTimeout(() => setNotice(''), 3000);
  };

  useEffect(() => {
    const token = window.localStorage.getItem('cdh.token');
    if (token) {
      getPlayerState(token)
        .then((state) => {
          setPlayer(state);
        })
        .catch(() => {
          window.localStorage.removeItem('cdh.token');
        });
    }
  }, []);

  const handleAuthSuccess = async (token: string, user: { name: string; surname: string; email: string }) => {
    window.localStorage.setItem('cdh.token', token);
    setAuthModalOpen(false);
    let playerState: PlayerState;
    try {
      playerState = await getPlayerState(token);
      setPlayer(playerState);
    } catch {
      playerState = {
        user: { id: 0, name: user.name, surname: user.surname, email: user.email, email_verified: true, address: null },
        wallet: { id: 0, diamonds: 0, soft_currency: 100 },
        inventory: { tower_cards: [{ id: 1, code: 'ember-archer', level: 1, quantity: 1, is_equipped: true }], items: [], boosts: [] },
        loadout: { id: 1, slots: ['ember-archer'] },
        endless_progress: null,
      };
      setPlayer(playerState);
    }
    setCurrentView('game');
    showNotice(language === 'RU' ? `Добро пожаловать на стражу, ${user.name}!` : `Welcome to the watch, ${user.name}!`);
  };

  const handleLogout = () => {
    window.localStorage.removeItem('cdh.token');
    setPlayer(null);
    setCurrentView('landing');
    showNotice(language === 'RU' ? 'Вы вышли из аккаунта.' : 'Signed out from the watch.');
  };

  // If user opened the full game arena view
  if (currentView === 'game' && player) {
    return (
      <GameDashboard
        player={player}
        language={language}
        onUpdatePlayer={(updater) => setPlayer((prev) => (prev ? updater(prev) : null))}
        onBackToLanding={() => setCurrentView('landing')}
        onLogout={handleLogout}
        onToggleLanguage={() => setLanguage((l) => (l === 'EN' ? 'RU' : 'EN'))}
      />
    );
  }

  return (
    <main className="site-shell">
      <div className="grain" />
      <div className="sky-orb orb-one" />
      <div className="sky-orb orb-two" />
      <div className="mountain mountain-back" />
      <div className="mountain mountain-front" />
      <div className="tower-silhouette"><span /><i /><b /></div>
      <div className="cloud cloud-one" /><div className="cloud cloud-two" />

      <nav className="topbar" aria-label="Main navigation">
        <a className="brand" href="#top" aria-label="Crazy Defense Hereoes home"><ShieldMark /><span>CDH<span className="brand-dot">.</span></span></a>
        <div className="nav-links">
          <a href="#vault">{language === 'RU' ? 'Сундуки' : 'Chest sale'}</a>
          <a href="#roster">{language === 'RU' ? 'Башни' : 'Get tower'}</a>
          <a href="#arsenal">{language === 'RU' ? 'Арсенал' : 'Inventory'}</a>
          <a href="#demo">{language === 'RU' ? 'Играть' : 'Play demo'}</a>
          <button className="language-button" onClick={() => setLanguage(language === 'EN' ? 'RU' : 'EN')}>Language <span>{language}</span>⌄</button>
        </div>

        {player ? (
          <div className="topbar-user">
            <button className="button-play-now" onClick={() => setCurrentView('game')}>
              <i className="fa-solid fa-gamepad" style={{ marginRight: 6 }} />
              {language === 'RU' ? 'В БОЙ' : 'PLAY'}
            </button>
            <div className="user-badge" title={player.user.email}>
              <span className="user-name"><i className="fa-solid fa-star" style={{ marginRight: 4, color: 'var(--orange)' }} />{player.user.name}</span>
              <span className="user-wallet">
                <span>{player.wallet?.diamonds ?? 0} <i className="fa-solid fa-gem" style={{ color: '#2980b9' }} /></span>
                <span>{player.wallet?.soft_currency ?? 100} <i className="fa-solid fa-coins" style={{ color: '#d4ac0d' }} /></span>
              </span>
              <button
                type="button"
                className="user-wallet-plus"
                title={language === 'RU' ? 'Пополнить баланс' : 'Top up balance'}
                onClick={() => setShowTopUp(true)}
              >
                +
              </button>
            </div>
            <button className="button-logout" onClick={handleLogout}>
              {language === 'RU' ? 'Выйти' : 'Sign out'}
            </button>
          </div>
        ) : (
          <button
            className="button button-connect"
            onClick={() => {
              setAuthInitialTab('register');
              setAuthModalOpen(true);
            }}
          >
            {language === 'RU' ? 'Войти / Регистрация' : 'Connect'} <span>↗</span>
          </button>
        )}
      </nav>

      <section className="hero" id="top">
        <div className="hero-copy">
          <p className="eyebrow"><span className="eyebrow-line" /> {language === 'RU' ? 'Играй · Зарабатывай · Побеждай' : 'Play · Earn · Enjoy'} <span className="eyebrow-line" /></p>
          <h1><span>Crazy</span> <em>Defense</em> <strong>Hereoes</strong></h1>
          <p className="hero-subtitle">
            {language === 'RU'
              ? <>Постройте непреодолимую крепость. Перехитрите орду.<br />Стража никогда не спит.</>
              : <>Build the impossible fortress. Outwit the horde.<br />The watch never ends.</>}
          </p>

          {player && (
            <div style={{ marginTop: '26px' }}>
              <button
                className="button-play-now"
                style={{ padding: '16px 36px', fontSize: '1rem' }}
                onClick={() => setCurrentView('game')}
              >
                <i className="fa-solid fa-gamepad" style={{ marginRight: 8 }} />
                {language === 'RU' ? 'ВОЙТИ В ИГРОВУЮ АРЕНУ' : 'ENTER BATTLE ARENA'} <span>↗</span>
              </button>
            </div>
          )}
        </div>

        <div className="vault-scene" id="vault">
          <Figure side="left" type="ranger" /><Figure side="right" type="mage" />
          <div className="side-loot side-loot-left">
            <span className="side-label">Silver cache</span><ChestArt kind="silver" /><span className="side-arrow">↙</span>
          </div>
          <div className="side-loot side-loot-right">
            <span className="side-label">Bronze stash</span><ChestArt kind="bronze" /><span className="side-arrow">↘</span>
          </div>
          <div className="vault-board">
            <div className="board-tacks"><i /><i /><i /><i /></div>
            <div className="board-topline"><span>THE VAULT</span><span>WAVE 07 <i className="live-dot" /></span></div>
            <div className="chest-display"><div className="chest-pedestal" /><ChestArt kind={activeChest} large /></div>
            <p className="chest-kicker">{active.eyebrow}</p>
            <h2>{active.name}</h2>
            <p className="chest-description">{active.description}</p>
            <div className="loot-meta"><span>{active.loot}</span><span className="loot-price">250 <i className="fa-solid fa-gem" style={{ color: '#2980b9' }} /></span></div>
            <div className="chest-tabs" role="tablist" aria-label="Choose a chest">
              {chests.map((chest) => <button key={chest.id} className={activeChest === chest.id ? 'active' : ''} onClick={() => setActiveChest(chest.id)} role="tab" aria-selected={activeChest === chest.id}>{chest.name.split(' ')[0]}</button>)}
            </div>
            <button className="button button-orange vault-cta" onClick={() => showNotice(`${active.name} ${language === 'RU' ? 'добавлен в список желаемого' : 'added to your watchlist'}`)}>
              {language === 'RU' ? 'Открыть сокровищницу' : 'Open the vault'} <span>↗</span>
            </button>
          </div>
        </div>

        <div className="hero-footer" id="loadout">
          <div className="feature"><span className="feature-icon">∞</span><span><b>{language === 'RU' ? 'Бесконечные волны' : 'Endless waves'}</b><small>{language === 'RU' ? 'Орда адаптируется' : 'The horde adapts'}</small></span></div>
          <div className="feature"><span className="feature-icon"><i className="fa-solid fa-star" style={{ color: '#df5e29', fontSize: '1rem' }} /></span><span><b>{language === 'RU' ? 'Ценная добыча' : 'Loot that matters'}</b><small>{language === 'RU' ? 'Бусты · башни · кристаллы' : 'Boosts · towers · gems'}</small></span></div>
          <div className="feature"><span className="feature-icon">⌁</span><span><b>{language === 'RU' ? 'Ваша стратегия' : 'Build your legend'}</b><small>{language === 'RU' ? 'Ваш лабиринт, ваши правила' : 'Your maze, your rules'}</small></span></div>
          <button className="mini-cta" onClick={() => showNotice(language === 'RU' ? 'Предпросмотр раскладки готов' : 'Loadout preview is ready')}>
            {language === 'RU' ? 'Обзор арсенала' : 'View loadout'} <span>↗</span>
          </button>
        </div>

        <section className="demo-section" id="demo" aria-labelledby="demo-title">
          <div className="demo-copy">
            <p className="eyebrow"><span className="eyebrow-line" /> LIVE MAP PREVIEW <span className="eyebrow-line" /></p>
            <h2 id="demo-title">{language === 'RU' ? 'Постройте первую линию' : 'Draw your first line.'}</h2>
            <p>
              {language === 'RU'
                ? 'Нажмите на светлую клетку, чтобы купить башню. Кликните по башне, чтобы переключить цель (Первый, Сильный, Близкий).'
                : 'Tap a pale tile to buy a tower. Click a tower to rotate targeting between First, Strong, and Close.'}
            </p>
          </div>
          <div className="game-frame">
            <GameCanvas />
          </div>
        </section>

        <section className="intel-section" id="watch" aria-labelledby="watch-title">
          <div className="section-heading">
            <p className="eyebrow"><span className="eyebrow-line" /> {language === 'RU' ? 'ЗАМЕТКИ СТРАЖНИКА' : "THE WATCHMAN'S FIELD NOTES"} <span className="eyebrow-line" /></p>
            <h2 id="watch-title">{language === 'RU' ? 'Стратегия без последней волны' : 'A strategy game with no last wave.'}</h2>
            <p>
              {language === 'RU'
                ? 'Правила просты, решения не ограничены. Постройте линию обороны, защитите цитадель и адаптируйтесь под каждую новую волну.'
                : 'The rules stay simple. The decisions keep changing. Build a line, protect the keep, and let the next wave tell you what to fix.'}
            </p>
          </div>
          <div className="watch-layout">
            <article className="field-card">
              <div className="field-card-topline"><span>RUN LOG / ENDLESS</span><span>∞ 24 / 24</span></div>
              <div className="field-map-lines" aria-hidden="true"><i /><i /><i /></div>
              <p className="field-kicker">{language === 'RU' ? 'ОРДА ЗАПОМИНАЕТ ВАШУ ФОРМУ' : 'THE HORDE LEARNS YOUR SHAPE.'}</p>
              <h3>{language === 'RU' ? <>Создайте лабиринт,<br />готовый к защите.</> : <>Make a maze<br />worth defending.</>}</h3>
              <p>
                {language === 'RU'
                  ? 'Первая башня выигрывает время. Вторая меняет путь врагов. К двадцатой волне карта помнит каждое ваше решение.'
                  : 'Your first tower buys time. Your second tower changes the route. By wave twenty, the map remembers every choice.'}
              </p>
              <div className="field-stat-row">
                <span><b>∞</b><small>{language === 'RU' ? 'ВОЛН' : 'ENDLESS WAVES'}</small></span>
                <span><b>1.2×</b><small>{language === 'RU' ? 'ДАВЛЕНИЕ' : 'WAVE PRESSURE'}</small></span>
                <span><b>3</b><small>{language === 'RU' ? 'РЕЖИМА' : 'TARGET MODES'}</small></span>
              </div>
            </article>
            <div className="watch-phases">
              {watchPhases.map((phase, index) => (
                <article className="phase-item" key={phase.label}>
                  <span className="phase-line" aria-hidden="true"><i /></span>
                  <div className="phase-copy">
                    <span className="phase-label">{String(index + 1).padStart(2, '0')} / {phase.label}</span>
                    <h3>{phase.title}</h3>
                    <p>{phase.copy}</p>
                  </div>
                </article>
              ))}
            </div>
          </div>
        </section>

        <section className="roster-section" id="roster" aria-labelledby="roster-title">
          <div className="section-heading section-heading-row">
            <div>
              <p className="eyebrow"><span className="eyebrow-line" /> {language === 'RU' ? 'ВАШИ ГЕРОИ' : 'BUILD YOUR LEGEND'} <span className="eyebrow-line" /></p>
              <h2 id="roster-title">{language === 'RU' ? 'У каждой башни своя роль' : 'Every tower has a job.'}</h2>
            </div>
            <p>{language === 'RU' ? 'Собирайте карты, настраивайте прицеливание и сформируйте идеальный отряд.' : 'Collect cards, tune your targeting, and make a roster that feels like yours.'}</p>
          </div>
          <div className="roster-grid">
            {roster.map((tower) => (
              <article className={'roster-card roster-' + tower.color} key={tower.code}>
                <div className="roster-card-topline"><span>{tower.code}</span><span>{tower.role}</span></div>
                <div className="roster-emblem" aria-hidden="true"><i /><b /><span /></div>
                <h3>{tower.name}</h3>
                <p>{tower.copy}</p>
                <div className="roster-card-footer"><span>{tower.stat}</span><span className="card-arrow">↗</span></div>
              </article>
            ))}
          </div>
        </section>

        <section className="arsenal-section" id="arsenal" aria-labelledby="arsenal-title">
          <div className="arsenal-copy">
            <p className="eyebrow"><span className="eyebrow-line" /> {language === 'RU' ? 'ВАША СТОРОЖЕВАЯ БАШНЯ' : 'YOUR WATCHTOWER'} <span className="eyebrow-line" /></p>
            <h2 id="arsenal-title">{language === 'RU' ? <>Добыча, которая<br />остаётся с вами.</> : <>Loot that stays<br />with you.</>}</h2>
            <p>{language === 'RU' ? 'Откройте сундук, примените буст и вернитесь на поле боя с новыми возможностями.' : 'Open a chest, claim a boost, and return to the map with more ways to solve the same impossible corner.'}</p>
            <a className="text-link" href="#vault">{language === 'RU' ? 'В сокровищницу' : 'Browse the vault'} <span>↗</span></a>
          </div>
          <div className="loadout-panel">
            <div className="loadout-panel-topline"><span>ACTIVE LOADOUT</span><span>03 / 03 SLOTS</span></div>
            <div className="loadout-slot"><span className="slot-mark slot-ember"><i className="fa-solid fa-star" /></span><span><b>Ember Archer</b><small>FIRST / LEVEL 02</small></span><strong>READY</strong></div>
            <div className="loadout-slot"><span className="slot-mark slot-stone"><i className="fa-solid fa-gem" /></span><span><b>Stone Warden</b><small>CLOSE / LEVEL 01</small></span><strong>READY</strong></div>
            <div className="loadout-slot"><span className="slot-mark slot-mage"><i className="fa-solid fa-bolt" /></span><span><b>Fortify Boost</b><small>3 CHARGES REMAINING</small></span><strong>×03</strong></div>
            <div className="loadout-meter"><span /><small>{language === 'RU' ? 'СИНХРОНИЗИРОВАНО С ВАШИМ АККАУНТОМ' : 'ROSTER SYNCED TO YOUR ACCOUNT'}</small></div>
          </div>
        </section>

        <section className="closing-section" aria-labelledby="closing-title">
          <p className="eyebrow"><span className="eyebrow-line" /> {language === 'RU' ? 'ВОРОТА ОТКРЫТЫ' : 'THE GATES ARE OPEN'} <span className="eyebrow-line" /></p>
          <h2 id="closing-title">{language === 'RU' ? <>Постройте крепость,<br />достойную легенд.</> : <>Build a fortress<br />worth remembering.</>}</h2>
          <p>{language === 'RU' ? 'Начните с одной клетки. Останьтесь ради бесконечной битвы.' : 'Start with one empty tile. Stay for the story the waves write back.'}</p>
          {player ? (
            <button className="button button-orange closing-cta" onClick={() => setCurrentView('game')}>
              <i className="fa-solid fa-gamepad" style={{ marginRight: 6 }} />
              {language === 'RU' ? 'В БОЙ' : 'Enter Battle Arena'} <span>↗</span>
            </button>
          ) : (
            <a className="button button-orange closing-cta" href="#demo">
              {language === 'RU' ? 'Войти в игру' : 'Enter the watch'} <span>↗</span>
            </a>
          )}
        </section>
      </section>

      {/* Rich Multi-Column Landing Footer */}
      <footer className="site-footer" role="contentinfo">
        <div className="footer-top-accent" />
        <div className="footer-container">
          <div className="footer-grid">
            {/* Column 1: Brand & Lore */}
            <div className="footer-col footer-col-brand">
              <a className="brand footer-brand" href="#top" aria-label="Crazy Defense Hereoes home">
                <ShieldMark />
                <span>CDH<span className="brand-dot">.</span></span>
              </a>
              <p className="footer-desc">
                {language === 'RU'
                  ? 'Тактическая стратегия нового поколения. Стройте цитадель, открывайте легендарные сундуки и держите оборону в бесконечных волнах.'
                  : 'Next-generation tactical tower defense. Erect the impossible fortress, unlock legendary chests, and defend against the endless tide.'}
              </p>
              <div className="footer-company-brief">
                <span><b>Crazy Defense Hereoes Ltd</b></span>
                <small>Reg. No: 14892341</small>
                <small>71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, UK</small>
              </div>
              <div className="footer-realm-status">
                <span className="live-dot" />
                <small>{language === 'RU' ? 'СЕРВЕР СТРАЖИ: ОНЛАЙН' : 'WATCH REALM SERVER: ONLINE'}</small>
              </div>
            </div>

            {/* Column 2: Game & Treasury */}
            <div className="footer-col">
              <h4 className="footer-heading">
                {language === 'RU' ? 'ИГРА И СОКРОВИЩА' : 'GAME & VAULT'}
              </h4>
              <ul className="footer-links">
                <li><a href="#vault">{language === 'RU' ? 'Сокровищница сундуков' : 'Chest Vault & Sales'}</a></li>
                <li><a href="#roster">{language === 'RU' ? 'Зал героев и башен' : 'Tower Roster & Codex'}</a></li>
                <li><a href="#arsenal">{language === 'RU' ? 'Арсенал и раскладка' : 'Arsenal & Loadout'}</a></li>
                <li>
                  <button
                    type="button"
                    className="footer-link-btn"
                    onClick={() => {
                      if (player) {
                        setShowTopUp(true);
                      } else {
                        setAuthInitialTab('login');
                        setAuthModalOpen(true);
                        showNotice(language === 'RU' ? 'Войдите для пополнения сокровищницы' : 'Sign in to access your treasury');
                      }
                    }}
                  >
                    <i className="fa-solid fa-gem" style={{ marginRight: 6, color: '#2980b9' }} />
                    {language === 'RU' ? 'Пополнить баланс (+)' : 'Top-Up Treasury (+)'}
                  </button>
                </li>
                <li>
                  <button
                    type="button"
                    className="footer-link-btn"
                    onClick={() => {
                      if (player) {
                        setCurrentView('game');
                      } else {
                        setAuthInitialTab('login');
                        setAuthModalOpen(true);
                      }
                    }}
                  >
                    <i className="fa-solid fa-gamepad" style={{ marginRight: 6 }} />
                    {language === 'RU' ? 'Боевая арена (Play)' : 'Battle Arena (Play)'}
                  </button>
                </li>
              </ul>
            </div>

            {/* Column 3: Security & Payment Methods */}
            <div className="footer-col footer-col-payments">
              <h4 className="footer-heading">
                {language === 'RU' ? 'ПЛАТЕЖИ И БЕЗОПАСНОСТЬ' : 'PAYMENTS & SECURITY'}
              </h4>
              <p className="footer-security-text">
                {language === 'RU'
                  ? 'Все транзакции защищены 256-битным шифрованием по международному стандарту PCI DSS Level 1.'
                  : 'All transactions are guarded with 256-bit SSL encryption adhering to PCI DSS Level 1 standards.'}
              </p>
              <div className="payment-badges-wrap">
                <div className="payment-badge-card" title="Verified Visa Payment Gateway">
                  <img src="/images/visa.png" alt="Visa" className="payment-logo" />
                </div>
                <div className="payment-badge-card" title="Mastercard Identity Check / 3D Secure">
                  <img src="/images/mastercard.png" alt="Mastercard" className="payment-logo" />
                </div>
                <div className="payment-badge-card pci-card" title="PCI DSS Level 1 Certified Security">
                  <img src="/images/pci-dss.png" alt="PCI DSS Compliant" className="payment-logo pci-logo" />
                </div>
              </div>
              <div className="footer-ssl-note">
                <span className="ssl-lock-icon"><i className="fa-solid fa-lock" /></span>
                <small>256-BIT SSL TLS 1.3 ENCRYPTION</small>
              </div>
            </div>

            {/* Column 4: Legal & Policies */}
            <div className="footer-col">
              <h4 className="footer-heading">
                {language === 'RU' ? 'ПРАВОВАЯ ИНФОРМАЦИЯ' : 'LEGAL & TRUST'}
              </h4>
              <ul className="footer-links-list">
                <li>
                  <button type="button" className="footer-link-btn" onClick={() => setLegalModalTab('terms')}>
                    <i className="fa-solid fa-scroll" style={{ marginRight: 6 }} />
                    {language === 'RU' ? 'Пользовательское соглашение' : 'Terms of Service'}
                  </button>
                </li>
                <li>
                  <button type="button" className="footer-link-btn" onClick={() => setLegalModalTab('privacy')}>
                    <i className="fa-solid fa-shield-halved" style={{ marginRight: 6 }} />
                    {language === 'RU' ? 'Политика конфиденциальности' : 'Privacy Policy'}
                  </button>
                </li>
                <li>
                  <button type="button" className="footer-link-btn" onClick={() => setLegalModalTab('refunds')}>
                    <i className="fa-solid fa-credit-card" style={{ marginRight: 6 }} />
                    {language === 'RU' ? 'Политика возврата и покупок' : 'Refund & Store Policy'}
                  </button>
                </li>
                <li>
                  <button type="button" className="footer-link-btn" onClick={() => setLegalModalTab('security')}>
                    <i className="fa-solid fa-lock" style={{ marginRight: 6 }} />
                    {language === 'RU' ? 'Безопасность и PCI DSS' : 'PCI DSS & Security Audit'}
                  </button>
                </li>
                <li>
                  <span className="footer-contact-item">
                    <i className="fa-solid fa-envelope" style={{ marginRight: 6, color: '#df5e29' }} />
                    <a href="mailto:info@crazydefensehereoes.co.uk">info@crazydefensehereoes.co.uk</a>
                  </span>
                </li>
                <li>
                  <span className="footer-contact-item">
                    <i className="fa-solid fa-globe" style={{ marginRight: 6, color: '#df5e29' }} />
                    <a href="https://crazydefensehereoes.co.uk" target="_blank" rel="noreferrer">crazydefensehereoes.co.uk</a>
                  </span>
                </li>
              </ul>
            </div>
          </div>

          <div className="footer-bottom-bar">
            <div className="footer-bottom-copy">
              © 2026 Crazy Defense Hereoes Ltd (Company No. 14892341). {language === 'RU' ? 'Все права защищены.' : 'All rights reserved.'} · 71-75 Shelton Street, London, WC2H 9JQ, UK
            </div>
            <div className="footer-bottom-tags">
              <span className="footer-tag">GDPR COMPLIANT</span>
              <span className="footer-tag">PCI DSS VERIFIED</span>
              <span className="footer-tag">STABLE v2.4.0</span>
            </div>
          </div>
        </div>
      </footer>

      {authModalOpen && (
        <AuthModal
          language={language}
          initialTab={authInitialTab}
          onClose={() => setAuthModalOpen(false)}
          onAuthSuccess={handleAuthSuccess}
        />
      )}
      {showTopUp && player && (
        <TopUpModal
          player={player}
          language={language}
          onClose={() => setShowTopUp(false)}
          onUpdatePlayer={(updater) => setPlayer((prev) => (prev ? updater(prev) : null))}
          onSuccessNotice={showNotice}
        />
      )}
      {legalModalTab && (
        <LegalModal
          initialTab={legalModalTab}
          language={language}
          onClose={() => setLegalModalTab(null)}
        />
      )}
      {notice && <div className="toast" role="status"><i className="fa-solid fa-star" style={{ color: '#f2c864', marginRight: 6 }} />{notice}</div>}
    </main>
  );
}
