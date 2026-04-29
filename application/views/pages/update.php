<!doctype html>
<html lang="pt-BR">
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">

    <title>Atualização | Capricha</title>

    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/themes/default.min.css') ?>">
    <link rel="icon" type="image/x-icon" href="<?= asset_url('assets/img/favicon.ico') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/general.css') ?>">
    <link rel="stylesheet" type="text/css" href="<?= asset_url('assets/css/pages/update.css') ?>">
</head>
<body>
<header>
    <div class="container">
        <h1 class="page-title">Atualização do Capricha</h1>
    </div>
</header>

<div class="container">
    <div class="row">
        <div class="col">
            <?php if (vars('success')): ?>
                <div class="jumbotron">
                    <h1 class="display-4">Concluído!</h1>
                    <p class="lead">
                        Banco de dados atualizado com sucesso.
                    </p>
                    <hr class="my-4">
                    <p>
                        Você já pode usar a versão mais recente do Capricha.
                    </p>
                    <a href="<?= site_url('about') ?>" class="btn btn-success btn-large">
                        <i class="fas fa-wrench me-2"></i>
                        <?= lang('backend_section') ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="jumbotron">
                    <h1 class="display-4">Falha!</h1>
                    <p class="lead">
                        Ocorreu um erro durante o processo de atualização.
                    </p>
                    <hr class="my-4">
                    <p>
                        Por favor, restaure o backup do banco de dados.
                    </p>
                    <a href="<?= site_url('login') ?>" class="btn btn-success btn-large">
                        <i class="fas fa-wrench me-2"></i>
                        <?= lang('backend_section') ?>
                    </a>
                </div>

                <div class="well text-start">
                    Error Message: <?= vars('exception') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<footer>
    <a href="https://capricha.app.br">Capricha</a>
</footer>

<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/fontawesome.min.js') ?>"></script>
<script src="<?= asset_url('assets/vendor/@fortawesome-fontawesome-free/solid.min.js') ?>"></script>
</body>
</html>
