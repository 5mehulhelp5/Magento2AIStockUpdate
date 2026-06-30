# Magento 2 StockPredict

**AI-powered inventory forecasting for Magento 2**

[![Magento 2.4+](https://img.shields.io/badge/Magento-2.4+-orange.svg)](https://magento.com/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1+-blue.svg)](https://php.net/)
[![AI Powered](https://img.shields.io/badge/AI-Gemini%20%7C%20OpenAI%20%7C%20Claude-purple.svg)](https://console.anthropic.com/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

StockPredict helps Magento store owners predict stockouts before they happen. It analyzes sales history, estimates demand per SKU, and creates alerts when stock is likely to run low. If you enable AI, the module can also generate demand forecasts and short reorder recommendations.

---

## What it does

### Forecasting
- Reviews Magento `sales_order` data to measure SKU sales velocity
- Calculates demand for the next **7, 30, and 90 days**
- Detects whether sales are **rising**, **stable**, or **declining**
- Predicts the stockout date and the days left in stock
- Assigns a confidence score based on sales volume

### Alerts
- Supports four severity levels:

| Severity | Default Threshold | Meaning |
|---|---|---|
| **Critical** | ≤ 7 days | Stockout is near |
| **Warning** | ≤ 14 days | Reorder soon |
| **Watch** | ≤ 30 days | Plan ahead |
| **OK** | > 30 days | Stock is healthy |

- Keeps one open alert per SKU
- Supports `open`, `acknowledged`, and `resolved` states
- Can send email alerts for critical and warning items

### AI support
The module works without AI, but you can enable it for better forecasting.

Supported providers:

| Provider | Model | Notes |
|---|---|---|
| **Google Gemini** | gemini-2.0-flash | Free tier available |
| **OpenAI** | GPT-4o-mini | Low-cost option |
| **Anthropic Claude** | Claude Sonnet 4.6 | Strong accuracy |

AI can add:
- `forecast_30d`
- `trend`
- `confidence`
- `recommendation`

---

## Admin features
- **Dashboard** — quick summary of critical, warning, watch, and OK SKUs
- **Forecasts Grid** — list of forecasted SKUs with stockout dates and recommendations
- **Forecast Detail** — full breakdown for a single SKU
- **Alerts Grid** — manage alerts with acknowledge and resolve actions
- **Settings** — configuration under **Stores → Configuration → AI → StockPredict**

---

## Installation

### Manual installation
```bash
mkdir -p app/code/AI/StockPredict
# Copy module files to app/code/AI/StockPredict/
php bin/magento module:enable AI_StockPredict
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
```

### Cron setup
Run Magento cron every minute:

```bash
* * * * * php /path/to/magento/bin/magento cron:run
```

---

## Quick start

### 1) Enable the module
**Admin → Stores → Configuration → AI → StockPredict → General Settings**

- Enable Module: `Yes`
- Enable Logging: `Yes` if you want to review runs
- Data Retention: `90` days

### 2) Set alert thresholds
**Admin → Stores → Configuration → AI → StockPredict → Alert Thresholds**

| Setting | Default |
|---|---|
| Critical Alert (Days) | 7 |
| Warning Alert (Days) | 14 |
| Watch Alert (Days) | 30 |

### 3) Configure forecasting
**Admin → Stores → Configuration → AI → StockPredict → Forecasting Settings**

| Setting | Default | Notes |
|---|---|---|
| Sales History Days | 365 | More history usually improves accuracy |
| Forecast Horizon (Days) | 30 | How far ahead to forecast |
| Minimum Order Threshold | 5 | Skips SKUs with very low order counts |

### 4) Configure AI (optional)
**Admin → Stores → Configuration → AI → StockPredict → AI Configuration**

- Enable AI Analysis: `Yes`
- Provider: Gemini, OpenAI, or Claude
- API Key: stored encrypted

### 5) Run a forecast
```bash
php bin/magento ai:stockpredict:forecast
```

### 6) View the results
- **Admin → Stock Predict → Dashboard**
- **Admin → Stock Predict → Forecasts**
- **Admin → Stock Predict → Alerts**

---

## AI provider setup

### Google Gemini
1. Open [Google AI Studio](https://aistudio.google.com/apikey)
2. Create an API key
3. Copy the key (usually starts with `AIza...`)

### OpenAI GPT-4o-mini
1. Open [OpenAI API Keys](https://platform.openai.com/api-keys)
2. Create a key
3. Copy the key (usually starts with `sk-...`)

### Anthropic Claude Sonnet 4.6
1. Open [Anthropic Console](https://console.anthropic.com/)
2. Create an API key
3. Add billing if needed

---

## How it works

### Forecast pipeline
```text
Cron runs daily at 2:00 AM (or manually via CLI/admin)
        ↓
DataCollector.getTopSkusByVolume()
  → Picks the top 500 SKUs by total qty sold
        ↓
DataCollector.getSalesData()
  → Aggregates total_qty, last_30d, last_90d, last_365d, avg_daily
        ↓
DataCollector.getCurrentStock()
  → Reads stock from cataloginventory_stock_item
        ↓
ForecastEngine.analyzeSku()
  → Calculates stockout timing and sales trend
  → Calls AI when enabled
  → Saves data to ai_stockpredict_forecast
        ↓
ForecastEngine.createAlerts()
  → Creates ai_stockpredict_alert for non-OK items
  → Skips SKUs that already have an open alert
  → Sends email if email alerts are enabled
```

### Trend detection
```text
avg_30d_rate = last_30d_sales / 30
avg_90d_rate = last_90d_sales / 90

Rising    → avg_30d_rate > avg_90d_rate × 1.15
Declining → avg_30d_rate < avg_90d_rate × 0.85
Stable    → anything in between
```

---

## CLI commands

```bash
# Run forecast for all top SKUs
php bin/magento ai:stockpredict:forecast

# Run forecast for one SKU
php bin/magento ai:stockpredict:forecast --sku=WSH12-XS-Orange

# Mark the run as triggered by admin
php bin/magento ai:stockpredict:forecast --triggered-by=admin
```

---

## Cron jobs

| Job | Schedule | What it does |
|---|---|---|
| `ai_stockpredict_run_forecast` | Daily at 2:00 AM | Forecast all top SKUs |
| `ai_stockpredict_cleanup` | Daily at 3:00 AM | Remove old data past retention_days |

---

## Database tables

### `ai_stockpredict_forecast`
Stores one forecast row per SKU.

Important columns:
- `sku`, `product_name`
- `current_qty`
- `avg_daily_sales`
- `forecast_demand_7d`, `forecast_demand_30d`, `forecast_demand_90d`
- `trend`
- `confidence_score`
- `predicted_stockout_date`, `days_until_stockout`
- `recommended_reorder_qty`
- `ai_recommendation`
- `severity`
- `last_30d_sales`, `last_90d_sales`, `last_365d_sales`

### `ai_stockpredict_alert`
Stores alerts for SKUs that need attention.

Important columns:
- `forecast_id`
- `sku`, `product_name`, `severity`
- `days_until_stockout`, `current_qty`
- `status`
- `created_at`, `acknowledged_at`, `resolved_at`

### `ai_stockpredict_run`
Stores each forecast execution.

Important columns:
- `status`
- `triggered_by`
- `skus_analyzed`, `skus_critical`, `skus_warning`, `skus_watch`
- `duration_ms`, `error_message`
- `started_at`, `completed_at`

---

## Configuration reference

```text
ai_stockpredict/general/enabled                 — Enable or disable the module
ai_stockpredict/general/enable_logging          — Write logs to var/log/ai_stockpredict.log
ai_stockpredict/general/retention_days          — Days to keep data (default: 90)

ai_stockpredict/forecasting/history_days        — Sales history window (default: 365)
ai_stockpredict/forecasting/forecast_horizon    — Forecast horizon days (default: 30)
ai_stockpredict/forecasting/min_order_threshold — Minimum orders needed to include a SKU

ai_stockpredict/alerts/critical_days            — Critical threshold in days (default: 7)
ai_stockpredict/alerts/warning_days             — Warning threshold in days (default: 14)
ai_stockpredict/alerts/watch_days               — Watch threshold in days (default: 30)
ai_stockpredict/alerts/email_enabled            — Send email alerts (default: No)
ai_stockpredict/alerts/email_recipient          — Alert email address

ai_stockpredict/ai/enabled                      — Enable AI analysis
ai_stockpredict/ai/provider                     — gemini | openai | claude
ai_stockpredict/ai/api_key                      — API key (encrypted at rest)
ai_stockpredict/ai/timeout_ms                   — AI request timeout (default: 30000)
```

---

## Module structure

```text
app/code/AI/StockPredict/
├── Api/
│   └── AiClientInterface.php
├── Block/Adminhtml/
│   ├── Dashboard.php
│   └── Forecast/View.php
├── Console/Command/
│   └── ForecastCommand.php
├── Controller/Adminhtml/
│   ├── Dashboard/Index.php
│   ├── Forecasts/Index.php
│   ├── Forecasts/View.php
│   ├── Alerts/Index.php
│   ├── Alerts/Acknowledge.php
│   ├── Alerts/Resolve.php
│   ├── Alert/Acknowledge.php
│   └── Alert/Resolve.php
├── Cron/
│   ├── RunForecast.php
│   └── Cleanup.php
├── Model/
│   ├── Ai/
│   │   ├── ClaudeClient.php
│   │   ├── GeminiClient.php
│   │   ├── OpenAiClient.php
│   │   └── ClientFactory.php
│   ├── Config/Source/
│   │   └── AiProvider.php
│   ├── ResourceModel/
│   │   ├── Forecast.php / Collection.php
│   │   ├── Alert.php / Collection.php
│   │   └── Run.php / Collection.php
│   ├── Alert.php
│   ├── DataCollector.php
│   ├── EmailNotifier.php
│   ├── Forecast.php
│   ├── ForecastEngine.php
│   ├── Log/LogService.php
│   ├── Run.php
│   └── Settings.php
├── Ui/Component/Listing/Column/
│   ├── AlertActions.php
│   ├── AlertStatusOptions.php
│   ├── ForecastActions.php
│   └── SeverityOptions.php
├── view/adminhtml/
│   ├── email/
│   │   └── stockpredict_alert.html
│   ├── layout/
│   ├── templates/
│   │   ├── stockpredict/dashboard.phtml
│   │   └── stockpredict/forecast/view.phtml
│   ├── ui_component/
│   │   ├── stockpredict_forecast_listing.xml
│   │   └── stockpredict_alert_listing.xml
│   └── web/css/admin-menu.css
└── etc/
    ├── acl.xml
    ├── adminhtml/
    │   ├── menu.xml
    │   ├── routes.xml
    │   └── system.xml
    ├── config.xml
    ├── crontab.xml
    ├── db_schema.xml
    ├── di.xml
    ├── email_templates.xml
    └── module.xml
```

---

## Logs

Module logs are stored here:

```bash
tail -f var/log/ai_stockpredict.log
```

Common log prefixes:

| Prefix | Meaning |
|---|---|
| `[STOCKPREDICT][ENGINE]` | Forecast lifecycle and SKU processing |
| `[STOCKPREDICT][DATACOLLECTOR]` | Sales or stock query issues |
| `[STOCKPREDICT][GEMINI]` | Gemini API calls |
| `[STOCKPREDICT][OPENAI]` | OpenAI API calls |
| `[STOCKPREDICT][CLAUDE]` | Claude API calls |
| `[STOCKPREDICT][EMAIL]` | Email events |

---

## Troubleshooting

### No SKUs analyzed
- Make sure orders exist with status `complete` or `processing`
- Check that SKUs meet the minimum order threshold
- Review logs in `var/log/ai_stockpredict.log`

### Severity is always OK
- Check `cataloginventory_stock_item` for current stock
- Confirm alert thresholds under **Stores → Configuration → AI → StockPredict**
- Try a single SKU forecast:

```bash
php bin/magento ai:stockpredict:forecast --sku=YOUR-SKU
```

### AI recommendation is empty
- Make sure AI is enabled
- Check that the API key is valid and funded
- Review provider logs with:

```bash
grep CLAUDE\|GEMINI\|OPENAI var/log/ai_stockpredict.log
```

### Menu does not show in admin
```bash
php bin/magento cache:flush
```
If needed, clear the backend menu cache entry manually:

```bash
sudo rm var/cache/mage--b/mage---*_BACKEND_MENU_OBJECT
php bin/magento cache:flush
```

### Alerts are not being created
- Duplicate open alerts are skipped by design
- Resolve or acknowledge the existing alert first
- Check the alert table:

```sql
SELECT * FROM ai_stockpredict_alert WHERE sku='YOUR-SKU' AND status='open';
```

### Cron is not running
```bash
php bin/magento cron:run
php bin/magento cron:history | grep stockpredict
```

---

## Extending the module

### Add a new AI provider
1. Create `Model/Ai/YourClient.php` and implement `AI\StockPredict\Api\AiClientInterface`
2. Implement `analyze(string $prompt): string`
3. Add the provider to `Model/Config/Source/AiProvider.php`
4. Add the case in `Model/Ai/ClientFactory::create()`
5. Register logger injection in `etc/di.xml`

### Adjust the AI prompt
The prompt is built in `ForecastEngine::getAiRecommendation()` and includes:
- SKU and product name
- Current stock
- Average daily sales
- Last 30/90 days of sales
- Trend direction

Example response:
```json
{
    "forecast_30d": 150,
    "trend": "rising",
    "confidence": 0.85,
    "recommendation": "Order 300 units urgently — rising demand, stock critical."
}
```

---

## Security

- API keys are stored encrypted with Magento’s `Encrypted` backend model
- No customer PII is sent to AI providers
- Admin access is protected by ACL resources under `AI_StockPredict`
- Alert actions only accept approved statuses

---

## License

MIT License — see [LICENSE](LICENSE) for details.

---

## Support

- **GitHub**: https://github.com/hassan-ai-work/Magento2AIStockUpdate
- **Issues**: https://github.com/hassan-ai-work/Magento2AIStockUpdate/issues
- **Email**: levosoft786@gmail.com

---

Built with care by **levosoft786@gmail.com**.
