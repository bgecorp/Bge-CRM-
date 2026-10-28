# Sari CRM

A sales and customer relationship management system for sari-sari stores and groceries. It records customers, products, orders and sales, and predicts from each customer's order history how long their latest order will last and when they will probably buy again.

- `backend/`: Laravel 12 REST API (PHP 8.2, MySQL via XAMPP)
- `frontend/`: React 19 + Vite single-page app

## Running it

1. **Start MySQL** in the XAMPP Control Panel.
2. **Backend** (first time only: create the database and tables):
   ```bash
   cd backend
   php artisan key:generate
   php artisan migrate --seed      # drop --seed to start with no sample records
   php artisan serve               # http://127.0.0.1:8000
   ```
   The database `sari_crm` must exist (create it in phpMyAdmin, or run
   `C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE sari_crm"`).
3. **Frontend**
   ```bash
   cd frontend
   npm install
   npm run dev                     # http://localhost:5173
   ```
   The API URL defaults to `http://127.0.0.1:8000/api`. To change it, set `VITE_API_URL` in `frontend/.env`.

Run the calculation tests with `php artisan test` in `backend/`.

## Website and web orders

The first page anyone sees (`http://<this PC>:5173`) is the public website: the minimum-order prices (10 packs of barley powder for ₱680, 20 packs of corn coffee for ₱1,360), the product posters, and a cash-on-delivery order form. Staff click **Sign in** at the top right to open the sign-in card.

- Orders from the form appear on the **Web Orders** page (super admin only), with a red badge for new ones. Call or text to confirm, then set Confirmed, Shipped, Delivered or Cancelled.
- Products, prices and pictures on the website are set in `backend/config/landing.php` (pictures in `frontend/public/landing/`). The capsule price is not set, so the website says it is confirmed by phone.
- Prices are always taken from that file, never from the visitor's browser. Each device can send at most 5 orders a minute, and a hidden field catches spam bots.

## Accounts and sign-in

Everyone signs in on the login page with an email and password.

