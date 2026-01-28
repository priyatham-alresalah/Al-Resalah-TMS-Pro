<?php
require '../includes/config.php';
require '../includes/auth_check.php';
?>
<!DOCTYPE html>
<html>
<head>
  <title>Create Client</title>
  <link rel="stylesheet" href="../assets/css/style.css">
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

  <div class="form-card">
    <form action="../api/clients/create.php" method="post">
      <?php require '../includes/csrf.php'; echo csrfField(); ?>
      <div class="form-group">
        <label>Company Name *</label>
        <input type="text" name="company_name" required autocomplete="organization">
      </div>

      <div class="form-group">
        <label>Contact Person</label>
        <input type="text" name="contact_person" autocomplete="name">
      </div>

      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" autocomplete="email">
      </div>

      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" autocomplete="tel">
      </div>

      <div class="form-group">
        <label>Address</label>
        <input type="text" name="address" autocomplete="street-address">
      </div>

      <div class="form-actions">
        <button class="btn" type="submit">Create Client</button>
        <a href="clients.php" class="btn-cancel">Cancel</a>
      </div>
    </form>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>




