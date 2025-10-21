document.addEventListener('DOMContentLoaded', function() {
    
    // --- LÓGICA DO SUBMENU ---
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(function(toggle) {
        toggle.addEventListener('click', function(event) {
            event.preventDefault();
            const parentMenuItem = this.closest('.has-submenu');
            if (parentMenuItem) {
                parentMenuItem.classList.toggle('open');
            }
        });
    });

    // --- LÓGICA DO TEMA ---
    const themeToggle = document.getElementById('theme-toggle');
    const html = document.documentElement;

    // Se o botão de tema existir nesta página
    if (themeToggle) {
        // 1. Define o estado inicial do toggle (lendo a classe que o PHP colocou)
        if (html.classList.contains('dark')) {
            themeToggle.checked = true;
        }

        // 2. Listener para a troca de tema
        themeToggle.addEventListener('change', function() {
            let tema;

            // 2a. Aplica a classe no HTML e salva no localStorage
            if (this.checked) {
                tema = 'dark';
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                tema = 'light';
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }

            // 4. Envia a requisição
            const formData = new FormData();
            formData.append('tema', tema);

            // --- CORREÇÃO DO CAMINHO ---
            // O arquivo ajax_salvar_tema.php está dentro da pasta /php/
            fetch(BASE_URL + '/php/ajax_salvar_tema.php', {
                method: 'POST',
                body: formData
            })
            // --- FIM DA CORREÇÃO ---
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log('Preferência salva no servidor.');
                } else {
                    console.warn('Falha ao salvar tema no servidor:', data.message);
                }
            })
            .catch(error => {
                console.error('Erro de rede ao salvar tema:', error);
            });
        });
    }
});
