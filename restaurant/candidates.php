<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_restaurant();

$message = '';
$error = '';
$applicationLabels = array(
    'pending' => 'Pendente',
    'confirmed' => 'Aprovado',
    'cancelled' => 'Recusado',
    'no_show' => 'Não compareceu',
    'completed' => 'Concluído'
);
$applicationBadges = array(
    'pending' => 'badge-warning',
    'confirmed' => 'badge-success',
    'cancelled' => 'badge-danger',
    'no_show' => 'badge-dark',
    'completed' => 'badge-info'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int)($_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $stmt = $pdo->prepare("
        SELECT a.*, s.vacancies, s.restaurant_id, s.shift_date, s.start_time, s.end_time, f.name func,
          (SELECT COUNT(*) FROM shift_applications ok WHERE ok.shift_id=s.id AND ok.status='confirmed') filled
        FROM shift_applications a
        JOIN shifts s ON s.id=a.shift_id
        JOIN job_functions f ON f.id=s.function_id
        WHERE a.id=? AND s.restaurant_id=?
        LIMIT 1
    ");
    $stmt->execute(array($applicationId, $_SESSION['restaurant_id']));
    $application = $stmt->fetch();

    if (!$application) {
        $error = 'Candidatura não encontrada.';
    } elseif ($action === 'approve') {
        if ($application['status'] === 'confirmed') {
            $message = 'Esse candidato já está aprovado.';
        } elseif ((int)$application['filled'] >= (int)$application['vacancies']) {
            $error = 'Essa vaga já atingiu o limite de aprovados.';
        } else {
            $conflictStmt = $pdo->prepare("
                SELECT s2.shift_date, s2.start_time, s2.end_time, r2.name restaurant, f2.name func
                FROM shift_applications a2
                JOIN shifts s2 ON s2.id=a2.shift_id
                JOIN restaurants r2 ON r2.id=s2.restaurant_id
                JOIN job_functions f2 ON f2.id=s2.function_id
                WHERE a2.freelancer_id=?
                  AND a2.status='confirmed'
                  AND s2.id<>?
                  AND s2.shift_date=?
                  AND s2.start_time=?
                  AND s2.end_time=?
                LIMIT 1
            ");
            $conflictStmt->execute(array($application['freelancer_id'], $application['shift_id'], $application['shift_date'], $application['start_time'], $application['end_time']));
            $conflict = $conflictStmt->fetch();

            if ($conflict) {
                $error = 'Este freelancer já está aprovado no mesmo dia e horário em ' . $conflict['restaurant'] . ' (' . $conflict['func'] . ').';
            } else {
                $pdo->prepare('UPDATE shift_applications SET status="confirmed" WHERE id=?')->execute(array($applicationId));
                $message = 'Candidato aprovado para a vaga.';
            }
        }
    } elseif ($action === 'reject') {
        $pdo->prepare('UPDATE shift_applications SET status="cancelled" WHERE id=?')->execute(array($applicationId));
        $message = 'Candidato recusado para a vaga.';
    }
}

$stmt = $pdo->prepare("
    SELECT
        a.*,
        s.shift_date,
        s.start_time,
        s.end_time,
        s.vacancies,
        s.pay_value,
        f.name func,
        fr.full_name,
        fr.whatsapp,
        fr.phone,
        fr.chavepix,
        fr.photo_path,
        fr.restaurant_experience,
        (SELECT COUNT(*) FROM shift_applications ok WHERE ok.shift_id=s.id AND ok.status='confirmed') filled
    FROM shift_applications a
    JOIN shifts s ON s.id=a.shift_id
    JOIN job_functions f ON f.id=s.function_id
    JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=?
      AND s.shift_date >= CURDATE()
    ORDER BY FIELD(a.status,'pending','confirmed','cancelled','no_show','completed'), s.shift_date, s.start_time, a.created_at
");
$stmt->execute(array($_SESSION['restaurant_id']));
$items = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Candidatos</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink);font-size:15px}
    .navbar-dark.bg-dark{background:linear-gradient(90deg,#0f172a 0,#115e59 100%)!important}
    .navbar{box-shadow:0 8px 24px rgba(15,118,110,.18)}
    .navbar-brand{font-weight:800}
    .card{border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07)}
    .btn,.form-control{border-radius:6px}
    .btn-dark{background:var(--brand);border-color:var(--brand);font-weight:700}
    .btn-dark:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .photo-thumb{width:56px;height:56px;object-fit:cover;border-radius:50%;background:#e4e7ec}
    .table thead th{border-top:0;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <span class="navbar-brand">Freela · <?=e($_SESSION['restaurant_name'])?></span>
  <div>
    <a class="text-white mr-3" href="<?=app_url('restaurant/shifts.php')?>">Vagas</a>
    <a class="text-white mr-3" href="<?=app_url('restaurant/payments.php')?>">Pagamentos</a>
    <a class="text-white" href="<?=app_url('restaurant/logout.php')?>">Sair</a>
  </div>
</nav>

<div class="container-fluid px-4">
  <div class="d-md-flex align-items-start justify-content-between mb-3">
    <div>
      <h3 class="font-weight-bold mb-1">Candidatos</h3>
      <p class="text-muted">Aprove candidatos até preencher o número de vagas cadastrado por função, dia e horário.</p>
    </div>
    <a class="btn btn-dark" href="<?=app_url('restaurant/shifts.php')?>">Criar nova vaga</a>
  </div>

  <?php if($message): ?><div class="alert alert-success"><?=e($message)?></div><?php endif; ?>
  <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>

  <div class="card p-3 mb-4">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Candidato</th>
            <th>Vaga</th>
            <th>Contato</th>
            <th>Pix</th>
            <th>Ocupação</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($items as $item): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <?php if($item['photo_path']): ?><img class="photo-thumb mr-2" src="<?=app_url($item['photo_path'])?>" alt="Foto de <?=e($item['full_name'])?>"><?php endif; ?>
                <div>
                  <b><?=e($item['full_name'])?></b><br>
                  <small class="text-muted"><?=e($item['restaurant_experience'])?></small>
                </div>
              </div>
            </td>
            <td>
              <b><?=e($item['func'])?></b><br>
              <small><?=date('d/m/Y', strtotime($item['shift_date']))?> · <?=substr($item['start_time'],0,5)?> às <?=substr($item['end_time'],0,5)?> · R$ <?=number_format($item['pay_value'], 2, ',', '.')?></small>
            </td>
            <td><?=e($item['whatsapp'])?><br><small class="text-muted"><?=e($item['phone'])?></small></td>
            <td><small><?=e($item['chavepix'] ?: 'Não informado')?></small></td>
            <td><?=e($item['filled'])?>/<?=e($item['vacancies'])?></td>
            <td><span class="badge <?=e($applicationBadges[$item['status']] ?? 'badge-secondary')?>"><?=e($applicationLabels[$item['status']] ?? $item['status'])?></span></td>
            <td style="min-width:180px">
              <?php if($item['status'] === 'pending'): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="application_id" value="<?=$item['id']?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn btn-sm btn-success mb-1">Aprovar</button>
                </form>
                <form method="post" class="d-inline">
                  <input type="hidden" name="application_id" value="<?=$item['id']?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn btn-sm btn-outline-danger mb-1">Recusar</button>
                </form>
              <?php else: ?>
                <span class="text-muted">Ação registrada</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if(!$items): ?><tr><td colspan="7" class="text-center text-muted py-4">Nenhuma candidatura recebida ainda.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
