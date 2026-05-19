<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$counts = [];
foreach (['freelancers','restaurants','job_functions','shifts'] as $table) {
    $counts[$table] = $pdo->query("SELECT COUNT(*) c FROM {$table}")->fetch()['c'];
}
$pending = $pdo->query("SELECT COUNT(*) c FROM freelancers WHERE status='pending'")->fetch()['c'];

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
  <div>
    <h3>Painel Freela</h3>
    <p>Visão geral de freelancers, restaurantes e vagas abertas.</p>
  </div>
  <a class="btn btn-dark" href="<?=app_url('admin/shifts.php')?>">Criar vaga</a>
</div>

<div class="row mt-3">
  <div class="col-md-3 mb-3"><div class="metric"><small>Freelancers</small><strong><?=$counts['freelancers']?></strong><span class="text-warning">Pendentes: <?=$pending?></span></div></div>
  <div class="col-md-3 mb-3"><div class="metric"><small>Restaurantes</small><strong><?=$counts['restaurants']?></strong></div></div>
  <div class="col-md-3 mb-3"><div class="metric"><small>Funções</small><strong><?=$counts['job_functions']?></strong></div></div>
  <div class="col-md-3 mb-3"><div class="metric"><small>Vagas</small><strong><?=$counts['shifts']?></strong></div></div>
</div>

<div class="card p-3 mt-2">
  <h5>Próximas vagas</h5>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>Data</th><th>Restaurante</th><th>Turno</th><th>Função</th><th>Vagas</th><th>Valor</th></tr></thead>
      <tbody>
      <?php
      $stmt = $pdo->query("SELECT s.*, r.name restaurant, f.name func, (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled FROM shifts s JOIN restaurants r ON r.id=s.restaurant_id JOIN job_functions f ON f.id=s.function_id WHERE s.shift_date>=CURDATE() ORDER BY s.shift_date, s.start_time LIMIT 10");
      foreach ($stmt as $shift): ?>
        <tr>
          <td><?=e(date('d/m/Y', strtotime($shift['shift_date'])))?></td>
          <td><?=e($shift['restaurant'])?></td>
          <td><?=e($shift['shift_type'])?></td>
          <td><?=e($shift['func'])?></td>
          <td><?=e($shift['filled'])?>/<?=e($shift['vacancies'])?></td>
          <td>R$ <?=number_format($shift['pay_value'], 2, ',', '.')?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
