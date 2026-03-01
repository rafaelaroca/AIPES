<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['codigo'])) {
    echo json_encode(['sucesso' => false, 'erro' => 'Dados inválidos ou código vazio.']);
    exit;
}

$codigo = $data['codigo'];
$historico = isset($data['historico']) ? $data['historico'] : '';
$projeto_id = isset($data['projeto_id']) && !empty($data['projeto_id']) ? $data['projeto_id'] : null;

// Define a pasta onde os apps estáticos ficarão hospedados
$pasta_apps = __DIR__ . '/../apps';

// Cria a pasta automaticamente se ela não existir
if (!is_dir($pasta_apps)) {
    mkdir($pasta_apps, 0755, true);
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($projeto_id) {
        // Atualiza projeto existente
        $stmt = $pdo->prepare("UPDATE projetos_pwa SET codigo = ?, historico = ? WHERE id = ?");
        $stmt->execute([$codigo, $historico, $projeto_id]);
    } else {

        // Gera um ID único curto e amigável (ex: a1b2c3d4)
        $projeto_id = substr(md5(uniqid(rand(), true)), 0, 8);

        // Insere novo projeto
        $stmt = $pdo->prepare("INSERT INTO projetos_pwa (id, codigo, historico) VALUES (?, ?, ?)");
        $stmt->execute([$projeto_id, $codigo, $historico]);

    }

    // ==========================================
    // MAGIA AQUI: Salva o código como um arquivo HTML real
    // ==========================================
    $caminho_arquivo = $pasta_apps . '/' . $projeto_id . '.html';

    // Salva o conteúdo. Se falhar, avisa.
    if (file_put_contents($caminho_arquivo, $codigo) === false) {
        echo json_encode(['sucesso' => false, 'erro' => 'Projeto salvo no banco, mas falha ao gravar arquivo HTML. Verifique permissões da pasta.']);
        exit;
    }

    // Monta a URL pública do App gerado dinamicamente
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $dominio = $_SERVER['HTTP_HOST'];
    $pasta_base = dirname(dirname($_SERVER['REQUEST_URI'])); // Volta uma pasta a partir de /api/

    // Limpa barras duplas que possam surgir
    $pasta_base = rtrim($pasta_base, '/');

    $url_app = $protocol . $dominio . $pasta_base . '/apps/' . $projeto_id . '.html';

    echo json_encode([
        'sucesso' => true,
        'id' => $projeto_id,
        'url_app' => $url_app // Retorna a URL do arquivo estático para o frontend
    ]);

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro de banco de dados: ' . $e->getMessage()]);
}
?>


  
