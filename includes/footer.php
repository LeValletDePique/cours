<?php if (utilisateur_connecte()): ?>
</main>
</div>
<?php else: ?>
    <button type="button" class="mc-btn mc-btn--ghost mc-btn--sm mc-auth__theme" data-action="theme"
            aria-label="Changer de thème"><?= icone('lune', 'mc-ico-sm') ?>Thème</button>
</main>
<?php endif; ?>
</body>
</html>
