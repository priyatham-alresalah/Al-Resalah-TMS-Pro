<?php
require 'includes/config.php';
require 'includes/auth_check.php';

$id = $_GET['id'] ?? null;
if (!$id) die('Invalid inquiry');

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

$inq = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/inquiries?id=eq.$id", false, $ctx),
  true
)[0];

// trainers
$trainers = json_decode(
  file_get_contents(SUPABASE_URL . "/rest/v1/profiles?role=eq.trainer", false, $ctx),
  true
);
?>

<!DOCTYPE html>
<html>
<head>
  <title>Convert Inquiry → Training</title>
  <link rel="stylesheet" href="assets/css/layout.css">
</head>
<body>

<?php include 'layout/header.php'; ?>
<?php include 'layout/sidebar.php'; ?>

<main class="content">
  <h2>Convert Inquiry → Training</h2>
  <p class="muted">Schedule training for confirmed inquiry</p>

  <form method="post" action="api/trainings/create_from_inquiry.php">
    <input type="hidden" name="inquiry_id" value="<?= $inq['id'] ?>">
    <input type="hidden" name="client_id" value="<?= $inq['client_id'] ?>">
    <input type="hidden" name="course_name" value="<?= htmlspecialchars($inq['course_name']) ?>">

    <div class="form-row">
      <label>Client</label>
      <input value="<?= htmlspecialchars($inq['client_name']) ?>" disabled>
    </div>

    <div class="form-row">
      <label>Course</label>
      <input value="<?= htmlspecialchars($inq['course_name']) ?>" disabled>
    </div>

    <div class="form-row">
      <label>Trainer</label>
      <select name="trainer_id">
        <option value="">Assign later</option>
        <?php foreach ($trainers as $t): ?>
          <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <label>Training Date *</label>
      <input type="date" name="training_date" required>
    </div>

    <button type="submit">Create Training</button>
    <a href="inquiries.php" class="link">Cancel</a>
  </form>
</main>

<?php include 'layout/footer.php'; ?>
</body>
</html>
