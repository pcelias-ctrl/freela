<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$statusLabels = array('pending'=>'Pendente','approved'=>'Aprovado','blocked'=>'Bloqueado','rejected'=>'Recusado');
$statusMessages = array(
    'pending' => 'Seu cadastro ainda está em análise. Assim que for aprovado, você poderá acompanhar as vagas abertas.',
    'approved' => 'Cadastro aprovado. Você já pode se candidatar às vagas abertas abaixo.',
    'blocked' => 'Seu cadastro está bloqueado. Fale com a administração para mais detalhes.',
    'rejected' => 'Seu cadastro não foi aprovado neste momento.'
);
$typeLabels = array('almoco'=>'Almoço','jantar'=>'Jantar','evento'=>'Evento');

$freelancer = null;
$vacancies = array();
$applications = array();
$commitments = array();
$error = '';
$message = '';
$whatsapp = trim($_POST['whatsapp'] ?? '');

function only_digits($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function load_freelancer_by_id($pdo, $id) {
    $stmt = $pdo->prepare("SELECT fr.*, jf.name func FROM freelancers fr LEFT JOIN job_functions jf ON jf.id=fr.main_function_id WHERE fr.id=? LIMIT 1");
    $stmt->execute(array($id));
    return $stmt->fetch();
}

function load_freelancer_by_whatsapp($pdo, $whatsapp) {
    $digits = only_digits($whatsapp);
    if (!$digits) return null;

    $stmt = $pdo->prepare("
        SELECT fr.*, jf.name func
        FROM freelancers fr
        LEFT JOIN job_functions jf ON jf.id=fr.main_function_id
        WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(fr.whatsapp,'+',''),' ',''),'-',''),'(',''),')','') LIKE ?
           OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(fr.whatsapp,'+',''),' ',''),'-',''),'(',''),')','') LIKE ?
        ORDER BY fr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute(array('%' . $digits, '%' . substr($digits, -11)));
    return $stmt->fetch();
}

