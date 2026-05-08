<?php
// This file is shown to visitors when maintenance mode is ON.
// The frontend PHP pages will include a check for maintenance.flag.

$message = file_exists(__DIR__ . '/maintenance.flag')
    ? trim(file_get_contents(__DIR__ . '/maintenance.flag'))
    : 'We are currently performing maintenance. We\'ll be back shortly!';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Under Maintenance | ASB Tours</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #03045E 0%, #0077B6 60%, #00B4D8 100%);
      display: flex; align-items: center; justify-content: center;
      font-family: 'Segoe UI', system-ui, sans-serif;
    }
    .card {
      background: rgba(255,255,255,.97);
      border-radius: 20px;
      padding: 3rem 2.5rem;
      max-width: 480px;
      width: 100%;
      margin: 1rem;
      text-align: center;
      box-shadow: 0 24px 64px rgba(3,4,94,.3);
    }
    .icon-wrap {
      width: 80px; height: 80px;
      background: linear-gradient(135deg, #0077B6, #00B4D8);
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      font-size: 2rem; color: #fff;
      margin: 0 auto 1.5rem;
      animation: pulse 2s ease infinite;
    }
    @keyframes pulse {
      0%,100% { box-shadow: 0 0 0 0 rgba(0,119,182,.3); }
      50%      { box-shadow: 0 0 0 14px rgba(0,119,182,0); }
    }
    h1 { font-size: 1.6rem; font-weight: 800; color: #03045E; margin-bottom: .75rem; }
    p  { color: #718096; line-height: 1.7; font-size: .95rem; }
    .brand { margin-top: 2rem; font-size: .8rem; color: #a0aec0; }
    .brand strong { color: #0077B6; }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon-wrap">
      <i class="bi bi-cone-striped"></i>
    </div>
    <h1>Under Maintenance</h1>
    <p><?= nl2br(htmlspecialchars($message)) ?></p>
    <div class="brand">
      <strong>ASB Tours</strong> - We'll be back soon
    </div>
  </div>
</body>
</html>