- **First time:** create your super admin account. In VS Code's terminal, in the `backend` folder, run `php artisan crm:create-admin` and answer the questions (the password is hidden while you type).
- **Super admin:** sees and changes everything, creates accounts on the **Accounts** page (a seller's name is added to the sales team automatically), and sees every sign-in, sign-out and failed attempt on the **Login Activity** page.
- **Seller:** sees **Products** (view only), **Stock Prediction** and **Follow-ups**. They can open a customer from those pages to record orders, and they can add and answer follow-ups. They cannot change products, customers, settings or accounts.
- A sign-in lasts 12 hours. Turning an account off or changing its password signs that person out.
- Forgotten password: the super admin sets a new one on the Accounts page.

## Stock prediction rules (`backend/app/Services/StockPredictor.php`)

Predictions come **only from each customer's order history**, per product. Sales records and stock counts are still kept for your records and for Sales Performance, but they don't change the prediction.

| Step | Formula | Example (orders Oct 1: 12, Oct 6: 6, Oct 10: 8) |
|---|---|---|
| A. Average daily consumption | packs of earlier orders ÷ days until each next order | (12 + 6) ÷ (5 + 4) = 2 packs/day |
| B. Estimated stock duration | latest order ÷ daily consumption | 8 ÷ 2 = 4 days |
| C. Predicted next purchase | latest order date + stock duration (whole days, rounded up) | Oct 10 + 4 = Oct 14 |

- **1st order:** recorded as the baseline. There's no prediction yet ("Waiting for 2nd order").
- **2nd order:** first estimate, from one interval (12 packs over 5 days = 2.4 a day).
- **3rd order onward:** refined, from all intervals together. Every new order recalculates it.
- Several orders on the same day count as one.
- **Buying pattern** comes from the average days between orders: up to 7 = frequent, up to 14 = moderate, more = occasional. Both limits are on the **Settings** page.
- **Recent weighting** (Settings, off by default) makes recent intervals count more, for customers whose order sizes change.
- These are estimates. They assume each order is used up by the next one, so leftovers, extra stock, sharing or irregular buying make them less accurate.

## Follow-ups

- **One automatic follow-up:** 5 days after a customer's **first order** of a product, a *First-order check* reminder is added (the number of days is on the Settings page). It shows in Follow-ups and the bell, and closes itself when they order that product again. Nothing else is scheduled automatically; you add other follow-ups yourself.
- Recording a follow-up saves the **customer response** (Interested, Ready to order, Not yet, No response), the **next action** (Call, Message, Confirm stock, Process order), an optional **next order date**, and an optional next contact date.
## Sales performance

`/api/performance?from=&to=&category=&sales_rep_id=&sort=` ranks customers by total packs, average daily sales, how long the latest order lasts, growth (compared with the previous period of the same length), days since last order, reorder frequency, predicted next purchase, or sales value. Each store also gets a rank **within its category**.

## API

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/dashboard` | totals, expected orders, ordering-soon list, 30-day sales |
| GET/POST | `/api/stores` | list (`search`, `category`, `status`, `sales_rep_id`, `sort=name\|sales\|urgency`, `dir`) / create |
| GET/PUT/DELETE | `/api/stores/{id}` | detail with predictions, orders, sales, follow-ups |
| POST | `/api/stores/{id}/sales` | record sale `{product_id, sale_date, quantity, days_covered, unit_price?}` |
| DELETE | `/api/sales/{id}` | remove a sale |
| POST | `/api/stores/{id}/stock-counts` | record stock on hand `{product_id, counted_at, quantity}` |
| POST | `/api/stores/{id}/orders` | record an order `{product_id, delivered_at, quantity, unit_price?}`: this drives the prediction |
| GET | `/api/predictions` | customer-by-customer prediction summary (`product_id`, `pattern`, `sort=next\|usage\|orders\|packs\|store`) |
| GET/POST | `/api/follow-ups` | list by `bucket=overdue\|today\|upcoming\|completed` / create manual |
| POST | `/api/follow-ups/{id}/complete` | `{outcome, outcome_notes, next_action, next_contact_date?}` |
| CRUD | `/api/sales-reps`, `/api/products` | team members and products |

## Assistant (Claude)

The **Assistant** page is a chat built on the Claude API through the official Anthropic PHP SDK (default model `claude-opus-5-5`). It can answer questions about your stores and sales, change data, and change the system itself.

**Setup**, in `backend/.env`:
- `ANTHROPIC_API_KEY=`: your key from platform.claude.com. Restart `php artisan serve` after setting it.
- `ASSISTANT_TOKEN`: the access code the Assistant page asks for. Keep it private.
- `ASSISTANT_ALLOW_CODE_EDITS=true`: lets it propose code and schema changes. This only works when `APP_ENV=local`.
- Optional: `ASSISTANT_MODEL` (another Claude model, e.g. `claude-sonnet-5-5` to spend less) and `ASSISTANT_EFFORT` (`low`/`medium`/`high`/`xhigh`/`max`; default `medium`).
- The Claude API is paid per use. The system prompt, tools and history are cached between steps to keep the cost down.
- If Claude declines a request for safety reasons, the API automatically retries it on Anthropic's default fallback model.

**How it works** (`backend/app/Services/Assistant/`):
- **Reading** runs immediately. The assistant can call the CRM's own GET endpoints, run read-only SQL, view the live schema, and list, read and search the source code.
- **Changing** always waits for you. It proposes API writes (create, update or delete records), file edits, new files, and migrations, and you **Approve** or **Reject** each one in the chat. Code edits keep a backup in `backend/storage/app/assistant-backups/`. Every decision is recorded on the **Change log** tab.
- **Studying the system:** ask "Study the system and save notes". It reads the key files a few at a time and saves what it learns to **Memory**.
- **Staying current:** every request includes a live map of the API routes, database tables, source files and saved notes, built fresh from the running system. Changes you or the assistant make are visible to it straight away.
- **Memory:** the model isn't retrained. It saves durable facts as notes, shown on the **Memory** tab, and those notes are added to every conversation. You can add notes or remove wrong ones there.
- **Limits:** it can't read `.env`, `vendor/` or `node_modules/`. It can't edit its own safeguards (`app/Services/Assistant`, its controller, middleware, config, `routes/assistant.php` or `bootstrap/app.php`). It can't run shell commands, tests or builds, so after a code change it tells you what to check.
- `php artisan serve` handles one request at a time, so other pages wait while the assistant is working. For a team, serve the backend through XAMPP Apache instead.

## Not yet included

- **Login and per-rep access:** the API is currently open. Before using it outside a trusted local network, add Laravel Sanctum authentication.
