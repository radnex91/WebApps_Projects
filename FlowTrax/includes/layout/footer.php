<?php if (isLoggedIn()): ?>
        </div>
        <footer class="footer">
            <p>&copy; <?= date('Y') ?> <?= e(getSetting('app_company', 'DEX Transport')) ?> — Tous droits réservés</p>
        </footer>
    </main>
</div>
<?php else: ?>
</div>
<?php endif; ?>
<script src="assets/js/app.js"></script>
</body>
</html>
