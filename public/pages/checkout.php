<?php

// Checkout: collect the billing (and, if any item ships, shipping) address
// and a card, and turn the cart into an order. The card payment is a demo:
// nothing is charged or stored, and a valid card marks the order paid.

declare(strict_types=1);

require_once __DIR__ . '/../../includes/cart.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$userId = currentUser()['user_id'];
$errors = [];
$billing = loadDefaultAddress($pdo, $userId, 'billing');
$shipping = loadDefaultAddress($pdo, $userId, 'shipping');
$shipToBilling = true;
// Only the name and expiry are put back into the form after an error;
// the card number and CVC are never echoed.
$card = ['name' => '', 'expiry' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billing = normalizeAddressInput($_POST, 'billing_');
    $shipping = normalizeAddressInput($_POST, 'shipping_');
    $shipToBilling = isset($_POST['ship_to_billing']);
    $submittedCard = normalizeCardInput($_POST);
    $card = ['name' => $submittedCard['name'], 'expiry' => $submittedCard['expiry']];

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please submit the form again.';
    } else {
        $result = placeOrder($pdo, $userId, $billing, $shipToBilling ? null : $shipping, $submittedCard);
        $errors = $result['errors'];

        if ($result['order_id'] !== null) {
            header('Location: /pages/orders.php?placed=' . $result['order_id'], true, 303);
            exit;
        }

        // The cart changed under us (sold out, removed...): the cart page shows what.
        if (isset($errors['cart'])) {
            $_SESSION['cart_flash'] = ['ok' => false, 'message' => $errors['cart']];
            header('Location: /pages/cart.php', true, 303);
            exit;
        }
    }
}

$cart = loadCart($pdo, $userId);

// Nothing to check out: send the buyer back to the cart, which explains why.
if (!$cart['can_checkout'] && !isset($errors['cart'])) {
    header('Location: /pages/cart.php');
    exit;
}

$cartCount = cartItemCount($pdo, $userId);
$pageTitle = 'Checkout | OmniBuy';

/**
 * One address fieldset. $prefix is 'billing_' or 'shipping_'.
 */
function renderAddressFields(array $address, string $prefix, array $errors, string $autocomplete): void
{
    $fields = [
        'address_line_1' => ['Street address', 'address-line1', true],
        'address_line_2' => ['Apartment, suite, etc.', 'address-line2', false],
        'city' => ['City', 'address-level2', true],
        'state_name' => ['State', 'address-level1', true],
        'zip_code' => ['ZIP code', 'postal-code', true],
        'country' => ['Country', 'country-name', true],
    ];

    foreach ($fields as $field => [$label, $token, $required]) {
        $name = $prefix . $field;
        $maxLength = $field === 'zip_code' ? 20 : 100;
        ?>
        <div class="auth-field">
          <label for="<?= $name ?>"><?= $label ?><?php if (!$required): ?> <span class="auth-field__optional">(optional)</span><?php endif; ?></label>
          <input type="text" id="<?= $name ?>" name="<?= $name ?>" maxlength="<?= $maxLength ?>"
            autocomplete="<?= $autocomplete ?> <?= $token ?>"<?= $required ? ' required' : '' ?>
            value="<?= htmlspecialchars($address[$field]) ?>"<?= invalidAttributes($errors, $name) ?>>
          <?= fieldError($errors, $name) ?>
        </div>
        <?php
    }
}

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/auth.css">
<link rel="stylesheet" href="/assets/css/cart.css">

