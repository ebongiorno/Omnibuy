<?php

// My Orders: the logged-in buyer's order history, newest first.

require_once __DIR__ . '/../../includes/orders.php';

requireLogin();

require __DIR__ . '/../../db_connect.php';

$orders = loadBuyerOrders($pdo, currentUser()['user_id']);

$pageTitle = 'My Orders | OmniBuy';

include __DIR__ . '/../components/header.php';
?>

<link rel="stylesheet" href="/assets/css/orders.css">

<section class="shell orders-page">
  <h1>My Orders</h1>

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
              </div>
              <span class="order-badge order-badge--<?= orderStatusTone($item['item_status']) ?>">
                <?= htmlspecialchars(formatOrderStatus($item['item_status'])) ?>
              </span>
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
