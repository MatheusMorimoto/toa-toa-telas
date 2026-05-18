<!-- 1. Cabeçalho de Identidade (Top Bar) -->
<style>
    /* Efeito de hover para os dropdowns aparecerem ao passar o mouse */
    .dropdown:hover .dropdown-menu {
        display: block;
        margin-top: 0;
    }
</style>
<header class="top-bar">
    <div class="brand-area">
        <img src="toatoa.png" alt="Tôa Tôa" onerror="this.src='https://via.placeholder.com/40x40?text=TT'">
        <div class="brand-text">
            <span class="brand-main">Tôa Tôa Moda Festa</span>
            <span class="brand-sub">Patrocinadora oficial do Miss Mato Grosso</span>
            <span class="brand-desc">Formandas, Madrinhas, Noivas e Balada</span>
        </div>
    </div>
    
    <div class="title-area">
        <!-- Título Central do Sistema -->
        <h1>SISTEMA TOA TOA</h1>
    </div>

    <div class="header-right-content">
        <div class="nav-links-top">
            <!-- Dropdown Clientes -->
            <div class="dropdown d-inline-block me-1">
                <button class="btn btn-outline-warning btn-sm dropdown-toggle" type="button" id="dropdownClientes" data-bs-toggle="dropdown" aria-expanded="false">
                    Clientes
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="dropdownClientes">
                    <li><a class="dropdown-item" href="cadastro_cliente.php">Cadastro de Cliente</a></li>
                    <li><a class="dropdown-item" href="clientes_cadastrados.php">Clientes Cadastrados</a></li>
                </ul>
            </div>

            <div class="dropdown d-inline-block">
                <button class="btn btn-outline-warning btn-sm me-1 dropdown-toggle" type="button" id="dropdownProdutos" aria-expanded="false">
                    Produtos
                </button>
                <ul class="dropdown-menu shadow" aria-labelledby="dropdownProdutos">
                    <li><a class="dropdown-item" href="index.php">Cadastro de Produtos</a></li>
                    <li><a class="dropdown-item" href="produtos.php">Produtos Cadastrados</a></li>
                </ul>
            </div>
        </div>
        <div class="search-container ms-3">
            <form action="pdv.php" method="POST">
                <input type="text" name="busca" class="form-control" placeholder="Busca rápida...">
            </form>
        </div>
    </div>
</header>