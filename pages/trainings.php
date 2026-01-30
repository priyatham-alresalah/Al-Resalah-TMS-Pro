<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/csrf.php';
require '../includes/rbac.php';
require '../includes/pagination.php';
require '../includes/cache.php';

/* RBAC Check */
requirePermission('trainings', 'view');

/* PAGINATION */
$pagination = getPaginationParams();
$page = $pagination['page'];
$limit = $pagination['limit'];
$offset = $pagination['offset'];

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Prefer: count=exact"
  ]
]);

/* FETCH TRAININGS (paginated) */
$trainingsUrl = SUPABASE_URL . "/rest/v1/trainings?order=training_date.desc&limit=$limit&offset=$offset";
$trainingsResponse = @file_get_contents($trainingsUrl, false, $ctx);

// Get total count from headers
$totalCount = 0;
if ($trainingsResponse !== false) {
  $responseHeaders = $http_response_header ?? [];
  foreach ($responseHeaders as $header) {
    if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $header, $matches)) {
      $totalCount = intval($matches[1]);
      break;
    }
  }
}

$trainings = json_decode($trainingsResponse, true) ?: [];
$totalPages = $totalCount > 0 ? ceil($totalCount / $limit) : 1;

/* FETCH CLIENTS (cached) */
$clientsCacheKey = 'clients_all';
$clients = getCache($clientsCacheKey, 600);
if ($clients === null) {
  $clients = json_decode(
    @file_get_contents(SUPABASE_URL . "/rest/v1/clients?select=id,company_name", false, $ctx),
    true
  ) ?: [];
  setCache($clientsCacheKey, $clients);
}

$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c['company_name'];
}

/* FETCH TRAINERS (cached) */
$trainersCacheKey = 'trainers_all';
$trainers = getCache($trainersCacheKey, 600);
if ($trainers === null) {
  $trainers = json_decode(
    @file_get_contents(SUPABASE_URL . "/rest/v1/profiles?role=eq.trainer&select=id,full_name", false, $ctx),
    true
  ) ?: [];
  setCache($trainersCacheKey, $trainers);
}

$trainerMap = [];
foreach ($trainers as $t) {
  $trainerMap[$t['id']] = $t['full_name'];
}

/* FETCH CANDIDATES (cached, for assignment modal only) */
$candidatesCacheKey = 'candidates_all';
$allCandidates = getCache($candidatesCacheKey, 600);
if ($allCandidates === null) {
  $allCandidates = json_decode(
    @file_get_contents(SUPABASE_URL . "/rest/v1/candidates?select=id,full_name,client_id,email&order=full_name.asc", false, $ctx),
    true
  ) ?: [];
  setCache($candidatesCacheKey, $allCandidates);
}

// Group candidates by client_id for modal
$candidatesByClient = [];
$individualCandidates = [];
foreach ($allCandidates as $c) {
  if (!empty($c['client_id']) && isset($clientMap[$c['client_id']])) {
    if (!isset($candidatesByClient[$c['client_id']])) {
      $candidatesByClient[$c['client_id']] = [];
    }
    $candidatesByClient[$c['client_id']][] = $c;
  } else {
    $individualCandidates[] = $c;
  }
}

