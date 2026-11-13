<?php

// My Orders: the logged-in buyer's order history, newest first.

require_once __DIR__ . '/../../includes/orders.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$buyerId = currentUser()['user_id'];

// Cancel/return one item (POST + CSRF, then redirect so a refresh can't repeat it).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderItemId = filter_var($_POST['order_item_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    if (!isValidCsrfToken($_POST['csrf_token'] ?? null)) {
        $result = ['ok' => false, 'message' => 'Your session expired. Please try again.'];
    } elseif (($_POST['action'] ?? '') !== 'cancel_item' || $orderItemId === false) {
        $result = ['ok' => false, 'message' => 'That order item could not be found.'];
    } else {
        $result = cancelOrderItem($pdo, $buyerId, $orderItemId);
    }

    $_SESSION['orders_flash'] = $result;
    header('Location: /pages/orders.php', true, 303);
    exit;
}

$flash = $_SESSION['orders_flash'] ?? null;
unset($_SESSION['orders_flash']);

$orders = loadBuyerOrders($pdo, $buyerId);

$pageTitle = 'My Orders | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/orders.css">

<section class="shell orders-page">
  <h1>My Orders</h1>

  <?php $placedId = filter_var($_GET['placed'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]); ?>
  <?php if ($placedId !== false && in_array($placedId, array_map('intval', array_column($orders, 'order_id')), true)): ?>
    <p class="orders-placed" role="status">Thanks! Order #<?= (int) $placedId ?> has been placed.</p>
  <?php endif; ?>

  <?php if (is_array($flash)): ?>
    <p class="orders-flash<?= $flash['ok'] ? '' : ' orders-flash--error' ?>" role="<?= $flash['ok'] ? 'status' : 'alert' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </p>
  <?php endif; ?>

  <?php if ($orders === []): ?>
    <div class="orders-empty">
      <p>You haven't placed any orders yet.</p>
      <a class="button button--primary" href="/pages/search.php">Browse listings</a>
    </div>
  <?php else: ?>
    <p class="orders-page__count">
      <?= count($orders) ?> <?= count($orders) === 1 ? 'order' : 'orders' ?>
    </p>

    <?php foreach ($orders as $order): ?>
      <article class="order-card" aria-labelledby="order-<?= (int) $order['order_id'] ?>">
        <header class="order-card__header">
          <div>
            <h2 id="order-<?= (int) $order['order_id'] ?>">Order #<?= (int) $order['order_id'] ?></h2>
            <p class="order-card__date">
              Placed <?= htmlspecialchars(date('F j, Y', strtotime($order['created_at']))) ?>
            </p>
          </div>
          <div class="order-card__badges">
            <span class="order-badge order-badge--<?= orderStatusTone($order['order_status']) ?>">
              <?= htmlspecialchars(formatOrderStatus($order['order_status'])) ?>
            </span>
            <span class="order-badge order-badge--<?= orderStatusTone($order['payment_status']) ?>">
              Payment <?= htmlspecialchars(strtolower(formatOrderStatus($order['payment_status']))) ?>
            </span>
          </div>
        </header>

        <ul class="order-items">
          <?php foreach ($order['items'] as $item): ?>
            <li class="order-item">
              <img class="order-item__image" alt=""
                src="<?= htmlspecialchars($item['image_url'] ?? ORDER_DEFAULT_IMAGE) ?>">
              <div class="order-item__details">
                <p class="order-item__name"><?= htmlspecialchars($item['item_name']) ?></p>
                <?php if ($item['store_name'] !== null): ?>
                  <p class="order-item__meta">Sold by <?= htmlspecialchars($item['store_name']) ?></p>
                <?php endif; ?>
                <p class="order-item__meta">
                  Qty <?= (int) $item['quantity'] ?> · <?= formatMoney($item['unit_price']) ?> each
                  · <?= $item['fulfillment_type'] === 'meetup' ? 'Local meetup' : 'Shipping' ?>
                </p>
                <p class="order-item__meta">
                  <?php $deadline = htmlspecialchars(date('F j, Y', strtotime((string) $item['return_deadline']))); ?>
                  <?php if ($item['item_status'] === 'cancelled'): ?>
                    Cancelled<?= $item['cancelled_at'] !== null ? ' ' . htmlspecialchars(date('F j, Y', strtotime((string) $item['cancelled_at']))) : '' ?>
                  <?php elseif ((int) $item['return_window_days'] === 0): ?>
                    No returns
                  <?php elseif ((bool) $item['can_cancel']): ?>
                    Returnable until <?= $deadline ?>
                  <?php else: ?>
                    Return window ended <?= $deadline ?>
                  <?php endif; ?>
                </p>
              </div>
              <div class="order-item__side">
                <span class="order-badge order-badge--<?= orderStatusTone($item['item_status']) ?>">
                  <?= htmlspecialchars(formatOrderStatus($item['item_status'])) ?>
                </span>
                <?php if ((bool) $item['can_cancel']): ?>
                  <form method="post" action="/pages/orders.php"
                    onsubmit="return confirm('Cancel this item? This can\'t be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                    <input type="hidden" name="action" value="cancel_item">
                    <input type="hidden" name="order_item_id" value="<?= (int) $item['order_item_id'] ?>">
                    <button type="submit" class="order-item__cancel">Cancel item</button>
                  </form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>

        <dl class="order-totals">
          <dt>Subtotal</dt>
          <dd><?= formatMoney($order['subtotal']) ?></dd>
          <dt>Shipping</dt>
          <dd><?= formatMoney($order['shipping_cost']) ?></dd>
          <dt>Tax</dt>
          <dd><?= formatMoney($order['tax_amount']) ?></dd>
          <dt class="order-totals__total">Total</dt>
          <dd class="order-totals__total"><?= formatMoney($order['total_amount']) ?></dd>
        </dl>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/../components/footer.php'; ?>
