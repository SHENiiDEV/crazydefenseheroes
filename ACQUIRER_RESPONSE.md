# Acquirer review response

Prepared: 22 October 2026

Release note for the operator: the changes described below have been implemented and tested locally. Deploy the release and check the production website before forwarding this response as a statement of live functionality. Real payment processing is not connected. Confirm the final commercial policies with a UK legal adviser before enabling paid checkout.

## 1. Selecting EUR, GBP or USD

The signed-in customer opens **Treasury & Top-Up** and uses **Order currency** to choose EUR, GBP or USD before confirming an allocation. The displayed line prices, total, saved order, PDF invoice and email receipt use the selected currency. Prices are configured separately for each currency; this is not a live foreign-exchange service.

The website currently operates in manual allocation mode, without an active acquirer. Confirmation credits the selected virtual items but does not collect money, request card details or create a payment obligation. The three currencies are supported for reference-value orders; their presence must not be interpreted as evidence of live EUR, GBP or USD payment processing. Accepted payment methods, taxes and any charges for future paid checkout will need to be disclosed and implemented before real payments are enabled.

## 2. UK GDPR in the Privacy Policy

The previous wording did not adequately identify the UK framework. Section 3 now expressly addresses the UK GDPR and Data Protection Act 2018, as amended, including applicable amendments under the Data (Use and Access) Act 2025. EU GDPR is mentioned only where its territorial requirements apply. The notice also covers the controller's identity, processing purposes and lawful bases, service providers, retention, browser storage, individual rights and the right to complain to the ICO.

