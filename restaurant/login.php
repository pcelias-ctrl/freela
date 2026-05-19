<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE email=? AND status='active' LIMIT 1");
    $stmt->execute([$_POST['email']]);
    $restaurant = $stmt->fetch();
    if ($restaurant && password_verify($_POST['password'], $restaurant['password_hash'])) {
        $_SESSION['restaurant_id'] = $restaurant['id'];
        $_SESSION['restaurant_name'] = $restaurant['name'];
        header('Location: ' . app_url('restaurant/shifts.php'));
        exit;
    }
    $error = 'Login inválido ou restaurante inativo.';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Restaurante</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    body{min-height:100vh;background:linear-gradient(135deg,#0f172a 0,#115e59 52%,#f97316 140%);color:#111827;display:flex;align-items:center}
    .card{border:1px solid #e4e7ec;border-radius:8px;box-shadow:0 24px 70px rgba(15,23,42,.28)}
    .brand{font-size:32px;font-weight:800;color:#0f766e;line-height:1}
    .btn,.form-control{border-radius:6px}
    .btn-dark{background:#0f766e;border-color:#0f766e;font-weight:700}
    .btn-dark:hover{background:#115e59;border-color:#115e59}
    .form-control:focus{border-color:#0f766e;box-shadow:0 0 0 .2rem rgba(15,118,110,.14)}
  </style>
</head>
<body>
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-4">
        <div class="card p-4">
          <div class="brand mb-3">Freela</div>
          <h4 class="mb-1">Área do Restaurante</h4>
          <p class="text-muted mb-4">Cadastre e acompanhe suas vagas.</p>
          <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
          <form method="post">
            <div class="form-group">
              <label>E-mail</label>
              <input class="form-control" name="email" type="email" required>
            </div>
            <div class="form-group">
              <label>Senha</label>
              <input class="form-control" name="password" type="password" required>
            </div>
            <button class="btn btn-dark btn-block">Entrar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
