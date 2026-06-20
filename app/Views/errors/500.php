<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>500 – Server Error | AttendX</title>
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Inter',system-ui,sans-serif;background:#0a0c14;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center}
    .wrap{text-align:center;padding:40px 20px}
    .code{font-size:6rem;font-weight:700;color:#6366f1;line-height:1}
    .title{font-size:1.5rem;font-weight:600;margin:16px 0 8px;color:#f1f5f9}
    .msg{color:#6b7280;max-width:440px;margin:0 auto 32px;line-height:1.6}
    .btn{display:inline-flex;align-items:center;gap:8px;background:#6366f1;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:500;font-size:.9rem}
    .btn:hover{background:#4f46e5}
    .sep{color:#374151;margin:0 12px}
    .link{color:#6366f1;text-decoration:none;font-size:.9rem}
    .link:hover{text-decoration:underline}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="code">500</div>
    <h1 class="title">Internal Server Error</h1>
    <p class="msg">Something went wrong on our end. The error has been logged and our team will look into it.</p>
    <a href="/dashboard" class="btn">← Back to Dashboard</a>
    <span class="sep">|</span>
    <a href="javascript:history.back()" class="link">Go Back</a>
  </div>
</body>
</html>
