<!-- 1. Cabeçalho de Identidade (Top Bar) -->
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
            <a href="cadastro_cliente.php" class="btn btn-outline-warning btn-sm me-1">Cadastro de Clientes</a>
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