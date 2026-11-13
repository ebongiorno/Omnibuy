<?php

// Shopping cart: add (from a listing page), change quantities, remove, and
// go to checkout. All changes are POSTs with a CSRF token, then a redirect
// back here so refreshing the page never repeats them.

declare(strict_types=1);

require_once __DIR__ . '/../../includes/cart.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$userId = currentUser()['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $listingId = filter_var($_POST['listing_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $result = ['ok' => false, 'message' => 'Your session expired. Please try again.'];
    } elseif ($listingId === false) {
        $result = ['ok' => false, 'message' => 'That listing could not be found.'];
    } elseif ($action === 'add') {
        $result = $quantity === false
            ? ['ok' => false, 'message' => 'Choose a quantity of at least 1.']
            : addToCart($pdo, $userId, $listingId, $quantity);
    } elseif ($action === 'update') {
        $result = $quantity === false
            ? ['ok' => false, 'message' => 'Enter a whole-number quantity.']
            : updateCartQuantity($pdo, $userId, $listingId, $quantity);
    } elseif ($action === 'remove') {
        removeFromCart($pdo, $userId, $listingId);
        $result = ['ok' => true, 'message' => 'Item removed from your cart.'];
    } else {
        $result = ['ok' => false, 'message' => 'Unknown cart action.'];
    }

    $_SESSION['cart_flash'] = $result;
    header('Location: /pages/cart.php', true, 303);
    exit;
}

$flash = $_SESSION['cart_flash'] ?? null;
unset($_SESSION['cart_flash']);

$cart = loadCart($pdo, $userId);
$cartCount = cartItemCount($pdo, $userId);
$pageTitle = 'Cart | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/cart.css">

<section class="shell cart-page">
  <h1>Your cart</h1>

  <?php if (is_array($flash)): ?>
    <p class="cart-alert<?= $flash['ok'] ? '' : ' cart-alert--error' ?>" role="<?= $flash['ok'] ? 'status' : 'alert' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </p>
  <?php endif; ?>

  <?php if ($cart['items'] === []): ?>
    <div class="cart-empty">
      <p>Your cart is empty.</p>
      <a class="button button--primary" href="/pages/search.php">Browse listings</a>
    </div>
  <?php else: ?>
    <div class="cart-layout">
      <ul class="cart-items">
        <?php foreach ($cart['items'] as $item): ?>
          <?php $listingId = (int) $item['listing_id']; ?>
          <li class="cart-item<?= $item['problem'] !== null ? ' cart-item--problem' : '' ?>">
            <img class="cart-item__image" alt="" width="96" height="96"
              src="<?= htmlspecialchars($item['image_url'] ?? ORDER_DEFAULT_IMAGE) ?>">

            <div class="cart-item__details">
              <a class="cart-item__name" href="/pages/listing.php?id=<?= $listingId ?>"><?= htmlspecialchars($item['item_name']) ?></a>
              <?php if ($item['store_name'] !== null): ?>
                <p class="cart-item__meta">Sold by <?= htmlspecialchars($item['store_name']) ?></p>
              <?php endif; ?>
              <p class="cart-item__meta">
                <?= formatMoney($item['price']) ?> each ·
                <?php if ($item['fulfillment_type'] === 'meetup'): ?>
                  Local meetup
                <?php else: ?>
                  Shipping <?= $item['line_shipping'] > 0 ? formatMoney($item['line_shipping']) : 'free' ?>
                <?php endif; ?>
              </p>
              <?php if ($item['problem'] !== null): ?>
                <p class="cart-item__problem" role="alert"><?= htmlspecialchars($item['problem']) ?></p>
              <?php endif; ?>

              <div class="cart-item__actions">
                <?php if ((int) $item['stock'] > 0 && $item['listing_status'] === 'active'): ?>
                  <form class="cart-item__quantity" method="post" action="/pages/cart.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                    <label for="quantity-<?= $listingId ?>">Qty</label>
                    <input type="number" id="quantity-<?= $listingId ?>" name="quantity" min="1"
                      max="<?= (int) $item['stock'] ?>" step="1" inputmode="numeric"
                      value="<?= (int) $item['quantity'] ?>">
                    <button type="submit" class="cart-link-button">Update</button>
                  </form>
                <?php endif; ?>

                <form method="post" action="/pages/cart.php">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="listing_id" value="<?= $listingId ?>">
                  <button type="submit" class="cart-link-button cart-link-button--danger">Remove</button>
                </form>
              </div>
            </div>

            <p class="cart-item__total"><?= formatMoney($item['line_total']) ?></p>
          </li>
        <?php endforeach; ?>
      </ul>

      <aside class="cart-summary" aria-labelledby="cart-summary-heading">
        <h2 id="cart-summary-heading">Order summary</h2>
        <dl class="cart-summary__totals">
          <dt>Items (<?= (int) $cart['summary']['quantity'] ?>)</dt>
          <dd><?= formatMoney($cart['summary']['subtotal']) ?></dd>
          <dt>Shipping</dt>
          <dd><?= formatMoney($cart['summary']['shipping']) ?></dd>
          <dt class="cart-summary__total">Total</dt>
          <dd class="cart-summary__total"><?= formatMoney($cart['summary']['total']) ?></dd>
        </dl>

        <?php if ($cart['can_checkout']): ?>
          <a class="button button--primary cart-summary__checkout" href="/pages/checkout.php">Proceed to checkout</a>
        <?php else: ?>
          <p class="cart-summary__note">Remove or fix the items marked above to check out.</p>
        <?php endif; ?>
      </aside>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
