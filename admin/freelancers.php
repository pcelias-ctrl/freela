<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$statusLabels = array(
    'pending' => 'Pendente',
    'approved' => 'Aprovado',
    'blocked' => 'Bloqueado',
    'rejected' => 'Recusado'
);

$statusBadges = array(
    'pending' => 'badge-warning',
    'approved' => 'badge-success',
    'blocked' => 'badge-dark',
    'rejected' => 'badge-danger'
);

$sexLabels = array(
    'feminino' => 'Feminino',
    'masculino' => 'Masculino',
    'outro' => 'Outro',
    'nao_informar' => 'Não informado'
);

function whatsapp_number($value) {
    $digits = preg_replace('/\D+/', '', (string)$value);
    if (strlen($digits) === 10 || strlen($digits) === 11) {
        $digits = '55' . $digits;
    }
    return $digits;
}

function whatsapp_message($name, $status) {
    if ($status === 'approved') {
        return 'Olá, ' . $name . '! Seu cadastro na Freela foi aprovado. Você já pode acompanhar as vagas disponíveis.';
    }
    if ($status === 'blocked') {
        return 'Olá, ' . $name . '. Seu cadastro na Freela foi bloqueado temporariamente. Fale com a administração para mais detalhes.';
    }
    if ($status === 'rejected') {
        return 'Olá, ' . $name . '. Seu cadastro na Freela não foi aprovado neste momento. Obrigado pelo interesse.';
    }
    return 'Olá, ' . $name . '! Recebemos seu cadastro na Freela e ele está em análise.';
}

function whatsapp_link($item, $status = null) {
    $number = whatsapp_number($item['whatsapp'] ?? '');
    if (!$number) {
        return '';
    }
    $targetStatus = $status ?: ($item['status'] ?? 'pending');
    return 'https://wa.me/' . $number . '?text=' . rawurlencode(whatsapp_message($item['full_name'] ?? '', $targetStatus));
}

if (isset($_GET['status'], $_GET['id'])) {
    $allowed = array('approved', 'blocked', 'rejected', 'pending');
    if (in_array($_GET['status'], $allowed)) {
        $stmt = $pdo->prepare('SELECT id, full_name, whatsapp FROM freelancers WHERE id=? LIMIT 1');
        $stmt->execute(array($_GET['id']));
        $freelancer = $stmt->fetch();

        if ($freelancer) {
            $pdo->prepare('UPDATE freelancers SET status=? WHERE id=?')->execute(array($_GET['status'], $_GET['id']));
            $_SESSION['freelancer_flash'] = array(
                'name' => $freelancer['full_name'],
                'status' => $_GET['status'],
                'whatsapp' => whatsapp_link(array(
                    'full_name' => $freelancer['full_name'],
                    'whatsapp' => $freelancer['whatsapp'],
                    'status' => $_GET['status']
                ))
            );
        }
    }
    header('Location: freelancers.php');
    exit;
}

$flash = $_SESSION['freelancer_flash'] ?? null;
unset($_SESSION['freelancer_flash']);

$items = $pdo->query('SELECT fr.*, jf.name func FROM freelancers fr LEFT JOIN job_functions jf ON jf.id=fr.main_function_id ORDER BY FIELD(fr.status,"pending","approved","blocked","rejected"), fr.created_at DESC')->fetchAll();
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-title">
  <div>
    <h3>Freelancers</h3>
    <p>Analise cadastros, confira Pix de pagamento e libere profissionais para as vagas.</p>
  </div>
</div>

<?php if ($flash): ?>
  <div class="alert alert-success d-flex align-items-center justify-content-between">
    <span>Status de <b><?=e($flash['name'])?></b> alterado para <?=e($statusLabels[$flash['status']] ?? $flash['status'])?>.</span>
    <?php if ($flash['whatsapp']): ?>
      <a class="btn btn-sm btn-success" target="_blank" href="<?=e($flash['whatsapp'])?>">Avisar no WhatsApp</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card p-3">
  <div class="table-responsive">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Foto</th>
          <th>Nome</th>
          <th>Contato</th>
          <th>Pix</th>
          <th>Idade/Sexo</th>
          <th>Função</th>
          <th>Status</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach($items as $item): $status = $item['status']; ?>
        <tr>
          <td><?php if($item['photo_path']): ?><img class="photo-thumb" src="<?=app_url($item['photo_path'])?>" alt="Foto de <?=e($item['full_name'])?>"><?php endif; ?></td>
          <td>
            <b><?=e($item['full_name'])?></b><br>
            <small><?=e($item['full_address'])?></small><br>
            <small class="text-muted"><?=e($item['restaurant_experience'])?></small>
          </td>
          <td>
            <b><?=e($item['whatsapp'])?></b><br>
            <small class="text-muted"><?=e($item['phone'])?></small>
          </td>
          <td>
            <?php if (!empty($item['chavepix'])): ?>
              <span class="badge badge-light d-inline-block text-left" style="max-width:220px;white-space:normal;word-break:break-word;"><?=e($item['chavepix'])?></span>
            <?php else: ?>
              <span class="text-muted">Não informado</span>
            <?php endif; ?>
          </td>
          <td><?=e($item['age'])?> / <?=e($sexLabels[$item['sex']] ?? $item['sex'])?></td>
          <td><?=e($item['func'])?></td>
          <td><span class="badge <?=e($statusBadges[$status] ?? 'badge-secondary')?>"><?=e($statusLabels[$status] ?? $status)?></span></td>
          <td style="min-width:230px">
            <?php if ($status === 'approved'): ?>
              <span class="btn btn-sm btn-success mb-1 disabled">Aprovado</span>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-success mb-1" href="?id=<?=$item['id']?>&status=approved">Aprovar</a>
            <?php endif; ?>

            <?php if ($status === 'blocked'): ?>
              <span class="btn btn-sm btn-dark mb-1 disabled">Bloqueado</span>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-dark mb-1" href="?id=<?=$item['id']?>&status=blocked">Bloquear</a>
            <?php endif; ?>

            <?php if ($status === 'rejected'): ?>
              <span class="btn btn-sm btn-danger mb-1 disabled">Recusado</span>
            <?php else: ?>
              <a class="btn btn-sm btn-outline-danger mb-1" href="?id=<?=$item['id']?>&status=rejected">Recusar</a>
            <?php endif; ?>

            <?php $notifyLink = whatsapp_link($item); ?>
            <?php if ($notifyLink): ?>
              <a class="btn btn-sm btn-outline-success mb-1" target="_blank" href="<?=e($notifyLink)?>">WhatsApp</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">Nenhum freelancer cadastrado ainda.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