/* FETCH TRAINING CANDIDATES */
$trainingIds = array_column($trainings, 'id');
$trainingCandidatesMap = [];
if (!empty($trainingIds)) {
  // Fetch all training_candidates for these trainings (Supabase uses in.(id1,id2) format)
  $trainingIdsStr = implode(',', $trainingIds);
  $trainingCandidates = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/training_candidates?training_id=in.(" . $trainingIdsStr . ")&select=training_id,candidate_id",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  // Get unique candidate IDs
  $candidateIds = array_unique(array_filter(array_column($trainingCandidates, 'candidate_id')));
  
  if (!empty($candidateIds)) {
    // Fetch candidate details
    $candidateIdsStr = implode(',', $candidateIds);
    $candidates = json_decode(
      file_get_contents(
        SUPABASE_URL . "/rest/v1/candidates?id=in.(" . $candidateIdsStr . ")&select=id,full_name,client_id",
        false,
        $ctx
      ),
      true
    ) ?: [];
    
    // Create candidate map
    $candidateMap = [];
    foreach ($candidates as $c) {
      $candidateMap[$c['id']] = $c;
    }
    
    // Group candidates by training_id
    foreach ($trainingCandidates as $tc) {
      $tid = $tc['training_id'];
      $cid = $tc['candidate_id'];
      if (!isset($trainingCandidatesMap[$tid])) {
        $trainingCandidatesMap[$tid] = [];
      }
      if (isset($candidateMap[$cid])) {
        $trainingCandidatesMap[$tid][] = $candidateMap[$cid];
      }
    }
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Trainings</title>
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_PATH ?>/assets/css/responsive.css">
  <link rel="icon" href="<?= BASE_PATH ?>/favicon.ico">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <div class="page-header">
    <div>
      <h2>Trainings</h2>
      <p class="muted">Manage training lifecycle. Each training can have one client and any number of candidates — use <strong>Candidates</strong> in Actions to add or bulk-assign.</p>
    </div>
    <?php if (hasPermission('trainings', 'create')): ?>
      <a href="schedule_training.php" class="btn btn-primary">
        + Schedule Training
      </a>
    <?php endif; ?>
  </div>

  <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET['success'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
  <?php endif; ?>

<table class="table">
  <thead>
    <tr>
      <th>Client &amp; candidates <span class="muted" style="font-weight: normal; font-size: 12px;">(many per training)</span></th>
      <th>Course</th>
      <th>Trainer</th>
      <th>Date</th>
      <th>Time</th>
      <th>Status</th>
      <th style="width: 60px;">Actions</th>
    </tr>
  </thead>
  <tbody>

<?php if ($trainings): foreach ($trainings as $t): 
  $trainingCandidates = $trainingCandidatesMap[$t['id']] ?? [];
  $hasClient = !empty($t['client_id']) && isset($clientMap[$t['client_id']]);
  $rowId = 'training_' . $t['id'];
?>
<tr class="training-row" data-training-id="<?= $t['id'] ?>">
  <td>
    <?php if ($hasClient): ?>
      <strong><?= htmlspecialchars($clientMap[$t['client_id']]) ?></strong>
      <?php
        $candidateCount = count($trainingCandidates);
      ?>
      <div style="margin-top: 4px; font-size: 13px;">
        <?php if ($candidateCount > 0): ?>
          <?php
            $firstCandidate = $trainingCandidates[0];
            $remainingCount = $candidateCount - 1;
          ?>
          <span style="color: #6b7280;"><?= $candidateCount ?> candidate<?= $candidateCount === 1 ? '' : 's' ?> — <?= htmlspecialchars($firstCandidate['full_name']) ?></span>
          <?php if ($remainingCount > 0): ?>
            <span class="candidate-count-badge">+<?= $remainingCount ?> more</span>
            <button type="button" class="btn-toggle-candidates" onclick="toggleTrainingCandidates('<?= $rowId ?>')">
              <span class="toggle-text-<?= $rowId ?>">Show</span>
            </button>
          <?php endif; ?>
        <?php else: ?>
          <span style="color: #9ca3af; font-style: italic;">No candidates (add via Candidates action)</span>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <!-- Direct candidates (no client) -->
      <?php if (!empty($trainingCandidates)): ?>
        <?php 
          $candidateCount = count($trainingCandidates);
          $firstCandidate = $trainingCandidates[0];
          $remainingCount = $candidateCount - 1;
        ?>
        <span style="color: #6b7280; font-size: 13px;"><?= $candidateCount ?> candidate<?= $candidateCount === 1 ? '' : 's' ?> — <?= htmlspecialchars($firstCandidate['full_name']) ?></span>
        <?php if ($remainingCount > 0): ?>
          <span class="candidate-count-badge">+<?= $remainingCount ?> more</span>
          <button type="button" onclick="toggleTrainingCandidates('<?= $rowId ?>')" 
                  style="margin-left: 6px; background: none; border: none; color: #2563eb; cursor: pointer; font-size: 12px; text-decoration: underline;">
            <span class="toggle-text-<?= $rowId ?>">Show</span>
          </button>
        <?php endif; ?>
        <!-- Hidden candidate rows -->
        <?php if ($candidateCount > 1): ?>
          <?php foreach (array_slice($trainingCandidates, 1) as $cand): ?>
            <tr class="candidate-row candidate-row-<?= $rowId ?>" style="display: none;">
              <td style="padding-left: 30px; color: #6b7280;">
                <?= htmlspecialchars($cand['full_name']) ?>
              </td>
              <td colspan="6"></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php else: ?>
        <span style="color: #6b7280; font-style: italic;">Individual</span>
      <?php endif; ?>
    <?php endif; ?>
  </td>
  <td><?= htmlspecialchars($t['course_name']) ?></td>
  <td>
    <?php 
      $trainerId = $t['trainer_id'] ?? null;
      if ($trainerId && isset($trainerMap[$trainerId])) {
        echo htmlspecialchars($trainerMap[$trainerId]);
      } else {
        echo '<span style="color: #9ca3af; font-style: italic;">Not assigned</span>';
      }
    ?>
  </td>
  <td>
    <?php 
      if (!empty($t['training_date'])) {
        $date = strtotime($t['training_date']);
        echo $date !== false ? date('d M Y', $date) : '-';
      } else {
        echo '-';
      }
    ?>
  </td>
  <td>
    <?php if (!empty($t['training_time'])): ?>
      <?php
        $time = strtotime($t['training_time']);
        echo $time !== false ? date('g:i A', $time) : '-';
      ?>
    <?php else: ?>
      —
    <?php endif; ?>
  </td>
  <td>
    <span class="badge badge-<?= strtolower($t['status']) ?>">
      <?= strtoupper($t['status']) ?>
    </span>
  </td>
  <td class="col-actions">
    <div class="action-menu-wrapper">
      <button type="button" class="btn-icon action-menu-toggle" aria-label="Open actions">
        &#8942;
      </button>
      <div class="action-menu">
        <a href="training_candidates.php?training_id=<?= $t['id'] ?>">Candidates</a>
        <a href="#" class="js-assign-trainer" data-training-id="<?= htmlspecialchars($t['id']) ?>" data-course-name="<?= htmlspecialchars($t['course_name'] ?? '') ?>" data-trainer-id="<?= htmlspecialchars($t['trainer_id'] ?? '') ?>">Assign Trainer</a>
        <?php if ($t['status'] === 'scheduled'): ?>
          <form method="post" action="../api/trainings/update.php" onsubmit="return validateStartTraining('<?= $t['id'] ?>', '<?= htmlspecialchars($t['trainer_id'] ?? '', ENT_QUOTES) ?>');">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <input type="hidden" name="status" value="ongoing">
            <button type="submit" <?= empty($t['trainer_id']) ? 'disabled title="Please assign a trainer first"' : '' ?>>Start</button>
          </form>
        <?php endif; ?>
        <?php if ($t['status'] === 'ongoing'): ?>
          <form method="post" action="../api/trainings/update.php">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <input type="hidden" name="status" value="completed">
            <button type="submit">Complete</button>
          </form>
        <?php endif; ?>
        <?php if ($t['status'] === 'completed'): ?>
          <a href="issue_certificates.php?training_id=<?= $t['id'] ?>">Issue Certificates</a>
        <?php endif; ?>
      </div>
    </div>
  </td>
</tr>
<?php 
  // Add hidden candidate rows after the main training row
  if (!empty($trainingCandidates) && count($trainingCandidates) > 1):
    foreach (array_slice($trainingCandidates, 1) as $cand): 
?>
<tr class="candidate-row candidate-row-<?= $rowId ?>" style="display: none;">
  <td style="padding-left: 30px; color: #6b7280;">
    <?= htmlspecialchars($cand['full_name']) ?>
  </td>
  <td colspan="6"></td>
</tr>
<?php 
    endforeach;
  endif;
endforeach; else: ?>
<tr>
  <td colspan="7" style="text-align: center; padding: 40px; color: #6b7280;">
    <div style="font-size: 16px; margin-bottom: 8px;">No trainings found</div>
    <div style="font-size: 14px;">Create your first training to get started</div>
  </td>
</tr>
<?php endif; ?>

  </tbody>
</table>

<!-- Mobile Cards -->
<div class="mobile-cards">
  <?php if ($trainings): foreach ($trainings as $t): 
    $trainingCandidates = $trainingCandidatesMap[$t['id']] ?? [];
    $hasClient = !empty($t['client_id']) && isset($clientMap[$t['client_id']]);
    $status = strtolower($t['status'] ?? 'scheduled');
    $badgeClass = 'badge-info';
    if ($status === 'completed') $badgeClass = 'badge-success';
    elseif ($status === 'cancelled') $badgeClass = 'badge-danger';
  ?>
    <div class="mobile-card">
      <div class="mobile-card-header">
        <div class="mobile-card-title">
          <?php if ($hasClient): ?>
            <?= htmlspecialchars($clientMap[$t['client_id']]) ?>
          <?php else: ?>
            <span style="color: #6b7280; font-style: italic;">Individual</span>
          <?php endif; ?>
        </div>
        <span class="badge <?= $badgeClass ?> mobile-card-badge">
          <?= strtoupper($status) ?>
        </span>
      </div>
      <div class="mobile-card-field">
        <span class="mobile-card-label">Course:</span>
        <span class="mobile-card-value"><?= htmlspecialchars($t['course_name'] ?? '-') ?></span>
      </div>
      <?php if (!empty($t['trainer_id']) && isset($trainerMap[$t['trainer_id']])): ?>
      <div class="mobile-card-field">
        <span class="mobile-card-label">Trainer:</span>
        <span class="mobile-card-value"><?= htmlspecialchars($trainerMap[$t['trainer_id']]) ?></span>
      </div>
      <?php endif; ?>
      <div class="mobile-card-field">
        <span class="mobile-card-label">Date:</span>
        <span class="mobile-card-value"><?= $t['training_date'] ? date('d M Y', strtotime($t['training_date'])) : '-' ?></span>
      </div>
      <?php if (!empty($t['training_time'])): ?>
      <div class="mobile-card-field">
        <span class="mobile-card-label">Time:</span>
        <span class="mobile-card-value"><?= htmlspecialchars($t['training_time']) ?></span>
      </div>
      <?php endif; ?>
      <?php if (!empty($trainingCandidates)): ?>
      <div class="mobile-card-field">
        <span class="mobile-card-label">Candidates:</span>
        <span class="mobile-card-value"><?= count($trainingCandidates) ?> assigned</span>
      </div>
      <?php endif; ?>
      <div class="mobile-card-actions">
        <a href="training_edit.php?id=<?= $t['id'] ?>" class="btn">View / Edit</a>
        <?php if ($t['status'] === 'scheduled'): ?>
          <form method="post" action="../api/trainings/update.php" style="margin: 0;">
            <?= csrfField() ?>
            <input type="hidden" name="id" value="<?= $t['id'] ?>">
            <input type="hidden" name="status" value="completed">
            <button type="submit" class="btn">Complete</button>
          </form>
        <?php endif; ?>
        <?php if ($t['status'] === 'completed'): ?>
          <a href="issue_certificates.php?training_id=<?= $t['id'] ?>" class="btn">Issue Certificates</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; else: ?>
    <div class="empty-state">
      <div class="empty-state-icon">🎓</div>
      <div class="empty-state-title">No trainings found</div>
      <div class="empty-state-message">Create your first training to get started</div>
    </div>
  <?php endif; ?>
</div>

<?php
// Render pagination
if ($totalPages > 1) {
  renderPagination($page, $totalPages);
}
?>

<!-- Trainer Assignment Modal -->
<div id="trainerModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1050; align-items: center; justify-content: center;">
  <div style="background: white; padding: 25px; border-radius: 8px; max-width: 400px; width: 90%; box-shadow: 0 10px 25px rgba(0,0,0,0.2);" onclick="event.stopPropagation();">
    <h3 style="margin-bottom: 15px;">Assign Trainer</h3>
    <p id="modalCourseName" style="color: #6b7280; margin-bottom: 15px;"></p>
    <form id="trainerForm" method="post" action="<?= htmlspecialchars(BASE_PATH) ?>/api/trainings/assign_trainer.php">
      <?= csrfField() ?>
      <input type="hidden" name="training_id" id="modalTrainingId">
      <div class="form-group">
        <label>Select Trainer</label>
        <select name="trainer_id" id="modalTrainerId" style="width: 100%; padding: 8px;">
          <option value="">Not assigned</option>
          <?php foreach ($trainers as $trainer): ?>
            <option value="<?= $trainer['id'] ?>"><?= htmlspecialchars($trainer['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Assign</button>
        <button type="button" class="btn-cancel" onclick="closeTrainerModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
  function assignTrainer(trainingId, courseName, currentTrainerId) {
    try {
      var modal = document.getElementById('trainerModal');
      var modalTrainingId = document.getElementById('modalTrainingId');
      var modalCourseName = document.getElementById('modalCourseName');
      var modalTrainerId = document.getElementById('modalTrainerId');
      
      if (!modal || !modalTrainingId || !modalCourseName || !modalTrainerId) {
        console.error('Modal elements not found');
        alert('Error: Modal elements not found. Please refresh the page.');
        return false;
      }
      
      modalTrainingId.value = trainingId;
      modalCourseName.textContent = 'Course: ' + courseName;
      
      // Set current trainer if exists
      modalTrainerId.value = currentTrainerId || '';
      
      modal.style.display = 'flex';
      
      // Close all action menus
      document.querySelectorAll('.action-menu.open').forEach(function (openMenu) {
        openMenu.classList.remove('open');
      });
      
      return false;
    } catch (error) {
      console.error('Error in assignTrainer:', error);
      alert('Error opening trainer assignment modal. Please check the console for details.');
      return false;
    }
  }

  function closeTrainerModal() {
    var modal = document.getElementById('trainerModal');
    if (modal) {
      modal.style.display = 'none';
    }
    return false;
  }

  // Close modal when clicking outside
  document.getElementById('trainerModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
      closeTrainerModal();
    }
  });

  // Assign Trainer link (delegated – uses data attributes so course names with quotes work)
  document.addEventListener('click', function(e) {
    var link = e.target.closest('.js-assign-trainer');
    if (link) {
      e.preventDefault();
      var trainingId = link.getAttribute('data-training-id');
      var courseName = link.getAttribute('data-course-name') || '';
      var trainerId = link.getAttribute('data-trainer-id') || '';
      assignTrainer(trainingId, courseName, trainerId);
      return false;
    }
  });

  document.addEventListener('click', function (event) {
    const isToggle = event.target.closest('.action-menu-toggle');
    const wrappers = document.querySelectorAll('.action-menu-wrapper');

    wrappers.forEach(function (wrapper) {
      const menu = wrapper.querySelector('.action-menu');
      if (!menu) return;

      if (isToggle && wrapper.contains(isToggle)) {
        const isOpen = menu.classList.contains('open');
        document.querySelectorAll('.action-menu.open').forEach(function (openMenu) {
          openMenu.classList.remove('open');
        });
        if (!isOpen) {
          menu.classList.add('open');
        }
      } else {
        menu.classList.remove('open');
      }
    });
  });

  function toggleTrainingCandidates(rowId) {
    const candidateRows = document.querySelectorAll('.candidate-row-' + rowId);
    const toggleText = document.querySelector('.toggle-text-' + rowId);
    
    if (!candidateRows.length || !toggleText) return;
    
    const isHidden = candidateRows[0].style.display === 'none';
    
    candidateRows.forEach(function(row) {
      row.style.display = isHidden ? 'table-row' : 'none';
    });
    
    toggleText.textContent = isHidden ? 'Hide' : 'Show';
  }

  function validateStartTraining(trainingId, trainerId) {
    if (!trainerId || trainerId === '') {
      alert('Cannot start training without a trainer. Please assign a trainer first.');
      return false;
    }
    return true;
  }

</script>

</main>

<?php include '../layout/footer.php'; ?>
</body>
</html>



