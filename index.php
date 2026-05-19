<?php
require_once __DIR__ . '/includes/auth.php';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Vagas para restaurantes</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59;--brand-soft:#ccfbf1;--accent:#f97316}
    body{background:#f8fafc;color:var(--ink)}
    .hero{min-height:78vh;background:linear-gradient(135deg,rgba(15,23,42,.94),rgba(17,94,89,.9)),url('https://images.unsplash.com/photo-1559329007-40df8a9345d8?auto=format&fit=crop&w=1600&q=80');background-size:cover;background-position:center;color:#fff;display:flex;align-items:center}
    .brand{font-size:18px;font-weight:800;letter-spacing:.02em}
    .hero h1{font-size:54px;font-weight:800;line-height:1.02;letter-spacing:0;margin-bottom:18px}
    .hero p{font-size:19px;color:#dbeafe;max-width:680px}
    .btn{border-radius:6px;font-weight:700;padding:12px 18px}
    .btn-primary{background:var(--brand);border-color:var(--brand)}
    .btn-primary:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .btn-light{color:#0f172a}
    .section{padding:56px 0}
    .card{border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07);height:100%}
    .eyebrow{color:#99f6e4;font-weight:800;text-transform:uppercase;font-size:12px;letter-spacing:.08em}
    .feature-number{width:36px;height:36px;border-radius:8px;background:var(--brand-soft);color:var(--brand-strong);display:flex;align-items:center;justify-content:center;font-weight:800;margin-bottom:14px}
    .topbar{position:absolute;top:0;left:0;right:0;z-index:2;padding:22px 0}
    .topbar a{color:#fff;font-weight:700}
    @media(max-width:767.98px){.hero{min-height:86vh}.hero h1{font-size:38px}.hero p{font-size:17px}.topbar .nav-links{margin-top:12px}}
  </style>
</head>
<body>
  <div class="topbar">
    <div class="container d-md-flex align-items-center justify-content-between">
      <div class="brand">Freela</div>
      <div class="nav-links">
        <a class="mr-3" href="<?=app_url('restaurant/login.php')?>">Área do restaurante</a>
        <a class="mr-3" href="<?=app_url('public/area.php')?>">Já tenho cadastro</a>
        <a href="<?=app_url('public/register.php')?>">Buscar vaga</a>
      </div>
    </div>
  </div>

  <section class="hero">
    <div class="container">
      <div class="eyebrow mb-3">Gestão de freelancers para restaurantes</div>
      <h1>Freela conecta restaurantes a profissionais disponíveis.</h1>
      <p>Organize vagas por data, acompanhe confirmações e mantenha uma base de freelancers aprovada para salões, cozinhas e eventos.</p>
      <div class="mt-4">
        <a class="btn btn-primary mr-2 mb-2" href="<?=app_url('restaurant/login.php')?>">Sou restaurante</a>
        <a class="btn btn-light mb-2" href="<?=app_url('public/register.php')?>">Quero buscar vagas</a>
        <a class="btn btn-outline-light mb-2 ml-md-2" href="<?=app_url('public/area.php')?>">Já tenho cadastro</a>
      </div>
    </div>
  </section>

  <section class="section">
    <div class="container">
      <div class="row">
        <div class="col-md-4 mb-3">
          <div class="card p-4">
            <div class="feature-number">1</div>
            <h5>Restaurantes publicam vagas</h5>
            <p class="text-muted mb-0">Crie turnos por data, função, horário, valor e quantidade de profissionais necessários.</p>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card p-4">
            <div class="feature-number">2</div>
            <h5>Freelancers se cadastram</h5>
            <p class="text-muted mb-0">Profissionais enviam dados, experiência e foto para aprovação antes de entrar na operação.</p>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="card p-4">
            <div class="feature-number">3</div>
            <h5>Admin acompanha tudo</h5>
            <p class="text-muted mb-0">O painel mostra vagas abertas, ocupação e cadastros pendentes em uma rotina mais simples.</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</body>
</html>
