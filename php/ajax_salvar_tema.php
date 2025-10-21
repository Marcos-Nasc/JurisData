<?php
// Garantir que a sessão seja iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resposta padrão
$response = ['status' => 'error', 'message' => 'Ocorreu um erro desconhecido.'];

// 1. Incluir dependências
// Use caminhos relativos corretos
require_once '../includes/conexao.php'; 
require_once '../includes/funcoes.php'; // Para registrar_log, se necessário

// 2. Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Usuário não autenticado.';
    echo json_encode($response);
    exit;
}

// 3. Pegar e validar o tema enviado pelo JavaScript
// O seu JS está usando FormData, então pegamos via $_POST
$novoTema = $_POST['tema'] ?? null;

if (!in_array($novoTema, ['light', 'dark'])) {
    $response['message'] = 'Valor de tema inválido.';
    echo json_encode($response);
    exit;
}

// 4. Este é o UPDATE que faltava!
try {
    $idUsuarioLogado = $_SESSION['user_id'];

    $sql = "UPDATE usuarios SET preferencia_tema = :tema WHERE id = :id_usuario";
    $stmt = $pdo->prepare($sql);
    
    $stmt->bindParam(':tema', $novoTema);
    $stmt->bindParam(':id_usuario', $idUsuarioLogado);
    
    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'Tema salvo com sucesso.';
        
        // Opcional: Registrar o log (já que você tem a função)
        $detalhes_log = "Usuário alterou seu tema para: " . $novoTema;
        registrar_log($pdo, 'Alteração de Tema', $idUsuarioLogado, $detalhes_log);
        
    } else {
        $response['message'] = 'Falha ao executar o update no banco.';
    }

} catch (PDOException $e) {
    $response['message'] = 'Erro de Banco de Dados: ' . $e->getMessage();
    // Em produção, você deve logar $e->getMessage() em vez de exibi-lo
}

// 5. Enviar resposta JSON de volta para o JavaScript
header('Content-Type: application/json');
echo json_encode($response);