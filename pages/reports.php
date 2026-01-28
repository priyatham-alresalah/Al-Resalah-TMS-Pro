<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';

/* RBAC Check */
requirePermission('reports', 'view');

/* Admin only */
if ($_SESSION['user']['role'] !== 'admin') {
  die('Access denied');
}

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* FETCH ALL USERS */
$users = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role&order=full_name.asc",
    false,
    $ctx
  ),
  true
) ?: [];

/* FETCH ALL DATA FOR STATISTICS */
$clients = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/clients?select=id,created_by,company_name",
    false,
    $ctx
  ),
  true
) ?: [];

$inquiries = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?select=id,created_by,status",
    false,
    $ctx
  ),
  true
) ?: [];

$trainings = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/trainings?select=id,trainer_id,status",
    false,
    $ctx
  ),
  true
) ?: [];

$certificates = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/certificates?select=id,issued_by,status",
    false,
    $ctx
  ),
  true
) ?: [];

$invoices = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/invoices?select=id,status",
    false,
    $ctx
  ),
  true
) ?: [];

/* CALCULATE STATISTICS PER USER */
$userStats = [];

foreach ($users as $user) {
  $userId = $user['id'];
  
  $userStats[$userId] = [
    'user' => $user,
    'clients' => 0,
    'inquiries' => ['total' => 0, 'new' => 0, 'quoted' => 0, 'accepted' => 0, 'scheduled' => 0],
    'trainings' => ['total' => 0, 'scheduled' => 0, 'ongoing' => 0, 'completed' => 0],
    'certificates' => ['total' => 0, 'active' => 0, 'revoked' => 0],
    'invoices' => 0
  ];
  
  /* Count clients created by user */
  foreach ($clients as $client) {
    if (!empty($client['created_by']) && $client['created_by'] === $userId) {
      $userStats[$userId]['clients']++;
    }
  }
  
  /* Count inquiries created by user */
  foreach ($inquiries as $inquiry) {
    if (!empty($inquiry['created_by']) && $inquiry['created_by'] === $userId) {
      $userStats[$userId]['inquiries']['total']++;
      $status = strtolower($inquiry['status'] ?? 'new');
      if (isset($userStats[$userId]['inquiries'][$status])) {
        $userStats[$userId]['inquiries'][$status]++;
      }
    }
  }
  
  /* Count trainings assigned to trainer */
  foreach ($trainings as $training) {
    if (!empty($training['trainer_id']) && $training['trainer_id'] === $userId) {
      $userStats[$userId]['trainings']['total']++;
      $status = strtolower($training['status'] ?? 'scheduled');
      if (isset($userStats[$userId]['trainings'][$status])) {
        $userStats[$userId]['trainings'][$status]++;
      }
    }
  }
  
  /* Count certificates issued by user */
  foreach ($certificates as $cert) {
    if (!empty($cert['issued_by']) && $cert['issued_by'] === $userId) {
      $userStats[$userId]['certificates']['total']++;
      $status = strtolower($cert['status'] ?? 'active');
      if ($status === 'active') {
        $userStats[$userId]['certificates']['active']++;
      } elseif ($status === 'revoked') {
        $userStats[$userId]['certificates']['revoked']++;
      }
    }
  }
}

/* CALCULATE OVERALL STATISTICS */
$overallStats = [
  'total_users' => count($users),
  'total_clients' => count($clients),
  'total_inquiries' => count($inquiries),
  'total_trainings' => count($trainings),
  'total_certificates' => count($certificates),
  'total_invoices' => count($invoices),
  'inquiries_by_status' => [],
  'trainings_by_status' => [],
  'certificates_by_status' => []
];

foreach ($inquiries as $inq) {
  $status = strtolower($inq['status'] ?? 'new');
  $overallStats['inquiries_by_status'][$status] = ($overallStats['inquiries_by_status'][$status] ?? 0) + 1;
}

foreach ($trainings as $tr) {
  $status = strtolower($tr['status'] ?? 'scheduled');
  $overallStats['trainings_by_status'][$status] = ($overallStats['trainings_by_status'][$status] ?? 0) + 1;
}

