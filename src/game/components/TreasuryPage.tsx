import { FormEvent, useEffect, useRef, useState } from 'react';
import { downloadInvoice, getOrders, getPlayerState, getTopUpSettings, Order, PlayerState, purchaseTopUp, TopUpSettings } from '../../lib/api';

type Props = {
  player: PlayerState;
  onUpdatePlayer: (updater: (previous: PlayerState) => PlayerState) => void;
};

export function TreasuryPage({ player, onUpdatePlayer }: Props) {
  const [settings, setSettings] = useState<TopUpSettings | null>(null);
  const [diamonds, setDiamonds] = useState('500');
  const [gold, setGold] = useState('0');
  const [orders, setOrders] = useState<Order[]>([]);
  const [error, setError] = useState('');
  const [historyError, setHistoryError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [downloadId, setDownloadId] = useState<number | null>(null);
  // Keep the reference on failures: retrying a timed-out request cannot credit twice.
  const purchaseRef = useRef<{ amounts: string; key: string } | null>(null);
  const submitLock = useRef(false);

  const token = () => {
    const value = window.localStorage.getItem('cdh.token');
    if (!value) throw new Error('Sign in again to access your treasury.');
    return value;
  };

  useEffect(() => {
    let cancelled = false;
    try {
      const auth = token();
      getTopUpSettings(auth).then((value) => { if (!cancelled) setSettings(value); })
        .catch((reason: Error) => { if (!cancelled) setError(reason.message); });
      getOrders(auth).then((value) => { if (!cancelled) setOrders(value); })
        .catch((reason: Error) => { if (!cancelled) setHistoryError(reason.message); });
    } catch (reason) {
      setError((reason as Error).message);
    }
    return () => { cancelled = true; };
  }, []);

  const validAmount = (value: string) => /^\d+$/.test(value) && Number.isSafeInteger(Number(value))
    && Number(value) <= (settings?.max_quantity ?? 0);
  const amountsValid = validAmount(diamonds) && validAmount(gold) && Number(diamonds) + Number(gold) > 0;
  const diamondCost = settings && validAmount(diamonds) ? Math.ceil(Number(diamonds) * 100 / settings.diamonds_per_euro) : 0;
  const goldCost = settings && validAmount(gold) ? Math.ceil(Number(gold) * 100 / settings.gold_per_euro) : 0;
  const money = (minor: number, currency = settings?.currency ?? 'EUR') => new Intl.NumberFormat('en-GB', { style: 'currency', currency }).format(minor / 100);
  const count = (value: number) => new Intl.NumberFormat('en-GB').format(value);

  const refresh = async () => {
    setRefreshing(true);
    setHistoryError('');
    try {
      const [history, state, options] = await Promise.all([getOrders(token()), getPlayerState(token()), getTopUpSettings(token())]);
      setOrders(history);
      setSettings(options);
      setError('');
      onUpdatePlayer(() => state);
    } catch (reason) {
      setHistoryError((reason as Error).message);
    } finally {
      setRefreshing(false);
    }
  };

  const purchase = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!amountsValid || !settings?.manual_enabled || submitLock.current) return;
    submitLock.current = true;
    setBusy(true);
    setError('');
    setSuccess('');
    const amounts = `${Number(diamonds)}:${Number(gold)}`;
    if (purchaseRef.current?.amounts !== amounts) {
      purchaseRef.current = { amounts, key: window.crypto.randomUUID() };
    }
    try {
      const result = await purchaseTopUp(token(), Number(diamonds), Number(gold), purchaseRef.current.key);
      onUpdatePlayer((previous) => ({ ...previous, wallet: result.wallet }));
      setOrders((previous) => [result.order, ...previous.filter((order) => order.id !== result.order.id)]);
      setSuccess(`Your treasury has been replenished. Your PDF invoice is ready; the receipt email is ${result.order.invoice?.sent_at ? 'sent' : 'queued'}.`);
      purchaseRef.current = null;
      setDiamonds('0');
      setGold('0');
    } catch (reason) {
      setError((reason as Error).message);
    } finally {
      submitLock.current = false;
      setBusy(false);
    }
  };

  const download = async (order: Order) => {
    if (!order.invoice) return;
    setDownloadId(order.id);
    setHistoryError('');
    try {
      const blob = await downloadInvoice(token(), order.invoice.id);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `${order.invoice.invoice_number}.pdf`;
      link.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (reason) {
      setHistoryError((reason as Error).message);
    } finally {
      setDownloadId(null);
    }
  };

  return (
    <section className="treasury-page" aria-labelledby="treasury-title">
      <header className="treasury-heading">
        <div><p className="eyebrow">THE ROYAL TREASURY</p><h1 id="treasury-title">Your next move.<br /><em>Your amount.</em></h1>
          <p>Diamonds for the vault. Gold for the fortress. Choose exactly how much you need.</p></div>
        <div className="treasury-balances" aria-label="Current balance">
          <span><i className="fa-solid fa-gem" aria-hidden="true" /><small>DIAMONDS</small><strong>{count(player.wallet?.diamonds ?? 0)}</strong></span>
          <span><i className="fa-solid fa-coins" aria-hidden="true" /><small>GOLD</small><strong>{count(player.wallet?.soft_currency ?? 0)}</strong></span>
        </div>
      </header>

      {!settings && !error && <p role="status">Opening the treasury…</p>}
      {error && <p className="treasury-message treasury-error" role="alert">{error}</p>}
      {success && <p className="treasury-message treasury-success" role="status">{success}</p>}

      {settings && <form className="treasury-layout" onSubmit={purchase}>
        <div className="treasury-amounts">
          {([
            { id: 'diamonds', label: 'Diamonds', value: diamonds, set: setDiamonds, rate: settings.diamonds_per_euro, icon: 'gem', presets: [100, 500, 1200, 2500] },
            { id: 'gold', label: 'Gold coins', value: gold, set: setGold, rate: settings.gold_per_euro, icon: 'coins', presets: [2000, 10000, 25000, 50000] },
          ]).map((item) => <fieldset key={item.id} className={`treasury-currency treasury-${item.id}`} disabled={busy}>
            <legend><i className={`fa-solid fa-${item.icon}`} aria-hidden="true" /> {item.label}</legend>
            <p>{money(100)} = {count(item.rate)} {item.label.toLowerCase()}</p>
            <label htmlFor={`amount-${item.id}`}>Enter your amount</label>
            <input id={`amount-${item.id}`} name={item.id} type="number" inputMode="numeric" min="0" max={settings.max_quantity} step="1"
              required value={item.value} onChange={(event) => item.set(event.target.value)} />
            <div className="treasury-presets" aria-label={`${item.label} suggestions`}>
              {item.presets.map((amount) => <button key={amount} type="button" onClick={() => item.set(String(amount))}>{count(amount)}</button>)}
              <button type="button" onClick={() => item.set('0')}>Clear</button>
            </div>
            <small>Any whole amount up to {count(settings.max_quantity)} per purchase.</small>
          </fieldset>)}
        </div>
        <aside className="treasury-summary">
          <span className="treasury-order-label">YOUR REQUISITION</span>
          <h2>Ready for the watch.</h2>
          <dl><div><dt>Diamonds</dt><dd>{money(diamondCost)}</dd></div><div><dt>Gold coins</dt><dd>{money(goldCost)}</dd></div>
            <div className="treasury-total"><dt>Total value</dt><dd>{money(diamondCost + goldCost)}</dd></div></dl>
          <p className="treasury-mode"><b>Manual purchase</b>No card payment is collected. Confirming adds the selected currency to your account.</p>
          <p className="treasury-receipt-note">A PDF invoice and receipt will be sent to <strong>{player.user.email}</strong>.</p>
          <button className="button button-orange" type="submit" disabled={!amountsValid || busy || !settings.manual_enabled}>
            {busy ? 'Processing purchase…' : 'Confirm purchase'} <span aria-hidden="true">↗</span>
          </button>
          {!settings.manual_enabled && <p role="status">Manual purchases are currently paused.</p>}
          <small>Each currency is rounded up to the nearest cent.</small>
        </aside>
      </form>}

      <section className="treasury-history" aria-labelledby="history-title">
        <header><div><p className="eyebrow">YOUR PAPER TRAIL</p><h2 id="history-title">Purchases & invoices</h2></div>
          <button className="button button-dark" type="button" disabled={refreshing} onClick={refresh}>{refreshing ? 'Refreshing…' : 'Refresh'}</button></header>
        {historyError && <p className="treasury-message treasury-error" role="alert">{historyError}</p>}
        {orders.length === 0 ? <p>Your first purchase will appear here, together with its PDF invoice.</p> :
          <ul>{orders.map((order) => <li key={order.id}>
            <div><strong>{order.items?.map((item) => item.product_name).join(' + ') || 'Purchase'}</strong>
              <small>{new Date(order.created_at).toLocaleDateString('en-GB')} · {order.invoice?.invoice_number ?? order.public_id.slice(0, 8)}</small></div>
            <span>{money(order.total_minor, order.currency)}</span>
            <span className="treasury-order-status">{order.status === 'paid' ? 'Completed' : order.status}
              <small>{order.invoice ? (order.invoice.sent_at ? 'Receipt sent' : 'Receipt pending') : 'Invoice pending'}</small></span>
            <button type="button" className="treasury-download" disabled={!order.invoice || downloadId === order.id} onClick={() => download(order)}>
              {downloadId === order.id ? 'Downloading…' : 'Invoice PDF'} <i className="fa-solid fa-arrow-down" aria-hidden="true" /></button>
          </li>)}</ul>}
      </section>
    </section>
  );
}
