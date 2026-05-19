<?php
require_once __DIR__ . '/../config/db.php'; require_once __DIR__ . '/../includes/auth.php'; require_admin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if(isset($_POST['delete'])){$pdo->prepare('UPDATE job_functions SET status="inactive" WHERE id=?')->execute([$_POST['id']]);}
  else{
    $pdo->prepare('INSERT INTO job_functions (name, description, default_daily_value, default_hours, status) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE description=VALUES(description), default_daily_value=VALUES(default_daily_value), default_hours=VALUES(default_hours), status=VALUES(status)')
      ->execute([trim($_POST['name']), trim($_POST['description']), $_POST['default_daily_value'], $_POST['default_hours'], $_POST['status']]);
  }
  header('Location: functions.php'); exit;
}
$items=$pdo->query('SELECT * FROM job_functions ORDER BY name')->fetchAll(); require_once __DIR__.'/../includes/header.php';
?>
<h3>Funções</h3><div class="row"><div class="col-md-4"><div class="card p-3"><h5>Nova função</h5><form method="post"><label>Nome</label><input class="form-control" name="name" required><label class="mt-2">Descrição</label><textarea class="form-control" name="description"></textarea><label class="mt-2">Valor padrão</label><input class="form-control" name="default_daily_value" type="number" step="0.01" value="120.00"><label class="mt-2">Horas</label><input class="form-control" name="default_hours" type="number" step="0.01" value="8.00"><label class="mt-2">Status</label><select class="form-control" name="status"><option value="active">Ativa</option><option value="inactive">Inativa</option></select><button class="btn btn-dark mt-3">Salvar</button></form></div></div><div class="col-md-8"><div class="card p-3"><table class="table"><thead><tr><th>Função</th><th>Valor</th><th>Horas</th><th>Status</th></tr></thead><tbody><?php foreach($items as $i): ?><tr><td><?=e($i['name'])?></td><td>R$ <?=number_format($i['default_daily_value'],2,',','.')?></td><td><?=e($i['default_hours'])?></td><td><?=e($i['status'])?></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php require_once __DIR__.'/../includes/footer.php'; ?>
