<?php
/**
 * index.php — Aplicação PHP para Atividade 4.1 de Computação em Nuvem II.
 * 
 * Esta aplicação demonstra a integração prática com serviços gerenciados de nuvem:
 * 1. Google Cloud Storage: Armazenamento, Listagem, Exclusão, Preview e Download.
 * 2. Cloud SQL (MySQL): Persistência de dados estruturados com Ciclo CRUD Completo.
 */

require __DIR__ . '/vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;
use Dotenv\Dotenv;

// === Gerenciamento de Configurações (.env) ===
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/');
    $dotenv->load();
}

function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// === Conexão com o Banco de Dados (Cloud SQL) ===
$db_host = env('DB_HOST', 'localhost');
$db_name = env('DB_NAME', 'app_projeto');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASSWORD', 'root_password');

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $db_error = "Erro ao conectar ao MySQL Cloud: " . $e->getMessage();
}

// === Configuração do Google Cloud Storage ===
$projectId = env('GCP_PROJECT_ID');
$bucketName = env('GCP_BUCKET_NAME');
$keyFilePath = env('GOOGLE_APPLICATION_CREDENTIALS');

$storage = null;
$bucket = null;
$storage_error = null;

if ($bucketName && file_exists(__DIR__ . '/' . $keyFilePath)) {
    try {
        $storage = new StorageClient([
            'projectId' => $projectId,
            'keyFilePath' => __DIR__ . '/' . $keyFilePath
        ]);
        $bucket = $storage->bucket($bucketName);
    } catch (Exception $e) {
        $storage_error = "Erro na configuração do Storage: " . $e->getMessage();
    }
}

// === PROXY DE STREAMING (Preview e Download) ===
// Esta lógica atua como um intermediário para exibir arquivos privados da nuvem.
if (isset($_GET['proxy_file']) && $bucket) {
    try {
        $filename = $_GET['proxy_file'];
        $object = $bucket->object($filename);
        
        if (!$object->exists()) {
            die("Arquivo não encontrado na nuvem.");
        }

        $info = $object->info();
        $contentType = $info['contentType'] ?? 'application/octet-stream';
        $disposition = isset($_GET['download']) ? 'attachment' : 'inline';

        header("Content-Type: $contentType");
        header("Content-Disposition: $disposition; filename=\"$filename\"");
        
        // Faz o download do stream diretamente para a saída do PHP
        echo $object->downloadAsString();
        exit;
    } catch (Exception $e) {
        die("Erro ao processar arquivo: " . $e->getMessage());
    }
}

// === Lógica de Operações CRUD e Storage ===
$message = "";

if (isset($pdo)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_db'])) {
        try {
            $nome = $_POST['nome'];
            $desc = $_POST['descricao'];
            $preco = $_POST['preco'];
            $cat = $_POST['categoria'];

            if ($_POST['action_db'] === 'create') {
                $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, categoria) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nome, $desc, $preco, $cat]);
                $message = "Produto '$nome' cadastrado com sucesso!";
            } elseif ($_POST['action_db'] === 'update') {
                $stmt = $pdo->prepare("UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=? WHERE id=?");
                $stmt->execute([$nome, $desc, $preco, $cat, $_POST['id']]);
                $message = "Produto atualizado com sucesso!";
            }
        } catch (Exception $e) { $message = "Erro DB: " . $e->getMessage(); }
    }
    if (isset($_GET['db_delete'])) {
        $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
        $stmt->execute([$_GET['db_delete']]);
        $message = "Produto removido.";
    }
}

if ($bucket) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo'])) {
        try {
            $file = fopen($_FILES['arquivo']['tmp_name'], 'r');
            $bucket->upload($file, ['name' => $_FILES['arquivo']['name']]);
            $message = "Upload concluído!";
        } catch (Exception $e) { $message = "Erro Upload: " . $e->getMessage(); }
    }
    if (isset($_GET['delete'])) {
        $bucket->object($_GET['delete'])->delete();
        $message = "Arquivo excluído.";
    }
}

