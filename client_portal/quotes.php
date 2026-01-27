<?php
session_start();
require '../includes/config.php';

if (!isset($_SESSION['client'])) {
  header('Location: login.php');
  exit;
}

$client = $_SESSION['client'];
$clientId = $client['id'];
$error = '';
$success = '';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE
  ]
]);

/* Fetch quoted inquiries */
$quotedInquiries = json_decode(
  file_get_contents(
    SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.$clientId&status=eq.quoted&order=quoted_at.desc",
    false,
    $ctx
  ),
  true
) ?: [];

/* Group by quote_no */
$quotes = [];
foreach ($quotedInquiries as $inq) {
  $quoteNo = $inq['quote_no'] ?? 'UNKNOWN';
  if (!isset($quotes[$quoteNo])) {
    $quotes[$quoteNo] = [
      'quote_no' => $quoteNo,
      'quote_pdf' => $inq['quote_pdf'] ?? null,
      'quoted_at' => $inq['quoted_at'] ?? $inq['created_at'],
      'courses' => [],
      'total' => 0
    ];
  }
  $quotes[$quoteNo]['courses'][] = $inq;
  $quotes[$quoteNo]['total'] += ($inq['quote_total'] ?? 0);
}

/* Handle response */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $inquiryId = $_POST['inquiry_id'] ?? '';
  $action = $_POST['action'] ?? '';
  $reason = trim($_POST['reason'] ?? '');
  
  if ($inquiryId && $action) {
    $data = [
      'status' => $action === 'accept' ? 'accepted' : ($action === 'reject' ? 'rejected' : 'new'),
      'response_reason' => $reason,
      'responded_at' => date('Y-m-d H:i:s')
    ];
    
    if ($action === 'accept') {
      $data['accepted_at'] = date('Y-m-d H:i:s');
    } elseif ($action === 'reject') {
      $data['rejected_at'] = date('Y-m-d H:i:s');
    }
    
    $updateCtx = stream_context_create([
      'http' => [
        'method' => 'PATCH',
        'header' =>
          "Content-Type: application/json\r\n" .
          "apikey: " . SUPABASE_SERVICE . "\r\n" .
          "Authorization: Bearer " . SUPABASE_SERVICE,
        'content' => json_encode($data)
      ]
    ]);
    
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?id=eq.$inquiryId",
      false,
      $updateCtx
    );
    
    $success = 'Response submitted successfully!';
  }
  
  // Refresh quotes
  $quotedInquiries = json_decode(
    file_get_contents(
      SUPABASE_URL . "/rest/v1/inquiries?client_id=eq.$clientId&status=eq.quoted&order=quoted_at.desc",
      false,
      $ctx
    ),
    true
  ) ?: [];
  
  $quotes = [];
  foreach ($quotedInquiries as $inq) {
    $quoteNo = $inq['quote_no'] ?? 'UNKNOWN';
    if (!isset($quotes[$quoteNo])) {
      $quotes[$quoteNo] = [
        'quote_no' => $quoteNo,
        'quote_pdf' => $inq['quote_pdf'] ?? null,
        'quoted_at' => $inq['quoted_at'] ?? $inq['created_at'],
        'courses' => [],
        'total' => 0
      ];
    }
    $quotes[$quoteNo]['courses'][] = $inq;
    $quotes[$quoteNo]['total'] += ($inq['quote_total'] ?? 0);
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Client Portal - Quotes</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div style="background: #1f2937; color: #fff; padding: 15px 25px; display: flex; justify-content: space-between; align-items: center;">
    <h2 style="margin: 0;">Client Portal - <?= htmlspecialchars($client['company_name']) ?></h2>
    <div>
      <a href="dashboard.php" style="color: #fff; margin-right: 15px; text-decoration: none;">Dashboard</a>
      <a href="inquiry.php" style="color: #fff; margin-right: 15px; text-decoration: none;">New Inquiry</a>
      <a href="quotes.php" style="color: #fff; margin-right: 15px; text-decoration: none; font-weight: bold;">Quotes</a>
      <span><?= htmlspecialchars($client['email']) ?></span>
      <a href="logout.php" style="color: #fff; margin-left: 15px; text-decoration: none;">Logout</a>
    </div>
  </div>

  <main class="content" style="margin-left: 0; margin-top: 0; padding: 25px;">
    <h2>Training Quotes</h2>
    <p class="muted">Review and respond to training quotes</p>

    <?php if ($error): ?>
      <div style="background: #fee2e2; color: #991b1b; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($success): ?>
      <div style="background: #dcfce7; color: #166534; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
        <?= htmlspecialchars($success) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($quotes)): ?>
      <?php foreach ($quotes as $quote): ?>
        <div class="form-card" style="margin-bottom: 20px;">
          <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <div>
              <h3 style="margin: 0 0 5px 0;">Quote #<?= htmlspecialchars($quote['quote_no']) ?></h3>
              <p style="color: #6b7280; margin: 0;">Quoted on: <?= date('d M Y', strtotime($quote['quoted_at'])) ?></p>
            </div>
            <div>
              <?php if ($quote['quote_pdf']): ?>
                <a href="../api/inquiries/download_quote.php?file=<?= urlencode($quote['quote_pdf']) ?>" class="btn btn-sm">Download PDF</a>
              <?php endif; ?>
            </div>
          </div>

          <table class="table" style="margin-bottom: 15px;">
            <thead>
              <tr>
                <th>Course</th>
                <th style="text-align: right;">Amount</th>
                <th style="text-align: right;">VAT</th>
                <th style="text-align: right;">Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($quote['courses'] as $inq): ?>
                <tr>
                  <td><?= htmlspecialchars($inq['course_name']) ?></td>
                  <td style="text-align: right;"><?= number_format($inq['quote_amount'] ?? 0, 2) ?></td>
                  <td style="text-align: right;"><?= number_format($inq['quote_vat'] ?? 0, 2) ?>%</td>
                  <td style="text-align: right;"><strong><?= number_format($inq['quote_total'] ?? 0, 2) ?></strong></td>
                </tr>
              <?php endforeach; ?>
              <tr style="border-top: 2px solid #e5e7eb; font-weight: bold;">
                <td colspan="3" style="text-align: right;">Grand Total:</td>
                <td style="text-align: right;"><?= number_format($quote['total'], 2) ?></td>
              </tr>
            </tbody>
          </table>

          <form method="post" style="border-top: 1px solid #e5e7eb; padding-top: 15px;">
            <input type="hidden" name="inquiry_id" value="<?= $quote['courses'][0]['id'] ?>">
            
            <div class="form-group">
              <label>Your Response *</label>
              <select name="action" required>
                <option value="">Select Response</option>
                <option value="accept">Accept Quote</option>
                <option value="reject">Reject Quote</option>
                <option value="requote">Request Requote</option>
              </select>
            </div>

            <div class="form-group">
              <label>Reason/Comments</label>
              <textarea name="reason" rows="3" placeholder="Enter your reason or comments" style="width: 100%; padding: 8px;"></textarea>
            </div>

            <div class="form-actions">
              <button class="btn" type="submit">Submit Response</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p class="empty-state">No quotes available</p>
    <?php endif; ?>
  </main>
</body>
</html>