foreach ($certificates as $cert) {
  $status = strtolower($cert['status'] ?? 'active');
  $overallStats['certificates_by_status'][$status] = ($overallStats['certificates_by_status'][$status] ?? 0) + 1;
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Reports</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/training-management-system/favicon.ico">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Reports</h2>
      <p class="muted">User-wise statistics and system overview</p>
    </div>
  </div>

  <!-- OVERALL STATISTICS -->
  <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_users'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Users</div>
    </div>
    
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_clients'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Clients</div>
    </div>
    
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_inquiries'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Inquiries</div>
    </div>
    
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_trainings'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Trainings</div>
    </div>
    
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_certificates'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Certificates</div>
    </div>
    
    <div class="card" style="text-align: center; padding: 20px;">
      <div style="font-size: 32px; font-weight: bold; color: #2563eb; margin-bottom: 8px;">
        <?= $overallStats['total_invoices'] ?>
      </div>
      <div style="color: #6b7280; font-size: 14px;">Total Invoices</div>
    </div>
  </div>

  <!-- USER-WISE REPORTS -->
  <h3 style="margin-bottom: 20px; color: #1f2937;">User-wise Statistics</h3>
  
  <table class="table">
    <thead>
      <tr>
        <th>User</th>
        <th>Role</th>
        <th>Clients</th>
        <th>Inquiries</th>
        <th>Trainings</th>
        <th>Certificates</th>
        <th>Invoices</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($userStats as $stats): 
        $user = $stats['user'];
        $hasActivity = $stats['clients'] > 0 || 
                      $stats['inquiries']['total'] > 0 || 
                      $stats['trainings']['total'] > 0 || 
                      $stats['certificates']['total'] > 0 || 
                      $stats['invoices'] > 0;
      ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($user['full_name']) ?></strong><br>
            <small style="color: #6b7280;"><?= htmlspecialchars($user['email']) ?></small>
          </td>
          <td>
            <span class="badge badge-info"><?= strtoupper($user['role']) ?></span>
          </td>
          <td>
            <?php if ($stats['clients'] > 0): ?>
              <strong><?= $stats['clients'] ?></strong>
            <?php else: ?>
              <span style="color: #9ca3af;">0</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($stats['inquiries']['total'] > 0): ?>
              <strong><?= $stats['inquiries']['total'] ?></strong>
              <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                <?php if ($stats['inquiries']['new'] > 0): ?>
                  <span>New: <?= $stats['inquiries']['new'] ?></span>
                <?php endif; ?>
                <?php if ($stats['inquiries']['quoted'] > 0): ?>
                  <span>Quoted: <?= $stats['inquiries']['quoted'] ?></span>
                <?php endif; ?>
                <?php if ($stats['inquiries']['accepted'] > 0): ?>
                  <span>Accepted: <?= $stats['inquiries']['accepted'] ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color: #9ca3af;">0</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($stats['trainings']['total'] > 0): ?>
              <strong><?= $stats['trainings']['total'] ?></strong>
              <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                <?php if ($stats['trainings']['scheduled'] > 0): ?>
                  <span>Scheduled: <?= $stats['trainings']['scheduled'] ?></span>
                <?php endif; ?>
                <?php if ($stats['trainings']['ongoing'] > 0): ?>
                  <span>Ongoing: <?= $stats['trainings']['ongoing'] ?></span>
                <?php endif; ?>
                <?php if ($stats['trainings']['completed'] > 0): ?>
                  <span>Completed: <?= $stats['trainings']['completed'] ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color: #9ca3af;">0</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($stats['certificates']['total'] > 0): ?>
              <strong><?= $stats['certificates']['total'] ?></strong>
              <div style="font-size: 11px; color: #6b7280; margin-top: 4px;">
                <?php if ($stats['certificates']['active'] > 0): ?>
                  <span style="color: #16a34a;">Active: <?= $stats['certificates']['active'] ?></span>
                <?php endif; ?>
                <?php if ($stats['certificates']['revoked'] > 0): ?>
                  <span style="color: #dc2626;">Revoked: <?= $stats['certificates']['revoked'] ?></span>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span style="color: #9ca3af;">0</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($stats['invoices'] > 0): ?>
              <strong><?= $stats['invoices'] ?></strong>
            <?php else: ?>
              <span style="color: #9ca3af;">0</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      
      <?php if (empty($userStats)): ?>
        <tr>
          <td colspan="7" style="text-align: center; color: #6b7280; padding: 40px;">
            No user statistics available
          </td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- STATUS BREAKDOWN -->
  <div style="margin-top: 40px;">
    <h3 style="margin-bottom: 20px; color: #1f2937;">Status Breakdown</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
      <!-- Inquiries Status -->
      <div class="card">
        <h4 style="margin-bottom: 15px; color: #374151;">Inquiries Status</h4>
        <?php if (!empty($overallStats['inquiries_by_status'])): ?>
          <?php foreach ($overallStats['inquiries_by_status'] as $status => $count): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
              <span style="text-transform: capitalize;"><?= htmlspecialchars($status) ?></span>
              <strong><?= $count ?></strong>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color: #6b7280;">No inquiries</p>
        <?php endif; ?>
      </div>
      
      <!-- Trainings Status -->
      <div class="card">
        <h4 style="margin-bottom: 15px; color: #374151;">Trainings Status</h4>
        <?php if (!empty($overallStats['trainings_by_status'])): ?>
          <?php foreach ($overallStats['trainings_by_status'] as $status => $count): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
              <span style="text-transform: capitalize;"><?= htmlspecialchars($status) ?></span>
              <strong><?= $count ?></strong>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color: #6b7280;">No trainings</p>
        <?php endif; ?>
      </div>
      
      <!-- Certificates Status -->
      <div class="card">
        <h4 style="margin-bottom: 15px; color: #374151;">Certificates Status</h4>
        <?php if (!empty($overallStats['certificates_by_status'])): ?>
          <?php foreach ($overallStats['certificates_by_status'] as $status => $count): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
              <span style="text-transform: capitalize;"><?= htmlspecialchars($status) ?></span>
              <strong><?= $count ?></strong>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p style="color: #6b7280;">No certificates</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>