<section class="shell cart-page">
  <h1>Checkout</h1>
  <p class="cart-page__back"><a href="/pages/cart.php">Back to cart</a></p>

  <?php if (isset($errors['form']) || isset($errors['cart'])): ?>
    <p class="cart-alert cart-alert--error" role="alert"><?= htmlspecialchars($errors['form'] ?? $errors['cart']) ?></p>
  <?php elseif ($errors !== []): ?>
    <p class="cart-alert cart-alert--error" role="alert">Please fix the highlighted fields.</p>
  <?php endif; ?>

  <div class="cart-layout">
    <form class="auth-form checkout-form" method="post" action="/pages/checkout.php" novalidate>
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

      <fieldset class="auth-fieldset">
        <legend>Billing address</legend>
        <?php renderAddressFields($billing, 'billing_', $errors, 'billing'); ?>
      </fieldset>

      <?php if ($cart['needs_shipping']): ?>
        <fieldset class="auth-fieldset">
          <legend>Shipping address</legend>
          <label class="auth-checkbox">
            <input type="checkbox" name="ship_to_billing" value="1" data-ship-to-billing<?= $shipToBilling ? ' checked' : '' ?>>
            Ship to my billing address
          </label>
          <div class="checkout-form__shipping" data-shipping-fields<?= $shipToBilling ? ' hidden' : '' ?>>
            <?php renderAddressFields($shipping, 'shipping_', $errors, 'shipping'); ?>
          </div>
        </fieldset>
      <?php else: ?>
        <input type="hidden" name="ship_to_billing" value="1">
        <p class="cart-summary__note">Every item is a local meetup, so no shipping address is needed. Arrange the meetup with each seller through Messages.</p>
      <?php endif; ?>

      <fieldset class="auth-fieldset">
        <legend>Payment</legend>
        <p class="cart-summary__note">Demo payment: no card is charged and card details are not saved. Use test card 4242 4242 4242 4242 with any future date and any 3-digit code.</p>

        <div class="auth-field">
          <label for="card_name">Name on card</label>
          <input type="text" id="card_name" name="card_name" maxlength="100" autocomplete="cc-name" required
            value="<?= htmlspecialchars($card['name']) ?>"<?= invalidAttributes($errors, 'card_name') ?>>
          <?= fieldError($errors, 'card_name') ?>
        </div>

        <div class="auth-field">
          <label for="card_number">Card number</label>
          <input type="text" id="card_number" name="card_number" maxlength="23" inputmode="numeric"
            autocomplete="cc-number" placeholder="1234 5678 9012 3456" required<?= invalidAttributes($errors, 'card_number') ?>>
          <?= fieldError($errors, 'card_number') ?>
        </div>

        <div class="checkout-form__card-row">
          <div class="auth-field">
            <label for="card_expiry">Expiration (MM/YY)</label>
            <input type="text" id="card_expiry" name="card_expiry" maxlength="7" inputmode="numeric"
              autocomplete="cc-exp" placeholder="MM/YY" required
              value="<?= htmlspecialchars($card['expiry']) ?>"<?= invalidAttributes($errors, 'card_expiry') ?>>
            <?= fieldError($errors, 'card_expiry') ?>
          </div>

          <div class="auth-field">
            <label for="card_cvc">Security code</label>
            <input type="text" id="card_cvc" name="card_cvc" maxlength="4" inputmode="numeric"
              autocomplete="cc-csc" placeholder="CVC" required<?= invalidAttributes($errors, 'card_cvc') ?>>
            <?= fieldError($errors, 'card_cvc') ?>
          </div>
        </div>
      </fieldset>

      <button type="submit" class="button button--primary">Place order · <?= formatMoney($cart['summary']['total']) ?></button>
    </form>

    <aside class="cart-summary" aria-labelledby="checkout-summary-heading">
      <h2 id="checkout-summary-heading">Order summary</h2>
      <ul class="checkout-lines">
        <?php foreach ($cart['items'] as $item): ?>
          <li>
            <span><?= htmlspecialchars($item['item_name']) ?> × <?= (int) $item['quantity'] ?></span>
            <span><?= formatMoney($item['line_total']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <dl class="cart-summary__totals">
        <dt>Subtotal</dt>
        <dd><?= formatMoney($cart['summary']['subtotal']) ?></dd>
        <dt>Shipping</dt>
        <dd><?= formatMoney($cart['summary']['shipping']) ?></dd>
        <dt class="cart-summary__total">Total</dt>
        <dd class="cart-summary__total"><?= formatMoney($cart['summary']['total']) ?></dd>
      </dl>
    </aside>
  </div>
</section>

<script>
  // Show the shipping fields only when the buyer unticks "ship to billing".
  (() => {
    const toggle = document.querySelector('[data-ship-to-billing]');
    const fields = document.querySelector('[data-shipping-fields]');
    if (!toggle || !fields) return;
    toggle.addEventListener('change', () => { fields.hidden = toggle.checked; });
  })();
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
