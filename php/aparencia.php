<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';
require_once '../includes/config.php'; // Carrega o nome da aplicação

// --- LOG: INCLUSÃO DE DEPENDÊNCIAS ---
require_once '../includes/conexao.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass
require_once '../includes/funcoes.php';
// --- FIM DO LOG ---

// --- LOG 1: TENTATIVA DE ACESSO NÃO AUTORIZADO ---
// Apenas Admins podem acessar esta página
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Aparência');
    
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}
// --- FIM DO LOG 1 ---

$feedback = ['type' => '', 'message' => ''];

// --- PROCESSAR FORMULÁRIOS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // --- AÇÃO: SALVAR IDENTIDADE (NOME E LOGO) ---
    if ($_POST['action'] === 'salvar_identidade') {
        $new_app_name = trim($_POST['app_name']);
        $config_file_path = __DIR__ . '/../includes/config.php';

        // Atualiza o nome da aplicação no arquivo de configuração
        if (!empty($new_app_name) && $new_app_name !== APP_NAME) {
            $config_content = file_get_contents($config_file_path);
            $config_content = preg_replace("/define\('APP_NAME', '.*?'\);/", "define('APP_NAME', '{$new_app_name}');", $config_content);
            
            if (file_put_contents($config_file_path, $config_content)) {
                $feedback = ['type' => 'success', 'message' => 'Nome do sistema atualizado com sucesso.'];
                
                // --- LOG 2: NOME SALVO (SUCESSO) ---
                $detalhes_log = "Nome do sistema alterado de '" . APP_NAME . "' para '" . $new_app_name . "'.";
                registrar_log($pdo, 'Alteração Identidade (Nome)', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 2 ---
                
            } else {
                $feedback = ['type' => 'error', 'message' => 'Falha ao salvar o nome do sistema. Verifique as permissões do arquivo includes/config.php.'];
                
                // --- LOG 3: NOME SALVO (FALHA) ---
                $detalhes_log = "Falha ao alterar nome. Verifique permissões em 'includes/config.php'.";
                registrar_log($pdo, 'Falha Alteração Identidade', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 3 ---
            }
        }

        // Processa o upload da logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../images/';
            $logo_path = $upload_dir . 'logo.png';
            $allowed_types = ['image/png'];
            
            if (in_array($_FILES['logo']['type'], $allowed_types)) {
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    $feedback['type'] = 'success';
                    $feedback['message'] .= ' Logo atualizada com sucesso.';
                    
                    // --- LOG 4: LOGO SALVA (SUCESSO) ---
                    registrar_log($pdo, 'Alteração Identidade (Logo)', $_SESSION['user_id'], 'Nova logo (logo.png) salva com sucesso.');
                    // --- FIM DO LOG 4 ---
                    
                } else {
                    $feedback = ['type' => 'error', 'message' => 'Erro ao mover o arquivo da logo. Verifique as permissões do diretório /images.'];
                    
                    // --- LOG 5: LOGO SALVA (FALHA DE PERMISSÃO) ---
                    $detalhes_log = "Falha ao salvar logo. Verifique permissões em '/images'.";
                    registrar_log($pdo, 'Falha Alteração Identidade', $_SESSION['user_id'], $detalhes_log);
                    // --- FIM DO LOG 5 ---
                }
            } else {
                $feedback = ['type' => 'error', 'message' => 'Formato de arquivo inválido. Apenas imagens .png são permitidas para a logo.'];
                
                // --- LOG 6: LOGO SALVA (FALHA DE TIPO) ---
                $detalhes_log = "Tentativa de upload de logo com tipo inválido: " . $_FILES['logo']['type'];
                registrar_log($pdo, 'Falha Alteração Identidade', $_SESSION['user_id'], $detalhes_log);
                // --- FIM DO LOG 6 ---
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Aparência do Sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* Estilos para o Toggle Switch */
        .switch { position: relative; display: inline-block; width: 60px; height: 34px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px; }
            .slider:before {
                position: absolute;
                content: "\f185"; /* Ícone de sol */
                font-family: "Font Awesome 5 Free";
                font-weight: 900;
                height: 26px;
                width: 26px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                transition: .4s;
                border-radius: 50%;
                display: flex;
                justify-content: center;
                align-items: center;
                color: #f39c12;
            }
        input:checked + .slider {
            background-color: #8b5cf6; /* Roxo */
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #8b5cf6;
        }
        input:checked + .slider:before {
            content: "\f186"; /* Ícone de lua */
            color: #8b5cf6; /* Roxo */
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Aparência do Sistema</h1>

            <?php if ($feedback['message']): ?>
                <div class="p-4 mb-6 text-sm border-l-4 <?php echo ($feedback['type'] === 'success') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars(trim($feedback['message'])); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow">
                 <h2 class="text-xl font-semibold text-gray-800 border-b pb-3 mb-4">Identidade Visual</h2>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="salvar_identidade">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="app_name" class="block font-medium text-gray-700">Nome do Sistema:</label>
                            <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars(APP_NAME); ?>" class="mt-1 form-control w-full p-2 border rounded-md">
                        </div>
                        <div class="form-group">
                            <label for="logo" class="block font-medium text-gray-700">Nova Logo (PNG):</label>
                            <input type="file" id="logo" name="logo" accept=".png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <p class="text-xs text-gray-500 mt-2">Logo atual:</p>
                            <img src="../images/logo.png" alt="Logo Atual" class="mt-2 h-16 border p-1 rounded-md">
                        </div>
                    </div>
                    <div class="mt-6 text-right">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center shadow-sm">
                            <i class="fas fa-save mr-2"></i>Salvar Alterações de Identidade
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
<script>
    const BASE_URL = 'http://localhost/juridico'; 
</script>
<script src="../js/script.js"></script>
</html>