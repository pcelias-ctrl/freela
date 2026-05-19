<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

$message = '';
$error = '';
$old = array(
    'full_name' => '',
    'full_address' => '',
    'phone' => '',
    'whatsapp' => '',
    'chavepix' => '',
    'password' => '',
    'password_confirm' => '',
    'age' => '',
    'sex' => 'nao_informar',
    'restaurant_experience' => '',
    'main_function_id' => ''
);

$functions = $pdo->query("SELECT * FROM job_functions WHERE status='active' ORDER BY name")->fetchAll();
$vacanciesStmt = $pdo->query("
    SELECT
        s.*,
        r.name restaurant,
        r.address restaurant_address,
        f.name func,
        (SELECT COUNT(*) FROM shift_applications a WHERE a.shift_id=s.id AND a.status='confirmed') filled
    FROM shifts s
    JOIN restaurants r ON r.id=s.restaurant_id
    JOIN job_functions f ON f.id=s.function_id
    WHERE s.status='open' AND s.shift_date >= CURDATE()
    HAVING filled < vacancies
    ORDER BY s.shift_date, s.start_time
    LIMIT 12
");
$vacancies = $vacanciesStmt->fetchAll();

function upload_error_message($code) {
    $messages = array(
        UPLOAD_ERR_INI_SIZE => 'A foto ultrapassou o limite configurado no servidor.',
        UPLOAD_ERR_FORM_SIZE => 'A foto ultrapassou o limite permitido pelo formulário.',
        UPLOAD_ERR_PARTIAL => 'A foto foi enviada parcialmente. Tente novamente.',
        UPLOAD_ERR_NO_FILE => 'Envie uma foto atualizada para completar o cadastro.',
        UPLOAD_ERR_NO_TMP_DIR => 'O servidor está sem pasta temporária para upload.',
        UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar a foto.',
        UPLOAD_ERR_EXTENSION => 'Uma extensão do servidor bloqueou o envio da foto.'
    );
    return $messages[$code] ?? 'Não foi possível enviar a foto. Tente novamente.';
}

function save_freelancer_photo($file, &$error) {
    if (empty($file['name'])) {
        $error = 'Envie uma foto atualizada para completar o cadastro.';
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = upload_error_message($file['error']);
        return null;
    }

    if ($file['size'] > 8 * 1024 * 1024) {
        $error = 'A foto está muito grande. Envie uma imagem de até 8 MB.';
        return null;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        $error = 'O arquivo enviado não parece ser uma imagem válida.';
        return null;
    }

    $mime = $imageInfo['mime'];
    $allowed = array('image/jpeg', 'image/png', 'image/webp');
    if (!in_array($mime, $allowed)) {
        $error = 'Envie uma foto nos formatos JPG, PNG ou WEBP.';
        return null;
    }
    $extensionByMime = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp');

    $uploadDir = __DIR__ . '/../uploads/freelancers';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $error = 'Não foi possível criar a pasta de fotos no servidor.';
        return null;
    }

    if (!is_writable($uploadDir)) {
        $error = 'A pasta de fotos não tem permissão de escrita.';
        return null;
    }

    $relativeName = 'uploads/freelancers/' . time() . '_' . mt_rand(1000, 9999) . '.jpg';
    $destination = __DIR__ . '/../' . $relativeName;

    if (function_exists('imagecreatetruecolor') && function_exists('imagejpeg')) {
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $source = @imagecreatefromjpeg($file['tmp_name']);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $source = @imagecreatefrompng($file['tmp_name']);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $source = @imagecreatefromwebp($file['tmp_name']);
        } else {
            $source = false;
        }

        if ($source) {
            $width = imagesx($source);
            $height = imagesy($source);
            $maxSize = 1280;
            $scale = min(1, $maxSize / max($width, $height));
            $newWidth = max(1, (int)round($width * $scale));
            $newHeight = max(1, (int)round($height * $scale));

            $target = imagecreatetruecolor($newWidth, $newHeight);
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefilledrectangle($target, 0, 0, $newWidth, $newHeight, $white);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if (imagejpeg($target, $destination, 82)) {
                imagedestroy($source);
                imagedestroy($target);
                return $relativeName;
            }

            imagedestroy($source);
            imagedestroy($target);
        }
    }

    $fallbackName = 'uploads/freelancers/' . time() . '_' . mt_rand(1000, 9999) . '.' . $extensionByMime[$mime];
    if ($file['size'] <= 2 * 1024 * 1024 && move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $fallbackName)) {
        return $fallbackName;
    }

    $error = 'Não foi possível reduzir e salvar a foto. Tente uma imagem menor.';
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array(
        'full_name' => trim($_POST['full_name'] ?? ''),
        'full_address' => trim($_POST['full_address'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'whatsapp' => trim($_POST['whatsapp'] ?? ''),
        'chavepix' => trim($_POST['chavepix'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'age' => trim($_POST['age'] ?? ''),
        'sex' => $_POST['sex'] ?? 'nao_informar',
        'restaurant_experience' => trim($_POST['restaurant_experience'] ?? ''),
        'main_function_id' => $_POST['main_function_id'] ?? ''
    );

    if (strlen($old['password']) < 6) {
        $error = 'Crie uma senha com pelo menos 6 caracteres.';
    } elseif ($old['password'] !== $old['password_confirm']) {
        $error = 'A confirmação de senha não confere.';
    }

    $photoPath = null;
    if (!$error) {
        $photoPath = save_freelancer_photo($_FILES['photo'], $error);
    }

    if (!$error) {
        $hash = password_hash($old['password'], PASSWORD_DEFAULT);
        $pdo->prepare('INSERT INTO freelancers (full_name,full_address,phone,whatsapp,chavepix,password_hash,age,sex,restaurant_experience,main_function_id,photo_path,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,"pending")')
            ->execute(array($old['full_name'], $old['full_address'], $old['phone'], $old['whatsapp'], $old['chavepix'], $hash, $old['age'], $old['sex'], $old['restaurant_experience'], $old['main_function_id'] ?: null, $photoPath));
        $message = 'Cadastro enviado com sucesso. Agora é só aguardar a aprovação para entrar nas vagas.';
        foreach ($old as $key => $value) {
            $old[$key] = $key === 'sex' ? 'nao_informar' : '';
        }
    }
}

$typeLabels = array('almoco'=>'Almoço','jantar'=>'Jantar','evento'=>'Evento');
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Freela | Buscar vagas</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
  <style>
    :root{--ink:#111827;--muted:#667085;--line:#e4e7ec;--brand:#0f766e;--brand-strong:#115e59;--brand-soft:#ccfbf1;--accent:#f97316;--surface:#f8fafc}
    body{background:linear-gradient(180deg,#f8fafc 0,#eef6f5 100%);color:var(--ink);font-size:15px}
    .app-shell{min-height:100vh}
    .topbar{background:linear-gradient(90deg,#0f172a 0,#115e59 100%);color:#fff;box-shadow:0 8px 24px rgba(15,118,110,.18)}
    .topbar a{color:#fff;font-weight:700}
    .brand{font-size:22px;font-weight:800}
    .hero{padding:42px 0 28px}
    .hero h1{font-size:40px;line-height:1.05;font-weight:800;margin:0}
    .hero p{color:#d1fae5;font-size:17px;max-width:760px;margin:12px 0 0}
    .panel{background:#fff;border:1px solid var(--line);border-radius:8px;box-shadow:0 10px 28px rgba(17,24,39,.07)}
    .section-title{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;margin-bottom:14px}
    .section-title h3{font-size:22px;font-weight:800;margin:0}
    .section-title p{color:var(--muted);margin:3px 0 0}
    .vacancy-card{border:1px solid var(--line);border-radius:8px;padding:16px;background:#fff;transition:.15s ease;height:100%;cursor:pointer}
    .vacancy-card:hover,.vacancy-card.is-selected{border-color:var(--brand);box-shadow:0 12px 28px rgba(15,118,110,.12);transform:translateY(-1px)}
    .vacancy-card.is-selected{background:#f0fdfa}
    .badge-soft{background:var(--brand-soft);color:var(--brand-strong);border-radius:6px;padding:5px 8px;font-weight:800;font-size:12px}
    .pay{font-size:26px;font-weight:800;color:var(--brand-strong)}
    .meta{color:var(--muted);font-size:13px}
    .form-control{border-radius:6px;border-color:#d8dde6}
    .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 .2rem rgba(15,118,110,.14)}
    .btn{border-radius:6px;font-weight:800}
    .btn-dark{background:var(--brand);border-color:var(--brand)}
    .btn-dark:hover{background:var(--brand-strong);border-color:var(--brand-strong)}
    .photo-drop{border:1px dashed #98a2b3;border-radius:8px;background:#f8fafc;padding:16px;text-align:center}
    .photo-preview{width:92px;height:92px;border-radius:50%;object-fit:cover;background:#e4e7ec;margin:0 auto 10px;display:none}
    .empty-state{border:1px dashed var(--line);border-radius:8px;padding:24px;color:var(--muted);background:#fff;text-align:center}
    @media(max-width:767.98px){.hero h1{font-size:32px}.section-title{display:block}.vacancy-card{margin-bottom:12px}}
  </style>
</head>
<body>
<div class="app-shell">
  <div class="topbar">
    <div class="container py-3 d-flex align-items-center justify-content-between">
      <div class="brand">Freela</div>
      <div>
        <a class="mr-3" href="<?=app_url('index.php')?>">Início</a>
        <a class="mr-3" href="<?=app_url('public/area.php')?>">Já tenho cadastro</a>
        <a href="<?=app_url('restaurant/login.php')?>">Sou restaurante</a>
      </div>
    </div>
    <div class="container hero">
      <h1>Encontre vagas em restaurantes, eventos e operações de cozinha.</h1>
      <p>Veja oportunidades abertas, escolha sua função principal e envie um cadastro completo para aprovação.</p>
    </div>
  </div>

  <main class="container py-4">
    <?php if($message): ?><div class="alert alert-success"><?=h($message)?></div><?php endif; ?>
    <?php if($error): ?><div class="alert alert-danger"><?=h($error)?></div><?php endif; ?>

    <div class="row">
      <div class="col-lg-7 mb-4">
        <div class="section-title">
          <div>
            <h3>Vagas abertas</h3>
            <p>Valores e disponibilidade atualizados com base nas vagas cadastradas.</p>
          </div>
          <span class="badge-soft"><?=count($vacancies)?> disponíveis</span>
        </div>

        <div class="row" id="vacancyList">
          <?php foreach($vacancies as $vacancy): $available = (int)$vacancy['vacancies'] - (int)$vacancy['filled']; ?>
            <div class="col-md-6 mb-3">
              <div class="vacancy-card" data-function-id="<?=h($vacancy['function_id'])?>" data-title="<?=h($vacancy['func'])?>" data-restaurant="<?=h($vacancy['restaurant'])?>">
                <div class="d-flex align-items-start justify-content-between mb-2">
                  <span class="badge-soft"><?=h($typeLabels[$vacancy['shift_type']] ?? $vacancy['shift_type'])?></span>
                  <span class="meta"><?=date('d/m/Y', strtotime($vacancy['shift_date']))?></span>
                </div>
                <h5 class="mb-1"><?=h($vacancy['func'])?></h5>
                <div class="meta mb-3"><?=h($vacancy['restaurant'])?></div>
                <div class="pay mb-2">R$ <?=number_format($vacancy['pay_value'], 2, ',', '.')?></div>
                <div class="meta"><?=substr($vacancy['start_time'],0,5)?> às <?=substr($vacancy['end_time'],0,5)?> · <?=$available?> vaga<?=$available === 1 ? '' : 's'?> livre<?=$available === 1 ? '' : 's'?></div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if(!$vacancies): ?>
            <div class="col-12"><div class="empty-state">Ainda não há vagas abertas. Faça seu cadastro para entrar na base de aprovação.</div></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5 mb-4">
        <div class="panel p-4">
          <div class="section-title">
            <div>
              <h3>Seu cadastro</h3>
              <p>Dados completos aumentam a chance de aprovação.</p>
            </div>
          </div>
          <form method="post" enctype="multipart/form-data" id="registerForm">
            <input type="hidden" name="MAX_FILE_SIZE" value="8388608">
            <div class="photo-drop mb-3">
              <img id="photoPreview" class="photo-preview" alt="Prévia da foto">
              <strong>Foto atualizada</strong>
              <p class="text-muted mb-2">Use uma foto nítida de rosto ou meio corpo.</p>
              <input class="form-control-file" name="photo" id="photoInput" type="file" accept="image/*" required>
            </div>

            <label>Nome completo</label>
            <input class="form-control mb-2" name="full_name" required value="<?=h($old['full_name'])?>">

            <div class="form-row">
              <div class="col-md-6 mb-2">
                <label>Telefone</label>
                <input class="form-control" name="phone" required value="<?=h($old['phone'])?>">
              </div>
              <div class="col-md-6 mb-2">
                <label>WhatsApp</label>
                <input class="form-control" name="whatsapp" required value="<?=h($old['whatsapp'])?>">
              </div>
            </div>

            <label>Chave Pix para pagamento</label>
            <input class="form-control mb-2" name="chavepix" maxlength="200" required value="<?=h($old['chavepix'])?>" placeholder="CPF, e-mail, telefone ou chave aleatória">
            <small class="d-block text-muted mb-2">Essa chave será usada pelo admin para organizar os pagamentos das diárias.</small>

            <div class="form-row">
              <div class="col-md-6 mb-2">
                <label>Senha de acesso</label>
                <input class="form-control" name="password" type="password" minlength="6" required>
              </div>
              <div class="col-md-6 mb-2">
                <label>Confirmar senha</label>
                <input class="form-control" name="password_confirm" type="password" minlength="6" required>
              </div>
            </div>

            <div class="form-row">
              <div class="col-md-5 mb-2">
                <label>Idade</label>
                <input class="form-control" name="age" type="number" min="16" required value="<?=h($old['age'])?>">
              </div>
              <div class="col-md-7 mb-2">
                <label>Sexo</label>
                <select class="form-control" name="sex">
                  <option value="nao_informar" <?=$old['sex'] === 'nao_informar' ? 'selected' : ''?>>Prefiro não informar</option>
                  <option value="feminino" <?=$old['sex'] === 'feminino' ? 'selected' : ''?>>Feminino</option>
                  <option value="masculino" <?=$old['sex'] === 'masculino' ? 'selected' : ''?>>Masculino</option>
                  <option value="outro" <?=$old['sex'] === 'outro' ? 'selected' : ''?>>Outro</option>
                </select>
              </div>
            </div>

            <label>Função principal</label>
            <select class="form-control mb-2" name="main_function_id" id="mainFunction">
              <option value="">Selecione</option>
              <?php foreach($functions as $function): ?>
                <option value="<?=$function['id']?>" <?=$old['main_function_id'] == $function['id'] ? 'selected' : ''?>><?=h($function['name'])?></option>
              <?php endforeach; ?>
            </select>

            <label>Endereço completo</label>
            <textarea class="form-control mb-2" name="full_address" required rows="2"><?=h($old['full_address'])?></textarea>

            <label>Experiência com restaurante</label>
            <textarea class="form-control mb-3" name="restaurant_experience" id="experience" rows="4" placeholder="Conte onde já trabalhou, quais funções já fez e sua disponibilidade."><?=h($old['restaurant_experience'])?></textarea>

            <button class="btn btn-dark btn-block py-2">Enviar cadastro para aprovação</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
document.querySelectorAll('.vacancy-card').forEach(function(card) {
  card.addEventListener('click', function() {
    document.querySelectorAll('.vacancy-card').forEach(function(item) { item.classList.remove('is-selected'); });
    card.classList.add('is-selected');
    document.getElementById('mainFunction').value = card.dataset.functionId;
    var experience = document.getElementById('experience');
    var note = 'Tenho interesse em vaga de ' + card.dataset.title + ' no restaurante ' + card.dataset.restaurant + '. ';
    if (experience.value.indexOf(note) === -1) {
      experience.value = note + experience.value;
    }
    document.getElementById('registerForm').scrollIntoView({behavior: 'smooth', block: 'start'});
  });
});

document.getElementById('photoInput').addEventListener('change', function(event) {
  var file = event.target.files[0];
  if (!file) return;
  var preview = document.getElementById('photoPreview');
  var reader = new FileReader();
  reader.onload = function(loadEvent) {
    var image = new Image();
    image.onload = function() {
      var maxSize = 1280;
      var scale = Math.min(1, maxSize / Math.max(image.width, image.height));
      var canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(image.width * scale));
      canvas.height = Math.max(1, Math.round(image.height * scale));
      var context = canvas.getContext('2d');
      context.fillStyle = '#fff';
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(image, 0, 0, canvas.width, canvas.height);
      canvas.toBlob(function(blob) {
        if (blob && window.DataTransfer) {
          var resizedFile = new File([blob], 'foto-freela.jpg', {type: 'image/jpeg'});
          var dataTransfer = new DataTransfer();
          dataTransfer.items.add(resizedFile);
          event.target.files = dataTransfer.files;
          preview.src = URL.createObjectURL(resizedFile);
        } else {
          preview.src = loadEvent.target.result;
        }
        preview.style.display = 'block';
      }, 'image/jpeg', 0.82);
    };
    image.src = loadEvent.target.result;
  };
  reader.readAsDataURL(file);
});
</script>
</body>
</html>
