<?php
require_once __DIR__ . '/../../config.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// ==========================================
// 🛡️ RATE LIMITER - PREPARADO PARA SAAS
// ==========================================

// 1. Identificação do Usuário (Troque por $_SESSION['user_id'] no futuro)
$identificador = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];

// 2. Definição das Regras de Negócio (Limites dos Planos)
$limites = [
    'free' => ['minuto' => 3, 'dia' => 15],   // Plano Gratuito: 3 por min, máx 15 por dia
    'pro'  => ['minuto' => 15, 'dia' => 200]  // Plano Pago: 15 por min, máx 200 por dia
];

try {

    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
    } catch (PDOException $e) {
        die("Erro na conexão: " . $e->getMessage());
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Busca o registro atual do usuário
    $stmt = $pdo->prepare("SELECT plano, req_minuto, req_dia, ultima_req_minuto, ultima_req_dia FROM ai_rate_limit WHERE identifica
dor = ?");
    $stmt->execute([$identificador]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    $agora_minuto = date('Y-m-d H:i:s');
    $hoje = date('Y-m-d');

      if ($usuario) {
        $plano = $usuario['plano'];

        // Se o plano não existir no array (caso de erro), força para 'free'
        if (!array_key_exists($plano, $limites)) $plano = 'free';

        $limite_minuto = $limites[$plano]['minuto'];
        $limite_dia = $limites[$plano]['dia'];

        // Lógica de reset do tempo
        // Se passou mais de 60 segundos, zera a contagem do minuto
        $passou_um_minuto = (strtotime($agora_minuto) - strtotime($usuario['ultima_req_minuto'])) > 60;
        $novo_req_minuto = $passou_um_minuto ? 1 : $usuario['req_minuto'] + 1;

        // Se mudou o dia, zera a contagem diária
        $mudou_o_dia = ($hoje != $usuario['ultima_req_dia']);
        $novo_req_dia = $mudou_o_dia ? 1 : $usuario['req_dia'] + 1;

        // VERIFICAÇÃO DE BLOQUEIOS
        if (!$mudou_o_dia && $usuario['req_dia'] >= $limite_dia) {
            echo json_encode(['sucesso' => false, 'erro' => "Daily limit reached. Please upgrade your plan. Limite diário atingido ({$limite_dia} requisições). Faça upgrade para o plano Pro para continuar usando hoje!"]);
            exit;
        }

        if (!$passou_um_minuto && $usuario['req_minuto'] >= $limite_minuto) {
            echo json_encode(['sucesso' => false, 'erro' => 'Too many requests. Please wait a moment and try again. Sorry...']);
            exit;
        }

        // Se passou pelas travas, atualiza o banco
        $stmt_update = $pdo->prepare("
            UPDATE ai_rate_limit
            SET req_minuto = ?, req_dia = ?,
                ultima_req_minuto = ?, ultima_req_dia = ?
            WHERE identificador = ?
        ");
      $stmt_update->execute([
            $novo_req_minuto,
            $novo_req_dia,
            $passou_um_minuto ? $agora_minuto : $usuario['ultima_req_minuto'],
            $hoje,
            $identificador
        ]);

    } else {
        // Novo usuário (Primeira vez acessando)
        $stmt_insert = $pdo->prepare("
            INSERT INTO ai_rate_limit (identificador, plano, req_minuto, req_dia, ultima_req_minuto, ultima_req_dia)
            VALUES (?, 'free', 1, 1, ?, ?)
        ");
        $stmt_insert->execute([$identificador, $agora_minuto, $hoje]);
    }

} catch (PDOException $e) {
    echo json_encode(['sucesso' => false, 'erro' => 'Internal security error.']);
    exit;
}

//If we get here, the user is authorized to use GEMINI API

$log_file = __DIR__ . '/gemini_log_pwa.txt'; // Nome de log alterado para evitar conflito
function registrarLog($mensagem) {
    global $log_file;
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] $mensagem\n", FILE_APPEND);
}

$apiKey = GOOGLE_API_KEY;
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=' . $apiKey;

$dados = json_decode(file_get_contents('php://input'), true);
$prompt_usuario = $dados['prompt'] ?? '';
$codigo_atual = $dados['codigo_atual'] ?? '';

if (empty($prompt_usuario)) {
 echo json_encode(['sucesso' => false, 'erro' => 'Prompt não fornecido.']);
    exit;
}

registrarLog("=== INICIANDO REQUISIÇÃO PWA ===");
registrarLog("Prompt: " . $prompt_usuario);

// ==========================================
// CONTEXTO DE ENGENHARIA DE PROMPT PWA
// ==========================================
$prompt_sistema = "Você é um Desenvolvedor Front-end Sênior especialista em Progressive Web Apps (PWA), UI/UX e JavaScript Vanilla.
Sua missão é criar ou atualizar aplicações web responsivas e otimizadas para dispositivos móveis (Mobile First).

REGRAS OBRIGATÓRIAS:
1. O código gerado deve ser sempre um ÚNICO arquivo HTML (<!DOCTYPE html>) contendo todo o CSS na tag <style> e o JavaScript na tag <script>.
2. Sempre inclua a tag: <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no\">
3. O design (CSS) deve ser moderno, utilizando Flexbox/Grid, com componentes touch-friendly.
4. NUNCA gere arquivos separados.

Retorne sua resposta usando EXATAMENTE as tags <explicacao> e <codigo>

<explicacao>
Texto em português explicando as mudanças. Use formatação HTML (<b>, <br>).
</explicacao>
<codigo>
<!DOCTYPE html>
</codigo>

Código atual:\n" . $codigo_atual . "\n\nPedido do usuário:\n" . $prompt_usuario;

$payload = ["contents" => [["parts" => [["text" => $prompt_sistema]]]]];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 40);

// Executa e pega os dados técnicos
$resposta = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

registrarLog("HTTP Status Code: " . $http_code);

if ($resposta === false) {
    registrarLog("FALHA cURL: " . $curl_error);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro interno (cURL): ' . $curl_error]);
    exit;
}

registrarLog("Resposta Bruta API Google:\n" . $resposta);

$resultado = json_decode($resposta, true);

if (isset($resultado['error'])) {
    $msg_erro = $resultado['error']['message'] ?? 'Erro desconhecido';
    registrarLog("ERRO RETORNADO PELA API: " . $msg_erro);
    echo json_encode(['sucesso' => false, 'erro' => 'Erro na IA: ' . $msg_erro]);
    exit;
}

$resposta_texto = $resultado['candidates'][0]['content']['parts'][0]['text'] ?? '';

$resposta_texto = $resultado['candidates'][0]['content']['parts'][0]['text'] ?? '';

if (empty($resposta_texto)) {
    registrarLog("ERRO: IA retornou resposta vazia (possível bloqueio de segurança).");
    echo json_encode(['sucesso' => false, 'erro' => 'A IA retornou um texto vazio.']);
    exit;
}

// Extração da Explicação
preg_match('/<explicacao>(.*?)<\/explicacao>/is', $resposta_texto, $matches_exp);
$explicacao = isset($matches_exp[1]) ? trim($matches_exp[1]) : "Aqui está o seu PWA atualizado.";

// Extração do Código (Agora aceita tanto <codigo> quanto <code>)
if (preg_match('/<codigo>(.*?)<\/codigo>/is', $resposta_texto, $matches_cod)) {
    $codigo = trim($matches_cod[1]);
} elseif (preg_match('/<code>(.*?)<\/code>/is', $resposta_texto, $matches_cod)) {
    $codigo = trim($matches_cod[1]);
} else {
    $codigo = "";
}

// Fallback de emergência (agora adaptado para remover markdown de html/xml/js/css)
if (empty($codigo)) {
    $codigo = preg_replace('/^```(html|xml|javascript|js|css)?|```$/im', '', trim($resposta_texto));
}

registrarLog("SUCESSO! Extração concluída. Código: " . strlen($codigo) . " bytes.");

// Retorno JSON formatado da mesma forma que o frontend original espera
echo json_encode([
    'sucesso' => true,
    'codigo' => trim($codigo),
    'explicacao' => nl2br($explicacao)
]);
?>



  
