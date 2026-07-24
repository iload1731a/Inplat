# Admin Market Setup Instructions

1. Login with an admin account.
2. Open **Settings Hub**: `/admin/settings/hub`.
3. In **Market Data Operations**, open each section in this order:
   - **Currencies** (`/admin/assets`)
   - **Pairs** (`/admin/assets/pairs`)
   - **Mappings** (`/admin/markets/mappings`)
   - **Chart Data** (`/admin/charts`)

## A) Configure Market Data Provider

4. Go to **Markets Data Sync**: `/admin/markets/data-sync`.
5. Click **Providers** and verify Binance provider is active.
6. If needed, open `/admin/markets/providers` and:
   - Add provider with code `binance`
   - Set API/base URLs
   - Enable provider
   - Save

## B) Import Currencies and Trading Pairs

7. Open `/admin/assets` and create or enable required currencies (BTC, ETH, USDT, etc.).
8. Open `/admin/markets/data-sync`.
9. Click **Import / Update Binance Symbols**.
10. Wait for success message and verify import logs in the same page.
11. Open `/admin/assets/pairs` and confirm pairs are visible and active.

## C) Sync Prices and Candles

12. On `/admin/markets/data-sync`, click **Sync Tickers From Binance**.
13. Run **Sync Candles** with:
    - Interval: `1m`, `5m`, `1h`, or `1d`
    - Limit: `300` (or preferred value)
14. Repeat candle sync for required intervals used by your chart pages.

## D) Map Assets and Enable Feed

15. Open `/admin/markets/mappings`.
16. Ensure internal currencies are mapped to Binance symbols/IDs.
17. Open `/admin/markets/feed`.
18. Enable feed entries for active pairs.
19. Save and verify feed status is active.

## E) Verify Admin and User Panels

20. Open `/admin/markets` and confirm:
    - Active pair count
    - Ticker values
    - Volume and market statistics
21. Open `/admin/charts` and confirm chart datasets are loading.
22. Open user trading pages (`/trade`, `/charts`, `/markets`) and verify live values display.

## F) User Management Actions

23. Open `/admin/users`.
24. Open a user profile via **Manage**.
25. Available actions:
    - **Login As User**
    - **Add/Adjust Balance**
    - **Send Email**
    - **Change Password**
    - Review recent orders, trades, deposits, withdrawals

## G) Daily Operations Checklist

26. Run ticker sync at scheduled intervals.
27. Run candle sync for required intervals.
28. Check `/admin/markets/sync-logs` for failed sync jobs.
29. Disable broken pairs or providers until fixed.
30. Re-check `/admin/markets` health metrics after every sync cycle.
