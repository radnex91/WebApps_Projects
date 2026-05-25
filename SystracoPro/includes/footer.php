  </div><!-- .page -->
</div><!-- .main -->
</div><!-- .app-wrap -->
<span id="csrf-token" data-token="<?= htmlspecialchars(csrfToken()) ?>"></span>
<script src="<?= BASE_URL ?>js/app.js"></script>
<script>
// ── Poll unread notifications ─────────────────────────────
var _lastNotifCount = <?= isset($_notifCount) ? (int)$_notifCount : 0 ?>;
var _notifBell = document.querySelector('.notif-btn .nb');
setInterval(function() {
  fetch('<?= BASE_URL ?>modules/notifications.php?ajax=count').then(function(r){return r.json();}).then(function(d){
    if (!_notifBell) { _notifBell = document.querySelector('.notif-btn .nb'); }
    if (d.count > _lastNotifCount) {
      var newCount = d.count - _lastNotifCount;
      showToast('info', newCount + ' nouvelle' + (newCount>1?'s':'') + ' notification' + (newCount>1?'s':''), 6000);
    }
    _lastNotifCount = d.count;
    if (_notifBell) _notifBell.textContent = d.count;
    else if (d.count > 0) {
      var btn = document.querySelector('.notif-btn');
      if (btn && !btn.querySelector('.nb')) { var s = document.createElement('span'); s.className='nb'; s.textContent=d.count; btn.appendChild(s); _notifBell=s; }
    }
  }).catch(function(){});
}, 30000);
</script>
<?php if(isset($extraJs)) echo $extraJs; ?>
</body>
</html>
