<?php
// 1. Inicia a sessão.
session_start();

// Se o usuário já estiver logado, redireciona para o index (dashboard)
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php"); // CORREÇÃO: Aponta para o dashboard
    exit();
}

// Inclui a conexão da pasta 'includes'
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass

// Variáveis de mensagem
$erro = '';
$sucesso = '';

// Mensagens de feedback (via URL)
if (isset($_GET['erro']) && $_GET['erro'] == 'acesso_restrito') {
    $erro = "Acesso negado. Por favor, faça o login para continuar.";
}
if (isset($_GET['status']) && $_GET['status'] == 'logout_sucesso') {
    $sucesso = "Você saiu do sistema com sucesso!";
}

// 2. Verifica se o formulário foi enviado
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $senha = trim($_POST['senha']);

    // --- INÍCIO - CAPTURA O IP DO USUÁRIO ---
    // Pega o IP do usuário para registrar no log
    $ip_usuario = $_SERVER['REMOTE_ADDR'];
    // --- FIM - CAPTURA O IP DO USUÁRIO ---

    if (empty($email) || empty($senha)) {
        $erro = "Por favor, preencha o e-mail e a senha.";
    } else {
        try {
            $sql = "SELECT id, nome, senha, cargo, nivel, ultimo_login FROM usuarios WHERE email = :email"; // Adicionei ultimo_login aqui
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $usuario = $stmt->fetch();

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // Login bem-sucedido. Armazena na sessão.
                $_SESSION['user_id'] = $usuario['id']; 
                $_SESSION['user_nivel'] = $usuario['nivel']; // Importante para o modo manutenção
                $_SESSION['nome_usuario'] = $usuario['nome'];
                $_SESSION['cargo_usuario'] = $usuario['cargo']; 
                $_SESSION['ultimo_login'] = $usuario['ultimo_login'];

                // --- INÍCIO - REGISTRO DE LOG (SUCESSO) ---
                try {
                    $sql_log = "INSERT INTO logs (id_usuario, acao, detalhes, ip_usuario) 
                                VALUES (:id_usuario, :acao, :detalhes, :ip_usuario)";
                    $stmt_log = $pdo->prepare($sql_log);
                    
                    $acao = "Login Bem-Sucedido";
                    $detalhes = "Usuário '" . $usuario['nome'] . "' (ID: " . $usuario['id'] . ") logou com sucesso.";
                    
                    $stmt_log->bindParam(':id_usuario', $usuario['id']);
                    $stmt_log->bindParam(':acao', $acao);
                    $stmt_log->bindParam(':detalhes', $detalhes);
                    $stmt_log->bindParam(':ip_usuario', $ip_usuario);
                    $stmt_log->execute();
                } catch (PDOException $e) {
                    // Falha ao registrar o log, mas não impede o login do usuário.
                    // Apenas registra o erro no log do PHP/servidor.
                    error_log("Erro ao registrar log de SUCESSO de login: " . $e->getMessage()); 
                }
                // --- FIM - REGISTRO DE LOG (SUCESSO) ---

                header("Location: ../index.php");
                exit();
            } else {
                // --- CORREÇÃO DE BUG ---
                // Você estava definindo $erro_login, mas o HTML exibe $erro
                $erro = "Usuário ou senha inválidos.";
                // --- FIM DA CORREÇÃO ---

                // --- INÍCIO - REGISTRO DE LOG (FALHA) ---
                try {
                    // Se o usuário foi encontrado (e-mail existe), pegamos o ID. Se não (e-mail não existe), o ID é null.
                    $id_usuario_log = $usuario ? $usuario['id'] : null; 
                    
                    $sql_log = "INSERT INTO logs (id_usuario, acao, detalhes, ip_usuario) 
                                VALUES (:id_usuario, :acao, :detalhes, :ip_usuario)";
                    $stmt_log = $pdo->prepare($sql_log);
                    
                    $acao = "Falha no Login";
                    $detalhes = "Tentativa de login falha para o e-mail: '" . $email . "'.";
                    
                    // PDO::PARAM_NULL é importante se $id_usuario_log for null
                    $stmt_log->bindParam(':id_usuario', $id_usuario_log, $id_usuario_log === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $stmt_log->bindParam(':acao', $acao);
                    $stmt_log->bindParam(':detalhes', $detalhes);
                    $stmt_log->bindParam(':ip_usuario', $ip_usuario);
                    $stmt_log->execute();
                } catch (PDOException $e) {
                    // Falha ao registrar o log, mas não impede a exibição do erro.
                    error_log("Erro ao registrar log de FALHA de login: " . $e->getMessage());
                }
                // --- FIM - REGISTRO DE LOG (FALHA) ---
            }
        } catch (PDOException $e) {
            $erro = "Erro no servidor. Por favor, tente novamente mais tarde.";
            // Você também pode logar este erro do servidor
            error_log("Erro de PDO no Login: " . $e->getMessage());
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema Jurídico Reviver</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-2">Sistema Jurídico Reviver</h2>
        <p class="text-center text-gray-600 mb-6">Acesse sua conta para continuar.</p>
        
        <?php if (!empty($erro)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($erro); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($sucesso)): ?> <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($sucesso); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="mb-4">
                <label for="email" class="block text-gray-700 text-sm font-bold mb-2">Endereço de e-mail</label>
                <input type="email" name="email" id="email" required 
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
            </div>
            <div class="mb-6">
                <label for="senha" class="block text-gray-700 text-sm font-bold mb-2">Senha</label>
                <input type="password" name="senha" id="senha" required
                       class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 mb-3 leading-tight focus:outline-none focus:shadow-outline">
                <a href="#" class="text-sm text-blue-600 hover:text-blue-800 float-right">Esqueci minha senha</a>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline w-full">
                    Entrar
                </button>
            </div>
        </form>
    </div>
    <script>
    const BASE_URL = 'http://localhost/juridico'; 
</script>
<script src="../js/script.js"></script>
</body>
</html>