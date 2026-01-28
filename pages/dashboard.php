<?php
require '../includes/config.php';
require '../includes/auth_check.php';
require '../includes/rbac.php';
require '../includes/app_log.php';

/* RBAC Check - Dashboard accessible to all authenticated users */
// No specific permission required for dashboard

// Performance timing
$dashboardStartTime = microtime(true);

$user = $_SESSION['user'];
$role = $user['role'];
$userId = $user['id'];

/* =========================
   OPTIMIZED DASHBOARD DATA FETCHING
   Uses aggregates and filters instead of loading all records
========================= */
require '../includes/cache.php';

$ctx = stream_context_create([
  'http' => [
    'method' => 'GET',
    'header' =>
      "apikey: " . SUPABASE_SERVICE . "\r\n" .
      "Authorization: Bearer " . SUPABASE_SERVICE . "\r\n" .
      "Prefer: count=exact"
  ]
]);

$currentMonth = date('Y-m');
$currentYear = date('Y');

// Use cached data where possible, or fetch aggregates
$cacheKey = 'dashboard_data_' . $role . '_' . $userId . '_' . $currentMonth;
$cachedData = getCache($cacheKey, 300); // Cache for 5 minutes

if ($cachedData === null) {
  // Fetch only what's needed for dashboard - use filters and aggregates
  // This Month Inquiries
  $thisMonthInquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?created_at=gte.$currentMonth-01&created_at=lt." . date('Y-m-d', strtotime('+1 month')) . "&select=id,status,created_at,created_by";
  $thisMonthInquiries = json_decode(@file_get_contents($thisMonthInquiriesUrl, false, $ctx), true) ?: [];
  
  // This Month Trainings
  $thisMonthTrainingsUrl = SUPABASE_URL . "/rest/v1/trainings?training_date=gte.$currentMonth-01&training_date=lt." . date('Y-m-d', strtotime('+1 month')) . "&select=id,status,training_date";
  $thisMonthTrainings = json_decode(@file_get_contents($thisMonthTrainingsUrl, false, $ctx), true) ?: [];
  
  // This Month Invoices (for revenue)
  $thisMonthInvoicesUrl = SUPABASE_URL . "/rest/v1/invoices?issued_date=gte.$currentMonth-01&issued_date=lt." . date('Y-m-d', strtotime('+1 month')) . "&select=id,total,status,issued_date";
  $thisMonthInvoices = json_decode(@file_get_contents($thisMonthInvoicesUrl, false, $ctx), true) ?: [];
  
  // Outstanding Invoices (unpaid)
  $outstandingInvoicesUrl = SUPABASE_URL . "/rest/v1/invoices?status=eq.unpaid&select=id,total";
  $outstandingInvoices = json_decode(@file_get_contents($outstandingInvoicesUrl, false, $ctx), true) ?: [];
  
  // Completed Trainings (for bottleneck check)
  $completedTrainingsUrl = SUPABASE_URL . "/rest/v1/trainings?status=eq.completed&select=id,training_date";
  $completedTrainings = json_decode(@file_get_contents($completedTrainingsUrl, false, $ctx), true) ?: [];
  
  // Certificates (for bottleneck check)
  $certificatesUrl = SUPABASE_URL . "/rest/v1/certificates?select=id,training_id,issued_date";
  $certificates = json_decode(@file_get_contents($certificatesUrl, false, $ctx), true) ?: [];
  
  // Overdue Invoices
  $overdueDate = date('Y-m-d', strtotime('-30 days'));
  $overdueInvoicesUrl = SUPABASE_URL . "/rest/v1/invoices?due_date=lt.$overdueDate&status=eq.unpaid&select=id";
  $overdueInvoices = json_decode(@file_get_contents($overdueInvoicesUrl, false, $ctx), true) ?: [];
  
  // Users (for team metrics) - only fetch needed fields
  $usersUrl = SUPABASE_URL . "/rest/v1/profiles?select=id,full_name,email,role,created_at";
  $users = json_decode(@file_get_contents($usersUrl, false, $ctx), true) ?: [];
  
  // All Inquiries (for conversion rate - but limit to recent)
  $recentInquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?created_at=gte." . date('Y-m-d', strtotime('-90 days')) . "&select=id,status,created_by";
  $inquiries = json_decode(@file_get_contents($recentInquiriesUrl, false, $ctx), true) ?: [];
  
  // Clients (minimal data)
  $clientsUrl = SUPABASE_URL . "/rest/v1/clients?select=id,created_by,created_at";
  $clients = json_decode(@file_get_contents($clientsUrl, false, $ctx), true) ?: [];
  
  $cachedData = [
    'thisMonthInquiries' => $thisMonthInquiries,
    'thisMonthTrainings' => $thisMonthTrainings,
    'thisMonthInvoices' => $thisMonthInvoices,
    'outstandingInvoices' => $outstandingInvoices,
    'completedTrainings' => $completedTrainings,
    'certificates' => $certificates,
    'overdueInvoices' => $overdueInvoices,
    'users' => $users,
    'inquiries' => $inquiries,
    'clients' => $clients
  ];
  
  setCache($cacheKey, $cachedData);
}

extract($cachedData);

// Build minimal maps
$clientMap = [];
foreach ($clients as $c) {
  $clientMap[$c['id']] = $c;
}

