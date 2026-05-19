<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO shifts (restaurant_id,function_id,shift_date,shift_type,start_time,end_time,vacancies,pay_value,notes,status,created_by_type,created_by_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $_POST['restaurant_id'],
            $_POST['function_id'],
            $_POST['shift_date'],
            $_POST['shift_type'],
            $_POST['start_time'],
            $_POST['end_time'],
            max(1, (int)$_POST['vacancies']),
            $_POST['pay_value'],
            trim($_POST['notes'] ?? ''),
            $_POST['status'] ?? 'open',
            'admin',
            $_SESSION['admin_id']
        ]);
    header('Location: ' . app_url('admin/shifts.php?month=' . date('Y-m', strtotime($_POST['shift_date'])) . '&date=' . $_POST['shift_date']));
    exit;
}

$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$selectedDate = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$firstOfMonth = DateTime::createFromFormat('Y-m-d', $selectedMonth . '-01');
$monthStart = $firstOfMonth->format('Y-m-01');
$monthEnd = $firstOfMonth->format('Y-m-t');
$prevMonth = (clone $firstOfMonth)->modify('-1 month')->format('Y-m');
$nextMonth = (clone $firstOfMonth)->modify('+1 month')->format('Y-m');
$monthNames = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$monthLabel = ucfirst($monthNames[(int)$firstOfMonth->format('n')]) . ' de ' . $firstOfMonth->format('Y');

$restaurants = $pdo->query("SELECT * FROM restaurants WHERE status='active' ORDER BY name")->fetchAll();
$functions = $pdo->query("SELECT * FROM job_functions WHERE status='active' ORDER BY name")->fetchAll();

$stmt = $pdo->prepare("SELECT s.*, r.name restaurant, f.name func, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled FROM shifts s JOIN restaurants r ON r.id=s.restaurant_id JOIN job_functions f ON f.id=s.function_id WHERE s.shift_date BETWEEN ? AND ? ORDER BY s.shift_date, s.start_time");
$stmt->execute([$monthStart, $monthEnd]);
$items = $stmt->fetchAll();

$byDate = [];
$totalVacancies = 0;
$totalFilled = 0;
$openShifts = 0;
foreach ($items as $shift) {
    $byDate[$shift['shift_date']][] = $shift;
    $totalVacancies += (int)$shift['vacancies'];
    $totalFilled += (int)$shift['filled'];
    if ($shift['status'] === 'open') $openShifts++;
}

$typeLabels = ['almoco'=>'Almoço','jantar'=>'Jantar','evento'=>'Evento'];
$statusLabels = ['open'=>'Aberta','closed'=>'Fechada','cancelled'=>'Cancelada'];
$weekDays = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom'];
$calendarStart = (clone $firstOfMonth)->modify('-' . ((int)$firstOfMonth->format('N') - 1) . ' days');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
  <div>
    <h3>Escalas e vagas</h3>
    <p>Cadastre vagas por data e acompanhe a ocupação mensal em uma visão de calendário.</p>
  </div>
  <a class="btn btn-outline-dark" href="<?=app_url('admin/index.php')?>">Voltar ao painel</a>
</div>

<div class="row mb-4">
  <div class="col-md-3 mb-2"><div class="metric"><small>Escalas abertas</small><strong><?=$openShifts?></strong></div></div>
  <div class="col-md-3 mb-2"><div class="metric"><small>Vagas no mês</small><strong><?=$totalVacancies?></strong></div></div>
  <div class="col-md-3 mb-2"><div class="metric"><small>Confirmados</small><strong><?=$totalFilled?></strong></div></div>
  <div class="col-md-3 mb-2"><div class="metric"><small>Disponíveis</small><strong><?=max(0, $totalVacancies - $totalFilled)?></strong></div></div>
</div>

