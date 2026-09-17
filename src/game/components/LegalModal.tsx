import { useState } from 'react';

export type LegalDocType = 'terms' | 'privacy' | 'refunds' | 'security';

type LegalModalProps = {
  initialTab?: LegalDocType;
  language: 'EN' | 'RU';
  onClose: () => void;
};

export const COMPANY_INFO = {
  name: 'Crazy Defense Heroes Ltd',
  number: '14892341',
  address: '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom',
  email: 'info@crazydefenseheroes.co.uk',
  domain: 'crazydefenseheroes.co.uk',
};

export function LegalModal({ initialTab = 'terms', language, onClose }: LegalModalProps) {
  const [activeTab, setActiveTab] = useState<LegalDocType>(initialTab);

  return (
    <div
      className="modal-backdrop"
      role="presentation"
      onMouseDown={(event) => event.target === event.currentTarget && onClose()}
    >
      <section
        className="modal-card legal-card-comprehensive"
        role="dialog"
        aria-modal="true"
        aria-labelledby="legal-title"
      >
        <button className="modal-close" onClick={onClose} aria-label="Close policy">
          ×
        </button>

        <div className="legal-header">
          <p className="eyebrow">
            <span className="eyebrow-line" /> THE REALM LEGAL CODEX <span className="eyebrow-line" />
          </p>
          <h2 id="legal-title">
            {activeTab === 'terms' && (language === 'RU' ? 'Условия обслуживания' : 'Terms & Conditions')}
            {activeTab === 'privacy' && (language === 'RU' ? 'Политика конфиденциальности' : 'Privacy Policy')}
            {activeTab === 'refunds' && (language === 'RU' ? 'Политика возврата и платежей' : 'Refund & Payments Policy')}
            {activeTab === 'security' && (language === 'RU' ? 'Безопасность и PCI DSS' : 'Security & Compliance')}
          </h2>
          <span className="legal-effective-date">
            {language === 'RU'
              ? 'Дата вступления в силу: 17 сентября 2026 г. · Версия 2.4.0'
              : 'Effective date: September 17, 2026 · Version 2.4.0'}
          </span>
        </div>

        {/* Legal Navigation Tabs */}
        <div className="legal-tabs-bar">
          <button
            type="button"
            className={`legal-tab-btn ${activeTab === 'terms' ? 'active' : ''}`}
            onClick={() => setActiveTab('terms')}
          >
            <i className="fa-solid fa-scroll" style={{ marginRight: 6 }} />
            {language === 'RU' ? 'Условия' : 'Terms'}
          </button>
          <button
            type="button"
            className={`legal-tab-btn ${activeTab === 'privacy' ? 'active' : ''}`}
            onClick={() => setActiveTab('privacy')}
          >
            <i className="fa-solid fa-shield-halved" style={{ marginRight: 6 }} />
            {language === 'RU' ? 'Конфиденциальность' : 'Privacy'}
          </button>
          <button
            type="button"
            className={`legal-tab-btn ${activeTab === 'refunds' ? 'active' : ''}`}
            onClick={() => setActiveTab('refunds')}
          >
            <i className="fa-solid fa-credit-card" style={{ marginRight: 6 }} />
            {language === 'RU' ? 'Возврат и покупки' : 'Refunds & Store'}
          </button>
          <button
            type="button"
            className={`legal-tab-btn ${activeTab === 'security' ? 'active' : ''}`}
            onClick={() => setActiveTab('security')}
          >
            <i className="fa-solid fa-lock" style={{ marginRight: 6 }} />
            {language === 'RU' ? 'PCI DSS & Защита' : 'PCI DSS & Security'}
          </button>
        </div>

        {/* Legal Content Scroll Area */}
        <div className="legal-scroll-body">
          {activeTab === 'terms' && (
            <div className="legal-text-section">
              {language === 'RU' ? (
                <>
                  <h3>1. Предмет соглашения и принятие условий</h3>
                  <p>
                    Настоящие Условия обслуживания («Условия») регулируют доступ к многопользовательской онлайн-игре
                    <strong> Crazy Defense Heroes</strong> (домен: <strong>{COMPANY_INFO.domain}</strong>), управляемой компанией{' '}
                    <strong>{COMPANY_INFO.name}</strong> (рег. номер: {COMPANY_INFO.number}), и всем связанным с ней веб-сервисам,
                    учетным записям, сокровищнице и внутриигровым сервисам. Создавая учетную запись или совершая покупки, вы подтверждаете свое
                    полное и безоговорочное согласие с настоящими Условиями.
                  </p>

                  <h3>2. Учетная запись и безопасность</h3>
                  <p>
                    Пользователь несет единоличную ответственность за сохранность своих учетных данных (логина и пароля).
                    Передача доступа к аккаунту третьим лицам строго запрещена. Сервис оставляет за собой право блокировать
                    учетные записи при обнаружении признаков взлома, мошенничества или попыток несанкционированного доступа.
                  </p>

                  <h3>3. Виртуальные предметы, валюты и сундуки</h3>
                  <p>
                    Все внутриигровые ценности, включая Кристаллы (Diamonds), Золотые Монеты (Soft Currency), Карты Башен
                    (Tower Cards), Бусты и Сундуки, являются цифровым нематериальным контентом с ограниченной лицензией на
                    использование исключительно в рамках игрового процесса. Виртуальные валюты не подлежат обмену на
                    реальные фиатные денежные средства за пределами сервиса.
                  </p>

                  <h3>4. Правила честной игры (Fair Play Code)</h3>
                  <p>
                    Категорически запрещается использование стороннего программного обеспечения для автоматизации игры (ботов,
                    кликеров), эксплойтов уязвимостей игрового движка, а также манипуляций сетевыми пакетами контрольных точек
                    (checkpoints). Нарушение ведет к перманентной блокировке учетной записи без права на компенсацию.
                  </p>

                  <h3>5. Интеллектуальная собственность</h3>
                  <p>
                    Все графические материалы, товарные знаки, звуковое сопровождение, программный код, механики и логотипы
                    Crazy Defense Heroes являются объектами авторского права <strong>{COMPANY_INFO.name}</strong> и защищены
                    международным законодательством.
                  </p>
                </>
              ) : (
                <>
                  <h3>1. Scope and Acceptance of Terms</h3>
                  <p>
                    These Terms of Service (&quot;Terms&quot;) govern your access to and use of the <strong>Crazy Defense Heroes</strong>{' '}
                    service (domain: <strong>{COMPANY_INFO.domain}</strong>), operated by <strong>{COMPANY_INFO.name}</strong> (Company No.{' '}
                    {COMPANY_INFO.number}), game client, chest vault, treasury, and all associated interactive services. By creating an
                    account or using our platform, you agree to be bound by these Terms.
                  </p>

                  <h3>2. Account Registration and Security</h3>
                  <p>
                    You are responsible for maintaining the confidentiality of your login credentials. You agree to notify us
                    immediately of any unauthorized access or security breach. Accounts are non-transferable, and sharing
                    credentials violates these Terms.
                  </p>

                  <h3>3. Virtual Currencies, Items and Digital Goods</h3>
                  <p>
                    In-game items, including Diamonds, Soft Currency, Tower Cards, Boosts, and Chests, represent a limited,
                    revocable, non-transferable license to use digital features strictly inside the Crazy Defense Heroes
                    ecosystem. Virtual assets possess no monetary value outside of the platform.
                  </p>

                  <h3>4. Fair Play & Anti-Cheat Policy</h3>
                  <p>
                    Any use of automated scripts, cheat software, memory injectors, checkpoint manipulation, or unauthorized
                    modifications is strictly prohibited and results in immediate permanent account termination.
                  </p>

                  <h3>5. Intellectual Property</h3>
                  <p>
                    All artwork, lore, soundscapes, game assets, trademarks, and source code are the exclusive property of{' '}
                    <strong>{COMPANY_INFO.name}</strong> and are protected under international copyright and intellectual property treaties.
                  </p>
                </>
              )}
            </div>
          )}

          {activeTab === 'privacy' && (
            <div className="legal-text-section">
              {language === 'RU' ? (
                <>
                  <h3>1. Какую информацию мы собираем</h3>
                  <p>Мы собираем только необходимые данные для предоставления качественного игрового процесса:</p>
                  <ul>
                    <li>Учетные данные: имя, фамилия, адрес электронной почты, дата рождения, номер телефона.</li>
                    <li>Данные профиля и адреса для формирования официальных фискальных инвойсов.</li>
                    <li>Игровой прогресс: рекорды волн, побежденные враги, уровни башен, баланс кошелька.</li>
                    <li>Технические параметры: IP-адрес, тип браузера, токены безопасности сессии.</li>
                  </ul>

                  <h3>2. Использование и защита персональных данных</h3>
                  <p>
                    Персональные данные используются исключительно для авторизации, безопасной синхронизации прогресса,
                    выставления счетов и технической поддержки. Мы <strong>никогда не продаем и не передаем</strong> ваши
                    данные рекламным сетям или неавторизованным третьим лицам.
                  </p>

                  <h3>3. Соответствие регламенту GDPR и права пользователей</h3>
                  <p>
                    В соответствии с европейским регламентом GDPR (General Data Protection Regulation), каждый пользователь
                    имеет право на доступ к своим данным, право на исправление, а также право на полное удаление учетной
                    записи (&quot;право на забвение&quot;) по письменному запросу на <strong>{COMPANY_INFO.email}</strong>.
                  </p>

                  <h3>4. Файлы Cookie и локальное хранилище</h3>
                  <p>
                    Мы используем защищенное локальное хранилище браузера (localStorage) и сессионные токены для сохранения
                    вашего статуса авторизации и выбранного языка интерфейса.
                  </p>
                </>
              ) : (
                <>
                  <h3>1. Information We Collect</h3>
                  <p>We collect essential data required to maintain your game account and secure transaction records:</p>
                  <ul>
                    <li>Account details: full name, email address, date of birth, contact telephone number.</li>
                    <li>Billing and invoice address for legally compliant VAT and fiscal receipt generation.</li>
                    <li>Telemetry and game state: wave milestones, tower card inventories, battle logs, wallet balances.</li>
                    <li>Security records: IP address, device fingerprints, encrypted authentication tokens.</li>
                  </ul>

                  <h3>2. Purpose of Data Processing</h3>
                  <p>
                    Data is processed strictly for authentication, state synchronization across devices, customer assistance,
                    and fraud prevention. We <strong>never sell, lease, or monetize</strong> player data to third parties.
                  </p>

                  <h3>3. GDPR Compliance & Player Rights</h3>
                  <p>
                    Under European GDPR regulations, you hold the right to request a full export of your personal data,
                    rectification of inaccuracies, and complete deletion (&quot;right to be forgotten&quot;) upon contacting{' '}
                    <strong>{COMPANY_INFO.email}</strong>.
                  </p>

                  <h3>4. Cookies and Local Storage</h3>
                  <p>
                    We employ cryptographic bearer tokens and local browser storage to keep you logged into the watch and remember
                    your language and audio preferences.
                  </p>
                </>
              )}
            </div>
          )}

          {activeTab === 'refunds' && (
            <div className="legal-text-section">
              {language === 'RU' ? (
                <>
                  <h3>1. Доставка цифрового контента</h3>
                  <p>
                    Все покупки пакетов кристаллов, сундуков и игровых бустов доставляются мгновенно на ваш аккаунт сразу
                    после успешного подтверждения транзакции платежным шлюзом.
                  </p>

                  <h3>2. Политика возврата денежных средств (Refund Policy)</h3>
                  <p>
                    В соответствии с законодательством о защите прав потребителей в отношении цифрового контента,
                    предоставляемого мгновенно, право на отзыв аннулируется с момента начала загрузки или начисления
                    цифровых ценностей на баланс.
                  </p>
                  <p>
                    В случае технических сбоев (например, двойное списание или недоставка кристаллов) вы можете обратиться в
                    нашу службу поддержки по адресу <strong>{COMPANY_INFO.email}</strong> в течение 14 дней с предоставлением номера
                    транзакции для проведения ручной сверки и полного возврата средств.
                  </p>

                  <h3>3. Платежные методы и валюты</h3>
                  <p>
                    Мы принимаем к оплате международные банковские карты Visa и Mastercard. Все платежи обрабатываются через
                    защищенные протоколы с поддержкой 3D-Secure 2.0.
                  </p>
                </>
              ) : (
                <>
                  <h3>1. Instant Digital Content Delivery</h3>
                  <p>
                    All virtual diamond packs, tactical chests, and game enhancements are delivered immediately to your active
                    game wallet upon successful authorization from the payment provider.
                  </p>

                  <h3>2. Digital Goods Refund Policy</h3>
                  <p>
                    Under applicable statutory consumer regulations for immediate digital content, the right of withdrawal is
                    waived once the digital asset has been credited to your active wallet or unboxed.
                  </p>
                  <p>
                    In the event of duplicate billing or verifiable technical failures preventing delivery, contact{' '}
                    <strong>{COMPANY_INFO.email}</strong> within 14 calendar days with your transaction reference for verification
                    and full refund.
                  </p>

                  <h3>3. Supported Payment Methods</h3>
                  <p>
                    We accept Visa and Mastercard credit/debit cards with mandatory 3D-Secure 2.0 multi-factor verification.
                  </p>
                </>
              )}
            </div>
          )}

          {activeTab === 'security' && (
            <div className="legal-text-section">
              {language === 'RU' ? (
                <>
                  <h3>1. Стандарты безопасности PCI DSS Level 1</h3>
                  <p>
                    <strong>{COMPANY_INFO.name}</strong> строго следует международным стандартам безопасности индустрии платежных карт
                    <strong> PCI DSS (Payment Card Industry Data Security Standard)</strong>.
                  </p>
                  <p>
                    Данные банковских карт никогда не сохраняются и не обрабатываются напрямую на наших игровых серверах — вся
                    обработка токенизируется сертифицированными банковскими шлюзами.
                  </p>

                  <h3>2. Сквозное шифрование SSL / TLS 1.3</h3>
                  <p>
                    Все соединения между вашим браузером и серверами <strong>{COMPANY_INFO.domain}</strong> защищены 256-битным
                    протоколом шифрования TLS 1.3 с использованием надежных криптографических сертификатов.
                  </p>

                  <h3>3. Защита от мошенничества и DDoS</h3>
                  <p>
                    Наша инфраструктура защищена автоматическими системами мониторинга аномалий, фильтрацией DDoS-атак и
                    контролем целостности игровых контрольных точек.
                  </p>
                </>
              ) : (
                <>
                  <h3>1. PCI DSS Level 1 Compliance</h3>
                  <p>
                    <strong>{COMPANY_INFO.name}</strong> adheres strictly to{' '}
                    <strong>Payment Card Industry Data Security Standards (PCI DSS)</strong>. Payment credentials are
                    tokenized and processed exclusively through certified Level 1 compliant financial gateways.
                  </p>

                  <h3>2. 256-bit SSL / TLS 1.3 Encryption</h3>
                  <p>
                    All communications across <strong>{COMPANY_INFO.domain}</strong> are shielded with end-to-end 256-bit TLS 1.3
                    encryption, preventing man-in-the-middle tampering.
                  </p>

                  <h3>3. Continuous Infrastructure Auditing</h3>
                  <p>
                    We run automated anomaly detection, DDoS mitigation layers, and tamper-proof database replication to keep
                    your progress and assets protected around the clock.
                  </p>
                </>
              )}
            </div>
          )}

          {/* Corporate Entity Details Block */}
          <div className="legal-company-box">
            <div className="legal-company-title">
              <i className="fa-solid fa-building-columns" style={{ marginRight: 6 }} />
              {language === 'RU' ? 'ЮРИДИЧЕСКАЯ ИНФОРМАЦИЯ О КОМПАНИИ' : 'OFFICIAL COMPANY IDENTIFICATION'}
            </div>
            <div className="legal-company-grid">
              <div>
                <span>{language === 'RU' ? 'Наименование:' : 'Company Name:'}</span>
                <strong>{COMPANY_INFO.name}</strong>
              </div>
              <div>
                <span>{language === 'RU' ? 'Регистрационный номер:' : 'Company Number:'}</span>
                <strong>{COMPANY_INFO.number}</strong>
              </div>
              <div>
                <span>{language === 'RU' ? 'Юридический адрес:' : 'Registered Address:'}</span>
                <strong>{COMPANY_INFO.address}</strong>
              </div>
              <div>
                <span>{language === 'RU' ? 'Официальный Email:' : 'Contact Email:'}</span>
                <strong>
                  <a href={`mailto:${COMPANY_INFO.email}`}>{COMPANY_INFO.email}</a>
                </strong>
              </div>
              <div>
                <span>{language === 'RU' ? 'Веб-домен:' : 'Web Domain:'}</span>
                <strong>https://{COMPANY_INFO.domain}</strong>
              </div>
            </div>
          </div>
        </div>

        {/* Footer actions */}
        <div className="legal-modal-footer">
          <div className="legal-badges-row">
            <span className="security-tag"><i className="fa-solid fa-lock" style={{ marginRight: 4 }} /> 256-BIT SSL</span>
            <span className="security-tag"><i className="fa-solid fa-shield-halved" style={{ marginRight: 4 }} /> PCI DSS COMPLIANT</span>
            <span className="security-tag"><i className="fa-solid fa-scale-balanced" style={{ marginRight: 4 }} /> GDPR READY</span>
          </div>
          <button type="button" className="button button-orange" onClick={onClose}>
            {language === 'RU' ? 'Понятно / Закрыть ↗' : 'Acknowledge & Close ↗'}
          </button>
        </div>
      </section>
    </div>
  );
}