$userMap = [];
foreach ($users as $u) {
  $userMap[$u['id']] = $u;
}

/* =========================
   HELPER FUNCTIONS
========================= */
function isThisMonth($dateStr) {
  if (!$dateStr) return false;
  $date = strtotime($dateStr);
  return date('Y-m', $date) === date('Y-m');
}

function daysAgo($dateStr) {
  if (!$dateStr) return 999;
  return floor((time() - strtotime($dateStr)) / 86400);
}

function formatCurrency($amount) {
  return number_format($amount, 2);
}

/* =========================
   CALCULATE METRICS BY ROLE
========================= */
$metrics = [];

if ($role === 'admin') {
  // ADMIN DASHBOARD - "CONTROL TOWER"
  // Data already filtered by month from optimized queries
  $revenueGenerated = array_sum(array_column($thisMonthInvoices, 'total'));
  $outstandingAmount = array_sum(array_column($outstandingInvoices, 'total'));
  
  // Process bottlenecks
  $completedTrainings = array_filter($trainings, function($t) {
    return strtolower($t['status'] ?? '') === 'completed';
  });
  
  $docsPending = [];
  foreach ($completedTrainings as $t) {
    $hasCert = false;
    foreach ($certificates as $cert) {
      if ($cert['training_id'] === $t['id']) {
        $hasCert = true;
        break;
      }
    }
    if (!$hasCert) {
      $daysSince = daysAgo($t['training_date'] ?? null);
      if ($daysSince > 2) {
        $docsPending[] = $t;
      }
    }
  }
  
  $certificatesPending = array_filter($certificates, function($cert) {
    $issuedDate = $cert['issued_date'] ?? null;
    return $issuedDate && daysAgo($issuedDate) > 2;
  });
  
  $invoicesOverdue = array_filter($invoices, function($inv) {
    $dueDate = $inv['due_date'] ?? null;
    if (!$dueDate) return false;
    return daysAgo($dueDate) > 30 && ($inv['status'] ?? 'unpaid') === 'unpaid';
  });
  
  // Team activity
  $bdoUsers = array_filter($users, function($u) {
    return strtolower($u['role'] ?? '') === 'bdo';
  });
  
  $bdoPerformance = [];
  foreach ($bdoUsers as $bdo) {
    $bdoInquiries = array_filter($inquiries, function($i) use ($bdo) {
      return ($i['created_by'] ?? '') === $bdo['id'];
    });
    $accepted = array_filter($bdoInquiries, function($i) {
      return strtolower($i['status'] ?? '') === 'accepted';
    });
    $conversion = count($bdoInquiries) > 0 ? (count($accepted) / count($bdoInquiries) * 100) : 0;
    
    $bdoPerformance[] = [
      'user' => $bdo,
      'inquiries' => count($bdoInquiries),
      'accepted' => count($accepted),
      'conversion' => $conversion
    ];
  }
  usort($bdoPerformance, function($a, $b) {
    return $b['conversion'] <=> $a['conversion'];
  });
  
  $inactiveUsers = [];
  $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
  foreach ($users as $u) {
    $hasActivity = false;
    foreach ($inquiries as $i) {
      if (($i['created_by'] ?? '') === $u['id'] && ($i['created_at'] ?? '') >= $sevenDaysAgo) {
        $hasActivity = true;
        break;
      }
    }
    if (!$hasActivity) {
      foreach ($clients as $c) {
        if (($c['created_by'] ?? '') === $u['id'] && ($c['created_at'] ?? '') >= $sevenDaysAgo) {
          $hasActivity = true;
          break;
        }
      }
    }
    if (!$hasActivity) {
      $inactiveUsers[] = $u;
    }
  }
  
  // Conversion rate (using recent inquiries only)
  $acceptedInquiries = array_filter($inquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'accepted';
  });
  $conversionRate = count($inquiries) > 0 ? (count($acceptedInquiries) / count($inquiries) * 100) : 0;
  
  $metrics = [
    'type' => 'admin',
    'snapshot' => [
      'total_inquiries' => count($thisMonthInquiries),
      'trainings_conducted' => count($thisMonthTrainings),
      'revenue_generated' => $revenueGenerated,
      'outstanding_payments' => $outstandingAmount
    ],
    'bottlenecks' => [
      'docs_pending' => count($docsPending),
      'certs_pending' => count($certificatesPending),
      'invoices_overdue' => count($invoicesOverdue)
    ],
    'team' => [
      'top_bdo' => $bdoPerformance[0] ?? null,
      'inactive_users' => array_slice($inactiveUsers, 0, 5)
    ],
    'trends' => [
      'conversion_rate' => round($conversionRate, 1)
    ]
  ];
  
} elseif ($role === 'bdm') {
  // BDM DASHBOARD - "SALES COMMAND"
  // Fetch inquiries by status (optimized)
  $newInquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?status=eq.new&select=id,created_at";
  $newInquiries = json_decode(@file_get_contents($newInquiriesUrl, false, $ctx), true) ?: [];
  
  $quotedInquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?status=eq.quoted&select=id,created_at";
  $quotedInquiries = json_decode(@file_get_contents($quotedInquiriesUrl, false, $ctx), true) ?: [];
  
  $acceptedInquiriesUrl = SUPABASE_URL . "/rest/v1/inquiries?status=eq.accepted&select=id,created_at";
  $acceptedInquiries = json_decode(@file_get_contents($acceptedInquiriesUrl, false, $ctx), true) ?: [];
  
  $confirmedTrainings = array_filter($trainings, function($t) {
    return in_array(strtolower($t['status'] ?? ''), ['scheduled', 'ongoing', 'completed']);
  });
  
  $totalInquiries = count($inquiries);
  $conversionNewToQuoted = $totalInquiries > 0 ? (count($quotedInquiries) / $totalInquiries * 100) : 0;
  $conversionQuotedToAccepted = count($quotedInquiries) > 0 ? (count($acceptedInquiries) / count($quotedInquiries) * 100) : 0;
  $conversionAcceptedToTraining = count($acceptedInquiries) > 0 ? (count($confirmedTrainings) / count($acceptedInquiries) * 100) : 0;
  
  // High-value deals
  $highValueQuotes = array_filter($quotedInquiries, function($i) {
    return ($i['quote_total'] ?? 0) > 10000; // > 10,000 AED
  });
  
  // BDO performance
  $bdoUsers = array_filter($users, function($u) {
    return strtolower($u['role'] ?? '') === 'bdo';
  });
  
  $bdoStats = [];
  foreach ($bdoUsers as $bdo) {
    $bdoInquiries = array_filter($inquiries, function($i) use ($bdo) {
      return ($i['created_by'] ?? '') === $bdo['id'];
    });
    $bdoRevenue = 0;
    foreach ($bdoInquiries as $inq) {
      if (strtolower($inq['status'] ?? '') === 'accepted') {
        $bdoRevenue += ($inq['quote_total'] ?? 0);
      }
    }
    
    $bdoStats[] = [
      'user' => $bdo,
      'inquiries' => count($bdoInquiries),
      'revenue' => $bdoRevenue
    ];
  }
  
  // Follow-ups overdue (inquiries with quoted_at > 7 days ago)
  $followUpsOverdue = array_filter($quotedInquiries, function($i) {
    $quotedAt = $i['quoted_at'] ?? null;
    return $quotedAt && daysAgo($quotedAt) > 7 && strtolower($i['status'] ?? '') === 'quoted';
  });
  
  // Clients with multiple inquiries
  $clientInquiryCount = [];
  foreach ($inquiries as $i) {
    $clientId = $i['client_id'] ?? null;
    if ($clientId) {
      $clientInquiryCount[$clientId] = ($clientInquiryCount[$clientId] ?? 0) + 1;
    }
  }
  $repeatClients = array_filter($clientInquiryCount, function($count) {
    return $count > 1;
  });
  
  // Revenue visibility
  $thisMonthInvoices = array_filter($invoices, function($inv) {
    return isThisMonth($inv['issued_date'] ?? null);
  });
  $thisMonthRevenue = array_sum(array_column($thisMonthInvoices, 'total'));
  
  $expectedRevenue = array_sum(array_column($acceptedInquiries, 'quote_total'));
  
  $metrics = [
    'type' => 'bdm',
    'funnel' => [
      'inquiries' => count($newInquiries),
      'quoted' => count($quotedInquiries),
      'confirmed' => count($acceptedInquiries),
      'trainings' => count($confirmedTrainings),
      'conv_new_quoted' => round($conversionNewToQuoted, 1),
      'conv_quoted_accepted' => round($conversionQuotedToAccepted, 1),
      'conv_accepted_training' => round($conversionAcceptedToTraining, 1)
    ],
    'approval_focus' => [
      'quotes_waiting' => count($quotedInquiries),
      'high_value_pending' => count($highValueQuotes)
    ],
    'team_performance' => array_slice($bdoStats, 0, 5),
    'hot_leads' => [
      'followups_overdue' => count($followUpsOverdue),
      'repeat_clients' => count($repeatClients)
    ],
    'revenue' => [
      'expected_this_month' => $expectedRevenue,
      'confirmed_vs_invoiced' => $thisMonthRevenue
    ]
  ];
  
} elseif ($role === 'bdo') {
  // BDO DASHBOARD - "DAILY WORK ENGINE"
  $myInquiries = array_filter($inquiries, function($i) use ($userId) {
    return ($i['created_by'] ?? '') === $userId;
  });
  
  $newInquiries = array_filter($myInquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'new';
  });
  
  $quotedInquiries = array_filter($myInquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'quoted';
  });
  
  $acceptedInquiries = array_filter($myInquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'accepted';
  });
  
  $rejectedInquiries = array_filter($myInquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'rejected';
  });
  
  // Follow-ups due today
  $today = date('Y-m-d');
  $followUpsDue = array_filter($quotedInquiries, function($i) use ($today) {
    $quotedAt = $i['quoted_at'] ?? null;
    if (!$quotedAt) return false;
    $followUpDate = date('Y-m-d', strtotime($quotedAt . ' +7 days'));
    return $followUpDate === $today;
  });
  
  // Awaiting client response
  $awaitingResponse = array_filter($quotedInquiries, function($i) {
    return strtolower($i['status'] ?? '') === 'quoted' && empty($i['responded_at'] ?? null);
  });
  
  // Urgent actions
  $followUpsOverdue = array_filter($quotedInquiries, function($i) {
    $quotedAt = $i['quoted_at'] ?? null;
    return $quotedAt && daysAgo($quotedAt) > 7 && strtolower($i['status'] ?? '') === 'quoted';
  });
  
  // LPO received but training not scheduled
  $lpoReceived = array_filter($acceptedInquiries, function($i) {
    return !empty($i['accepted_at'] ?? null);
  });
  
  $lpoNotScheduled = [];
  foreach ($lpoReceived as $inq) {
    $hasTraining = false;
    foreach ($trainings as $t) {
      // Check if training exists for this inquiry (would need inquiry_id in trainings table)
      // For now, check by client_id and course_name
      if (($t['client_id'] ?? null) === ($inq['client_id'] ?? null) && 
          ($t['course_name'] ?? '') === ($inq['course_name'] ?? '')) {
        $hasTraining = true;
        break;
      }
    }
    if (!$hasTraining) {
      $lpoNotScheduled[] = $inq;
    }
  }
  
  // Personal targets (simplified)
  $totalAccepted = count($acceptedInquiries);
  $totalInquiries = count($myInquiries);
  $conversionAchieved = $totalInquiries > 0 ? ($totalAccepted / $totalInquiries * 100) : 0;
  
  // Clients trained previously
  $myClients = [];
  foreach ($myInquiries as $inq) {
    $clientId = $inq['client_id'] ?? null;
    if ($clientId && !in_array($clientId, $myClients)) {
      $myClients[] = $clientId;
    }
  }
  
  $repeatClients = [];
  foreach ($myClients as $cid) {
    $clientInquiries = array_filter($myInquiries, function($i) use ($cid) {
      return ($i['client_id'] ?? null) === $cid;
    });
    if (count($clientInquiries) > 1) {
      $repeatClients[] = $clientMap[$cid]['company_name'] ?? 'Unknown';
    }
  }
  
  $metrics = [
    'type' => 'bdo',
    'open_inquiries' => [
      'new' => count($newInquiries),
      'followup_due_today' => count($followUpsDue),
      'awaiting_response' => count($awaitingResponse)
    ],
    'quotation_status' => [
      'draft' => 0, // Would need draft status
      'sent' => count($quotedInquiries),
      'accepted' => count($acceptedInquiries),
      'rejected' => count($rejectedInquiries)
    ],
    'urgent_actions' => [
      'followups_overdue' => count($followUpsOverdue),
      'lpo_not_scheduled' => count($lpoNotScheduled)
    ],
    'targets' => [
      'monthly_target' => 0, // Would need target field
      'conversion_achieved' => round($conversionAchieved, 1)
    ],
    'client_signals' => [
      'repeat_clients' => array_slice($repeatClients, 0, 5)
    ]
  ];
  
} elseif ($role === 'trainer') {
  // TRAINER DASHBOARD - "MY WORK"
  $myTrainings = array_filter($trainings, function($t) use ($userId) {
    return ($t['trainer_id'] ?? '') === $userId;
  });
  
  $upcomingTrainings = array_filter($myTrainings, function($t) {
    $trainingDate = $t['training_date'] ?? null;
    if (!$trainingDate) return false;
    return strtotime($trainingDate) >= strtotime('today');
  });
  
  usort($upcomingTrainings, function($a, $b) {
    return strtotime($a['training_date'] ?? '') <=> strtotime($b['training_date'] ?? '');
  });
  
  $pendingCompletion = array_filter($myTrainings, function($t) {
    return in_array(strtolower($t['status'] ?? ''), ['scheduled', 'ongoing']);
  });
  
  $completedTrainings = array_filter($myTrainings, function($t) {
    return strtolower($t['status'] ?? '') === 'completed';
  });
  
  $myCertificates = array_filter($certificates, function($cert) use ($userId) {
    return ($cert['issued_by'] ?? '') === $userId;
  });
  
  $metrics = [
    'type' => 'trainer',
    'upcoming' => array_slice($upcomingTrainings, 0, 5),
    'pending_actions' => count($pendingCompletion),
    'history' => [
      'trainings_conducted' => count($completedTrainings),
      'certificates_issued' => count($myCertificates)
    ]
  ];
  
} elseif ($role === 'accounts') {
  // ACCOUNTS DASHBOARD - "MONEY CONTROL"
  $draftInvoices = array_filter($invoices, function($inv) {
    return strtolower($inv['status'] ?? '') === 'draft';
  });
  
  $issuedInvoices = array_filter($invoices, function($inv) {
    return strtolower($inv['status'] ?? '') === 'issued';
  });
  
  $overdueInvoices = array_filter($invoices, function($inv) {
    $dueDate = $inv['due_date'] ?? null;
    if (!$dueDate) return false;
    return daysAgo($dueDate) > 0 && ($inv['status'] ?? 'unpaid') === 'unpaid';
  });
  
  $overdue30 = array_filter($overdueInvoices, function($inv) {
    return daysAgo($inv['due_date'] ?? null) > 30;
  });
  
  $overdue60 = array_filter($overdueInvoices, function($inv) {
    return daysAgo($inv['due_date'] ?? null) > 60;
  });
  
  $receivedToday = array_filter($invoices, function($inv) {
    return isThisMonth($inv['issued_date'] ?? null) && strtolower($inv['status'] ?? '') === 'paid';
  });
  $receivedTodayAmount = array_sum(array_column($receivedToday, 'total'));
  
  // Risk alerts
  $completedTrainings = array_filter($trainings, function($t) {
    return strtolower($t['status'] ?? '') === 'completed';
  });
  
  $completedNoInvoice = [];
  foreach ($completedTrainings as $t) {
    $hasInvoice = false;
    foreach ($invoices as $inv) {
      // Check if invoice exists for this training (would need training_id in invoices)
      // Simplified check
      if (($inv['client_id'] ?? null) === ($t['client_id'] ?? null)) {
        $hasInvoice = true;
        break;
      }
    }
    if (!$hasInvoice) {
      $completedNoInvoice[] = $t;
    }
  }
  
  $certsNoInvoice = [];
  foreach ($certificates as $cert) {
    $hasInvoice = false;
    foreach ($invoices as $inv) {
      // Simplified check
      if (($inv['client_id'] ?? null) === ($cert['client_id'] ?? null)) {
        $hasInvoice = true;
        break;
      }
    }
    if (!$hasInvoice) {
      $certsNoInvoice[] = $cert;
    }
  }
  
  // Cash flow
  $thisMonthInvoiced = array_filter($invoices, function($inv) {
    return isThisMonth($inv['issued_date'] ?? null);
  });
  $thisMonthInvoicedAmount = array_sum(array_column($thisMonthInvoiced, 'total'));
  
  $thisMonthCollected = array_filter($invoices, function($inv) {
    return isThisMonth($inv['issued_date'] ?? null) && strtolower($inv['status'] ?? '') === 'paid';
  });
  $thisMonthCollectedAmount = array_sum(array_column($thisMonthCollected, 'total'));
  
  $outstandingBalance = array_sum(array_column($overdueInvoices, 'total'));
  
  // VAT
  $thisMonthVAT = array_sum(array_column($thisMonthInvoiced, 'vat'));
  $vatInvoicesCount = count($thisMonthInvoiced);
  
  $metrics = [
    'type' => 'accounts',
    'invoices_overview' => [
      'draft' => count($draftInvoices),
      'issued' => count($issuedInvoices),
      'overdue' => count($overdueInvoices)
    ],
    'payments' => [
      'received_today' => $receivedTodayAmount,
      'pending_30' => count($overdue30),
      'pending_60' => count($overdue60)
    ],
    'risk_alerts' => [
      'trainings_no_invoice' => count($completedNoInvoice),
      'certs_no_invoice' => count($certsNoInvoice)
    ],
    'cash_flow' => [
      'this_month_invoiced' => $thisMonthInvoicedAmount,
      'this_month_collected' => $thisMonthCollectedAmount,
      'outstanding' => $outstandingBalance
    ],
    'vat' => [
      'payable_this_month' => $thisMonthVAT,
      'invoices_count' => $vatInvoicesCount
    ]
  ];
  
} else {
  // Default/Coordinator or other roles
  $today = date('Y-m-d');
  $next7Days = date('Y-m-d', strtotime('+7 days'));
  
  $upcomingToday = array_filter($trainings, function($t) use ($today) {
    return ($t['training_date'] ?? null) === $today;
  });
  
  $upcoming7Days = array_filter($trainings, function($t) use ($today, $next7Days) {
    $tDate = $t['training_date'] ?? null;
    return $tDate && $tDate >= $today && $tDate <= $next7Days;
  });
  
  $trainerUtilization = [];
  foreach ($users as $u) {
    if (strtolower($u['role'] ?? '') === 'trainer') {
      $trainerTrainings = array_filter($trainings, function($t) use ($u) {
        return ($t['trainer_id'] ?? '') === $u['id'];
      });
      $upcoming = array_filter($trainerTrainings, function($t) {
        $tDate = $t['training_date'] ?? null;
        return $tDate && strtotime($tDate) >= strtotime('today');
      });
      
      $trainerUtilization[] = [
        'user' => $u,
        'total' => count($trainerTrainings),
        'upcoming' => count($upcoming),
        'available' => count($upcoming) < 5 // Simplified availability
      ];
    }
  }
  
  $completedTrainings = array_filter($trainings, function($t) {
    return strtolower($t['status'] ?? '') === 'completed';
  });
  
  $docsPending = [];
  foreach ($completedTrainings as $t) {
    $hasCert = false;
    foreach ($certificates as $cert) {
      if ($cert['training_id'] === $t['id']) {
        $hasCert = true;
        break;
      }
    }
    if (!$hasCert) {
      $docsPending[] = $t;
    }
  }
  
  $metrics = [
    'type' => 'coordinator',
    'upcoming' => [
      'today' => count($upcomingToday),
      'next_7_days' => count($upcoming7Days),
      'trainer_assigned' => count(array_filter($upcoming7Days, function($t) {
        return !empty($t['trainer_id'] ?? null);
      })),
      'trainer_missing' => count(array_filter($upcoming7Days, function($t) {
        return empty($t['trainer_id'] ?? null);
      }))
    ],
    'trainer_utilization' => array_slice($trainerUtilization, 0, 10),
    'documentation' => [
      'docs_pending' => count($docsPending)
    ],
    'risks' => [
      'trainer_not_confirmed' => count(array_filter($upcoming7Days, function($t) {
        return empty($t['trainer_id'] ?? null);
      }))
    ]
  ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard | <?= APP_NAME ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="/training-management-system/favicon.ico">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>

<?php include '../layout/header.php'; ?>
<?php include '../layout/sidebar.php'; ?>

<main class="content">
  <h2>Dashboard</h2>
  <p class="muted">
    Welcome back, <strong><?= htmlspecialchars($user['name']) ?></strong>
    (<?= htmlspecialchars($role) ?>)
  </p>

  <?php if ($metrics['type'] === 'admin'): ?>
    <!-- ADMIN DASHBOARD -->
    <div class="dashboard-grid">
      <!-- Live Business Snapshot -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Live Business Snapshot</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['snapshot']['total_inquiries'] ?></div>
              <div class="metric-label">Total Inquiries (This Month)</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['snapshot']['trainings_conducted'] ?></div>
              <div class="metric-label">Trainings Conducted</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['snapshot']['revenue_generated']) ?> AED</div>
              <div class="metric-label">Revenue Generated</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['snapshot']['outstanding_payments']) ?> AED</div>
              <div class="metric-label">Outstanding Payments</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Process Bottlenecks -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Process Bottlenecks</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['bottlenecks']['docs_pending'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['bottlenecks']['docs_pending'] ?></strong> trainings completed but documents pending
            </div>
          <?php endif; ?>
          <?php if ($metrics['bottlenecks']['certs_pending'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['bottlenecks']['certs_pending'] ?></strong> certificates pending > 48 hrs
            </div>
          <?php endif; ?>
          <?php if ($metrics['bottlenecks']['invoices_overdue'] > 0): ?>
            <div class="alert-item danger">
              <strong><?= $metrics['bottlenecks']['invoices_overdue'] ?></strong> invoices overdue > 30 days
            </div>
          <?php endif; ?>
          <?php if ($metrics['bottlenecks']['docs_pending'] === 0 && $metrics['bottlenecks']['certs_pending'] === 0 && $metrics['bottlenecks']['invoices_overdue'] === 0): ?>
            <div class="alert-item success">All processes running smoothly</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Team Activity Pulse -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Team Activity Pulse</div>
        <div class="dashboard-card-body">
          <?php if (!empty($metrics['team']['top_bdo'])): ?>
            <div class="metric-row">
              <div class="metric-item">
                <div class="metric-value"><?= htmlspecialchars($metrics['team']['top_bdo']['user']['full_name']) ?></div>
                <div class="metric-label">Top BDO (<?= round($metrics['team']['top_bdo']['conversion'], 1) ?>% conversion)</div>
              </div>
            </div>
          <?php endif; ?>
          <?php if (!empty($metrics['team']['inactive_users'])): ?>
            <div class="alert-list">
              <div class="alert-item info">Inactive users (no activity in last 7 days):</div>
              <ul class="alert-list-items">
                <?php foreach ($metrics['team']['inactive_users'] as $iu): ?>
                  <li><?= htmlspecialchars($iu['full_name']) ?> (<?= htmlspecialchars($iu['email']) ?>)</li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Trend Insight -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Trend Insight</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['trends']['conversion_rate'] ?>%</div>
              <div class="metric-label">Inquiry → Training Conversion</div>
            </div>
          </div>
        </div>
      </div>

  <?php elseif ($metrics['type'] === 'bdm'): ?>
    <!-- BDM DASHBOARD -->
    <div class="dashboard-grid">
      <!-- Sales Funnel -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Sales Funnel</div>
        <div class="dashboard-card-body">
          <div class="funnel-steps">
            <div class="funnel-step">
              <div class="funnel-number"><?= $metrics['funnel']['inquiries'] ?></div>
              <div class="funnel-label">Inquiries</div>
            </div>
            <div class="funnel-arrow">→</div>
            <div class="funnel-step">
              <div class="funnel-number"><?= $metrics['funnel']['quoted'] ?></div>
              <div class="funnel-label">Quotations (<?= $metrics['funnel']['conv_new_quoted'] ?>%)</div>
            </div>
            <div class="funnel-arrow">→</div>
            <div class="funnel-step">
              <div class="funnel-number"><?= $metrics['funnel']['confirmed'] ?></div>
              <div class="funnel-label">Confirmed (<?= $metrics['funnel']['conv_quoted_accepted'] ?>%)</div>
            </div>
            <div class="funnel-arrow">→</div>
            <div class="funnel-step">
              <div class="funnel-number"><?= $metrics['funnel']['trainings'] ?></div>
              <div class="funnel-label">Trainings (<?= $metrics['funnel']['conv_accepted_training'] ?>%)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Approval Focus -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Approval Focus</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['approval_focus']['quotes_waiting'] ?></div>
              <div class="metric-label">Quotations waiting for approval</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['approval_focus']['high_value_pending'] ?></div>
              <div class="metric-label">High-value deals pending (>10K AED)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Team Performance -->
      <?php if (!empty($metrics['team_performance'])): ?>
        <div class="dashboard-card">
          <div class="dashboard-card-header">Team Performance</div>
          <div class="dashboard-card-body">
            <ul class="alert-list-items">
              <?php foreach ($metrics['team_performance'] as $bdo): ?>
                <li>
                  <strong><?= htmlspecialchars($bdo['user']['full_name']) ?></strong>:
                  <?= $bdo['inquiries'] ?> inquiries,
                  <?= formatCurrency($bdo['revenue']) ?> AED revenue
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <!-- Hot Leads -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Hot Leads</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['hot_leads']['followups_overdue'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['hot_leads']['followups_overdue'] ?></strong> follow-ups overdue
            </div>
          <?php endif; ?>
          <?php if ($metrics['hot_leads']['repeat_clients'] > 0): ?>
            <div class="alert-item info">
              <strong><?= $metrics['hot_leads']['repeat_clients'] ?></strong> clients with multiple inquiries
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Revenue Visibility -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Revenue Visibility</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['revenue']['expected_this_month']) ?> AED</div>
              <div class="metric-label">Expected revenue this month</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['revenue']['confirmed_vs_invoiced']) ?> AED</div>
              <div class="metric-label">Confirmed vs Invoiced revenue</div>
            </div>
          </div>
        </div>
      </div>

  <?php elseif ($metrics['type'] === 'bdo'): ?>
    <!-- BDO DASHBOARD -->
    <div class="dashboard-grid">
      <!-- My Open Inquiries -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">My Open Inquiries</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['open_inquiries']['new'] ?></div>
              <div class="metric-label">New</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['open_inquiries']['followup_due_today'] ?></div>
              <div class="metric-label">Follow-up due today</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['open_inquiries']['awaiting_response'] ?></div>
              <div class="metric-label">Awaiting client response</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Quotation Status -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Quotation Status</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['quotation_status']['sent'] ?></div>
              <div class="metric-label">Sent</div>
            </div>
            <div class="metric-item">
              <div class="metric-value success"><?= $metrics['quotation_status']['accepted'] ?></div>
              <div class="metric-label">Accepted</div>
            </div>
            <div class="metric-item">
              <div class="metric-value danger"><?= $metrics['quotation_status']['rejected'] ?></div>
              <div class="metric-label">Rejected</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Urgent Actions -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Urgent Actions</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['urgent_actions']['followups_overdue'] > 0): ?>
            <div class="alert-item danger">
              <strong><?= $metrics['urgent_actions']['followups_overdue'] ?></strong> follow-ups overdue
            </div>
          <?php endif; ?>
          <?php if ($metrics['urgent_actions']['lpo_not_scheduled'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['urgent_actions']['lpo_not_scheduled'] ?></strong> LPO received but training not scheduled
            </div>
          <?php endif; ?>
          <?php if ($metrics['urgent_actions']['followups_overdue'] === 0 && $metrics['urgent_actions']['lpo_not_scheduled'] === 0): ?>
            <div class="alert-item success">No urgent actions</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Personal Targets -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Personal Targets</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['targets']['conversion_achieved'] ?>%</div>
              <div class="metric-label">Conversion achieved</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Client Relationship Signals -->
      <?php if (!empty($metrics['client_signals']['repeat_clients'])): ?>
        <div class="dashboard-card">
          <div class="dashboard-card-header">Client Relationship Signals</div>
          <div class="dashboard-card-body">
            <ul class="alert-list-items">
              <?php foreach ($metrics['client_signals']['repeat_clients'] as $clientName): ?>
                <li class="alert-item info"><?= htmlspecialchars($clientName) ?> - Repeat client</li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

  <?php elseif ($metrics['type'] === 'trainer'): ?>
    <!-- TRAINER DASHBOARD -->
    <div class="dashboard-grid">
      <!-- My Upcoming Trainings -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">My Upcoming Trainings</div>
        <div class="dashboard-card-body">
          <?php if (!empty($metrics['upcoming'])): ?>
            <ul class="alert-list-items">
              <?php foreach ($metrics['upcoming'] as $t): ?>
                <li>
                  <strong><?= htmlspecialchars($t['course_name'] ?? '-') ?></strong><br>
                  <?= date('d M Y', strtotime($t['training_date'] ?? '')) ?>
                  <?php if (!empty($t['training_time'])): ?>
                    at <?= date('g:i A', strtotime($t['training_time'])) ?>
                  <?php endif; ?><br>
                  <small style="color: #6b7280;">
                    <?= htmlspecialchars($clientMap[$t['client_id']]['company_name'] ?? 'Individual') ?>
                  </small>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="alert-item info">No upcoming trainings</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Pending Actions -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Pending Actions</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['pending_actions'] ?></div>
              <div class="metric-label">Trainings to confirm completion</div>
            </div>
          </div>
        </div>
      </div>

      <!-- My Training History -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">My Training History</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['history']['trainings_conducted'] ?></div>
              <div class="metric-label">Trainings conducted</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['history']['certificates_issued'] ?></div>
              <div class="metric-label">Certificates issued</div>
            </div>
          </div>
        </div>
      </div>

  <?php elseif ($metrics['type'] === 'accounts'): ?>
    <!-- ACCOUNTS DASHBOARD -->
    <div class="dashboard-grid">
      <!-- Invoices Overview -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Invoices Overview</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['invoices_overview']['draft'] ?></div>
              <div class="metric-label">Draft</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['invoices_overview']['issued'] ?></div>
              <div class="metric-label">Issued</div>
            </div>
            <div class="metric-item">
              <div class="metric-value danger"><?= $metrics['invoices_overview']['overdue'] ?></div>
              <div class="metric-label">Overdue</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Payments Status -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Payments Status</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value success"><?= formatCurrency($metrics['payments']['received_today']) ?> AED</div>
              <div class="metric-label">Received today</div>
            </div>
            <div class="metric-item">
              <div class="metric-value warning"><?= $metrics['payments']['pending_30'] ?></div>
              <div class="metric-label">Pending > 30 days</div>
            </div>
            <div class="metric-item">
              <div class="metric-value danger"><?= $metrics['payments']['pending_60'] ?></div>
              <div class="metric-label">Pending > 60 days</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Risk Alerts -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Risk Alerts</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['risk_alerts']['trainings_no_invoice'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['risk_alerts']['trainings_no_invoice'] ?></strong> trainings completed but invoice not generated
            </div>
          <?php endif; ?>
          <?php if ($metrics['risk_alerts']['certs_no_invoice'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['risk_alerts']['certs_no_invoice'] ?></strong> certificates generated but invoice missing
            </div>
          <?php endif; ?>
          <?php if ($metrics['risk_alerts']['trainings_no_invoice'] === 0 && $metrics['risk_alerts']['certs_no_invoice'] === 0): ?>
            <div class="alert-item success">No risk alerts</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Cash Flow Snapshot -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Cash Flow Snapshot</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['cash_flow']['this_month_invoiced']) ?> AED</div>
              <div class="metric-label">This month invoiced</div>
            </div>
            <div class="metric-item">
              <div class="metric-value success"><?= formatCurrency($metrics['cash_flow']['this_month_collected']) ?> AED</div>
              <div class="metric-label">This month collected</div>
            </div>
            <div class="metric-item">
              <div class="metric-value danger"><?= formatCurrency($metrics['cash_flow']['outstanding']) ?> AED</div>
              <div class="metric-label">Outstanding balance</div>
            </div>
          </div>
        </div>
      </div>

      <!-- VAT Focus -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">VAT Focus</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= formatCurrency($metrics['vat']['payable_this_month']) ?> AED</div>
              <div class="metric-label">VAT payable this month</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['vat']['invoices_count'] ?></div>
              <div class="metric-label">VAT invoices count</div>
            </div>
          </div>
        </div>
      </div>

  <?php else: ?>
    <!-- COORDINATOR DASHBOARD -->
    <div class="dashboard-grid">
      <!-- Upcoming Trainings -->
      <div class="dashboard-card">
        <div class="dashboard-card-header">Upcoming Trainings</div>
        <div class="dashboard-card-body">
          <div class="metric-row">
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['upcoming']['today'] ?></div>
              <div class="metric-label">Today</div>
            </div>
            <div class="metric-item">
              <div class="metric-value"><?= $metrics['upcoming']['next_7_days'] ?></div>
              <div class="metric-label">Next 7 days</div>
            </div>
            <div class="metric-item">
              <div class="metric-value success"><?= $metrics['upcoming']['trainer_assigned'] ?></div>
              <div class="metric-label">Trainer assigned</div>
            </div>
            <div class="metric-item">
              <div class="metric-value danger"><?= $metrics['upcoming']['trainer_missing'] ?></div>
              <div class="metric-label">Trainer missing</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Trainer Utilization -->
      <?php if (!empty($metrics['trainer_utilization'])): ?>
        <div class="dashboard-card">
          <div class="dashboard-card-header">Trainer Utilization</div>
          <div class="dashboard-card-body">
            <ul class="alert-list-items">
              <?php foreach ($metrics['trainer_utilization'] as $tu): ?>
                <li>
                  <strong><?= htmlspecialchars($tu['user']['full_name']) ?></strong>:
                  <?= $tu['upcoming'] ?> upcoming
                  <?php if ($tu['available']): ?>
                    <span class="badge-success">Available</span>
                  <?php else: ?>
                    <span class="badge-warning">Busy</span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <!-- Documentation Status -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Documentation Status</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['documentation']['docs_pending'] > 0): ?>
            <div class="alert-item warning">
              <strong><?= $metrics['documentation']['docs_pending'] ?></strong> trainings completed → docs pending
            </div>
          <?php else: ?>
            <div class="alert-item success">All documentation up to date</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Operational Risks -->
      <div class="dashboard-card alert-card">
        <div class="dashboard-card-header">Operational Risks</div>
        <div class="dashboard-card-body">
          <?php if ($metrics['risks']['trainer_not_confirmed'] > 0): ?>
            <div class="alert-item danger">
              <strong><?= $metrics['risks']['trainer_not_confirmed'] ?></strong> trainer not confirmed
            </div>
          <?php else: ?>
            <div class="alert-item success">All trainers confirmed</div>
          <?php endif; ?>
        </div>
      </div>

  <?php endif; ?>

<?php
// Log dashboard performance
$dashboardEndTime = microtime(true);
$dashboardLoadTime = round(($dashboardEndTime - $dashboardStartTime) * 1000, 2); // milliseconds
logInfo('dashboard_load', [
  'load_time_ms' => $dashboardLoadTime,
  'role' => $role,
  'user_id' => $userId
]);
?>

</main>

<?php include '../layout/footer.php'; ?>

</body>
</html>
