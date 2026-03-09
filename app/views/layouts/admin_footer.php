</div><!-- /.container-fluid -->
  </section><!-- /.content -->
</div><!-- /.content-wrapper -->

    <footer class="main-footer" style="background:#fff;border-top:1px solid #e2e8f0;padding:12px 20px;">
      <span style="font-size:.78rem;color:#94a3b8;">
        &copy; <?= date('Y') ?> <?= COMPANY_NAME ?> &mdash; <?= APP_NAME ?> v<?= APP_VERSION ?>
      </span>
      <span class="float-right" style="font-size:.78rem;color:#94a3b8;">
        Powered by <strong>Rocky HRIS</strong>
      </span>
    </footer>
  </div><!-- ./wrapper -->

  <!-- JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/admin-lte/3.2.0/js/adminlte.min.js"></script>
  <script>
    $(document).ready(function() {
      $('[data-widget="treeview"]').Treeview('init');
    });
  </script>
  <?php if (isset($extraJs)) echo '<script>' . $extraJs . '</script>'; ?>
  <script>
    // Auto-dismiss alerts after 4 seconds
    setTimeout(() => {
      document.querySelectorAll('.alert-auto-dismiss').forEach(el => {
        el.style.transition = 'opacity .5s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 500);
      });
    }, 4000);
  </script>
</body>
</html>