    </main>
</div>

<script>setInterval(function(){var now=new Date();var el=document.getElementById('currentTime');if(el)el.innerHTML='<i class="far fa-clock"></i> '+now.toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'});},1e3);</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="<?= APP_ROOT ?>assets/js/main.js"></script>
<?= $extraJS ?? '' ?>
</body>
</html>
