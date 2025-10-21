<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once '../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/config.php'; 
require_once '../includes/conexao.php';
require_once '../includes/funcoes.php'; // --- LOG 1: INCLUSÃO DAS FUNÇÕES DE LOG ---
require_once '../includes/carregar_tema.php'; // Define $themeClass

// --- LOG 2: VERIFICAÇÃO DE ACESSO COM LOG ---
// Apenas Admins podem acessar esta página
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    // Pega o ID da sessão se existir (pode ser um usuário não-admin logado)
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Configurações Gerais (SMTP)');
    
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}
// --- FIM DO LOG 2 ---


// Função para obter uma configuração (sem alterações)
function getSetting($pdo, $key, $default = '') {
    $stmt = $pdo->prepare("SELECT valor FROM configuracoes WHERE chave = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['valor'] : $default;
}

// Função para definir uma configuração (sem alterações)
function setSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    return $stmt->execute([$key, $value, $value]);
}

$feedback = ['type' => '', 'message' => ''];

// Lidar com o envio do formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_email_settings') {
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = $_POST['smtp_port'] ?? '';
        $smtp_user = $_POST['smtp_user'] ?? '';
        $smtp_pass_new = $_POST['smtp_pass'] ?? ''; 
        $smtp_from_name = $_POST['smtp_from_name'] ?? '';
        $smtp_from_email = $_POST['smtp_from_email'] ?? '';

        if (empty($smtp_host) || empty($smtp_port) || empty($smtp_user) || empty($smtp_from_name) || empty($smtp_from_email)) {
            $feedback = ['type' => 'error', 'message' => 'Por favor, preencha todos os campos obrigatórios, exceto a senha (se não quiser alterar).'];
        } else {
            try {
                setSetting($pdo, 'smtp_host', $smtp_host);
                setSetting($pdo, 'smtp_port', $smtp_port);
                setSetting($pdo, 'smtp_user', $smtp_user);
                
                $senha_info_log = "(Mantida)"; // Info para o log
                if (!empty($smtp_pass_new)) {
                    setSetting($pdo, 'smtp_pass', $smtp_pass_new); 
                    $senha_info_log = "(Alterada)"; // Info para o log
                }
                setSetting($pdo, 'smtp_from_name', $smtp_from_name);
                setSetting($pdo, 'smtp_from_email', $smtp_from_email);
                
                // --- LOG 3: SUCESSO AO SALVAR ---
                $detalhes_log = "Configurações de SMTP atualizadas. ";
                $detalhes_log .= "Host: $smtp_host, Porta: $smtp_port, Usuário: $smtp_user, ";
                $detalhes_log .= "Senha: $senha_info_log, ";
                $detalhes_log .= "Remetente: $smtp_from_name <$smtp_from_email>";
                registrar_log($pdo, 'Configurações SMTP Salvas', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 3 ---
                
                $feedback = ['type' => 'success', 'message' => 'Configurações de e-mail salvas com sucesso!'];

            } catch (PDOException $e) {
                $feedback = ['type' => 'error', 'message' => 'Erro ao salvar configurações de e-mail: ' . $e->getMessage()];
                
                // --- LOG 3 (B): FALHA AO SALVAR ---
                registrar_log($pdo, 'Falha ao Salvar SMTP', $_SESSION['user_id'], 'Erro PDO: ' . $e->getMessage());
                // --- FIM DO LOG 3 (B) ---
            }
        }
    } elseif ($action === 'test_email_settings') {
        
        // --- INÍCIO DA CORREÇÃO DE BUG ---
        // Buscar o e-mail e nome do admin logado, pois não estão na sessão
        $stmt_admin = $pdo->prepare("SELECT email, nome FROM usuarios WHERE id = ?");
        $stmt_admin->execute([$_SESSION['user_id']]);
        $admin_info = $stmt_admin->fetch(PDO::FETCH_ASSOC);

        if (!$admin_info) {
            $feedback = ['type' => 'error', 'message' => 'Não foi possível encontrar os dados do administrador logado.'];
        } else {
            $admin_email = $admin_info['email'];
            $admin_nome = $admin_info['nome'];
            // --- FIM DA CORREÇÃO DE BUG ---

            $mail = new PHPMailer(true);
            try {
                // Configurações do servidor
                $mail->isSMTP();
                $mail->Host = $_POST['smtp_host'];
                $mail->SMTPAuth = true;
                $mail->Username = $_POST['smtp_user'];
                $mail->Password = $_POST['smtp_pass'] ?: getSetting($pdo, 'smtp_pass'); // Usa a senha do form ou a do banco
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $_POST['smtp_port'];

                // Remetente e Destinatário (Corrigido)
                $mail->setFrom($_POST['smtp_from_email'], $_POST['smtp_from_name']);
                $mail->addAddress($admin_email, $admin_nome); // Envia para o admin logado

                // Conteúdo
                $mail->isHTML(true);
                $mail->Subject = 'Teste de Configuração de E-mail - ' . APP_NAME; // Usando APP_NAME
                $mail->Body    = 'Este é um e-mail de teste para verificar se as configurações de SMTP estão corretas no sistema ' . APP_NAME . '.';
                $mail->AltBody = 'Este é um e-mail de teste para verificar se as configurações de SMTP estão corretas no sistema ' . APP_NAME . '.';

                $mail->send();
                $feedback = ['type' => 'success', 'message' => 'E-mail de teste enviado com sucesso para ' . $admin_email . '!'];
                
                // --- LOG 4: SUCESSO AO TESTAR ---
                $detalhes_log = "E-mail de teste enviado com sucesso para: " . $admin_email;
                registrar_log($pdo, 'Teste SMTP (Sucesso)', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 4 ---

            } catch (Exception $e) {
                $feedback = ['type' => 'error', 'message' => "O e-mail de teste não pôde ser enviado. Erro: {$mail->ErrorInfo}"];
                
                // --- LOG 4 (B): FALHA AO TESTAR ---
                $detalhes_log = "Falha ao enviar e-mail de teste para: " . $admin_email . ". Erro: " . $mail->ErrorInfo;
                registrar_log($pdo, 'Teste SMTP (Falha)', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 4 (B) ---
            }
        } // Fim do else $admin_info
    }
}

// Carregar configurações existentes (sem alterações)
$smtp_host = getSetting($pdo, 'smtp_host');
$smtp_port = getSetting($pdo, 'smtp_port');
$smtp_user = getSetting($pdo, 'smtp_user');
$smtp_from_name = getSetting($pdo, 'smtp_from_name');
$smtp_from_email = getSetting($pdo, 'smtp_from_email');

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Configurações Gerais</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Configurações Gerais</h1>

            <?php if ($feedback['message']): ?>
                <div class="p-4 mb-6 text-sm border-l-4 <?php echo ($feedback['type'] === 'success') ? 'bg-green-100 border-green-500 text-green-700' : (($feedback['type'] === 'info') ? 'bg-blue-100 border-blue-500 text-blue-700' : 'bg-red-100 border-red-500 text-red-700'); ?>" role="alert">
                    <?php echo htmlspecialchars(trim($feedback['message'])); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Configurações de E-mail (SMTP)</h2>
                <form action="" method="POST">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="form-group">
                            <label for="smtp_host" class="block font-medium text-gray-700">Servidor SMTP:</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="mt-1 form-control w-full p-2 border rounded-md" value="<?php echo htmlspecialchars($smtp_host); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_port" class="block font-medium text-gray-700">Porta SMTP:</label>
                            <input type="text" id="smtp_port" name="smtp_port" class="mt-1 form-control w-full p-2 border rounded-md" value="<?php echo htmlspecialchars($smtp_port); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_user" class="block font-medium text-gray-700">Usuário SMTP (E-mail):</label>
                            <input type="email" id="smtp_user" name="smtp_user" class="mt-1 form-control w-full p-2 border rounded-md" value="<?php echo htmlspecialchars($smtp_user); ?>">
                        </div>

                        <div class="form-group">
                            <label for="smtp_pass" class="block font-medium text-gray-700">Senha SMTP:</label>
                            <input type="password" id="smtp_pass" name="smtp_pass" class="mt-1 form-control w-full p-2 border rounded-md" value="">
                            <p class="text-xs text-gray-500 mt-1">Deixe em branco para não alterar a senha atual.</p>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_name" class="block font-medium text-gray-700">Nome do Remetente:</label>
                            <input type="text" id="smtp_from_name" name="smtp_from_name" class="mt-1 form-control w-full p-2 border rounded-md" value="<?php echo htmlspecialchars($smtp_from_name); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_from_email" class="block font-medium text-gray-700">E-mail do Remetente:</label>
                            <input type="email" id="smtp_from_email" name="smtp_from_email" class="mt-1 form-control w-full p-2 border rounded-md" value="<?php echo htmlspecialchars($smtp_from_email); ?>">
                        </div>

                    </div>
                    <div class="mt-6 text-right">
                        <input type="hidden" name="action" id="form-action" value="save_email_settings">
                        <button type="submit" onclick="document.getElementById('form-action').value='test_email_settings';" name="test_email_settings" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm mr-2">
                            <i class="fas fa-paper-plane mr-2"></i>Testar E-mail
                        </button>
                        <button type="submit" onclick="document.getElementById('form-action').value='save_email_settings';" name="save_email_settings" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                            <i class="fas fa-save mr-2"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script>
    const BASE_URL = 'http://localhost/juridico'; 
</script>
    <script src="../js/script.js"></script>
</body>
</html>