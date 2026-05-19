<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_restaurant();

function initial_letter($name) {
    $name = trim((string)$name);
    if ($name === '') return '?';
    return function_exists('mb_substr') ? mb_substr($name, 0, 1, 'UTF-8') : substr($name, 0, 1);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO shifts (restaurant_id,function_id,shift_date,shift_type,start_time,end_time,vacancies,pay_value,notes,status,created_by_type,created_by_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute(array(
            $_SESSION['restaurant_id'],
            $_POST['function_id'],
            $_POST['shift_date'],
            $_POST['shift_type'],
            $_POST['start_time'],
            $_POST['end_time'],
            max(1, (int)$_POST['vacancies']),
            $_POST['pay_value'],
            trim($_POST['notes'] ?? ''),
            'open',
            'restaurant',
            $_SESSION['restaurant_id']
        ));
    header('Location: ' . app_url('restaurant/shifts.php?month=' . date('Y-m', strtotime($_POST['shift_date'])) . '&date=' . $_POST['shift_date']));
    exit;
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) $selectedMonth = date('Y-m');
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) $selectedDate = date('Y-m-d');

$firstOfMonth = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
$monthStart = $firstOfMonth->format('Y-m-01');
$monthEnd = $firstOfMonth->format('Y-m-t');
$prevMonth = (clone $firstOfMonth)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $firstOfMonth)->modify('+1 month')->format('Y-m');
$monthNames = array(1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro');
$monthLabel = ucfirst($monthNames[(int)$firstOfMonth->format('n')]) . ' de ' . $firstOfMonth->format('Y');

$functions = $pdo->query("SELECT * FROM job_functions WHERE status='active' ORDER BY name")->fetchAll();
$stmt = $pdo->prepare("
    SELECT
      s.*,
      f.name func,
      (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled,
      (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='pending') pending_count
    FROM shifts s
    JOIN job_functions f ON f.id=s.function_id
    WHERE s.restaurant_id=? AND s.shift_date BETWEEN ? AND ?
    ORDER BY s.shift_date, s.start_time
");
$stmt->execute(array($_SESSION['restaurant_id'], $monthStart, $monthEnd));
$items = $stmt->fetchAll();

$pendingStmt = $pdo->prepare("
    SELECT
      a.id application_id,
      a.created_at,
      s.id shift_id,
      s.shift_date,
      s.start_time,
      s.end_time,
      s.vacancies,
      s.pay_value,
      f.name func,
      fr.full_name,
      fr.whatsapp,
      fr.photo_path,
      (SELECT COUNT(*) FROM shift_applications ok WHERE ok.shift_id=s.id AND ok.status='confirmed') filled
    FROM shift_applications a
    JOIN shifts s ON s.id=a.shift_id
    JOIN job_functions f ON f.id=s.function_id
    JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=? AND a.status='pending' AND s.shift_date >= CURDATE()
    ORDER BY s.shift_date, s.start_time, a.created_at
    LIMIT 6
");
$pendingStmt->execute(array($_SESSION['restaurant_id']));
$pendingCandidates = $pendingStmt->fetchAll();

$pendingPreviewStmt = $pdo->prepare("
    SELECT
      a.shift_id,
      fr.full_name,
      fr.photo_path
    FROM shift_applications a
    JOIN shifts s ON s.id=a.shift_id
    JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=?
      AND s.shift_date BETWEEN ? AND ?
      AND a.status='pending'
    ORDER BY a.created_at
");
$pendingPreviewStmt->execute(array($_SESSION['restaurant_id'], $monthStart, $monthEnd));
$pendingPreviewByShift = array();
foreach ($pendingPreviewStmt->fetchAll() as $preview) {
    $pendingPreviewByShift[(int)$preview['shift_id']][] = $preview;
}

$confirmedPreviewStmt = $pdo->prepare("
    SELECT
      a.shift_id,
      fr.full_name,
      fr.photo_path
    FROM shift_applications a
    JOIN shifts s ON s.id=a.shift_id
    JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=?
      AND s.shift_date BETWEEN ? AND ?
      AND a.status='confirmed'
    ORDER BY a.created_at
");
$confirmedPreviewStmt->execute(array($_SESSION['restaurant_id'], $monthStart, $monthEnd));
$confirmedPreviewByShift = array();
foreach ($confirmedPreviewStmt->fetchAll() as $preview) {
    $confirmedPreviewByShift[(int)$preview['shift_id']][] = $preview;
}

$detailStmt = $pdo->prepare("
    SELECT
      s.id shift_id,
      s.shift_date,
      s.start_time,
      s.end_time,
      s.vacancies,
      s.pay_value,
      s.notes,
      f.name func,
      a.status application_status,
      fr.full_name,
      fr.photo_path,
      fr.whatsapp
    FROM shifts s
    JOIN job_functions f ON f.id=s.function_id
    LEFT JOIN shift_applications a ON a.shift_id=s.id AND a.status IN ('pending','confirmed')
    LEFT JOIN freelancers fr ON fr.id=a.freelancer_id
    WHERE s.restaurant_id=?
      AND s.shift_date BETWEEN ? AND ?
    ORDER BY s.shift_date, s.start_time, f.name, FIELD(a.status,'confirmed','pending'), fr.full_name
");
$detailStmt->execute(array($_SESSION['restaurant_id'], $monthStart, $monthEnd));
$detailByDate = array();
foreach ($detailStmt->fetchAll() as $row) {
    $date = $row['shift_date'];
    $shiftId = (int)$row['shift_id'];
    if (!isset($detailByDate[$date])) $detailByDate[$date] = array();
    if (!isset($detailByDate[$date][$shiftId])) {
        $detailByDate[$date][$shiftId] = array(
            'id' => $shiftId,
            'func' => $row['func'],
            'start_time' => substr($row['start_time'], 0, 5),
            'end_time' => substr($row['end_time'], 0, 5),
            'vacancies' => (int)$row['vacancies'],
            'pay_value' => number_format($row['pay_value'], 2, ',', '.'),
            'notes' => $row['notes'],
            'confirmed' => array(),
            'pending' => array()
        );
    }
    if ($row['application_status'] && $row['full_name']) {
        $detailByDate[$date][$shiftId][$row['application_status'] === 'confirmed' ? 'confirmed' : 'pending'][] = array(
            'name' => $row['full_name'],
            'photo' => $row['photo_path'] ? app_url($row['photo_path']) : '',
            'initial' => initial_letter($row['full_name']),
            'whatsapp' => $row['whatsapp']
        );
    }
}
$calendarDetails = array();
foreach ($detailByDate as $date => $shifts) {
    $calendarDetails[$date] = array_values($shifts);
}

$byDate = array();
$totalVacancies = 0;
$totalFilled = 0;
$totalPending = 0;
$openShifts = 0;
foreach ($items as $shift) {
    $byDate[$shift['shift_date']][] = $shift;
    $totalVacancies += (int)$shift['vacancies'];
    $totalFilled += (int)$shift['filled'];
    $totalPending += (int)$shift['pending_count'];
    if ($shift['status'] === 'open') $openShifts++;
}

$typeLabels = array('almoco'=>'Almoço','jantar'=>'Jantar','evento'=>'Evento');
$statusLabels = array('open'=>'Aberta','closed'=>'Fechada','cancelled'=>'Cancelada');
$weekDays = array('Seg','Ter','Qua','Qui','Sex','Sáb','Dom');
$calendarStart = (clone $firstOfMonth)->modify('-' . ((int)$firstOfMonth->format('N') - 1) . ' days');
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Vagas do Restaurante</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59;--brand-soft:#ccfbf1;--accent:#f97316}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink);font-size:15px}
    .navbar-dark.bg-dark{background:linear-gradient(90deg,#0f172a 0,#115e59 100%)!important}
    .navbar{box-shadow:0 8px 24px rgba(15,118,110,.18)}
    .navbar-brand{font-weight:800}
    .nav-alert{background:#f97316;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;font-weight:800;margin-left:4px}
    .card{border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07)}
    .btn,.form-control{border-radius:6px}
    .btn-dark{background:var(--brand);border-color:var(--brand);font-weight:700}
    .btn-dark:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 .2rem rgba(15,118,110,.14)}
    .page-title{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}
    .page-title h3{font-weight:700;margin:0}
    .page-title p{color:var(--muted);margin:4px 0 0}
    .metric{background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px 16px}
    .metric.alert-metric{border-color:#fed7aa;background:#fff7ed}
    .metric small{display:block;color:var(--muted);font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
    .metric strong{display:block;font-size:24px;margin-top:3px}
    .calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));border:1px solid var(--line);border-radius:8px;overflow:hidden;background:#fff}
    .calendar-head,.calendar-day{min-height:118px;border-right:1px solid var(--line);border-bottom:1px solid var(--line);padding:10px}
    .calendar-head{min-height:auto;background:#f0fdfa;color:#0f766e;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;text-align:center}
    .calendar-grid>*:nth-child(7n){border-right:0}
    .calendar-day{cursor:pointer;transition:.15s ease;background:#fff;position:relative}
    .calendar-day:hover{background:#f0fdfa}
    .calendar-day.has-pending{background:#fff7ed}
    .calendar-day.has-pending:before{content:"";position:absolute;top:8px;right:8px;width:9px;height:9px;border-radius:50%;background:#f97316}
    .calendar-day.is-muted{background:#f8fafc;color:#adb4bf}
    .calendar-day.is-today{box-shadow:inset 0 0 0 2px var(--brand)}
    .calendar-day.is-selected{background:var(--brand-soft)}
    .day-number{font-weight:700;font-size:14px}
    .shift-pill{display:block;margin-top:7px;padding:5px 7px;border-radius:6px;background:#ccfbf1;color:#115e59;font-size:12px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .shift-pill.warning{background:#ffedd5;color:#9a3412}
    .shift-pill.pending{background:#fed7aa;color:#9a3412;font-weight:800}
    .candidate-preview{display:flex;align-items:center;gap:4px;margin-top:6px;min-width:0}
    .candidate-mini{width:22px;height:22px;border-radius:50%;object-fit:cover;background:#e4e7ec;border:2px solid #fff;box-shadow:0 1px 4px rgba(17,24,39,.12);display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:800;color:#115e59;flex:0 0 auto}
    .candidate-preview.confirmed .candidate-mini{background:#ccfbf1;color:#115e59}
    .candidate-name{font-size:11px;color:#9a3412;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
    .candidate-preview.confirmed .candidate-name{color:#115e59}
    .confirmed-label{display:block;margin-top:6px;color:#115e59;font-size:11px;font-weight:800}
    .candidate-row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--line)}
    .candidate-row:last-child{border-bottom:0}
    .candidate-photo{width:42px;height:42px;border-radius:50%;object-fit:cover;background:#e4e7ec}
    .detail-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1040;padding:24px;overflow:auto}
    .detail-backdrop.is-open{display:block}
    .detail-panel{background:#fff;border-radius:8px;box-shadow:0 24px 80px rgba(15,23,42,.35);max-width:900px;margin:40px auto;padding:20px}
    .detail-shift{border:1px solid var(--line);border-radius:8px;padding:14px;margin-top:12px}
    .person-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:8px}
    .person-chip{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:8px}
    .person-chip.confirmed{background:#f0fdfa;border-color:#99f6e4}
    .person-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#e4e7ec;display:inline-flex;align-items:center;justify-content:center;font-weight:800;color:#115e59;flex:0 0 auto}
    .table thead th{border-top:0;color:#667085;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
    @media(max-width:767.98px){.page-title{display:block}.calendar-day{min-height:92px;padding:7px}.shift-pill{font-size:11px}.calendar-head{font-size:10px;padding:7px 2px}}
  </style>
</head>
<body>
<nav class="navbar navbar-dark bg-dark mb-4">
  <span class="navbar-brand">Freela · <?=e($_SESSION['restaurant_name'])?></span>
  <div>
    <a class="text-white mr-3" href="<?=app_url('restaurant/candidates.php')?>">Candidatos<?php if($totalPending): ?><span class="nav-alert"><?=$totalPending?></span><?php endif; ?></a>
    <a class="text-white mr-3" href="<?=app_url('restaurant/payments.php')?>">Pagamentos</a>
    <a class="text-white" href="<?=app_url('restaurant/logout.php')?>">Sair</a>
  </div>
</nav>
<div class="container-fluid px-4">
  <div class="page-title">
    <div>
      <h3>Agenda de vagas</h3>
      <p>Dias em laranja têm candidatos aguardando aprovação. Aprove até completar a ocupação da vaga.</p>
    </div>
    <a class="btn btn-dark" href="<?=app_url('restaurant/candidates.php')?>">Revisar candidatos</a>
  </div>

  <div class="row mb-4">
    <div class="col-md-3 mb-2"><div class="metric"><small>Escalas abertas</small><strong><?=$openShifts?></strong></div></div>
    <div class="col-md-3 mb-2"><div class="metric"><small>Vagas no mês</small><strong><?=$totalVacancies?></strong></div></div>
    <div class="col-md-3 mb-2"><div class="metric"><small>Confirmados</small><strong><?=$totalFilled?></strong></div></div>
    <div class="col-md-3 mb-2"><div class="metric alert-metric"><small>Aguardando aprovação</small><strong><?=$totalPending?></strong></div></div>
  </div>

  <div class="row">
    <div class="col-xl-8 mb-4">
      <div class="card p-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <a class="btn btn-sm btn-outline-secondary" href="?month=<?=$prevMonth?>&date=<?=$prevMonth?>-01">Anterior</a>
          <h5 class="mb-0"><?=$monthLabel?></h5>
          <a class="btn btn-sm btn-outline-secondary" href="?month=<?=$nextMonth?>&date=<?=$nextMonth?>-01">Próximo</a>
        </div>
        <div class="calendar-grid">
          <?php foreach ($weekDays as $day): ?><div class="calendar-head"><?=$day?></div><?php endforeach; ?>
          <?php for ($i = 0; $i < 42; $i++): $date = (clone $calendarStart)->modify("+{$i} days"); $dateKey = $date->format('Y-m-d'); $dayShifts = $byDate[$dateKey] ?? []; $dayPending = array_sum(array_map(function($s){ return (int)$s['pending_count']; }, $dayShifts)); ?>
            <div class="calendar-day <?=$date->format('Y-m') !== $selectedMonth ? 'is-muted' : ''?> <?=$dateKey === date('Y-m-d') ? 'is-today' : ''?> <?=$dateKey === $selectedDate ? 'is-selected' : ''?> <?=$dayPending ? 'has-pending' : ''?>" data-date="<?=$dateKey?>">
              <div class="day-number"><?=$date->format('d')?></div>
              <?php foreach (array_slice($dayShifts, 0, 3) as $shift): ?>
                <span class="shift-pill <?=((int)$shift['filled'] >= (int)$shift['vacancies']) ? 'warning' : ''?>">
                  <?=substr($shift['start_time'], 0, 5)?> · <?=e($shift['func'])?> (<?=e($shift['filled'])?>/<?=e($shift['vacancies'])?>)
                </span>
                <?php if((int)$shift['filled'] > 0): ?>
                  <span class="confirmed-label">Confirmados</span>
                  <?php $confirmedPreviews = array_slice($confirmedPreviewByShift[(int)$shift['id']] ?? array(), 0, 2); ?>
                  <?php foreach($confirmedPreviews as $preview): ?>
                    <div class="candidate-preview confirmed">
                      <?php if($preview['photo_path']): ?>
                        <img class="candidate-mini" src="<?=app_url($preview['photo_path'])?>" alt="Foto de <?=e($preview['full_name'])?>">
                      <?php else: ?>
                        <span class="candidate-mini"><?=e(initial_letter($preview['full_name']))?></span>
                      <?php endif; ?>
                      <span class="candidate-name"><?=e($preview['full_name'])?></span>
                    </div>
                  <?php endforeach; ?>
                  <?php $confirmedRemaining = (int)$shift['filled'] - count($confirmedPreviews); ?>
                  <?php if($confirmedRemaining > 0): ?><span class="candidate-name">+<?=$confirmedRemaining?> confirmado<?=$confirmedRemaining === 1 ? '' : 's'?></span><?php endif; ?>
                <?php endif; ?>
                <?php if((int)$shift['pending_count'] > 0): ?>
                  <span class="shift-pill pending"><?=e($shift['pending_count'])?> candidato<?=((int)$shift['pending_count'] === 1 ? '' : 's')?></span>
                  <?php $previews = array_slice($pendingPreviewByShift[(int)$shift['id']] ?? array(), 0, 2); ?>
                  <?php foreach($previews as $preview): ?>
                    <div class="candidate-preview">
                      <?php if($preview['photo_path']): ?>
                        <img class="candidate-mini" src="<?=app_url($preview['photo_path'])?>" alt="Foto de <?=e($preview['full_name'])?>">
                      <?php else: ?>
                        <span class="candidate-mini"><?=e(initial_letter($preview['full_name']))?></span>
                      <?php endif; ?>
                      <span class="candidate-name"><?=e($preview['full_name'])?></span>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (count($dayShifts) > 3): ?><span class="shift-pill">+<?=count($dayShifts)-3?> escalas</span><?php endif; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="col-xl-4 mb-4">
      <div class="card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h5 class="mb-0">Aprovar agora</h5>
          <a class="btn btn-sm btn-outline-dark" href="<?=app_url('restaurant/candidates.php')?>">Ver todos</a>
        </div>
        <?php foreach($pendingCandidates as $candidate): ?>
          <div class="candidate-row">
            <?php if($candidate['photo_path']): ?>
              <img class="candidate-photo" src="<?=app_url($candidate['photo_path'])?>" alt="Foto de <?=e($candidate['full_name'])?>">
            <?php else: ?>
              <span class="candidate-photo d-inline-flex align-items-center justify-content-center font-weight-bold text-success"><?=e(initial_letter($candidate['full_name']))?></span>
            <?php endif; ?>
            <div class="flex-grow-1">
              <b><?=e($candidate['full_name'])?></b><br>
              <small class="text-muted"><?=date('d/m', strtotime($candidate['shift_date']))?> · <?=substr($candidate['start_time'],0,5)?> · <?=e($candidate['func'])?> · <?=e($candidate['filled'])?>/<?=e($candidate['vacancies'])?></small>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if(!$pendingCandidates): ?><div class="text-muted py-3">Nenhum candidato pendente agora.</div><?php endif; ?>
      </div>

      <div class="card p-3">
        <h5 class="mb-3">Nova vaga</h5>
        <form method="post" id="shiftForm">
          <div class="form-group">
            <label>Função</label>
            <select class="form-control" name="function_id" required>
              <?php foreach($functions as $function): ?><option value="<?=$function['id']?>"><?=e($function['name'])?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-row">
            <div class="form-group col-6"><label>Data</label><input class="form-control" id="shiftDate" name="shift_date" type="date" required value="<?=e($selectedDate)?>"></div>
            <div class="form-group col-6">
              <label>Turno</label>
              <select class="form-control" name="shift_type">
                <option value="almoco">Almoço</option>
                <option value="jantar">Jantar</option>
                <option value="evento">Evento</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-6"><label>Entrada</label><input class="form-control" name="start_time" type="time" required></div>
            <div class="form-group col-6"><label>Saída</label><input class="form-control" name="end_time" type="time" required></div>
          </div>
          <div class="form-row">
            <div class="form-group col-6"><label>Vagas</label><input class="form-control" name="vacancies" type="number" min="1" value="1" required></div>
            <div class="form-group col-6"><label>Valor pago</label><input class="form-control" name="pay_value" type="number" step="0.01" value="120.00" required></div>
          </div>
          <div class="form-group"><label>Observações</label><textarea class="form-control" name="notes" rows="3"></textarea></div>
          <button class="btn btn-dark btn-block">Lançar vaga</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card p-3 mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h5 class="mb-0">Escalas do mês</h5>
      <span class="text-muted"><?=count($items)?> registros</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0">
        <thead><tr><th>Data</th><th>Turno</th><th>Função</th><th>Horário</th><th>Vagas</th><th>Pendentes</th><th>Valor</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach($items as $shift): ?>
          <tr>
            <td><?=date('d/m/Y', strtotime($shift['shift_date']))?></td>
            <td><?=e($typeLabels[$shift['shift_type']] ?? $shift['shift_type'])?></td>
            <td><?=e($shift['func'])?></td>
            <td><?=substr($shift['start_time'],0,5)?> às <?=substr($shift['end_time'],0,5)?></td>
            <td><?=e($shift['filled'])?>/<?=e($shift['vacancies'])?></td>
            <td><?php if((int)$shift['pending_count']): ?><a class="badge badge-warning" href="<?=app_url('restaurant/candidates.php')?>"><?=e($shift['pending_count'])?> aguardando</a><?php else: ?><span class="text-muted">0</span><?php endif; ?></td>
            <td>R$ <?=number_format($shift['pay_value'],2,',','.')?></td>
            <td><span class="badge badge-light"><?=e($statusLabels[$shift['status']] ?? $shift['status'])?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhuma escala cadastrada neste mês.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="detail-backdrop" id="dayDetail">
  <div class="detail-panel">
    <div class="d-flex align-items-start justify-content-between">
      <div>
        <h4 class="font-weight-bold mb-1" id="detailTitle">Detalhes do dia</h4>
        <p class="text-muted mb-0">Vagas, ocupantes confirmados e candidatos pendentes.</p>
      </div>
      <button class="btn btn-sm btn-outline-secondary" type="button" id="detailClose">Fechar</button>
    </div>
    <div id="detailContent" class="mt-3"></div>
  </div>
</div>
<script>
var calendarDetails = <?=json_encode($calendarDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)?>;
function escapeHtml(value) {
  return String(value || '').replace(/[&<>"']/g, function(char) {
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
  });
}
function renderPeople(list, confirmed) {
  if (!list || !list.length) return '<div class="text-muted">Nenhum ' + (confirmed ? 'confirmado' : 'pendente') + '.</div>';
  return '<div class="person-list">' + list.map(function(person) {
    var avatar = person.photo
      ? '<img class="person-avatar" src="' + escapeHtml(person.photo) + '" alt="Foto de ' + escapeHtml(person.name) + '">'
      : '<span class="person-avatar">' + escapeHtml(person.initial) + '</span>';
    return '<div class="person-chip ' + (confirmed ? 'confirmed' : '') + '">' + avatar + '<div><b>' + escapeHtml(person.name) + '</b><br><small class="text-muted">' + escapeHtml(person.whatsapp || '') + '</small></div></div>';
  }).join('') + '</div>';
}
function openDayDetail(date) {
  var shifts = calendarDetails[date] || [];
  document.getElementById('detailTitle').textContent = 'Detalhes de ' + date.split('-').reverse().join('/');
  if (!shifts.length) {
    document.getElementById('detailContent').innerHTML = '<div class="text-muted py-3">Nenhuma vaga cadastrada para este dia.</div>';
  } else {
    document.getElementById('detailContent').innerHTML = shifts.map(function(shift) {
      var confirmedCount = shift.confirmed ? shift.confirmed.length : 0;
      var pendingCount = shift.pending ? shift.pending.length : 0;
      return '<div class="detail-shift">' +
        '<div class="d-md-flex align-items-start justify-content-between">' +
          '<div><h5 class="mb-1">' + escapeHtml(shift.func) + '</h5><div class="text-muted">' + escapeHtml(shift.start_time) + ' às ' + escapeHtml(shift.end_time) + ' · R$ ' + escapeHtml(shift.pay_value) + '</div></div>' +
          '<span class="badge badge-light mt-2 mt-md-0">' + confirmedCount + '/' + escapeHtml(shift.vacancies) + ' ocupadas · ' + pendingCount + ' pendentes</span>' +
        '</div>' +
        (shift.notes ? '<div class="mt-2 text-muted">' + escapeHtml(shift.notes) + '</div>' : '') +
        '<div class="mt-3"><b>Ocupantes confirmados</b>' + renderPeople(shift.confirmed, true) + '</div>' +
        '<div class="mt-3"><b>Candidatos pendentes</b>' + renderPeople(shift.pending, false) + '</div>' +
      '</div>';
    }).join('');
  }
  document.getElementById('dayDetail').classList.add('is-open');
}
document.querySelectorAll('.calendar-day').forEach(function(day) {
  day.addEventListener('click', function() {
    document.querySelectorAll('.calendar-day').forEach(function(item) { item.classList.remove('is-selected'); });
    day.classList.add('is-selected');
    document.getElementById('shiftDate').value = day.dataset.date;
    openDayDetail(day.dataset.date);
  });
});
document.getElementById('detailClose').addEventListener('click', function() {
  document.getElementById('dayDetail').classList.remove('is-open');
});
document.getElementById('dayDetail').addEventListener('click', function(event) {
  if (event.target === this) this.classList.remove('is-open');
});
</script>
</body>
</html>
