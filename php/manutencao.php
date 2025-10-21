<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/verifica_login.php';

// --- INÍCIO - INCLUSÃO PARA LOG ---
require_once '../includes/conexao.php';
require_once '../includes/funcoes.php';
require_once '../includes/carregar_tema.php'; // Define $themeClass
// --- FIM - INCLUSÃO PARA LOG ---


// --- LOG 1: TENTATIVA DE ACESSO NÃO AUTORIZADO ---
// Apenas Admins podem acessar esta página
if (!isset($_SESSION['user_nivel']) || $_SESSION['user_nivel'] != 3) {
    // Pega o ID da sessão se existir (pode ser um usuário não-admin logado)
    $id_usuario_tentativa = $_SESSION['user_id'] ?? null;
    registrar_log($pdo, 'Tentativa de acesso não autorizado', $id_usuario_tentativa, 'Página: Modo de Manutenção');
    
    die('Acesso negado. Você precisa ser um administrador para acessar esta página.');
}
// --- FIM DO LOG 1 ---

$feedback = ['type' => '', 'message' => ''];
$flag_file = __DIR__ . '/../maintenance.flag';

// Processar a mudança de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'ativar') {
        if (!file_exists($flag_file)) touch($flag_file);
        
        // --- LOG 2: MODO ATIVADO ---
        registrar_log($pdo, 'Modo Manutenção Ativado', $_SESSION['user_id'], 'Sistema entrou em modo de manutenção.');
        // --- FIM DO LOG 2 ---
        
    } elseif ($_POST['action'] === 'desativar') {
        if (file_exists($flag_file)) unlink($flag_file);
        
        // --- LOG 3: MODO DESATIVADO ---
        registrar_log($pdo, 'Modo Manutenção Desativado', $_SESSION['user_id'], 'Sistema saiu do modo de manutenção.');
        // --- FIM DO LOG 3 ---
    }
    
    header('Location: manutencao.php?status=changed');
    exit();
}

if (isset($_GET['status']) && $_GET['status'] === 'changed'){
    $feedback = ['type' => 'success', 'message' => 'Status do modo de manutenção foi alterado com sucesso.'];
}

$maintenance_mode_active = file_exists($flag_file);

?>
<!DOCTYPE html>
<html lang="pt-br" class="<?php echo $themeClass; ?>">
<head>
    <meta charset="UTF-8">
    <title>Modo de Manutenção</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 28px;
        }
        .switch input { 
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--cor-borda);
            transition: .4s;
            border-radius: 28px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #22c55e; /* Verde para "ATIVO" */
        }
        input:focus + .slider {
            box-shadow: 0 0 1px #22c55e;
        }
        input:checked + .slider:before {
            transform: translateX(22px);
        }
    </style>
</head>
<body class="bg-fundo">
    <div class="app-layout">
        <?php require_once '../includes/sidebar.php'; ?>
        <main class="main-content">
            <h1 class="text-2xl font-bold text-principal mb-6">Modo de Manutenção</h1>

            <?php if ($feedback['message']): ?>
                <div class="p-4 mb-6 text-sm border-l-4 <?php echo ($feedback['type'] === 'success') ? 'bg-green-100 border-green-500 text-green-700' : 'bg-red-100 border-red-500 text-red-700'; ?>" role="alert">
                    <?php echo htmlspecialchars($feedback['message']); ?>
                </div>
            <?php endif; ?>

            <div class="bg-superficie p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-principal">Status do Sistema</h2>
                        <p class="text-secundario mt-2 max-w-2xl">Ative o modo de manutenção para realizar atualizações. Somente usuários com nível "Administrador" poderão acessar o sistema enquanto este modo estiver ativo.</p>
                    </div>
                    <div class="ml-6 flex flex-col items-center">
                        <form id="maintenance-form" action="" method="POST">
                            <input type="hidden" name="action" value="<?php echo $maintenance_mode_active ? 'desativar' : 'ativar'; ?>">
                            <label class="switch">
                                <input type="checkbox" id="maintenance-toggle" <?php echo $maintenance_mode_active ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </form>
                        <span class="mt-2 font-semibold <?php echo $maintenance_mode_active ? 'text-green-600' : 'text-secundario'; ?>">
                            <?php echo $maintenance_mode_active ? 'ATIVO' : 'INATIVO'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toggle = document.getElementById('maintenance-toggle');
            const form = document.getElementById('maintenance-form');

            toggle.addEventListener('change', function() {
                form.submit();
            });
        });
        const BASE_URL = 'http://localhost/juridico'; 
    </script>
    <script src="../js/script.js"></script>
</body>
</html>