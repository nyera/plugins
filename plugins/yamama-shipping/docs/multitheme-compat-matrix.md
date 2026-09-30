# Yamama Shipping Multi-theme Compatibility Matrix

## Scope

Manual compatibility checks for the current Yamama platform themes with the plugin-first shipping integration.

## Test Cases

- Shipping method appears in checkout rates.
- Billing-to-shipping fallback works when shipping fields are overridden.
- Payment intent metadata is stored on order create.
- Thank you page shows payment state and checkout URL.
- My account order view shows provider reference when available.
- Middleware webhook can update order status with signature validation.

## Matrix

| Theme | Checkout Rate | Field Fallback | Thank You Block | Account Block | Notes |
| --- | --- | --- | --- | --- | --- |
| mallati-theme | Pass | Pass | Pass | Pass | Custom checkout field order still works with compat layer. |
| dark-theme | Pass | Pass | Pass | Pass | Cart totals override does not block hook rendering. |
| al-thabihah-theme | Pass | Pass | Pass | Pass | Shipping address disable override recovered via fallback. |
| sweet-house-theme | Pass | Pass | Pass | Pass | No blocking issues found. |
| beauty-care-theme | Pass | Pass | Pass | Pass | Thank you template override remains compatible. |
| my-clinic-theme | Pass | Pass | Pass | Pass | Order details custom template still receives hooks. |

## Residual Edge Cases

- Stores that hard-disable `woocommerce_shipping_methods` in theme code need a theme patch.
- Checkout builders that bypass Woo checkout hooks require dedicated integration points.
- If a theme hides shipping city completely, quote fallback uses billing city and logs a warning.
