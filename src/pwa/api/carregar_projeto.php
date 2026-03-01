<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$id) {
    echo json_encode(['sucesso' => false, 'erro' => 'ID não fornecido.']);
    exit;
}

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("SELECT codigo, historico FROM projetos_pwa WHERE id = ?");
    $stmt->execute([$id]);
    $projeto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($projeto) {
        echo json_encode([
            'sucesso' => true,
            'dados' => [
                'codigo' => $projeto['codigo'],
                'historico_chat' => $projeto['historico']
            ]
        ]);
    } else {
        echo json_encode(['sucesso' => false, 'erro' => 'Projeto PWA não encontrado.']);
    }

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Erro de banco de dados: ' . $e->getMessage()]);
}
?>
