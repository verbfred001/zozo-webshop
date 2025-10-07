<div class="header-row">
    <div>
        <h1 class="page-title">Artikel bewerken</h1>
    </div>
    <div>
        <!-- ALLEEN DE TERUG KNOP -->
        <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn--gray">
            <svg class="btn-icon mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Terug naar overzicht
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>