<div class="row">
  <div class="col-lg-8 mb-4">
    <div class="card p-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <a class="btn btn-sm btn-outline-secondary" href="?month=<?=$prevMonth?>&date=<?=$prevMonth?>-01">Anterior</a>
        <h5 class="mb-0"><?=$monthLabel?></h5>
        <a class="btn btn-sm btn-outline-secondary" href="?month=<?=$nextMonth?>&date=<?=$nextMonth?>-01">Próximo</a>
      </div>
      <div class="calendar-grid" id="shiftCalendar">
        <?php foreach ($weekDays as $day): ?><div class="calendar-head"><?=$day?></div><?php endforeach; ?>
        <?php for ($i = 0; $i < 42; $i++): $date = (clone $calendarStart)->modify("+{$i} days"); $dateKey = $date->format('Y-m-d'); $dayShifts = $byDate[$dateKey] ?? []; ?>
          <div class="calendar-day <?=$date->format('Y-m') !== $selectedMonth ? 'is-muted' : ''?> <?=$dateKey === date('Y-m-d') ? 'is-today' : ''?> <?=$dateKey === $selectedDate ? 'is-selected' : ''?>" data-date="<?=$dateKey?>">
            <div class="day-number"><?=$date->format('d')?></div>
            <?php foreach (array_slice($dayShifts, 0, 3) as $shift): ?>
              <span class="shift-pill <?=((int)$shift['filled'] >= (int)$shift['vacancies']) ? 'warning' : ''?>">
                <?=substr($shift['start_time'], 0, 5)?> · <?=e($shift['func'])?> (<?=e($shift['filled'])?>/<?=e($shift['vacancies'])?>)
              </span>
            <?php endforeach; ?>
            <?php if (count($dayShifts) > 3): ?><span class="shift-pill">+<?=count($dayShifts)-3?> escalas</span><?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-4 mb-4">
    <div class="card p-3">
      <h5 class="mb-3">Nova vaga</h5>
      <form method="post" id="shiftForm">
        <div class="form-group">
          <label>Restaurante</label>
          <select class="form-control" name="restaurant_id" required>
            <?php foreach($restaurants as $restaurant): ?><option value="<?=$restaurant['id']?>"><?=e($restaurant['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Função</label>
          <select class="form-control" name="function_id" required>
            <?php foreach($functions as $function): ?><option value="<?=$function['id']?>"><?=e($function['name'])?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group col-6">
            <label>Data</label>
            <input class="form-control" name="shift_date" id="shiftDate" type="date" required value="<?=e($selectedDate)?>">
          </div>
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
        <div class="form-group">
          <label>Status</label>
          <select class="form-control" name="status">
            <option value="open">Aberta</option>
            <option value="closed">Fechada</option>
            <option value="cancelled">Cancelada</option>
          </select>
        </div>
        <div class="form-group">
          <label>Observações</label>
          <textarea class="form-control" name="notes" rows="3"></textarea>
        </div>
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
      <thead><tr><th>Data</th><th>Restaurante</th><th>Turno</th><th>Função</th><th>Horário</th><th>Vagas</th><th>Valor</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach($items as $shift): ?>
        <tr>
          <td><?=date('d/m/Y', strtotime($shift['shift_date']))?></td>
          <td><?=e($shift['restaurant'])?></td>
          <td><?=e($typeLabels[$shift['shift_type']] ?? $shift['shift_type'])?></td>
          <td><?=e($shift['func'])?></td>
          <td><?=substr($shift['start_time'],0,5)?> às <?=substr($shift['end_time'],0,5)?></td>
          <td><?=e($shift['filled'])?>/<?=e($shift['vacancies'])?></td>
          <td>R$ <?=number_format($shift['pay_value'],2,',','.')?></td>
          <td><span class="badge badge-light"><?=e($statusLabels[$shift['status']] ?? $shift['status'])?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhuma escala cadastrada neste mês.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.querySelectorAll('.calendar-day').forEach(function(day) {
  day.addEventListener('click', function() {
    document.querySelectorAll('.calendar-day').forEach(function(item) { item.classList.remove('is-selected'); });
    day.classList.add('is-selected');
    document.getElementById('shiftDate').value = day.dataset.date;
    document.getElementById('shiftForm').scrollIntoView({behavior: 'smooth', block: 'start'});
  });
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
