<!-- ════════════════════════ PAGE BODY END ══════════════════════════ -->
</div><!-- /container.py-4 -->

<footer class="bg-white border-top mt-auto py-4">
  <div class="container-fluid px-4">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
        <span class="fw-semibold text-primary">
          <i class="bi bi-search-heart me-1"></i>Campus Lost &amp; Found
        </span>
        <span class="text-muted ms-2">— KZ-WEB-03 Capstone</span>
      </div>
      <div class="col-md-6 text-center text-md-end text-muted">
        <small>&copy; <?= date('Y') ?> &nbsp;|&nbsp; BSc IT / BCA Final Year Project</small>
      </div>
    </div>
  </div>
</footer>

<!-- Local Bootstrap 5 JS (no CDN — works offline) -->
<script src="<?= BASE_URL ?>/assets/js/bootstrap.bundle.min.js"></script>
<?php if (isset($extraScripts)): ?>
<?= $extraScripts ?>
<?php endif; ?>
</body>
</html>
