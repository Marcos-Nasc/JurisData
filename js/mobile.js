document.addEventListener('DOMContentLoaded', function() {
    
    // --- LÓGICA DO MENU MOBILE ---
    
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('#mobile-overlay');
    const openBtn = document.querySelector('#mobile-menu-toggle');
    const closeBtn = document.querySelector('#sidebar-toggle'); // O botão que vira 'X'
    const body = document.body;

    // Função para abrir o menu
    function openMobileMenu() {
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('open');
        if (body) body.classList.add('mobile-menu-open');
    }

    // Função para fechar o menu
    function closeMobileMenu() {
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
        if (body) body.classList.remove('mobile-menu-open');
    }

    // 1. Abrir menu com o hamburguer
    if (openBtn) {
        openBtn.addEventListener('click', openMobileMenu);
    }

    // 2. Fechar menu com o 'X'
    if (closeBtn) {
        closeBtn.addEventListener('click', closeMobileMenu);
    }

    // 3. Fechar menu clicando no overlay
    if (overlay) {
        overlay.addEventListener('click', closeMobileMenu);
    }

    // --- LÓGICA DE SUBMENU (ACCORDION) PARA MOBILE ---

    // Verifica se estamos em uma tela "de toque" (mobile)
    const isMobile = window.matchMedia("(max-width: 767.98px)").matches;

    if (isMobile) {
        const submenuToggles = document.querySelectorAll('.sidebar .submenu-toggle');

        submenuToggles.forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                // Impede que o link '#' navegue
                e.preventDefault(); 
                
                const parentItem = this.closest('.nav-item');
                
                // Fecha outros submenus que possam estar abertos
                const parentNav = this.closest('.sidebar-nav');
                parentNav.querySelectorAll('.nav-item.active').forEach(item => {
                    // Se não for o item que clicamos, remova o 'active'
                    if (item !== parentItem) {
                        item.classList.remove('active');
                    }
                });

                // Abre ou fecha o submenu atual
                parentItem.classList.toggle('active');
            });
        });
    }

    const filtroMobile = document.getElementById('filtro-ano-mobile');
    
    if (filtroMobile) {
        filtroMobile.addEventListener('change', function() {
            // Pega o ano que o usuário selecionou
            const anoSelecionado = this.value;
            
            // Cria um objeto URL a partir da localização atual
            // (Isso preserva outros parâmetros GET que possam existir)
            const url = new URL(window.location.href);
            
            // Atualiza ou adiciona o parâmetro 'ano'
            url.searchParams.set('ano', anoSelecionado);
            
            // Redireciona a página para a nova URL com o filtro aplicado
            window.location.href = url.toString();
        });
    }
    
    // Exemplo: Lógica do Dark Mode (você já deve ter isso)
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        // Lógica para carregar o tema salvo
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.checked = true;
        }

        // Lógica para trocar o tema
        themeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('theme', 'light');
            }
        });
    }

}); // Fim do DOMContentLoaded