The UK framework is described by the [UK Government](https://www.gov.uk/data-protection/the-data-protection-act); its guidance explains that the [2025 Act amends rather than replaces the existing framework](https://www.gov.uk/guidance/data-use-and-access-act-2025-data-protection-and-privacy-changes).

## 3. Silver Cache and Gold Legendary Chest delivery

The previous implementation was inconsistent: a browser fallback could display apparent rewards when the server operation failed, and the advertised contents were not consistently aligned with persisted inventory. That was a defect, not intended product behaviour. The fallback has been removed. An unsuccessful request now displays an error instead of inventing a reward.

The Vault now displays fixed contents before opening:

| Product | Account-currency cost | Delivered contents |
| --- | --- | --- |
| Bronze Stash | 50 Gold | 1 Ember Archer card and 1 Speed Burst charge |
| Silver Cache | 120 Gold | 1 Stone Warden card and 2 Fortify charges |
| Gold Legendary Chest | 250 Gold + 10 Diamonds | 1 Vault Mage card and 3 Meteor Strike charges |

The server deducts the cost and saves the corresponding inventory changes in one database transaction. A new card unlocks its tower at level 1; a duplicate increases the owned tower's level by one. Insufficient funds do not deduct a partial price or grant partial contents. There are no random contents, cash prizes or advertised rarity probabilities.

## 4. Bronze Stash and the currency-generation loop

The previous Gold-return mechanic was an economy defect. Bronze Stash no longer returns Gold or Diamonds. Its purpose is to obtain or improve the Ember Archer and obtain a usable Speed Burst charge. Each opening reduces account Gold by 50. Repeated openings therefore cannot generate additional Gold.

## 5. Nature of the game and losing a run

This is a single-player browser tower-defense game for entertainment. Players deploy towers beside a fixed enemy path, choose targeting priorities and use upgrades and limited boost charges to defend the keep. Enemy health and wave size increase over time.

A run starts with 20 lives. Each enemy reaching the keep removes a life. At zero lives the run ends, wave progression stops and no further rewards are generated. The player can start a fresh run. “Endless” means no predefined final wave, not an inability to lose.

Battle Supply is the temporary deployment budget for the current run, starting at 150. It resets on a new run and is separate from account Gold. Completed waves earn account Gold only after their checkpoints are saved: 10 + twice the wave number, capped at 1,000 Gold per wave. A checkpoint is rewarded once per run/wave. The landing-page practice demo does not award account Gold.

## 6. Uses and commercial rationale for Diamonds and Gold

Diamonds can be spent on the Gold Legendary Chest, which requires 10 Diamonds in addition to 250 Gold, and on an owned tower's upgrade at 5 Diamonds multiplied by its current level. The alternative upgrade price is 80 Gold multiplied by its current level. The Arsenal shows both options and the account wallet is debited by the selected amount.

Boost charges are usable in the Battle Arena: Speed Burst doubles tower firing speed for 10 seconds; Fortify restores up to 5 lives, capped at 20; Meteor Strike deals 100 damage to each enemy currently on the map. Activation consumes one owned charge.

Gold supports chest opening and tower upgrades; Diamonds provide an additional route to upgrades and access to the Gold chest bundle. Optional top-ups allow customers to obtain these features without waiting to earn Gold through completed waves. They do not guarantee victory. Under the current manual mode these are no-charge allocations, not genuine processed sales.

Neither currency is withdrawable, transferable to other players, redeemable for money, an investment or a means of winning cash. The Bronze Stash no longer creates a currency-farming rationale for avoiding normal progression.

## 7. Retaining earned Gold and inventory

Confirmed balances, tower levels and unused boost charges are stored in the player's server-side account and remain available after signing out, returning later or losing a run. They have no scheduled expiry under the current product rules. Only Battle Supply resets with the run.

The interface distinguishes pending reward saves from confirmed balances. A failed connection triggers checkpoint retries using browser storage; the customer should keep the browser data until confirmation. The system does not represent an unsaved local reward as a confirmed server balance. Repeated submission of the same saved checkpoint does not credit it twice.

## 8. Corporate information in the members' area

The omission has been corrected. Every members-area tab now includes the operator's legal name, registration number, registered address, support email, domain and policy links:

PROTECT YOUR ASSETS TODAY LTD, company number 15969857. Registered address: Academy House, 11 Dunraven Place, Bridgend, Mid Glamorgan, CF31 1JF. Contact: info@crazydefensehereoes.co.uk. Website: crazydefensehereoes.co.uk.

These details are read from the backend company configuration, not maintained as separate legal text in each tab.

## 9. Terms & Conditions: commercial relationship and virtual goods

The revised Terms identify the operator and explain account registration, order review and confirmation, personal licences to digital goods, exact chest contents, duplicate-card upgrades, currency acquisition and spending, retained balances, lack of scheduled expiry and the absence of cash redemption. The current manual mode is expressly distinguished from any future paid checkout. Terms in the registration/member interface and the public policy pages come from the same versioned server documents.

## 10. Payment methods, currencies, taxes, timing and fees

Section 2 states that no card payment method is currently active, no money or tax is collected in manual mode and no recurring payment or payment obligation is created. It identifies EUR, GBP and USD, fixed reference pricing and line-level rounding up to the nearest cent or penny.

It also explains that any future paid checkout must show the actual accepted methods and the final total, applicable taxes and additional charges before an order creates an obligation to pay. Payment authorisation would precede delivery. Possible charges imposed independently by a customer's bank are distinguished from platform charges. We do not claim present card-network acceptance or PCI DSS certification.

## 11. Delivery timeframes and customer rights

Digital goods are delivered to the signed-in account, normally within seconds of successful server confirmation. PDF invoices are available in Treasury and emailed separately. Email delay does not prevent use of already credited items. Customers are directed to support with their order reference if delivery remains missing, including after 15 minutes; this is troubleshooting guidance, not a claim deadline.

The Terms and Delivery & Refunds policy preserve statutory rights for faulty, misdescribed or undelivered paid digital content, rather than applying a blanket no-refunds rule. A loss of an applicable digital-content cancellation right requires the legally required prior express consent, acknowledgement and confirmation; accepting general terms alone is not treated as that consent. Manual allocations have no collected payment to refund.

Relevant sources are the [Consumer Rights Act 2015, digital content provisions](https://www.legislation.gov.uk/ukpga/2015/15/part/1/chapter/3) and [Consumer Contracts Regulations 2013, regulation 37](https://www.legislation.gov.uk/uksi/2013/3134/regulation/37).

## 12. Governing law and disputes

Section 8 expressly selects the law of England and Wales while preserving applicable mandatory protections of consumers' habitual residence. Jurisdiction is non-exclusive and does not remove a consumer's applicable right to use their home courts.

Complaints can be submitted by email or to the registered address. The stated service aim is acknowledgement within three working days and a substantive response within 14 days, with an explanation if investigation takes longer. The Terms provide for relevant ADR information where required, do not impose compulsory arbitration and do not exclude court access.

## Release verification and limitations

Automated backend tests cover all three order currencies and invoice currency, exact chest delivery and deductions, removal of Bronze currency farming, Diamond upgrades, charge consumption, saved wave rewards, authentication and consistent legal documents. Local browser checks cover currency totals, chest success/failure handling, the members-area company information, mobile layout, boost effects and defeat at zero lives.

The checks do not demonstrate production deployment, real acquirer processing, successful delivery to an external mailbox, a legal certification, or a fully server-authoritative anti-cheat system. Production SMTP and the supervised receipt queue must be checked separately after deployment; future paid checkout requires its own implementation and review.
