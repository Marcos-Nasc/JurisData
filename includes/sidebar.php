<?php
// Pega o nome do arquivo da página atual para saber qual link destacar
$pagina_atual = basename($_SERVER['PHP_SELF']);

// ✅ Função para gerar iniciais (Nome Sobrenome -> NS)
function getInitials($name) {
    if (empty($name)) return 'U';
    $words = preg_split("/\s+/", $name);
    $initials = '';
    if (isset($words[0])) {
        $initials .= mb_substr($words[0], 0, 1);
    }
    if (count($words) > 1 && isset($words[count($words) - 1])) {
        $initials .= mb_substr($words[count($words) - 1], 0, 1);
    } elseif (mb_strlen($name) > 1) { 
        $initials .= mb_substr($name, 1, 1);
    }
    return mb_strtoupper($initials);
}

$user_initials = getInitials($_SESSION['nome_usuario'] ?? 'Usuário');

function getAvatarColor($name) {
    $colors = ['#dc2626', '#d97706', '#65a30d', '#059669', '#0891b2', '#0284c7', '#4f46e5', '#7c3aed', '#c026d3'];
    $hash = crc32($name); // Gera um número a partir do nome
    $index = abs($hash) % count($colors); // Pega um índice de cor
    return $colors[$index];
}

$user_initials = getInitials($_SESSION['nome_usuario'] ?? 'Usuário');
$avatar_color = getAvatarColor($_SESSION['nome_usuario'] ?? 'U'); // Pega a cor
?>

<aside class="sidebar border-r border-gray-700">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>/index.php" class="logo-container">
            <i class="logo-icon"><img src="<?php echo BASE_URL; ?>/images/logo_icone.png" alt="Icone Reviver" class="logo-icone"></i>
            <img src="<?php echo BASE_URL; ?>/images/logo.png" alt="Logo Reviver" class="logo-image">
        </a>
        
        <button id="sidebar-toggle" class="sidebar-toggle-btn" title="Fixar/Recolher menu">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo ($pagina_atual == 'index.php') ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle">
                    <i class="fas fa-chart-bar"></i>
                    <span>Relatórios</span>
                    <span class="arrow"></span>
                </a>
                <ul class="submenu">
                    <li><a href="<?php echo BASE_URL; ?>/php/performance_financeira.php">Performance Financeira</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/provisao_risco.php">Provisão de Risco</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/volume_entrada.php">Volume de Entrada</a></li>
                </ul>
            </li>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle">
                    <i class="fas fa-wallet"></i>
                    <span>Gestão</span>
                    <span class="arrow"></span>
                </a>
                <ul class="submenu">
                    <li><a href="<?php echo BASE_URL; ?>/php/totais_mensais.php">Totais Mensais</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/processos_detalhados.php">Processos Detalhados</a></li>
                </ul>
            </li>
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link submenu-toggle">
                    <i class="fas fa-cog"></i>
                    <span>Configuração</span>
                    <span class="arrow"></span>
                </a>
                <ul class="submenu">
                    <li><a href="<?php echo BASE_URL; ?>/php/configuracoes_gerais.php">Configurações Gerais</a></li>
                    </ul>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
    <div class="theme-switcher">
        <span class="theme-label">Modo Escuro</span> 
        <label class="switch">
            <input type="checkbox" id="theme-toggle">
            <span class="slider"></span>
        </label>
    </div>

    <div class="user-profile-footer">
        <div class="user-avatar" style="background-color: <?php echo $avatar_color; ?>;">
            <span><?php echo htmlspecialchars($user_initials); ?></span>
        </div>
        
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nome_usuario'] ?? 'Usuário'); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['cargo_usuario'] ?? 'Cargo'); ?></span>
        </div>
    </div>

    <a href="<?php echo BASE_URL; ?>/includes/logout.php" class="logout-button">
        <i class="fas fa-sign-out-alt"></i>
        <span>Sair</span>
    </a>
</div>
</aside>