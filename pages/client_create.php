<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/csrf.php';

/* RBAC Check */
requirePermission('clients', 'create');
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Client</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Create Client</h2>
      <p class="muted">Add a new client company and contact details</p>
    </div>
    <div class="actions">
      <a href="clients.php" class="btn btn-sm btn-secondary">Back to Clients</a>
    </div>
  </div>

  <?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['error']) ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
      <?= htmlspecialchars($_GET['success']) ?>
    </div>
  <?php endif; ?>

  <div style="background: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-top: 20px; max-width: 700px;">
    <form action="<?= BASE_PATH ?>/api/clients/create.php" method="post" id="clientForm">
      <?= csrfField() ?>
      
      <div style="margin-bottom: 24px;">
        <label for="company_name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Company Name *</label>
        <input type="text" id="company_name" name="company_name" required autocomplete="organization" placeholder="Enter company name" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="contact_person" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Contact Person</label>
        <input type="text" id="contact_person" name="contact_person" autocomplete="name" placeholder="Enter contact person name" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="email" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Email</label>
        <input type="email" id="email" name="email" autocomplete="email" placeholder="Enter email address" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="phone" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Phone</label>
        <input type="text" id="phone" name="phone" autocomplete="tel" placeholder="Enter phone number" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #ffffff;">
      </div>

      <div style="margin-bottom: 24px;">
        <label for="address" style="display: block; margin-bottom: 8px; font-weight: 600; color: #111827; font-size: 14px;">Address</label>
        <textarea id="address" name="address" autocomplete="street-address" rows="4" placeholder="Enter company address" style="width: 100%; padding: 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; resize: vertical; box-sizing: border-box; background: #ffffff; min-height: 100px; font-family: inherit;"></textarea>
      </div>

      <div style="margin-top: 32px; display: flex; gap: 12px; align-items: center;">
        <button type="submit" style="padding: 12px 24px; font-size: 14px; font-weight: 600; background: #2563eb; color: #ffffff; border: none; border-radius: 6px; cursor: pointer; transition: background 0.2s;">Create Client</button>
        <a href="clients.php" style="padding: 12px 24px; text-decoration: none; color: #dc2626; border: 1px solid #dc2626; border-radius: 6px; display: inline-block; font-size: 14px; font-weight: 500; transition: all 0.2s;">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