if (!empty($_SESSION['freelancer_id'])) {
    $freelancer = load_freelancer_by_id($pdo, $_SESSION['freelancer_id']);
    if (!$freelancer) unset($_SESSION['freelancer_id']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    if ($action === 'logout') {
        unset($_SESSION['freelancer_id']);
        header('Location: ' . app_url('public/area.php'));
        exit;
    }

    if ($action === 'login') {
        $freelancer = load_freelancer_by_whatsapp($pdo, $whatsapp);
        $password = $_POST['password'] ?? '';
        if (!$freelancer) {
            $error = 'Não encontramos cadastro com esse WhatsApp.';
        } elseif (empty($freelancer['password_hash'])) {
            $error = 'Seu cadastro ainda não tem senha. Faça um novo cadastro ou peça atualização ao administrador.';
            $freelancer = null;
        } elseif (!password_verify($password, $freelancer['password_hash'])) {
            $error = 'WhatsApp ou senha inválidos.';
            $freelancer = null;
        } else {
            $_SESSION['freelancer_id'] = $freelancer['id'];
        }
    }

    if ($action === 'apply') {
        if (!$freelancer) {
            $error = 'Acesse com seu WhatsApp e senha antes de se candidatar.';
        } elseif ($freelancer['status'] !== 'approved') {
            $error = 'Seu cadastro precisa estar aprovado para se candidatar às vagas.';
        } else {
            $shiftId = (int)($_POST['shift_id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT s.*, r.name restaurant, f.name func,
                  (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled
                FROM shifts s
                JOIN restaurants r ON r.id=s.restaurant_id
                JOIN job_functions f ON f.id=s.function_id
                WHERE s.id=? AND s.status='open' AND s.shift_date >= CURDATE()
                LIMIT 1
            ");
            $stmt->execute(array($shiftId));
            $shift = $stmt->fetch();

            if (!$shift) {
                $error = 'Essa vaga não está mais disponível.';
            } elseif ((int)$shift['filled'] >= (int)$shift['vacancies']) {
                $error = 'Essa vaga já foi preenchida.';
            } else {
                try {
                    $pdo->prepare('INSERT INTO shift_applications (shift_id, freelancer_id, status) VALUES (?, ?, "pending")')
                        ->execute(array($shiftId, $freelancer['id']));
                    $message = 'Candidatura enviada para ' . $shift['func'] . ' em ' . $shift['restaurant'] . '. Aguarde aprovação do restaurante.';
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') {
                        $message = 'Você já se candidatou para essa vaga.';
                    } else {
                        throw $e;
                    }
                }
            }
        }
    }

    if ($action === 'checkin' || $action === 'checkout') {
        if (!$freelancer) {
            $error = 'Acesse com seu WhatsApp e senha antes de registrar presença.';
        } else {
            $applicationId = (int)($_POST['application_id'] ?? 0);
            $lat = trim($_POST['lat'] ?? '');
            $lng = trim($_POST['lng'] ?? '');
            $stmt = $pdo->prepare("
                SELECT a.*, s.shift_date, s.start_time, s.end_time, r.name restaurant, f.name func
                FROM shift_applications a
                JOIN shifts s ON s.id=a.shift_id
                JOIN restaurants r ON r.id=s.restaurant_id
                JOIN job_functions f ON f.id=s.function_id
                WHERE a.id=? AND a.freelancer_id=? AND a.status='confirmed'
                LIMIT 1
            ");
            $stmt->execute(array($applicationId, $freelancer['id']));
            $application = $stmt->fetch();

            if (!$application) {
                $error = 'Compromisso não encontrado.';
            } elseif ($application['shift_date'] !== date('Y-m-d')) {
                $error = 'Check-in e check-out ficam disponíveis apenas no dia da vaga.';
            } elseif ($action === 'checkin' && !empty($application['checkin_at'])) {
                $message = 'Check-in já registrado para esse compromisso.';
            } elseif ($action === 'checkout' && empty($application['checkin_at'])) {
                $error = 'Faça o check-in antes do check-out.';
            } elseif ($action === 'checkout' && !empty($application['checkout_at'])) {
                $message = 'Check-out já registrado para esse compromisso.';
            } else {
                $latValue = is_numeric($lat) ? $lat : null;
                $lngValue = is_numeric($lng) ? $lng : null;
                if ($action === 'checkin') {
                    $pdo->prepare('UPDATE shift_applications SET checkin_at=NOW(), checkin_lat=?, checkin_lng=? WHERE id=?')
                        ->execute(array($latValue, $lngValue, $applicationId));
                    $message = 'Check-in registrado para ' . $application['func'] . ' em ' . $application['restaurant'] . '.';
                } else {
                    $pdo->prepare('UPDATE shift_applications SET checkout_at=NOW(), checkout_lat=?, checkout_lng=? WHERE id=?')
                        ->execute(array($latValue, $lngValue, $applicationId));
                    $message = 'Check-out registrado. Obrigado pelo trabalho!';
                }
            }
        }
    }
}

if ($freelancer && $freelancer['status'] === 'approved') {
    $appStmt = $pdo->prepare('SELECT shift_id, status FROM shift_applications WHERE freelancer_id=?');
    $appStmt->execute(array($freelancer['id']));
    foreach ($appStmt->fetchAll() as $application) {
        $applications[(int)$application['shift_id']] = $application['status'];
    }

    $commitmentStmt = $pdo->prepare("
        SELECT s.*, r.name restaurant, f.name func, a.id application_id, a.checkin_at, a.checkout_at, a.payment_status
        FROM shift_applications a
        JOIN shifts s ON s.id=a.shift_id
        JOIN restaurants r ON r.id=s.restaurant_id
        JOIN job_functions f ON f.id=s.function_id
        WHERE a.freelancer_id=?
          AND a.status='confirmed'
          AND s.shift_date >= CURDATE()
        ORDER BY s.shift_date, s.start_time
    ");
    $commitmentStmt->execute(array($freelancer['id']));
    $commitments = $commitmentStmt->fetchAll();

    $vacanciesStmt = $pdo->query("
        SELECT s.*, r.name restaurant, f.name func,
          (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled
        FROM shifts s
        JOIN restaurants r ON r.id=s.restaurant_id
        JOIN job_functions f ON f.id=s.function_id
        WHERE s.status='open' AND s.shift_date >= CURDATE()
        HAVING filled < vacancies
        ORDER BY s.shift_date, s.start_time
        LIMIT 24
    ");
    $vacancies = $vacanciesStmt->fetchAll();
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Já tenho cadastro</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59;--brand-soft:#ccfbf1}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink)}
    .topbar{background:linear-gradient(90deg,#0f172a 0,#115e59 100%);color:#fff}
    .topbar a{color:#fff;font-weight:700}
    .brand{font-size:22px;font-weight:800}
    .panel,.vacancy-card{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07)}
    .btn{border-radius:6px;font-weight:800}
    .btn-dark{background:var(--brand);border-color:var(--brand)}
    .btn-dark:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .form-control{border-radius:6px}
    .badge-soft{background:var(--brand-soft);color:var(--brand-strong);border-radius:6px;padding:5px 8px;font-weight:800;font-size:12px}
    .commitment-card{border:1px solid #99f6e4;background:#f0fdfa;border-radius:8px;padding:14px;height:100%}
    .presence-line{font-size:13px;color:var(--muted);margin-top:8px}
    .geo-status{display:block;color:var(--muted);font-size:12px;margin-top:6px}
    .pay{font-size:24px;font-weight:800;color:var(--brand-strong)}
    .meta{color:var(--muted);font-size:13px}
  </style>
</head>
<body>
  <div class="topbar">
    <div class="container py-3 d-flex align-items-center justify-content-between">
      <div class="brand">Freela</div>
      <div>
        <a class="mr-3" href="<?=app_url('index.php')?>">Início</a>
        <a href="<?=app_url('public/register.php')?>">Novo cadastro</a>
      </div>
    </div>
  </div>

  <main class="container py-4">
    <?php if($message): ?><div class="alert alert-success"><?=h($message)?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>

    <?php if(!$freelancer): ?>
      <div class="row justify-content-center">
        <div class="col-lg-7">
          <div class="panel p-4 mb-4">
            <h3 class="font-weight-bold mb-1">Já tenho cadastro</h3>
            <p class="text-muted">Informe seu WhatsApp e senha para acessar suas candidaturas.</p>
            <form method="post" class="form-row align-items-end">
              <input type="hidden" name="action" value="login">
              <div class="col-md-5 mb-2">
                <label>WhatsApp cadastrado</label>
                <input class="form-control" name="whatsapp" required value="<?=h($whatsapp)?>" placeholder="Ex: 11999998888">
              </div>
              <div class="col-md-4 mb-2">
                <label>Senha</label>
                <input class="form-control" name="password" type="password" required>
              </div>
              <div class="col-md-3 mb-2">
                <button class="btn btn-dark btn-block">Acessar</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if($freelancer): ?>
      <div class="panel p-4 mb-4">
        <div class="d-md-flex align-items-center justify-content-between">
          <div>
            <h4 class="font-weight-bold mb-1">Olá, <?=h($freelancer['full_name'])?></h4>
            <p class="text-muted mb-md-0"><?=h($statusMessages[$freelancer['status']] ?? 'Status encontrado.')?></p>
          </div>
          <div class="text-md-right mt-3 mt-md-0">
            <span class="badge-soft d-inline-block mb-2"><?=h($statusLabels[$freelancer['status']] ?? $freelancer['status'])?></span>
            <form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-sm btn-outline-secondary">Sair</button></form>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if($freelancer && $freelancer['status'] === 'approved'): ?>
      <?php if($commitments): ?>
        <div class="d-flex align-items-end justify-content-between mb-3">
          <div>
            <h3 class="font-weight-bold mb-1">Meus compromissos aprovados</h3>
            <p class="text-muted mb-0">Essas vagas já estão confirmadas para você.</p>
          </div>
          <span class="badge-soft"><?=count($commitments)?> confirmado<?=count($commitments) === 1 ? '' : 's'?></span>
        </div>
        <div class="row mb-4">
          <?php foreach($commitments as $commitment): ?>
            <div class="col-md-4 mb-3">
              <div class="commitment-card">
                <div class="d-flex align-items-start justify-content-between mb-2">
                  <span class="badge-soft"><?=h($typeLabels[$commitment['shift_type']] ?? $commitment['shift_type'])?></span>
                  <span class="meta"><?=date('d/m/Y', strtotime($commitment['shift_date']))?></span>
                </div>
                <h5 class="mb-1"><?=h($commitment['func'])?></h5>
                <div class="meta mb-2"><?=h($commitment['restaurant'])?></div>
                <div class="font-weight-bold"><?=substr($commitment['start_time'],0,5)?> às <?=substr($commitment['end_time'],0,5)?></div>
                <div class="pay mt-2">R$ <?=number_format($commitment['pay_value'], 2, ',', '.')?></div>
                <div class="presence-line">
                  Check-in: <?=!empty($commitment['checkin_at']) ? date('d/m H:i', strtotime($commitment['checkin_at'])) : 'pendente'?><br>
                  Check-out: <?=!empty($commitment['checkout_at']) ? date('d/m H:i', strtotime($commitment['checkout_at'])) : 'pendente'?><br>
                  Pagamento: <?=$commitment['payment_status'] === 'paid' ? 'pago' : 'pendente'?>
                </div>
                <?php if($commitment['shift_date'] === date('Y-m-d')): ?>
                  <?php if(empty($commitment['checkin_at'])): ?>
                    <form method="post" class="geo-form mt-3">
                      <input type="hidden" name="action" value="checkin">
                      <input type="hidden" name="application_id" value="<?=$commitment['application_id']?>">
                      <input type="hidden" name="lat">
                      <input type="hidden" name="lng">
                      <button class="btn btn-dark btn-block">Fazer check-in</button>
                      <small class="geo-status"></small>
                    </form>
                  <?php elseif(empty($commitment['checkout_at'])): ?>
                    <form method="post" class="geo-form mt-3">
                      <input type="hidden" name="action" value="checkout">
                      <input type="hidden" name="application_id" value="<?=$commitment['application_id']?>">
                      <input type="hidden" name="lat">
                      <input type="hidden" name="lng">
                      <button class="btn btn-outline-dark btn-block">Fazer check-out</button>
                      <small class="geo-status"></small>
                    </form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="d-flex align-items-end justify-content-between mb-3">
        <div>
          <h3 class="font-weight-bold mb-1">Vagas abertas</h3>
          <p class="text-muted mb-0">Candidate-se. O restaurante aprova até o limite de vagas disponíveis.</p>
        </div>
        <span class="badge-soft"><?=count($vacancies)?> disponíveis</span>
      </div>
      <div class="row">
        <?php foreach($vacancies as $vacancy): $available = (int)$vacancy['vacancies'] - (int)$vacancy['filled']; $appStatus = $applications[(int)$vacancy['id']] ?? ''; ?>
          <div class="col-md-4 mb-3">
            <div class="vacancy-card p-3 h-100">
              <div class="d-flex align-items-start justify-content-between mb-2">
                <span class="badge-soft"><?=h($typeLabels[$vacancy['shift_type']] ?? $vacancy['shift_type'])?></span>
                <span class="meta"><?=date('d/m/Y', strtotime($vacancy['shift_date']))?></span>
              </div>
              <h5 class="mb-1"><?=h($vacancy['func'])?></h5>
              <div class="meta mb-3"><?=h($vacancy['restaurant'])?></div>
              <div class="pay mb-2">R$ <?=number_format($vacancy['pay_value'], 2, ',', '.')?></div>
              <div class="meta mb-3"><?=substr($vacancy['start_time'],0,5)?> às <?=substr($vacancy['end_time'],0,5)?> · <?=$available?> vaga<?=$available === 1 ? '' : 's'?> livre<?=$available === 1 ? '' : 's'?></div>
              <?php if($appStatus === 'pending'): ?>
                <button class="btn btn-warning btn-block" disabled>Aguardando aprovação</button>
              <?php elseif($appStatus === 'confirmed'): ?>
                <button class="btn btn-success btn-block" disabled>Aprovado na vaga</button>
              <?php elseif($appStatus === 'cancelled'): ?>
                <button class="btn btn-outline-danger btn-block" disabled>Recusado</button>
              <?php else: ?>
                <form method="post">
                  <input type="hidden" name="action" value="apply">
                  <input type="hidden" name="shift_id" value="<?=$vacancy['id']?>">
                  <button class="btn btn-dark btn-block">Candidatar-me</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if(!$vacancies): ?><div class="col-12"><div class="panel p-4 text-center text-muted">Não há vagas abertas no momento.</div></div><?php endif; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
<script>
document.querySelectorAll('.geo-form').forEach(function(form) {
  form.addEventListener('submit', function(event) {
    if (form.dataset.ready === '1') return;
    event.preventDefault();
    var status = form.querySelector('.geo-status');
    var button = form.querySelector('button');
    function submitWithoutGps(reason) {
      var ok = confirm(reason + '\n\nDeseja registrar mesmo assim, sem GPS?');
      if (ok) {
        form.dataset.ready = '1';
        form.submit();
      } else if (button) {
        button.disabled = false;
      }
    }
    if (button) button.disabled = true;
    if (status) status.textContent = 'Capturando localização...';

    if (!navigator.geolocation) {
      if (status) status.textContent = 'Este navegador não oferece GPS.';
      submitWithoutGps('Este navegador não oferece captura de localização.');
      return;
    }

    if (window.isSecureContext === false) {
      if (status) status.textContent = 'GPS bloqueado: acesse por HTTPS para liberar a permissão.';
      submitWithoutGps('O navegador bloqueou o GPS porque a página não está em HTTPS.');
      return;
    }

    navigator.geolocation.getCurrentPosition(function(position) {
      form.querySelector('input[name="lat"]').value = position.coords.latitude;
      form.querySelector('input[name="lng"]').value = position.coords.longitude;
      if (status) status.textContent = 'Localização capturada.';
      form.dataset.ready = '1';
      form.submit();
    }, function(error) {
      var reason = 'Não foi possível capturar a localização.';
      if (error.code === error.PERMISSION_DENIED) reason = 'Permissão de localização negada.';
      if (error.code === error.POSITION_UNAVAILABLE) reason = 'Localização indisponível no aparelho.';
      if (error.code === error.TIMEOUT) reason = 'Tempo esgotado ao buscar localização.';
      if (status) status.textContent = reason;
      submitWithoutGps(reason);
    }, {enableHighAccuracy:true, timeout:8000, maximumAge:0});
  });
});
</script>
</html>