// === Dados para View ===
$produtos = isset($pdo) ? $pdo->query("SELECT * FROM produtos ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
$fileList = $bucket ? array_map(fn($o) => $o->name(), iterator_to_array($bucket->objects())) : [];
$editRecord = (isset($_GET['db_edit']) && isset($pdo)) ? $pdo->prepare("SELECT * FROM produtos WHERE id = ?") : null;
if ($editRecord) { $editRecord->execute([$_GET['db_edit']]); $editRecord = $editRecord->fetch(PDO::FETCH_ASSOC); }
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cloud Console - Full Integration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #4285f4; --secondary: #34a853; --error: #ea4335; --bg: #fdfdfd; --text-main: #202124; --text-muted: #5f6368; --border: #dadce0; --card-bg: #ffffff; --shadow: 0 4px 12px rgba(0,0,0,0.08); }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); color: var(--text-main); line-height: 1.6; padding: 40px 20px; }
        .container { max-width: 1000px; margin: auto; }
        header { text-align: center; margin-bottom: 50px; }
        header h1 { font-size: 2.2rem; font-weight: 700; color: var(--primary); }
        .card { background: var(--card-bg); border-radius: 16px; box-shadow: var(--shadow); padding: 32px; margin-bottom: 40px; border: 1px solid var(--border); }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; border-bottom: 2px solid #f1f3f4; padding-bottom: 12px; }
        .alert { padding: 16px; border-radius: 12px; margin-bottom: 24px; font-weight: 500; border: 1px solid transparent; background-color: #e6f4ea; color: #137333; }
        
        table { width: 100%; border-collapse: collapse; }
        table th { background: #f8f9fa; text-align: left; padding: 12px; font-size: 0.85rem; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        table td { padding: 14px 12px; border-bottom: 1px solid #f1f3f4; font-size: 0.9rem; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 8px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.2s; border: none; font-size: 0.85rem; text-decoration: none; }
        .btn-icon { width: 34px; height: 34px; padding: 0; border-radius: 50%; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: var(--secondary); color: white; }
        .btn-danger { background: #fce8e6; color: var(--error); }
        .btn-edit { background: #e8f0fe; color: var(--primary); margin-right: 5px; }
        .badge-cat { padding: 4px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #e8f0fe; color: var(--primary); }

        /* Modais */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; backdrop-filter: blur(4px); }
        .modal-content { background: #fff; padding: 32px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); animation: slideUp 0.3s; position: relative; }
        .modal-large { max-width: 850px; width: 90%; height: 85vh; display: flex; flex-direction: column; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .close-modal { position: absolute; top: 20px; right: 20px; cursor: pointer; font-size: 1.5rem; color: var(--text-muted); background: none; border: none; }

        .preview-body { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; overflow: hidden; background: #f1f3f4; border-radius: 8px; margin: 20px 0; }
        .preview-body img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .preview-body iframe { width: 100%; height: 100%; border: none; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 600; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; }
    </style>
</head>
<body>

<!-- Modal Banco de Dados -->
<div id="productModal" class="modal-overlay" <?php echo $editRecord ? 'style="display:flex;"' : ''; ?>>
    <div class="modal-content">
        <button class="close-modal" onclick="closeModal('productModal')">&times;</button>
        <h3><?php echo $editRecord ? '✏️ Editar Produto' : '📦 Novo Produto'; ?></h3>
        <form action="index.php" method="POST" style="margin-top:20px;">
            <input type="hidden" name="action_db" value="<?php echo $editRecord ? 'update' : 'create'; ?>">
            <?php if ($editRecord): ?><input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>"><?php endif; ?>
            <div class="form-group"><label>Nome</label><input type="text" name="nome" value="<?php echo $editRecord['nome'] ?? ''; ?>" required></div>
            <div class="form-group"><label>Descrição</label><input type="text" name="descricao" value="<?php echo $editRecord['descricao'] ?? ''; ?>"></div>
            <div class="form-group"><label>Preço</label><input type="number" step="0.01" name="preco" value="<?php echo $editRecord['preco'] ?? ''; ?>" required></div>
            <div class="form-group"><label>Categoria</label><input type="text" name="categoria" value="<?php echo $editRecord['categoria'] ?? ''; ?>"></div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                <button type="button" class="btn" onclick="closeModal('productModal')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?php echo $editRecord ? 'Salvar' : 'Cadastrar'; ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Preview Storage -->
<div id="previewModal" class="modal-overlay">
    <div class="modal-content modal-large">
        <button class="close-modal" onclick="closeModal('previewModal')">&times;</button>
        <h3 id="previewTitle">📄 Pré-visualização</h3>
        <div id="previewBody" class="preview-body">
            <!-- Conteúdo dinâmico via JS -->
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <span id="previewInfo" style="font-size:0.85rem; color:var(--text-muted);">Detectando tipo de arquivo...</span>
            <a id="downloadBtn" href="#" class="btn btn-secondary"><i class="fas fa-download"></i> &nbsp; Baixar Arquivo</a>
        </div>
    </div>
</div>

<div class="container">
    <header>
        <h1>Cloud Data & Storage Control</h1>
        <p>Cassia Voltolin • Atividade 4.1 — GCP Integration</p>
    </header>

    <?php if ($message): ?><div class="alert"><?php echo $message; ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>📊 Banco de Dados (Cloud SQL)</h2>
            <button onclick="document.getElementById('productModal').style.display='flex'" class="btn btn-primary"><i class="fas fa-plus"></i> &nbsp; Novo Produto</button>
        </div>
        <table>
            <thead><tr><th>ID</th><th>Produto</th><th>Preço</th><th>Ações</th></tr></thead>
            <tbody>
                <?php foreach ($produtos as $p): ?>
                <tr>
                    <td>#<?php echo $p['id']; ?></td>
                    <td><strong><?php echo $p['nome']; ?></strong> &nbsp; <span class="badge-cat"><?php echo $p['categoria'] ?: 'Geral'; ?></span><br/><small style="color:#888;"><?php echo $p['descricao']; ?></small></td>
                    <td>R$ <?php echo number_format($p['preco'], 2, ',', '.'); ?></td>
                    <td>
                        <a href="?db_edit=<?php echo $p['id']; ?>" class="btn btn-edit btn-icon"><i class="fas fa-edit"></i></a>
                        <a href="?db_delete=<?php echo $p['id']; ?>" onclick="return confirm('Excluir?')" class="btn btn-danger btn-icon"><i class="fas fa-trash"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header"><h2>☁️ Cloud Storage (Buckets)</h2></div>
        <form action="index.php" method="POST" enctype="multipart/form-data" style="margin-bottom:30px; text-align:center; background:#f8f9fa; padding:25px; border-radius:12px; border:2px dashed var(--border);">
            <input type="file" name="arquivo" required style="margin-bottom:15px; display:block; margin-left:auto; margin-right:auto;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> &nbsp; Upload para Nuvem</button>
        </form>
        <div class="file-list">
            <?php foreach ($fileList as $fileName): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border); padding:12px; border-radius:10px; margin-bottom:8px;">
                <span><i class="far fa-file-alt" style="color:var(--primary); margin-right:8px;"></i> <?php echo $fileName; ?></span>
                <div style="display:flex; gap:8px;">
                    <button onclick="openPreview('<?php echo $fileName; ?>')" class="btn btn-edit btn-icon" title="Visualizar"><i class="fas fa-eye"></i></button>
                    <a href="?delete=<?php echo urlencode($fileName); ?>" onclick="return confirm('Excluir?')" class="btn btn-danger btn-icon" title="Remover"><i class="fas fa-times"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
        if (id === 'productModal' && window.location.search.includes('db_edit')) window.location.href = 'index.php';
    }

    function openPreview(filename) {
        const modal = document.getElementById('previewModal');
        const title = document.getElementById('previewTitle');
        const body = document.getElementById('previewBody');
        const info = document.getElementById('previewInfo');
        const dlBtn = document.getElementById('downloadBtn');
        
        title.innerText = "📄 " + filename;
        dlBtn.href = "index.php?proxy_file=" + encodeURIComponent(filename) + "&download=1";
        body.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="font-size:2rem; color:var(--primary);"></i>';
        modal.style.display = 'flex';

        const ext = filename.split('.').pop().toLowerCase();
        const proxyUrl = "index.php?proxy_file=" + encodeURIComponent(filename);

        if (['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'].includes(ext)) {
            body.innerHTML = `<img src="${proxyUrl}" alt="Preview">`;
            info.innerText = "Tipo: Imagem";
        } else if (ext === 'pdf') {
            body.innerHTML = `<iframe src="${proxyUrl}"></iframe>`;
            info.innerText = "Tipo: Documento PDF";
        } else {
            body.innerHTML = '<div style="text-align:center;"><i class="fas fa-eye-slash" style="font-size:3rem; color:#ccc; margin-bottom:15px;"></i><br/>Pré-visualização não disponível para este tipo de arquivo.</div>';
            info.innerText = "Tipo: " + ext.toUpperCase();
        }
    }

    window.onclick = function(e) { if (e.target.className === 'modal-overlay') e.target.style.display = 'none'; }
</script>
</body>
</html>
