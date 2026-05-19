<?php require_once __DIR__ . '/auth.php'; ?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--panel:#fff;--brand:#0f766e;--brand-strong:#115e59;--brand-soft:#ccfbf1;--accent:#f97316;--surface:#f8fafc}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink);font-size:15px}
    .card{border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07)}
    .navbar{box-shadow:0 8px 24px rgba(15,118,110,.18)}
    .navbar-dark.bg-dark{background:linear-gradient(90deg,#0f172a 0,#115e59 100%)!important}
    .navbar-brand{font-weight:800;letter-spacing:0}
    .nav-link{font-weight:500}
    .page-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h3{font-weight:700;margin:0}
    .page-title p{color:var(--muted);margin:4px 0 0}
    .badge{font-size:85%;border-radius:6px;padding:.35rem .5rem}
    .btn{border-radius:6px;font-weight:600}
    .btn-dark{background:var(--brand);border-color:var(--brand)}
    .btn-dark:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .btn-outline-dark{color:var(--brand-strong);border-color:var(--brand)}
    .btn-outline-dark:hover{background:var(--brand);border-color:var(--brand);color:#fff}
    .form-control{border-radius:6px;border-color:#d8dde6}
    .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 .2rem rgba(15,118,110,.14)}
    .table thead th{border-top:0;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
    .photo-thumb{width:70px;height:70px;object-fit:cover;border-radius:12px;background:#ddd}
    .calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
    .calendar-head,.calendar-day{min-height:112px;border-right:1px solid var(--line);border-bottom:1px solid var(--line);padding:10px}
    .calendar-head{min-height:auto;background:#f0fdfa;color:#0f766e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;text-align:center}
    .calendar-grid>*:nth-child(7n){border-right:0}
    .calendar-day{cursor:pointer;transition:.15s ease;background:#fff}
    .calendar-day:hover{background:#f0fdfa}
    .calendar-day.is-muted{background:#f8fafc;color:#adb4bf}
    .calendar-day.is-today{box-shadow:inset 0 0 0 2px var(--brand)}
    .calendar-day.is-selected{background:var(--brand-soft)}
    .day-number{font-weight:700;font-size:14px}
    .shift-pill{display:block;margin-top:7px;padding:5px 7px;border-radius:6px;background:#ccfbf1;color:#115e59;font-size:12px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .shift-pill.warning{background:#ffedd5;color:#9a3412}
    .metric{background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px 16px}
    .metric small{display:block;color:var(--muted);font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
    .metric strong{display:block;font-size:24px;margin-top:3px}
    @media(max-width:767.98px){.page-title{display:block}.calendar-day{min-height:86px;padding:7px}.shift-pill{font-size:11px}.calendar-head{font-size:10px;padding:7px 2px}}
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <a class="navbar-brand" href="<?=app_url('admin/index.php')?>">Freela</a>
  <div class="navbar-nav">
    <a class="nav-link" href="<?=app_url('admin/freelancers.php')?>">Freelancers</a>
    <a class="nav-link" href="<?=app_url('admin/restaurants.php')?>">Restaurantes</a>
    <a class="nav-link" href="<?=app_url('admin/functions.php')?>">Funções</a>
    <a class="nav-link" href="<?=app_url('admin/shifts.php')?>">Vagas</a>
  </div>
  <div class="ml-auto navbar-nav">
    <a class="nav-link" href="<?=app_url('admin/logout.php')?>">Sair</a>
  </div>
</nav>
<div class="container-fluid px-4">
