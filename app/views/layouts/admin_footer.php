</div><!-- /.container-fluid -->
  </section><!-- /.content -->
</div><!-- /.content-wrapper -->

    <footer class="main-footer" class="main-footer">
      <span class="footer-text">
        &copy; <?= date('Y') ?> <?= COMPANY_NAME ?> &mdash; <?= APP_NAME ?> v<?= APP_VERSION ?>
      </span>
      <span class="float-right" class="footer-text">
        Powered by <strong>Rocky HRIS + PAYROLL</strong>
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

    // Live pending leave badge — polls every 30 seconds
    function refreshPendingBadge() {
      $.getJSON('<?= BASE_URL ?>/app/ajax/pending_count.php', function(data) {
        var count = data.count || 0;

        // Navbar bell badge
        var $bell = $('.navbar-badge');
        if (count > 0) { $bell.text(count).show(); } else { $bell.hide(); }

        // Sidebar Leave Management badge
        var $sidebar = $('a[href*="leave.php"] .right.badge-danger');
        if (count > 0) {
          if ($sidebar.length) { $sidebar.text(count); }
          else { $('a[href*="leave.php"] p').after('<span class="right badge badge-danger">' + count + '</span>'); }
        } else {
          $sidebar.remove();
        }

        // Navbar dropdown header text
        $('.dropdown-header:contains("Pending Leave")').text(count + ' Pending Leave(s)');
      });
    }

    // Poll every 30 seconds
    setInterval(refreshPendingBadge, 30000);
  </script>
</body>
</html>