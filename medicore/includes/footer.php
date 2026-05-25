    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /app -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php
// Vider et envoyer le tampon de sortie
if (ob_get_level() > 0) {
    ob_end_flush();
}
