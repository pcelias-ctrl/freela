<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$error = '';
$old = array(
    'name' => '',
    'responsible_name' => '',
    'phone' => '',
    'email' => '',
    'address' => '',
    'status' => 'active'
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array(
        'name' => trim($_POST['name'] ?? ''),
        'responsible_name' => trim($_POST['responsible_name'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'email' => strtolower(trim($_POST['email'] ?? '')),
        'address' => trim($_POST['address'] ?? ''),
        'status' => $_POST['status'] ?? 'active'
    );

    $stmt = $pdo->prepare('SELECT id FROM restaurants WHERE email = ? LIMIT 1');
    $stmt->execute(array($old['email']));

    if ($stmt->fetch()) {
        $error = 'Este e-mail já está cadastrado para outro restaurante.';
    } else {
        try {
            $hash = password_hash($_POST['password'] ?: '123456', PASSWORD_DEFAULT);
            $pdo->prepare('INSERT INTO restaurants (name,responsible_name,phone,email,password_hash,address,status) VALUES (?,?,?,?,?,?,?)')
                ->execute(array($old['name'], $old['responsible_name'], $old['phone'], $old['email'], $hash, $old['address'], $old['status']));
            header('Location: restaurants.php');
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $error = 'Este e-mail já está cadastrado para outro restaurante.';
            } else {
                throw $e;
            }
        }
    }
}

$items = $pdo->query('SELECT * FROM restaurants ORDER BY name')->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
  <div>
    <h3>Restaurantes</h3>
    <p>Cadastre parceiros, defina o acesso e acompanhe o status de cada conta.</p>
  </div>
</div>

<div class="row">
  <div class="col-md-4 mb-4">
    <div class="card p-3">
      <h5>Novo restaurante</h5>
      <?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
      <form method="post">
        <label>Nome</label>
        <input class="form-control mb-2" name="name" required value="<?=e($old['name'])?>">

        <label>Responsável</label>
        <input class="form-control mb-2" name="responsible_name" value="<?=e($old['responsible_name'])?>">

        <label>Telefone</label>
        <input class="form-control mb-2" name="phone" value="<?=e($old['phone'])?>">

        <label>E-mail de acesso</label>
        <input class="form-control mb-2" name="email" type="email" required value="<?=e($old['email'])?>">

        <label>Senha inicial</label>
        <input class="form-control mb-2" name="password" placeholder="Padrão: 123456">

        <label>Endereço</label>
        <textarea class="form-control mb-2" name="address"><?=e($old['address'])?></textarea>

        <label>Status</label>
        <select class="form-control" name="status">
          <option value="active" <?=$old['status'] === 'active' ? 'selected' : ''?>>Ativo</option>
          <option value="inactive" <?=$old['status'] === 'inactive' ? 'selected' : ''?>>Inativo</option>
        </select>
        <button class="btn btn-dark mt-3">Salvar restaurante</button>
      </form>
    </div>
  </div>

  <div class="col-md-8 mb-4">
    <div class="card p-3">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nome</th><th>Login</th><th>Telefone</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($items as $item): ?>
            <tr>
              <td><?=e($item['name'])?></td>
              <td><?=e($item['email'])?></td>
              <td><?=e($item['phone'])?></td>
              <td><span class="badge badge-light"><?=e($item['status'])?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
