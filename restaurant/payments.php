<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_restaurant();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("
        SELECT a.id
        FROM shift_applications a
        JOIN shifts s ON s.id=a.shift_id
        WHERE a.id=? AND s.restaurant_id=?
        LIMIT 1
    ");
    $stmt->execute(array($applicationId, $_SESSION['restaurant_id']));
    $application = $stmt->fetch();

    if (!$application) {
        $error = 'Registro não encontrado.';
    } elseif ($action === 'mark_paid') {
        $pdo->prepare('UPDATE shift_applications SET payment_status="paid", payment_at=NOW() WHERE id=?')->execute(array($applicationId));
        $message = 'Pagamento marcado como feito.';
    } elseif ($action === 'mark_pending') {
        $pdo->prepare('UPDATE shift_applications SET payment_status="pending", payment_at=NULL WHERE id=?')->execute(array($applicationId));
        $message = 'Pagamento voltou para pendente.';
    }
}

$stmt = $pdo->prepare("
    SELECT
      a.*,
      s.shift_date,
      s.start_time,
      s.end_time,
      s.pay_value,
      f.name func,
      fr.full_name,
      fr.whatsapp,
      fr.chavepix,
      fr.photo_path
    FROM shift_applications a
    JOIN shifts s ON s.id=a.shift_id
    JOIN job_functions f ON f.id=s.function_id
    JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=?
      AND a.status IN ('confirmed','completed')
    ORDER BY FIELD(a.payment_status,'pending','paid'), s.shift_date DESC, s.start_time DESC
");
$stmt->execute(array($_SESSION['restaurant_id']));
$items = $stmt->fetchAll();

$pendingTotal = 0;
$paidTotal = 0;
foreach ($items as $item) {
    if ($item['payment_status'] === 'paid') $paidTotal += (float)$item['pay_value'];
    else $pendingTotal += (float)$item['pay_value'];
}

function map_link($lat, $lng) {
    if ($lat === null || $lng === null || $lat === '' || $lng === '') return '';
    return 'https://www.google.com/maps?q=' . rawurlencode($lat . ',' . $lng);
}

function presence_text($label, $time, $lat, $lng) {
    if (empty($time)) return $label . ': pendente';
    $gps = map_link($lat, $lng) ? 'com GPS' : 'sem GPS';
    return $label . ': ' . date('d/m/Y H:i', strtotime($time)) . ' (' . $gps . ')';
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Pagamentos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink);font-size:15px}
    .navbar-dark.bg-dark{background:linear-gradient(90deg,#0f172a 0,#115e59 100%)!important}
    .navbar{box-shadow:0 8px 24px rgba(15,118,110,.18)}
    .navbar-brand{font-weight:800}
    .card,.metric{border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07);background:#fff}
    .metric{padding:14px 16px}
    .metric small{display:block;color:var(--muted);font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
    .metric strong{display:block;font-size:24px;margin-top:3px}
    .btn{border-radius:6px;font-weight:800}
    .btn-dark{background:var(--brand);border-color:var(--brand)}
    .photo-thumb{width:48px;height:48px;object-fit:cover;border-radius:50%;background:#e4e7ec}
    .table thead th{border-top:0;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <span class="navbar-brand">Freela · <?=e($_SESSION['restaurant_name'])?></span>
  <div>
    <a class="text-white mr-3" href="<?=app_url('restaurant/shifts.php')?>">Vagas</a>
    <a class="text-white mr-3" href="<?=app_url('restaurant/candidates.php')?>">Candidatos</a>
    <a class="text-white" href="<?=app_url('restaurant/logout.php')?>">Sair</a>
  </div>
</nav>

<div class="container-fluid px-4">
  <div class="d-md-flex align-items-start justify-content-between mb-3">
    <div>
      <h3 class="font-weight-bold mb-1">Pagamentos</h3>
      <p class="text-muted">Confira check-in, check-out, localização e marque pagamentos realizados.</p>
    </div>
  </div>

  <?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

  <div class="row mb-4">
    <div class="col-md-4 mb-2"><div class="metric"><small>Pendente</small><strong>R$ <?=number_format($pendingTotal, 2, ',', '.')?></strong></div></div>
    <div class="col-md-4 mb-2"><div class="metric"><small>Pago</small><strong>R$ <?=number_format($paidTotal, 2, ',', '.')?></strong></div></div>
    <div class="col-md-4 mb-2"><div class="metric"><small>Registros</small><strong><?=count($items)?></strong></div></div>
  </div>

  <div class="card p-3 mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Freelancer</th><th>Vaga</th><th>Presença</th><th>Localização</th><th>Pix</th><th>Valor</th><th>Pagamento</th><th>Ações</th></tr></thead>
        <tbody>
        <?php foreach($items as $item): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <?php if($item['photo_path']): ?><img class="photo-thumb mr-2" src="<?=app_url($item['photo_path'])?>" alt="Foto de <?=e($item['full_name'])?>"><?php endif; ?>
                <div><b><?=e($item['full_name'])?></b><br><small class="text-muted"><?=e($item['whatsapp'])?></small></div>
              </div>
            </td>
            <td><b><?=e($item['func'])?></b><br><small><?=date('d/m/Y', strtotime($item['shift_date']))?> · <?=substr($item['start_time'],0,5)?> às <?=substr($item['end_time'],0,5)?></small></td>
            <td>
              <small><?=e(presence_text('Entrada', $item['checkin_at'], $item['checkin_lat'], $item['checkin_lng']))?></small><br>
              <small><?=e(presence_text('Saída', $item['checkout_at'], $item['checkout_lat'], $item['checkout_lng']))?></small>
            </td>
            <td>
              <?php $checkinMap = map_link($item['checkin_lat'], $item['checkin_lng']); $checkoutMap = map_link($item['checkout_lat'], $item['checkout_lng']); ?>
              <?php if($checkinMap): ?><a target="_blank" href="<?=e($checkinMap)?>">Mapa do check-in</a><?php elseif(!empty($item['checkin_at'])): ?><span class="text-muted">Check-in registrado sem GPS</span><?php else: ?><span class="text-muted">Check-in pendente</span><?php endif; ?><br>
              <?php if($checkoutMap): ?><a target="_blank" href="<?=e($checkoutMap)?>">Mapa do check-out</a><?php elseif(!empty($item['checkout_at'])): ?><span class="text-muted">Check-out registrado sem GPS</span><?php else: ?><span class="text-muted">Check-out pendente</span><?php endif; ?>
            </td>
            <td><small><?=e($item['chavepix'] ?: 'Não informado')?></small></td>
            <td><b>R$ <?=number_format($item['pay_value'], 2, ',', '.')?></b></td>
            <td>
              <?php if($item['payment_status'] === 'paid'): ?>
                <span class="badge badge-success">Pago</span><br><small><?=date('d/m H:i', strtotime($item['payment_at']))?></small>
              <?php else: ?>
                <span class="badge badge-warning">Pendente</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if($item['payment_status'] === 'paid'): ?>
                <form method="post"><input type="hidden" name="application_id" value="<?=$item['id']?>"><input type="hidden" name="action" value="mark_pending"><button class="btn btn-sm btn-outline-secondary">Voltar pendente</button></form>
              <?php else: ?>
                <form method="post"><input type="hidden" name="application_id" value="<?=$item['id']?>"><input type="hidden" name="action" value="mark_paid"><button class="btn btn-sm btn-dark">Marcar pago</button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$items): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhum pagamento para controlar ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
