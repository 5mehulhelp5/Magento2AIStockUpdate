<?php
/**
 * Email Notifier
 *
 * Magento 2 Stock prediction using AI
 * @author Hassan Ali Shahzad
 * email: levosoft786@gmail.com
 */
declare(strict_types=1);

namespace AI\StockPredict\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\Store;
use Psr\Log\LoggerInterface;

/**
 * EmailNotifier
 *
 * Sends stockout alert notification emails to the configured recipient.
 * Silently skips sending if email alerts are disabled or no recipient is set.
 * Never throws — all errors are logged.
 */
class EmailNotifier
{
    /** @var string Email template identifier registered in email_templates.xml */
    private const EMAIL_TEMPLATE_ID = 'ai_stockpredict_alert';

    /** @var string Config path for the general sender email address */
    private const XML_PATH_SENDER_EMAIL = 'trans_email/ident_general/email';

    /** @var string Config path for the general sender name */
    private const XML_PATH_SENDER_NAME = 'trans_email/ident_general/name';

    /**
     * Constructor
     *
     * @param TransportBuilder $transportBuilder Mail transport builder
     * @param Settings $settings Module configuration
     * @param ScopeConfigInterface $scopeConfig Magento scope config
     * @param LoggerInterface $logger Logger instance
     */
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly Settings $settings,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Send a stockout alert notification email
     *
     * Checks email_enabled and email_recipient config before sending.
     * On any error, logs and returns — never throws.
     *
     * @param Alert $alert The alert model to notify about
     * @return void
     */
    public function sendAlert(Alert $alert): void
    {
        if (!$this->settings->isEmailEnabled()) {
            return;
        }

        $recipient = $this->settings->getEmailRecipient();

        if (empty($recipient)) {
            $this->logger->warning(
                '[STOCKPREDICT][EMAIL] Email alerts enabled but no recipient address configured.'
            );
            return;
        }

        try {
            $senderEmail = (string)$this->scopeConfig->getValue(self::XML_PATH_SENDER_EMAIL);
            $senderName  = (string)($this->scopeConfig->getValue(self::XML_PATH_SENDER_NAME) ?: 'StockPredict Alerts');

            if (empty($senderEmail)) {
                $senderEmail = 'noreply@example.com';
            }

            $transport = $this->transportBuilder
                ->setTemplateIdentifier(self::EMAIL_TEMPLATE_ID)
                ->setTemplateOptions([
                    'area'  => 'adminhtml',
                    'store' => Store::DEFAULT_STORE_ID,
                ])
                ->setTemplateVars([
                    'alert_sku'      => (string)$alert->getData('sku'),
                    'alert_product'  => (string)($alert->getData('product_name') ?: '—'),
                    'alert_severity' => strtoupper((string)$alert->getData('severity')),
                    'alert_days'     => $alert->getData('days_until_stockout') !== null
                        ? (int)$alert->getData('days_until_stockout')
                        : 'N/A',
                    'alert_qty'      => number_format((float)$alert->getData('current_qty'), 2),
                    'alert_created'  => (string)($alert->getData('created_at') ?: date('Y-m-d H:i:s')),
                ])
                ->setFrom(['email' => $senderEmail, 'name' => $senderName])
                ->addTo($recipient)
                ->getTransport();

            $transport->sendMessage();

            $this->logger->info(sprintf(
                '[STOCKPREDICT][EMAIL] Alert email sent for SKU %s (%s severity) to %s',
                $alert->getData('sku'),
                $alert->getData('severity'),
                $recipient
            ));

        } catch (\Exception $e) {
            $this->logger->error(
                '[STOCKPREDICT][EMAIL] Failed to send alert email for SKU '
                . $alert->getData('sku') . ': ' . $e->getMessage()
            );
        }
    }
}
