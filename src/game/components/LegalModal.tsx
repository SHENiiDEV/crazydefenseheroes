import { useEffect, useState } from 'react';
import { API_BASE_URL, CompanyInfo, LegalDocument, getLegalDocument } from '../../lib/api';

export type LegalDocType = 'terms' | 'privacy' | 'refunds' | 'security';
export const DEFAULT_COMPANY_INFO: CompanyInfo = {
  name: 'PROTECT YOUR ASSETS TODAY LTD', number: '15969857',
  address: 'Academy House, 11 Dunraven Place, Bridgend, Mid Glamorgan, CF31 1JF',
  email: 'info@crazydefensehereoes.co.uk', domain: 'crazydefensehereoes.co.uk',
};
const labels: Record<LegalDocType, string> = { terms: 'Terms & Conditions', privacy: 'Privacy Policy', refunds: 'Delivery & Refunds', security: 'Security' };

export function LegalModal({ initialTab = 'terms', company = DEFAULT_COMPANY_INFO, onClose }: {
  initialTab?: LegalDocType; company?: CompanyInfo; onClose: () => void;
}) {
  const [activeTab, setActiveTab] = useState(initialTab);
  const [document, setDocument] = useState<LegalDocument | null>(null);
  const [error, setError] = useState('');
  const fullPolicyUrl = API_BASE_URL.replace(/\/api\/v1\/?$/, '') + '/legal/' + activeTab;
  useEffect(() => {
    const abort = new AbortController();
    setDocument(null);
    setError('');
    getLegalDocument(activeTab, abort.signal).then(setDocument).catch((reason: Error) => {
      if (!abort.signal.aborted) setError(reason.message);
    });
    return () => abort.abort();
  }, [activeTab]);

  return <div className="modal-backdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
    <section className="modal-card legal-card-comprehensive" role="dialog" aria-modal="true" aria-labelledby="legal-title">
      <button className="modal-close" onClick={onClose} aria-label="Close policy">×</button>
      <div className="legal-header"><p className="eyebrow">THE REALM LEGAL CODEX</p><h2 id="legal-title">{labels[activeTab]}</h2>
        {document && <span className="legal-effective-date">Updated {document.version}</span>}</div>
      <nav className="legal-tabs-bar" aria-label="Policies">{(Object.keys(labels) as LegalDocType[]).map((tab) =>
        <button type="button" key={tab} className={`legal-tab-btn ${activeTab === tab ? 'active' : ''}`} aria-pressed={activeTab === tab} onClick={() => setActiveTab(tab)}>{labels[tab]}</button>)}</nav>
      <div className="legal-scroll-body">
        {!document && !error && <p role="status">Loading current policy…</p>}
        {error && <p role="alert">{error}</p>}
        <div className="legal-text-section">{document?.sections.map((section) => <section key={section.title}><h3>{section.title}</h3>{section.paragraphs.map((paragraph) => <p key={paragraph}>{paragraph}</p>)}</section>)}</div>
        <div className="legal-company-box"><strong>{company.name}</strong><p>Company registration number: {company.number}<br />{company.address}</p>
          <a href={`mailto:${company.email}`}>{company.email}</a> · <a href={`https://${company.domain}`}>{company.domain}</a></div>
        {activeTab === 'privacy' && <p><a href="https://ico.org.uk/make-a-complaint/" target="_blank" rel="noreferrer">Contact the UK Information Commissioner’s Office</a></p>}
      </div>
      <div className="legal-modal-footer"><a href={fullPolicyUrl} target="_blank" rel="noreferrer">Open full policy page</a><button className="button button-orange" onClick={onClose}>Close</button></div>
    </section>
  </div>;
}
