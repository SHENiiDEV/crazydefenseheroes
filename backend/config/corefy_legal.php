<?php

return [
    'terms' => [
        0 => [
            'Crazy Defense Hereoes at {company.domain} is operated by {company.name}, company registration number {company.number}, registered address {company.address}. Contact: {company.email}. We supply access to a browser tower-defense game and personal licences to use its digital goods.',
            'Registration creates a free account. Before ordering you can review and correct quantities and the final EUR total in Treasury. An order remains pending while payment is being verified. We accept and deliver the digital goods when payment has been confirmed by the provider and recorded by our server. Merely returning to our website is not proof of payment. Your order history and PDF receipt record a confirmed purchase.',
        ],
        1 => [
            'Online checkout uses the Sterling Pay / Corefy hosted payment page. Only EUR is currently enabled. Enter payment details on the provider’s page, not on this website. The payment methods actually available to your order are displayed there before authorisation. We do not claim acceptance of methods that are not offered for that order.',
            'Treasury displays the final EUR total before the Pay button, including the listed digital goods. Prices are fixed merchant prices, not foreign-exchange quotations. Individual lines are rounded up to the nearest cent. We do not add a platform payment surcharge or create a subscription or recurring payment. Your bank may impose its own conversion charges.',
            'Payment authorisation is requested at the hosted checkout. Digital delivery follows confirmed successful payment, not order creation. In test mode the interface labels the checkout as a test and no real funds are collected. Manual no-charge allocation is not available through this checkout mode.',
        ],
        5 => [
            'Nothing here removes your mandatory consumer rights. For faulty, undelivered or misdescribed paid digital content, the Consumer Rights Act 2015 may entitle you to repair or replacement and, where applicable, a price reduction or refund. Contact {company.email} with your account and order reference.',
            'Before ordering immediate digital delivery, you separately give express consent to supply after payment and acknowledge that the applicable cancellation right is lost once supply begins. The recorded consent and applicable terms version are confirmed in your receipt. This does not remove rights for defective or misdescribed content. Accepting general terms alone is not treated as this separate consent.',
            'Refund requests are investigated using the provider and account records. Any refund due is made to the original method unless otherwise agreed or required by law, without a platform refund fee and within applicable statutory deadlines. A refund notification may require support review of already consumed virtual goods; this review does not remove statutory rights.',
        ],
    ],
    'privacy' => [
        1 => [
            'We process the account, contact, billing address, authentication, game, inventory and order information described in this service. For online checkout, necessary customer identity, contact and billing details, order reference, EUR amount and payment mode are sent to Sterling Pay / Corefy. Payment card details are entered on the provider’s hosted page; our application does not collect or store full card details.',
            'We use these data to perform the account and digital-goods contract, provide payment and delivery, meet applicable accounting/legal duties, protect the service and resolve disputes. Optional marketing consent is separate from transaction processing. Transactional receipts are service messages.',
        ],
        3 => [
            'Relevant data are processed by our hosting/database services, Sterling Pay / Corefy for hosted payment processing and the configured Private Email service for transactional messages. They receive only the information needed for those tasks, subject to applicable data protection requirements. We do not sell player information to advertisers.',
            'Where a restricted international transfer occurs, applicable transfer requirements and safeguards must be satisfied. Contact {company.email} for provider or transfer information. Passwords are hashed, authenticated account actions are protected and invoices are account-restricted. No system is guaranteed completely secure.',
        ],
        5 => [
            'You may contact {company.email} about privacy and complain to the UK Information Commissioner’s Office at https://ico.org.uk/make-a-complaint/. Where applicable, you can also contact your local supervisory authority.',
            'This notice covers the configured hosted checkout and the version shown above. We communicate material changes where required. Test and live payment modes are distinguished in checkout and account records.',
        ],
    ],
    'refunds' => [
        1 => [
            'Treasury orders use Sterling Pay / Corefy hosted checkout in EUR. The server verifies payment before crediting digital goods or issuing the PDF receipt. A pending, declined or partly paid invoice does not deliver the order. Returning from the hosted page does not establish payment. A clearly labelled test checkout does not collect real funds.',
        ],
        2 => [
            'Statutory rights under the Consumer Rights Act 2015 and applicable distance-selling rules remain. Faulty, undelivered or misdescribed paid digital content may entitle you to repair, replacement, a price reduction or refund. Immediate supply requires the separate express consent and acknowledgement recorded at checkout; those acknowledgements do not remove rights relating to faults. There is no blanket no-refunds rule or compulsory store-credit remedy.',
        ],
    ],
    'security' => [
        1 => [
            'Sterling Pay / Corefy hosts the payment page. Our application does not collect full card details. The server validates the callback signature and independently reads the provider invoice to check the reference, currency, expected amount, actual settled amount and test/live mode before delivery. Browser redirects are not payment proof. We do not claim PCI DSS certification for our website.',
        ],
    ],
];
