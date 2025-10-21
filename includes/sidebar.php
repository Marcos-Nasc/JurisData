<?php
// Lembre-se que session_start()
// ou pelo verifica_login.php

// Pega o nome do arquivo da página atual para saber qual link destacar
$pagina_atual = basename($_SERVER['PHP_SELF']);
?>


<aside class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_URL; ?>/index.php" class="logo-container">
            <img src="<?php echo BASE_URL; ?>/images/logo.png" alt="Logo Reviver" class="logo-image">
        </a>
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
                    <li><a href="<?php echo BASE_URL; ?>/php/config_banco.php">Banco de Dados</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/manutencao.php">Manutenção</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/aparencia.php">Aparência</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/php/configuracoes_usuarios.php">Usuários</a></li>
                    <?php if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] == 3): ?>
                    <li><a href="<?php echo BASE_URL; ?>/php/logs.php">Logs de Atividades</a></li>
                    <?php endif; ?>
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
        <div class="user-info">
            <span class="user-name"><?php echo htmlspecialchars($_SESSION['nome_usuario'] ?? 'Usuário'); ?></span>
            <span class="user-role"><?php echo htmlspecialchars($_SESSION['cargo_usuario'] ?? 'Cargo'); ?></span>
        </div>
        <a href="<?php echo BASE_URL; ?>/includes/logout.php" class="logout-button">
            <i class="fas fa-sign-out-alt"></i>
            <span>Sair</span>
        </a>
    </div>
</aside>