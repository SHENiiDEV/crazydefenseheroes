import { FormEvent, useEffect, useRef, useState } from 'react';
import { API_BASE_URL, checkOrderPayment, downloadInvoice, getOrders, getPlayerState, getTopUpSettings, Order, PlayerState, purchaseTopUp, TopUpSettings } from '../../lib/api';

type Props = {
  player: PlayerState;
  onUpdatePlayer: (updater: (previous: PlayerState) => PlayerState) => void;
};

export function TreasuryPage({ player, onUpdatePlayer }: Props) {
  const [settings, setSettings] = useState<TopUpSettings | null>(null);
  const [diamonds, setDiamonds] = useState('500');
  const [gold, setGold] = useState('0');
  const [currency, setCurrency] = useState('EUR');
  const [orders, setOrders] = useState<Order[]>([]);
  const [error, setError] = useState('');
  const [historyError, setHistoryError] = useState('');
  const [success, setSuccess] = useState('');
  const [busy, setBusy] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [downloadId, setDownloadId] = useState<number | null>(null);
  const [acceptTerms, setAcceptTerms] = useState(false);
  const [immediateDelivery, setImmediateDelivery] = useState(false);
  const [paymentOrderId, setPaymentOrderId] = useState<number | null>(null);
  const [checkingId, setCheckingId] = useState<number | null>(null);
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
      getOrders(auth).then((value) => { if (!cancelled) {
        setOrders(value);
        const reference = new URLSearchParams(window.location.search).get('payment_return');
        const order = value.find((item) => item.public_id === reference && item.provider === 'corefy');
        if (order) { setPaymentOrderId(order.id); setSuccess('Checking your payment with Sterling Pay. Goods are credited only after confirmation.'); }
      } })
        .catch((reason: Error) => { if (!cancelled) setHistoryError(reason.message); });
    } catch (reason) {
      setError((reason as Error).message);
    }
    return () => { cancelled = true; };
  }, []);

  const isCorefy = settings?.payment_mode === 'corefy';
  const consentGiven = acceptTerms && immediateDelivery;
  const enabled = settings?.enabled ?? settings?.manual_enabled ?? false;

  const checkPayment = async (id: number) => {
    setCheckingId(id);
    setHistoryError('');
    try {
      const result = await checkOrderPayment(token(), id);
      setOrders((previous) => [result.order, ...previous.filter((item) => item.id !== id)]);
      onUpdatePlayer((previous) => ({ ...previous, wallet: result.wallet }));
      if (result.order.status === 'paid') {
        setSuccess('Payment confirmed. Your goods have been credited. Your PDF invoice is available below.');
        setPaymentOrderId(null);
        const url = new URL(window.location.href);
        url.searchParams.delete('payment_return');
        window.history.replaceState(null, '', url);
      } else if (result.order.status !== 'pending') {
        setSuccess('Order status: ' + result.order.status.replace(/_/g, ' ') + '. No additional goods have been credited.');
        setPaymentOrderId(null);
      }
    } catch (reason) { setHistoryError((reason as Error).message); }
    finally { setCheckingId(null); }
  };

  useEffect(() => {
    if (!paymentOrderId) return;
    let cancelled = false;
    let timer: number;
    let attempts = 0;
    const poll = async () => {
      if (cancelled) return;
      await checkPayment(paymentOrderId);
      if (!cancelled && ++attempts < 24) timer = window.setTimeout(poll, 5000);
    };
    void poll();
    return () => { cancelled = true; window.clearTimeout(timer); };
  }, [paymentOrderId]);

  const validAmount = (value: string) => /^\d+$/.test(value) && Number.isSafeInteger(Number(value))
    && Number(value) <= (settings?.max_quantity ?? 0);
  const amountsValid = validAmount(diamonds) && validAmount(gold) && Number(diamonds) + Number(gold) > 0;
  const rates = settings?.currencies[currency];
  const diamondCost = rates && validAmount(diamonds) ? Math.ceil(Number(diamonds) * 100 / rates.diamonds_per_unit) : 0;
  const goldCost = rates && validAmount(gold) ? Math.ceil(Number(gold) * 100 / rates.gold_per_unit) : 0;
  const money = (minor: number, code = currency) => new Intl.NumberFormat('en-GB', { style: 'currency', currency: code }).format(minor / 100);
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
    if (!amountsValid || !enabled || (isCorefy && !consentGiven) || submitLock.current) return;
    submitLock.current = true;
    setBusy(true);
    setError('');
    setSuccess('');
    const amounts = `${currency}:${Number(diamonds)}:${Number(gold)}`;
    if (purchaseRef.current?.amounts !== amounts) {
      purchaseRef.current = { amounts, key: window.crypto.randomUUID() };
    }
    try {
      const result = await purchaseTopUp(token(), Number(diamonds), Number(gold), purchaseRef.current.key, currency, consentGiven);
      onUpdatePlayer((previous) => ({ ...previous, wallet: result.wallet }));
      setOrders((previous) => [result.order, ...previous.filter((order) => order.id !== result.order.id)]);
      if (result.order.status !== 'paid') {
        setSuccess('Your order is awaiting payment. Complete checkout with Sterling Pay; no goods have been credited yet.');
        setPaymentOrderId(result.order.id);
        if (result.order.checkout_url) window.location.assign(result.order.checkout_url);
        return;
      }
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
            { id: 'diamonds', label: 'Diamonds', value: diamonds, set: setDiamonds, rate: rates?.diamonds_per_unit ?? 100, icon: 'gem', presets: [100, 500, 1200, 2500] },
            { id: 'gold', label: 'Gold coins', value: gold, set: setGold, rate: rates?.gold_per_unit ?? 2000, icon: 'coins', presets: [2000, 10000, 25000, 50000] },
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
          <label className="treasury-currency-label" htmlFor="payment-currency">Order currency</label>
          <select id="payment-currency" value={currency} disabled={busy} onChange={(event) => setCurrency(event.target.value)}>
            {Object.keys(settings.currencies).map((code) => <option key={code} value={code}>{code} — {({ EUR: 'Euro', GBP: 'Pound sterling', USD: 'US dollar' } as Record<string, string>)[code]}</option>)}
          </select>
          <p className="treasury-receipt-note">Your total and invoice use {currency}. These are fixed game prices, not a live exchange-rate service.</p>
          <dl><div><dt>Diamonds</dt><dd>{money(diamondCost)}</dd></div><div><dt>Gold coins</dt><dd>{money(goldCost)}</dd></div>
            <div className="treasury-total"><dt>Total value</dt><dd>{money(diamondCost + goldCost)}</dd></div></dl>
          <p className="treasury-mode"><b>{isCorefy ? (settings.test_mode ? 'Sterling Pay · test checkout' : 'Pay with Sterling Pay') : 'Manual purchase'}</b>
            {isCorefy ? 'EUR payments are completed on the provider’s hosted page. Goods are credited only after the server verifies payment.' : 'No card payment is collected. Confirming adds the selected currency to your account.'}
            {isCorefy && settings.test_mode && ' Test mode is active. No real payment is collected.'}</p>
          {isCorefy && <div className="treasury-consents">
            <label><input type="checkbox" checked={acceptTerms} onChange={(event) => setAcceptTerms(event.target.checked)} disabled={busy} required />
              <span>I agree to the <a href={`${API_BASE_URL}/legal/terms/page`} target="_blank" rel="noreferrer">Terms & Conditions</a> and acknowledge the <a href={`${API_BASE_URL}/legal/privacy/page`} target="_blank" rel="noreferrer">Privacy Policy</a>.</span></label>
            <label><input type="checkbox" checked={immediateDelivery} onChange={(event) => setImmediateDelivery(event.target.checked)} disabled={busy} required />
              <span>I expressly consent to immediate supply of the digital goods after payment and acknowledge that I lose my cancellation right once supply begins. My statutory rights for faulty or misdescribed content are unaffected.</span></label>
          </div>}
          <p className="treasury-receipt-note">{isCorefy ? 'After payment confirmation, a' : 'A'} PDF invoice and receipt will be sent to <strong>{player.user.email}</strong>.</p>
          <button className="button button-orange" type="submit" disabled={!amountsValid || busy || !enabled || (isCorefy && !consentGiven)}>
            {busy ? 'Preparing your order…' : isCorefy ? `Pay ${money(diamondCost + goldCost)}` : 'Confirm purchase'} <span aria-hidden="true">↗</span>
          </button>
          {!enabled && <p role="status">Purchases are currently paused.</p>}
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
              <small>{order.payment_test_mode ? 'Test payment · ' : ''}{order.invoice ? (order.invoice.sent_at ? 'Receipt sent' : 'Receipt pending') : 'Invoice after payment confirmation'}</small></span>
            {order.provider === 'corefy' && order.status === 'pending' && <div className="treasury-payment-actions">
              {order.checkout_url && <a href={order.checkout_url}>Continue payment ↗</a>}
              <button type="button" disabled={checkingId === order.id} onClick={() => { void checkPayment(order.id); }}>{checkingId === order.id ? 'Checking…' : 'Check payment'}</button>
            </div>}
            <button type="button" className="treasury-download" disabled={!order.invoice || downloadId === order.id} onClick={() => download(order)}>
              {downloadId === order.id ? 'Downloading…' : 'Invoice PDF'} <i className="fa-solid fa-arrow-down" aria-hidden="true" /></button>
          </li>)}</ul>}
      </section>
    </section>
  );
}
